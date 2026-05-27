<?php
if (!defined('ABSPATH')) exit;

class RANKREPAIR_AI_Divi_Recovery {
    public static function scan($args = []) {
        $post_type = sanitize_key($args['post_type'] ?? 'any');
        $status = sanitize_key($args['post_status'] ?? 'any');
        $limit = max(1, min(500, intval($args['limit'] ?? 100)));
        $offset = max(0, intval($args['offset'] ?? 0));
        $search = sanitize_text_field($args['search'] ?? '');
        $source_type = sanitize_key($args['source_type'] ?? 'auto');

        $q = new WP_Query([
            'post_type' => $post_type === 'any' ? get_post_types(['public' => true]) : $post_type,
            'post_status' => $status === 'any' ? ['publish','draft','pending','private','future'] : $status,
            'posts_per_page' => $limit,
            'offset' => $offset,
            's' => $search,
            'orderby' => 'ID',
            'order' => 'DESC',
        ]);

        $items = [];
        foreach ($q->posts as $post) {
            $source = self::best_source($post->ID, $source_type);
            $score = self::recovery_score($source['content'], $source['type']);
            if ($score <= 0 && empty($source['revision_id']) && $source_type !== 'html') continue;
            $converted_html = self::source_to_html($source['content'], $source['type'], $post->ID);
            $items[] = [
                'id' => $post->ID,
                'title' => get_the_title($post),
                'post_type' => $post->post_type,
                'status' => $post->post_status,
                'edit_link' => get_edit_post_link($post->ID, 'raw'),
                'source' => $source['source'],
                'source_type' => $source['type'],
                'revision_id' => $source['revision_id'],
                'shortcode_count' => self::shortcode_count($source['content']),
                'elementor_widget_count' => self::elementor_widget_count($source['content']),
                'html_block_count' => self::html_block_count($converted_html),
                'current_word_count' => str_word_count(wp_strip_all_tags($post->post_content)),
                'recoverable_word_count' => str_word_count(wp_strip_all_tags($converted_html)),
                'has_backup' => (bool) get_post_meta($post->ID, '_rankrepair_content_recovery_backup', true),
            ];
        }
        return ['items'=>$items, 'found_posts'=>intval($q->found_posts)];
    }

    public static function best_source($post_id, $preferred = 'auto') {
        $post = get_post($post_id);
        $content = $post ? $post->post_content : '';
        $elementor = get_post_meta($post_id, '_elementor_data', true);

        if ($preferred === 'elementor' && $elementor) return ['content'=>$elementor, 'source'=>'elementor_data', 'type'=>'elementor', 'revision_id'=>0];
        if ($preferred === 'divi' && self::divi_score($content) > 0) return ['content'=>$content, 'source'=>'current_content', 'type'=>'divi', 'revision_id'=>0];
        if ($preferred === 'html' && $content) return ['content'=>$content, 'source'=>'current_content', 'type'=>'html', 'revision_id'=>0];

        $candidates = [];
        if ($elementor) $candidates[] = ['content'=>$elementor, 'source'=>'elementor_data', 'type'=>'elementor', 'revision_id'=>0, 'score'=>self::recovery_score($elementor, 'elementor')];
        if ($content) $candidates[] = ['content'=>$content, 'source'=>'current_content', 'type'=> self::divi_score($content) ? 'divi' : 'html', 'revision_id'=>0, 'score'=>self::recovery_score($content, self::divi_score($content) ? 'divi' : 'html')];

        $revisions = wp_get_post_revisions($post_id, ['order'=>'DESC', 'orderby'=>'date']);
        foreach ($revisions as $revision) {
            $type = self::divi_score($revision->post_content) ? 'divi' : 'html';
            $candidates[] = ['content'=>$revision->post_content, 'source'=>'revision', 'type'=>$type, 'revision_id'=>$revision->ID, 'score'=>self::recovery_score($revision->post_content, $type)];
        }
        usort($candidates, function($a,$b){ return $b['score'] <=> $a['score']; });
        $best = $candidates[0] ?? ['content'=>$content, 'source'=>'current_content', 'type'=>'html', 'revision_id'=>0, 'score'=>0];
        unset($best['score']);
        return $best;
    }

