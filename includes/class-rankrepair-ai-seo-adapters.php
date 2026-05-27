<?php
if (!defined('ABSPATH')) exit;

class RANKREPAIR_AI_SEO_Adapters {
    public static function providers() {
        return [
            'auto' => ['label' => 'Auto-detect active SEO plugin', 'keys' => null],
            'yoast' => [
                'label' => 'Yoast SEO',
                'detect' => 'wordpress-seo/wp-seo.php',
                'keys' => [
                    'seo_title' => '_yoast_wpseo_title',
                    'meta_description' => '_yoast_wpseo_metadesc',
                    'focus_keyword' => '_yoast_wpseo_focuskw',
                    'focus_keyword_input' => '_yoast_wpseo_focuskw_text_input',
                    'score' => '_yoast_wpseo_linkdex',
                    'content_score' => '_yoast_wpseo_content_score',
                ],
            ],
            'rank_math' => [
                'label' => 'Rank Math SEO',
                'detect' => 'seo-by-rank-math/rank-math.php',
                'keys' => [
                    'seo_title' => 'rank_math_title',
                    'meta_description' => 'rank_math_description',
                    'focus_keyword' => 'rank_math_focus_keyword',
                    'focus_keyword_input' => 'rank_math_focus_keyword',
                    'score' => 'rank_math_seo_score',
                ],
            ],
            'aioseo' => [
                'label' => 'All in One SEO',
                'detect' => 'all-in-one-seo-pack/all_in_one_seo_pack.php',
                'keys' => [
                    'seo_title' => '_aioseo_title',
                    'meta_description' => '_aioseo_description',
                    'focus_keyword' => '_aioseo_keywords',
                    'focus_keyword_input' => '_aioseo_keywords',
                ],
            ],
            'seopress' => [
                'label' => 'SEOPress',
                'detect' => 'wp-seopress/seopress.php',
                'keys' => [
                    'seo_title' => '_seopress_titles_title',
                    'meta_description' => '_seopress_titles_desc',
                    'focus_keyword' => '_seopress_analysis_target_kw',
                    'focus_keyword_input' => '_seopress_analysis_target_kw',
                ],
            ],
            'the_seo_framework' => [
                'label' => 'The SEO Framework',
                'detect' => 'autodescription/autodescription.php',
                'keys' => [
                    'seo_title' => '_genesis_title',
                    'meta_description' => '_genesis_description',
                    'focus_keyword' => '_rankrepair_focus_keyword',
                    'focus_keyword_input' => '_rankrepair_focus_keyword',
                ],
            ],
            'slim_seo' => [
                'label' => 'Slim SEO',
                'detect' => 'slim-seo/slim-seo.php',
                'keys' => [
                    'seo_title' => 'slim_seo_title',
                    'meta_description' => 'slim_seo_description',
                    'focus_keyword' => '_rankrepair_focus_keyword',
                    'focus_keyword_input' => '_rankrepair_focus_keyword',
                ],
            ],
            'smartcrawl' => [
                'label' => 'SmartCrawl SEO',
                'detect' => 'wpmu-dev-seo/wpmu-dev-seo.php',
                'keys' => [
                    'seo_title' => '_wds_title',
                    'meta_description' => '_wds_metadesc',
                    'focus_keyword' => '_wds_focus-keywords',
                    'focus_keyword_input' => '_wds_focus-keywords',
                ],
            ],
            'custom' => [
                'label' => 'Custom SEO meta keys',
                'keys' => null,
            ],
        ];
    }

    public static function active_provider_key() {
        $settings = RANKREPAIR_AI_Plugin::settings();
        $selected = isset($settings['seo_plugin']) ? sanitize_key($settings['seo_plugin']) : 'auto';
        if ($selected && $selected !== 'auto') return $selected;
        return self::detect_provider_key();
    }

