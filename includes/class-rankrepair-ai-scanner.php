<?php
if (!defined('ABSPATH')) exit;

class RANKREPAIR_AI_Scanner {
    public static function scan($args = []) {
        $settings = RANKREPAIR_AI_Plugin::settings();
        $keys = RANKREPAIR_AI_SEO_Adapters::keys();
        $args = wp_parse_args($args, [
            'post_type' => 'post',
            'limit' => 50,
            'offset' => 0,
            'issue_filter' => 'all',
            'search' => '',
            'post_status' => 'any',
            'min_id' => 0,
            'max_id' => 0,
            'scan_depth' => 1000,
        ]);

        $limit = min(1000, max(1, intval($args['limit'])));
        $offset = max(0, intval($args['offset']));
        $issue_filter = sanitize_key($args['issue_filter']);
        $scan_depth = min(10000, max($limit, intval($args['scan_depth'])));
        $min_id = max(0, intval($args['min_id']));
        $max_id = max(0, intval($args['max_id']));
        $status = sanitize_key($args['post_status']);
        $status = $status === 'any' ? ['publish', 'draft', 'private', 'pending', 'future'] : [$status];

        $meta_query = [];
        if ($issue_filter === 'missing_title') {
            $meta_query[] = ['relation' => 'OR',
                ['key' => $keys['seo_title'], 'compare' => 'NOT EXISTS'],
                ['key' => $keys['seo_title'], 'value' => '', 'compare' => '='],
            ];
        } elseif ($issue_filter === 'missing_description') {
            $meta_query[] = ['relation' => 'OR',
                ['key' => $keys['meta_description'], 'compare' => 'NOT EXISTS'],
                ['key' => $keys['meta_description'], 'value' => '', 'compare' => '='],
            ];
        } elseif ($issue_filter === 'missing_keyword') {
            $meta_query[] = ['relation' => 'OR',
                ['key' => $keys['focus_keyword'], 'compare' => 'NOT EXISTS'],
                ['key' => $keys['focus_keyword'], 'value' => '', 'compare' => '='],
            ];
        }

        $base_query_args = [
            'post_type' => sanitize_key($args['post_type']),
            'post_status' => $status,
            'orderby' => 'ID',
            'order' => 'DESC',
            's' => sanitize_text_field($args['search']),
            'no_found_rows' => false,
        ];

        if (!empty($meta_query)) {
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Metadata scanning is required for SEO status filters and is paginated.
            $base_query_args['meta_query'] = $meta_query;
        }

        $items = [];
        $inspected = 0;
        $found_posts = 0;
        $page_size = ($issue_filter === 'issues') ? min(250, max(50, $limit)) : $limit;
        $current_offset = $offset;

        while (count($items) < $limit && $inspected < $scan_depth) {
            $query_args = $base_query_args;
            $query_args['posts_per_page'] = min($page_size, $scan_depth - $inspected);
            $query_args['offset'] = $current_offset;

            $query = new WP_Query($query_args);
            if ($found_posts === 0) $found_posts = intval($query->found_posts);
            if (empty($query->posts)) break;

            foreach ($query->posts as $post) {
                $inspected++;
                if ($min_id && intval($post->ID) < $min_id) continue;
                if ($max_id && intval($post->ID) > $max_id) continue;

                $audit = self::audit_post($post->ID, $settings);

                if ($issue_filter === 'issues' && empty($audit['issues'])) continue;
                if ($issue_filter === 'missing_title' && $audit['seo_title'] !== '') continue;
                if ($issue_filter === 'missing_description' && $audit['meta_description'] !== '') continue;
                if ($issue_filter === 'missing_keyword' && $audit['focus_keyword'] !== '') continue;

                $items[] = $audit;
                if (count($items) >= $limit) break;
            }

            $current_offset += count($query->posts);
            if (count($query->posts) < $query_args['posts_per_page']) break;

            // For "all" and direct missing filters, one query is enough because SQL already narrows the result.
            if ($issue_filter !== 'issues') break;
        }

        return [
            'items' => $items,
            'found_posts' => intval($found_posts),
            'max_num_pages' => $limit ? intval(ceil(max(1, $found_posts) / $limit)) : 1,
            'inspected' => intval($inspected),
            'next_offset' => intval($current_offset),
            'limit' => intval($limit),
            'issue_filter' => $issue_filter,
        ];
    }

    public static function health_summary() {
        $public_posts = 0;
        foreach (['post', 'page'] as $pt) {
            $counts = wp_count_posts($pt);
            if ($counts && isset($counts->publish)) $public_posts += intval($counts->publish);
        }

        $query = new WP_Query([
            'post_type' => ['post', 'page'],
            'post_status' => ['publish'],
            'posts_per_page' => 250,
            'orderby' => 'modified',
            'order' => 'DESC',
            'no_found_rows' => true,
        ]);

        $issues = 0;
        $missing_any = 0;
        foreach ($query->posts as $post) {
            $audit = self::audit_post($post->ID);
            if (!empty($audit['issues'])) $issues++;
            if ($audit['seo_title'] === '' || $audit['meta_description'] === '' || $audit['focus_keyword'] === '') $missing_any++;
        }

        return [
            'provider_label' => RANKREPAIR_AI_SEO_Adapters::active_provider_label(),
            'public_posts' => $public_posts,
            'sampled' => count($query->posts),
            'issues' => $issues,
            'missing_any' => $missing_any,
        ];
    }