    public static function recovery_score($content, $type = 'auto') {
        if (!$content) return 0;
        if ($type === 'elementor') return self::elementor_widget_count($content) * 3;
        if ($type === 'divi') return self::divi_score($content) * 2;
        if ($type === 'html') return self::html_block_count($content);
        return max(self::divi_score($content) * 2, self::elementor_widget_count($content) * 3, self::html_block_count($content));
    }

    public static function divi_score($content) {
        if (!$content) return 0;
        preg_match_all('/\[\/?et_pb_[^\]]+\]/i', $content, $m);
        return count($m[0]);
    }

    public static function shortcode_count($content) { return self::divi_score($content); }

    public static function elementor_widget_count($content) {
        if (!$content) return 0;
        $data = json_decode($content, true);
        if (!is_array($data)) return 0;
        return self::count_elementor_widgets($data);
    }

    private static function count_elementor_widgets($nodes) {
        $count = 0;
        foreach ((array) $nodes as $node) {
            if (!is_array($node)) continue;
            if (($node['elType'] ?? '') === 'widget') $count++;
            if (!empty($node['elements'])) $count += self::count_elementor_widgets($node['elements']);
        }
        return $count;
    }

    public static function html_block_count($html) {
        if (!$html) return 0;
        preg_match_all('/<(h[1-6]|p|ul|ol|blockquote|figure|img|table|div|section|article)\b/i', $html, $m);
        return count($m[0]);
    }

    private static function shortcode_attrs($tag) {
        $attrs = [];
        if (preg_match_all('/([a-zA-Z0-9_\-]+)="([^"]*)"/', $tag, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) $attrs[$m[1]] = html_entity_decode($m[2], ENT_QUOTES, get_bloginfo('charset'));
        }
        return $attrs;
    }

    public static function source_to_html($content, $type, $post_id = 0) {
        if ($type === 'elementor') return self::elementor_to_html($content, $post_id);
        if ($type === 'divi') return self::divi_to_html($content);
        return self::clean_html($content);
    }

    public static function divi_to_html($content) {
        $content = preg_replace('/\[et_pb_button([^\]]*)\]/i', function($m){
            $a = self::shortcode_attrs($m[0]);
            $text = esc_html($a['button_text'] ?? $a['title'] ?? 'Learn more');
            $url = esc_url($a['button_url'] ?? '#');
            return '<p><a href="' . $url . '">' . $text . '</a></p>';
        }, $content);

        $content = preg_replace('/\[et_pb_image([^\]]*)\]/i', function($m){
            $a = self::shortcode_attrs($m[0]);
            if (empty($a['src'])) return '';
            $alt = esc_attr($a['alt'] ?? '');
            return '<figure><img src="' . esc_url($a['src']) . '" alt="' . $alt . '" /></figure>';
        }, $content);

        $content = preg_replace_callback('/\[et_pb_(blurb|cta|promo|toggle|accordion_item)([^\]]*)\](.*?)\[\/et_pb_\1\]/is', function($m){
            $a = self::shortcode_attrs($m[0]);
            $title = trim($a['title'] ?? $a['admin_label'] ?? '');
            $inner = self::divi_to_html($m[3]);
            return ($title ? '<h2>' . esc_html($title) . '</h2>' : '') . $inner;
        }, $content);

        $content = preg_replace_callback('/\[et_pb_text([^\]]*)\](.*?)\[\/et_pb_text\]/is', function($m){
            return self::divi_to_html($m[2]);
        }, $content);

        $content = preg_replace('/\[\/?et_pb_(section|row|column|specialty_section|fullwidth_section)[^\]]*\]/i', '', $content);
        $content = preg_replace('/\[et_pb_[^\]]+\]/i', '', $content);
        $content = preg_replace('/\[\/et_pb_[^\]]+\]/i', '', $content);
        $content = html_entity_decode($content, ENT_QUOTES, get_bloginfo('charset'));
        return self::clean_html(do_shortcode($content));
    }