    public static function detect_provider_key() {
        if (!function_exists('is_plugin_active')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        foreach (self::providers() as $key => $provider) {
            if (in_array($key, ['auto','custom'], true)) continue;
            if (!empty($provider['detect']) && function_exists('is_plugin_active') && is_plugin_active($provider['detect'])) {
                return $key;
            }
        }
        return 'custom';
    }

    public static function active_provider_label() {
        $providers = self::providers();
        $key = self::active_provider_key();
        return isset($providers[$key]) ? $providers[$key]['label'] : 'SEO plugin';
    }

    public static function keys() {
        $settings = RANKREPAIR_AI_Plugin::settings();
        $key = self::active_provider_key();
        if ($key === 'custom') {
            return [
                'seo_title' => sanitize_key($settings['custom_title_key'] ?? ''),
                'meta_description' => sanitize_key($settings['custom_description_key'] ?? ''),
                'focus_keyword' => sanitize_key($settings['custom_keyword_key'] ?? ''),
                'focus_keyword_input' => sanitize_key($settings['custom_keyword_key'] ?? ''),
            ];
        }
        $providers = self::providers();
        return $providers[$key]['keys'] ?? $providers['yoast']['keys'];
    }


    public static function title_template_suffix() {
        $provider = self::active_provider_key();
        if ($provider === 'rank_math') return '%sep% %sitename%';
        if ($provider === 'aioseo') return '#separator_sa #site_title';
        if ($provider === 'seopress') return '%%sep%% %%sitetitle%%';
        if ($provider === 'the_seo_framework') return '%%sep%% %%sitename%%';
        if ($provider === 'slim_seo') return '%%sep%% %%sitename%%';
        if ($provider === 'smartcrawl') return '%%sep%% %%sitename%%';
        if ($provider === 'yoast') return '%%sep%% %%sitename%%';
        return '%%sep%% %%sitename%%';
    }

    public static function title_template_instruction() {
        $suffix = self::title_template_suffix();
        return 'Use the active SEO plugin title suffix variables at the end of SEO titles: ' . $suffix . '. Keep the human-written part before the suffix concise and natural.';
    }

    public static function enforce_title_template_suffix($title) {
        $title = trim((string) $title);
        if ($title === '') return $title;
        $suffix = self::title_template_suffix();
        $tokens = ['%%sep%%', '%%sitename%%', '%%sitetitle%%', '%sep%', '%sitename%', '#separator_sa', '#site_title'];
        foreach ($tokens as $token) {
            if (stripos($title, $token) !== false) return $title;
        }
        return trim($title . ' ' . $suffix);
    }

    public static function get_values($post_id) {
        $keys = self::keys();
        return [
            'seo_title' => !empty($keys['seo_title']) ? get_post_meta($post_id, $keys['seo_title'], true) : '',
            'meta_description' => !empty($keys['meta_description']) ? get_post_meta($post_id, $keys['meta_description'], true) : '',
            'focus_keyword' => !empty($keys['focus_keyword']) ? get_post_meta($post_id, $keys['focus_keyword'], true) : '',
        ];
    }

    public static function backup($post_id) {
        $keys = self::keys();
        $values = self::get_values($post_id);
        $backup = [
            'time' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'seo_plugin' => self::active_provider_key(),
            'seo_plugin_label' => self::active_provider_label(),
            'keys' => $keys,
            'values' => $values,
        ];
        add_post_meta($post_id, '_rankrepair_ai_backup', wp_json_encode($backup));
        update_post_meta($post_id, '_rankrepair_ai_last_backup', wp_json_encode($backup));
    }

    public static function update($post_id, $values) {
        if (!current_user_can('edit_post', $post_id)) return new WP_Error('forbidden', 'You cannot edit this post.');
        self::backup($post_id);
        $keys = self::keys();
        if (isset($values['seo_title']) && !empty($keys['seo_title'])) update_post_meta($post_id, $keys['seo_title'], sanitize_text_field(self::enforce_title_template_suffix($values['seo_title'])));
        if (isset($values['meta_description']) && !empty($keys['meta_description'])) update_post_meta($post_id, $keys['meta_description'], sanitize_textarea_field($values['meta_description']));
        if (isset($values['focus_keyword']) && !empty($keys['focus_keyword'])) {
            $kw = sanitize_text_field($values['focus_keyword']);
            update_post_meta($post_id, $keys['focus_keyword'], $kw);
            if (!empty($keys['focus_keyword_input']) && $keys['focus_keyword_input'] !== $keys['focus_keyword']) update_post_meta($post_id, $keys['focus_keyword_input'], $kw);
        }
        update_post_meta($post_id, '_rankrepair_ai_last_updated', current_time('mysql'));
        update_post_meta($post_id, '_rankrepair_ai_last_provider', self::active_provider_key());
        self::refresh_after_update($post_id, $values);
        return true;
    }

    public static function refresh_after_update($post_id, $values = []) {
        $settings = RANKREPAIR_AI_Plugin::settings();
        if (empty($settings['refresh_scores_after_update'])) return;
        $post = get_post($post_id);
        if (!$post) return;
        $keys = self::keys();
        $current = self::get_values($post_id);
        $seo_title = $values['seo_title'] ?? $current['seo_title'];
        $meta_description = $values['meta_description'] ?? $current['meta_description'];
        $focus_keyword = $values['focus_keyword'] ?? $current['focus_keyword'];
        $title_len = function_exists('mb_strlen') ? mb_strlen(wp_strip_all_tags($seo_title)) : strlen(wp_strip_all_tags($seo_title));
        $desc_len = function_exists('mb_strlen') ? mb_strlen(wp_strip_all_tags($meta_description)) : strlen(wp_strip_all_tags($meta_description));
        $kw_len = function_exists('mb_strlen') ? mb_strlen(trim($focus_keyword)) : strlen(trim($focus_keyword));
        $score = 0;
        if ($title_len >= 30 && $title_len <= 70) $score += 30;
        if ($desc_len >= 110 && $desc_len <= 160) $score += 40;
        if ($kw_len >= 2 && $kw_len <= 80) $score += 20;
        if ($focus_keyword && stripos(wp_strip_all_tags($seo_title . ' ' . $meta_description), $focus_keyword) !== false) $score += 10;
        if (!empty($keys['score'])) update_post_meta($post_id, $keys['score'], min(90, max(1, $score)));
        if (!empty($keys['content_score'])) update_post_meta($post_id, $keys['content_score'], min(90, max(1, $score)));
        clean_post_cache($post_id);
        wp_cache_delete($post_id, 'post_meta');
        do_action('rankrepair_ai_after_seo_meta_update', $post_id, $values, self::active_provider_key(), $score);
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Third-party Yoast hook intentionally triggered for compatibility.
        do_action('wpseo_save_indexable', $post_id);
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Third-party Yoast hook intentionally triggered for compatibility.
        do_action('wpseo_saved_postdata', $post_id);
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Third-party Rank Math hook intentionally triggered for compatibility.
        do_action('rank_math/updated_post_meta', $post_id);
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Third-party SEOPress hook intentionally triggered for compatibility.
        do_action('seopress_titles_options_save', $post_id);
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Third-party SEO Framework hook intentionally triggered for compatibility.
        do_action('the_seo_framework_updated_post_meta', $post_id);
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Third-party Slim SEO hook intentionally triggered for compatibility.
        do_action('slim_seo_post_updated', $post_id);
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Third-party SmartCrawl hook intentionally triggered for compatibility.
        do_action('wds_update_post_meta', $post_id);
        wp_update_post(['ID' => $post_id, 'post_modified' => current_time('mysql'), 'post_modified_gmt' => current_time('mysql', 1)], true, false);
    }
}