    public static function audit_post($post_id, $settings = null) {
        $settings = $settings ?: RANKREPAIR_AI_Plugin::settings();
        $values = RANKREPAIR_AI_SEO_Adapters::get_values($post_id);
        $title = $values['seo_title'];
        $desc = $values['meta_description'];
        $kw = $values['focus_keyword'];
        $issues = [];
        $old = trim((string) $settings['old_domain']);
        $brand = trim((string) $settings['brand_name']);
        $max_title = intval($settings['max_title_chars']);
        $max_desc = intval($settings['max_description_chars']);
        $title_for_length = trim(preg_replace('/(%%sep%%|%%sitename%%|%%sitetitle%%|%sep%|%sitename%|#separator_sa|#site_title)/i', '', (string) $title));

        foreach ([['SEO title',$title], ['Meta description',$desc], ['Focus keyword',$kw]] as $pair) {
            if ($old && stripos($pair[1], $old) !== false) $issues[] = $pair[0] . ' contains old domain';
            if (preg_match('/https?:\/\/|www\./i', $pair[1])) $issues[] = $pair[0] . ' contains URL/domain pattern';
            if (preg_match('/%%(title|excerpt|category|tag|term_title)%%/i', $pair[1])) $issues[] = $pair[0] . ' contains unresolved SEO template variables';
            if (preg_match('/(.)\1{5,}/u', $pair[1])) $issues[] = $pair[0] . ' has repeated characters';
            if (preg_match('/[|\-–—:]{2,}/u', $pair[1])) $issues[] = $pair[0] . ' has duplicated separators';
        }

        // Missing SEO fields are always issues. A post with empty metadata must never be marked as good.
        if (trim((string) $title) === '') $issues[] = 'Missing SEO title';
        if (trim((string) $desc) === '') $issues[] = 'Missing meta description';
        if (trim((string) $kw) === '') $issues[] = 'Missing focus keyword';

        if ($title_for_length !== '' && mb_strlen($title_for_length) > $max_title) $issues[] = 'SEO title is too long';
        if ($title_for_length !== '' && mb_strlen($title_for_length) < 15) $issues[] = 'SEO title is too short';
        if ($desc !== '' && mb_strlen($desc) > $max_desc) $issues[] = 'Meta description is too long';
        if ($desc !== '' && mb_strlen($desc) < 50) $issues[] = 'Meta description is too short';
        if ($kw !== '' && mb_strlen($kw) > 70) $issues[] = 'Focus keyword is too long';
        if ($brand && substr_count(strtolower($title), strtolower($brand)) > 1) $issues[] = 'SEO title repeats brand name';

        return [
            'id' => $post_id,
            'edit_link' => get_edit_post_link($post_id, 'raw'),
            'post_title' => get_the_title($post_id),
            'post_type' => get_post_type($post_id),
            'status' => get_post_status($post_id),
            'seo_plugin' => RANKREPAIR_AI_SEO_Adapters::active_provider_label(),
            'seo_title' => $title,
            'meta_description' => $desc,
            'focus_keyword' => $kw,
            'issues' => array_values(array_unique($issues)),
            'has_backup' => (bool) get_post_meta($post_id, '_rankrepair_ai_last_backup', true),
        ];
    }

    public static function apply_rule_preview($post_id, $field, $action, $params) {
        $keys = RANKREPAIR_AI_SEO_Adapters::keys();
        $map = ['seo_title'=>$keys['seo_title'] ?? '', 'meta_description'=>$keys['meta_description'] ?? '', 'focus_keyword'=>$keys['focus_keyword'] ?? ''];
        if (!isset($map[$field]) || empty($map[$field])) return new WP_Error('invalid_field', 'Invalid field or meta key.');
        $current = get_post_meta($post_id, $map[$field], true);
        $next = $current;
        switch ($action) {
            case 'replace_text': $next = str_replace($params['find'] ?? '', $params['replace'] ?? '', $current); break;
            case 'remove_text': $next = str_replace($params['text'] ?? '', '', $current); break;
            case 'add_prefix': $next = ($params['prefix'] ?? '') . $current; break;
            case 'add_suffix': $next = $current . ($params['suffix'] ?? ''); break;
            case 'set_pattern': $next = RANKREPAIR_AI_Plugin::render_pattern($params['pattern'] ?? '{post_title}', $post_id, $current); break;
            case 'copy_post_title': $next = get_the_title($post_id); break;
            case 'copy_excerpt': $p = get_post($post_id); $next = $p ? wp_strip_all_tags($p->post_excerpt) : ''; break;
            case 'limit_chars': $limit = max(1, intval($params['limit'] ?? 60)); $next = mb_substr($current, 0, $limit); break;
            case 'clear': $next = ''; break;
            default: return new WP_Error('invalid_action', 'Invalid action.');
        }
        $next = trim(preg_replace('/\s+/', ' ', $next));
        return ['post_id'=>$post_id, 'field'=>$field, 'before'=>$current, 'after'=>$next];
    }
}