    public static function elementor_to_html($json, $post_id = 0) {
        $data = json_decode($json, true);
        if (!is_array($data)) return '';
        return self::elementor_nodes_to_html($data, $post_id);
    }

    private static function elementor_nodes_to_html($nodes, $post_id = 0) {
        $html = '';
        foreach ((array) $nodes as $node) {
            if (!is_array($node)) continue;
            $el = $node['elType'] ?? '';
            $widget = $node['widgetType'] ?? '';
            $settings = $node['settings'] ?? [];
            if ($el === 'widget') {
                if ($widget === 'heading') {
                    $title = trim(wp_kses_post($settings['title'] ?? ''));
                    $tag = strtolower(preg_replace('/[^a-z0-9]/', '', $settings['header_size'] ?? 'h2'));
                    if (!in_array($tag, ['h1','h2','h3','h4','h5','h6'], true)) $tag = 'h2';
                    if ($title !== '') $html .= '<' . $tag . '>' . $title . '</' . $tag . '>';
                } elseif (in_array($widget, ['text-editor','theme-post-content'], true)) {
                    $html .= wp_kses_post($settings['editor'] ?? '');
                } elseif ($widget === 'image') {
                    $img = $settings['image'] ?? [];
                    $url = is_array($img) ? ($img['url'] ?? '') : '';
                    $alt = '';
                    if (!empty($img['id'])) $alt = get_post_meta(intval($img['id']), '_wp_attachment_image_alt', true);
                    if ($url) $html .= '<figure><img src="' . esc_url($url) . '" alt="' . esc_attr($alt) . '" /></figure>';
                } elseif ($widget === 'button') {
                    $text = esc_html($settings['text'] ?? 'Learn more');
                    $url = isset($settings['link']['url']) ? esc_url($settings['link']['url']) : '#';
                    $html .= '<p><a href="' . $url . '">' . $text . '</a></p>';
                } elseif (in_array($widget, ['icon-box','image-box','call-to-action'], true)) {
                    $title = trim(wp_kses_post($settings['title_text'] ?? $settings['title'] ?? ''));
                    $desc = trim(wp_kses_post($settings['description_text'] ?? $settings['description'] ?? ''));
                    if ($title) $html .= '<h2>' . $title . '</h2>';
                    if ($desc) $html .= '<p>' . $desc . '</p>';
                } elseif ($widget === 'toggle' || $widget === 'accordion') {
                    foreach (($settings['tabs'] ?? []) as $tab) {
                        if (!empty($tab['tab_title'])) $html .= '<h3>' . esc_html($tab['tab_title']) . '</h3>';
                        if (!empty($tab['tab_content'])) $html .= wp_kses_post($tab['tab_content']);
                    }
                } elseif ($widget === 'html') {
                    $html .= wp_kses_post($settings['html'] ?? '');
                }
            }
            if (!empty($node['elements'])) $html .= self::elementor_nodes_to_html($node['elements'], $post_id);
        }
        return self::clean_html($html);
    }

