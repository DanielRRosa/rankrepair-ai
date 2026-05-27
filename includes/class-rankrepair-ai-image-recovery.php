<?php
if (!defined('ABSPATH')) exit;

class RANKREPAIR_AI_Image_Recovery {
    public static function scan($args = []) {
        $post_type = sanitize_key($args['post_type'] ?? 'any');
        $post_status = sanitize_key($args['post_status'] ?? 'any');
        $limit = max(1, min(300, intval($args['limit'] ?? 100)));
        $offset = max(0, intval($args['offset'] ?? 0));
        $mode = sanitize_key($args['mode'] ?? 'all');
        $query = [
            'post_type' => $post_type === 'any' ? get_post_types(['public' => true], 'names') : $post_type,
            'post_status' => $post_status === 'any' ? ['publish','draft','pending','private','future'] : $post_status,
            'posts_per_page' => $limit,
            'offset' => $offset,
            'orderby' => 'ID',
            'order' => 'DESC',
            'no_found_rows' => false,
        ];
        $q = new WP_Query($query);
        $items = [];
        foreach ($q->posts as $post) {
            $audit = self::audit_post($post->ID, $mode);
            if (!empty($audit['issues'])) $items[] = $audit;
        }
        return ['items' => $items, 'found_posts' => intval($q->found_posts), 'inspected' => count($q->posts)];
    }

    public static function audit_post($post_id, $mode = 'all') {
        $post = get_post($post_id);
        if (!$post) return ['post_id' => $post_id, 'issues' => []];
        $issues = [];
        $thumb_id = get_post_thumbnail_id($post_id);
        if (!$thumb_id) {
            $issues[] = ['type' => 'missing_featured_image', 'label' => 'Missing featured image', 'target' => 'featured_image'];
        } elseif (!get_post($thumb_id) || get_post_type($thumb_id) !== 'attachment') {
            $issues[] = ['type' => 'broken_featured_image', 'label' => 'Featured image attachment is missing', 'target' => 'featured_image', 'attachment_id' => intval($thumb_id)];
        } else {
            $path = get_attached_file($thumb_id);
            if ($path && !file_exists($path)) $issues[] = ['type' => 'missing_featured_image_file', 'label' => 'Featured image file is missing from uploads', 'target' => 'featured_image', 'attachment_id' => intval($thumb_id)];
            $alt = get_post_meta($thumb_id, '_wp_attachment_image_alt', true);
            if (!$alt) $issues[] = ['type' => 'missing_featured_alt', 'label' => 'Featured image is missing alt text', 'target' => 'featured_image', 'attachment_id' => intval($thumb_id)];
        }
        preg_match_all('/<img\s[^>]*>/i', (string) $post->post_content, $matches);
        $idx = 0;
        foreach ($matches[0] as $tag) {
            $idx++;
            $src = self::attr($tag, 'src');
            $alt = self::attr($tag, 'alt');
            $attachment_id = self::attachment_id_from_tag($tag, $src);
            if (!$src) { $issues[] = ['type'=>'image_missing_src','label'=>'Inline image has no src','target'=>'inline_image','index'=>$idx]; continue; }
            if (strpos($src, 'http') === 0 && self::looks_like_old_or_broken_url($src)) $issues[] = ['type'=>'possibly_broken_image_url','label'=>'Inline image URL may be external/old','target'=>'inline_image','index'=>$idx,'src'=>$src];
            if ($attachment_id && !get_post($attachment_id)) $issues[] = ['type'=>'missing_inline_attachment','label'=>'Inline image attachment ID is missing','target'=>'inline_image','index'=>$idx,'attachment_id'=>$attachment_id,'src'=>$src];
            if ($attachment_id && get_post($attachment_id)) {
                $path = get_attached_file($attachment_id);
                if ($path && !file_exists($path)) $issues[] = ['type'=>'missing_inline_file','label'=>'Inline image file is missing from uploads','target'=>'inline_image','index'=>$idx,'attachment_id'=>$attachment_id,'src'=>$src];
                if (!get_post_meta($attachment_id, '_wp_attachment_image_alt', true) && !$alt) $issues[] = ['type'=>'missing_inline_alt','label'=>'Inline image is missing alt text','target'=>'inline_image','index'=>$idx,'attachment_id'=>$attachment_id,'src'=>$src];
            } elseif (!$alt) {
                $issues[] = ['type'=>'missing_inline_alt','label'=>'Inline image is missing alt text','target'=>'inline_image','index'=>$idx,'src'=>$src];
            }
        }
        if ($mode === 'broken_only') $issues = array_values(array_filter($issues, function($i){ return strpos($i['type'], 'missing') !== false || strpos($i['type'], 'broken') !== false; }));
        return [
            'post_id' => intval($post_id),
            'post_title' => get_the_title($post_id),
            'post_type' => get_post_type($post_id),
            'edit_link' => get_edit_post_link($post_id, 'raw'),
            'featured_image_id' => intval($thumb_id),
            'featured_image_thumb' => $thumb_id ? wp_get_attachment_image_url($thumb_id, 'thumbnail') : '',
            'featured_image_edit_link' => $thumb_id ? get_edit_post_link($thumb_id, 'raw') : '',
            'issues' => $issues,
        ];
    }

    public static function suggest($post_id, $issue = []) {
        if (class_exists('RANKREPAIR_AI_AI') && method_exists('RANKREPAIR_AI_AI', 'suggest_replacement_image')) {
            return RANKREPAIR_AI_AI::suggest_replacement_image($post_id, $issue);
        }
        return new WP_Error('ai_unavailable', 'AI image suggestion is unavailable.');
    }

    public static function apply_replacement($post_id, $args = []) {
        $target = sanitize_key($args['target'] ?? 'featured_image');
        $attachment_id = intval($args['attachment_id'] ?? 0);
        $image_url = esc_url_raw($args['image_url'] ?? '');
        $alt_text = sanitize_text_field($args['alt_text'] ?? '');
        if (!$attachment_id && $image_url) {
            $attachment_id = self::sideload_image($image_url, $post_id, $alt_text);
            if (is_wp_error($attachment_id)) return $attachment_id;
        }
        if (!$attachment_id || !get_post($attachment_id)) return new WP_Error('missing_attachment', 'Please provide a valid Media Library attachment ID or image URL.');
        update_post_meta($post_id, '_rankrepair_image_recovery_backup', wp_json_encode(['featured_image_id' => get_post_thumbnail_id($post_id), 'content' => get_post_field('post_content', $post_id), 'time' => current_time('mysql')]));
        if ($alt_text) update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
        if ($target === 'featured_image') {
            set_post_thumbnail($post_id, $attachment_id);
            return ['message' => 'Featured image replaced.', 'attachment_id' => $attachment_id];
        }
        return ['message' => 'Image metadata updated. Inline replacement can be handled manually from the preview for safety.', 'attachment_id' => $attachment_id];
    }

    private static function sideload_image($url, $post_id, $desc = '') {
        if (!function_exists('media_sideload_image')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }
        $result = media_sideload_image($url, $post_id, $desc, 'id');
        return is_wp_error($result) ? $result : intval($result);
    }

    private static function attr($tag, $attr) {
        if (preg_match('/\s' . preg_quote($attr, '/') . '\s*=\s*["\']([^"\']*)["\']/i', $tag, $m)) return html_entity_decode($m[1]);
        return '';
    }

    private static function attachment_id_from_tag($tag, $src) {
        if (preg_match('/wp-image-(\d+)/', $tag, $m)) return intval($m[1]);
        if ($src) {
            $id = attachment_url_to_postid($src);
            if ($id) return intval($id);
        }
        return 0;
    }

    private static function looks_like_old_or_broken_url($src) {
        $settings = RANKREPAIR_AI_Plugin::settings();
        $old = trim($settings['old_domain'] ?? '');
        if ($old && stripos($src, $old) !== false) return true;
        $home_host = wp_parse_url(home_url(), PHP_URL_HOST);
        $src_host = wp_parse_url($src, PHP_URL_HOST);
        return $src_host && $home_host && $src_host !== $home_host;
    }
}