    public static function clean_html($html) {
        $html = html_entity_decode((string) $html, ENT_QUOTES, get_bloginfo('charset'));
        $html = preg_replace('/<!--\s*\/?.*?-->/s', '', $html);
        $html = preg_replace('/\sclass="[^"]*"/i', '', $html);
        $html = preg_replace('/\sstyle="[^"]*"/i', '', $html);
        $html = preg_replace('/\sid="[^"]*"/i', '', $html);
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);
        return trim(wp_kses_post($html));
    }

    public static function html_to_gutenberg($html) {
        $html = trim(self::clean_html($html));
        if (!$html) return '';
        $html = wpautop($html);
        $blocks = [];
        preg_match_all('/<(h[1-6]|p|ul|ol|blockquote|figure|table)\b[^>]*>.*?<\/\1>/is', $html, $matches);
        if (empty($matches[0])) {
            foreach (preg_split('/\n{2,}/', wp_strip_all_tags($html)) as $para) {
                $para = trim($para);
                if ($para !== '') $blocks[] = '<!-- wp:paragraph --><p>' . esc_html($para) . '</p><!-- /wp:paragraph -->';
            }
            return implode("\n\n", $blocks);
        }
        foreach ($matches[0] as $block_html) {
            if (preg_match('/^<h([1-6])\b/i', $block_html, $hm)) {
                $level = intval($hm[1]);
                $blocks[] = '<!-- wp:heading {"level":' . $level . '} -->' . wp_kses_post($block_html) . '<!-- /wp:heading -->';
            } elseif (preg_match('/^<p\b/i', $block_html)) {
                $text = trim(wp_strip_all_tags($block_html));
                if ($text !== '') $blocks[] = '<!-- wp:paragraph -->' . wp_kses_post($block_html) . '<!-- /wp:paragraph -->';
            } elseif (preg_match('/^<(ul|ol)\b/i', $block_html)) {
                $ordered = stripos($block_html, '<ol') === 0 ? ' {"ordered":true}' : '';
                $blocks[] = '<!-- wp:list' . $ordered . ' -->' . wp_kses_post($block_html) . '<!-- /wp:list -->';
            } elseif (preg_match('/^<blockquote\b/i', $block_html)) {
                $blocks[] = '<!-- wp:quote -->' . wp_kses_post($block_html) . '<!-- /wp:quote -->';
            } elseif (preg_match('/^<figure\b/i', $block_html)) {
                $blocks[] = '<!-- wp:image -->' . wp_kses_post($block_html) . '<!-- /wp:image -->';
            } elseif (preg_match('/^<table\b/i', $block_html)) {
                $blocks[] = '<!-- wp:table -->' . wp_kses_post($block_html) . '<!-- /wp:table -->';
            }
        }
        return implode("\n\n", $blocks);
    }

    public static function preview($post_id, $source_type = 'auto') {
        $source = self::best_source($post_id, $source_type);
        if (!$source['content']) return new WP_Error('no_content', 'No recoverable builder or HTML content was found.');
        $html = self::source_to_html($source['content'], $source['type'], $post_id);
        $gutenberg = self::html_to_gutenberg($html);
        return [
            'post_id' => $post_id,
            'source' => $source['source'],
            'source_type' => $source['type'],
            'revision_id' => $source['revision_id'],
            'html_preview' => wp_kses_post($html),
            'gutenberg_content' => $gutenberg,
            'word_count' => str_word_count(wp_strip_all_tags($gutenberg)),
            'heading_count' => preg_match_all('/<!-- wp:heading/i', $gutenberg),
        ];
    }

    public static function apply($post_id, $gutenberg_content) {
        if (!current_user_can('edit_post', $post_id)) return new WP_Error('forbidden', 'You cannot edit this post.');
        $post = get_post($post_id);
        if (!$post) return new WP_Error('not_found', 'Post not found.');
        update_post_meta($post_id, '_rankrepair_content_recovery_backup', wp_json_encode([
            'content' => $post->post_content,
            'elementor_data' => get_post_meta($post_id, '_elementor_data', true),
            'date' => current_time('mysql'),
            'user_id' => get_current_user_id(),
        ]));
        $updated = wp_update_post(['ID'=>$post_id, 'post_content'=>wp_kses_post($gutenberg_content)], true);
        clean_post_cache($post_id);
        return $updated;
    }
}
