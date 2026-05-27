<?php
if (!defined('ABSPATH')) exit;

class RANKREPAIR_AI_Plugin {
    const OPTION_KEY = 'rankrepair_ai_settings';

    public static function init() {
        new RANKREPAIR_AI_Admin();
    }

    public static function activate() {
        $defaults = self::defaults();
        $current = get_option(self::OPTION_KEY, []);
        update_option(self::OPTION_KEY, wp_parse_args($current, $defaults));
    }

    public static function defaults() {
        return [
            'old_domain' => '',
            'new_domain' => home_url(),
            'brand_name' => get_bloginfo('name'),
            'language' => 'English',
            'ai_provider' => 'openai',
            'openai_api_key' => '',
            'openai_model' => 'gpt-4o-mini',
            'anthropic_api_key' => '',
            'anthropic_model' => 'claude-3-5-haiku-latest',
            'gemini_api_key' => '',
            'gemini_model' => 'gemini-2.5-flash',
            'ai_batch_size' => 3,
            'ai_batch_delay_ms' => 0,
            'ai_throttle_mode' => 'auto',
            'retry_attempts' => 3,
            'retry_delay_ms' => 800,
            'max_content_chars' => 3500,
            'max_title_chars' => 60,
            'max_description_chars' => 155,
            'refresh_yoast_after_update' => 1,
            'refresh_scores_after_update' => 1,
            'seo_plugin' => 'auto',
            'custom_title_key' => '',
            'custom_description_key' => '',
            'custom_keyword_key' => '',
            'brand_voice' => 'Professional, helpful, trustworthy, and concise.',
            'default_content_status' => 'draft',
            'enabled_post_types' => ['post','page'],
            'safe_compatibility_mode' => 1,
            'show_editor_box' => 1,
            'show_list_table_tools' => 1,
            'disable_builder_content_overwrite' => 1,
            'render_schema_frontend' => 0,
            'onboarding_completed' => 0,
        ];
    }

    public static function settings() {
        return wp_parse_args(get_option(self::OPTION_KEY, []), self::defaults());
    }



    public static function has_ai_credentials($settings = null) {
        $settings = $settings ? wp_parse_args($settings, self::defaults()) : self::settings();
        $provider = isset($settings['ai_provider']) ? $settings['ai_provider'] : 'openai';
        if ($provider === 'anthropic') return !empty($settings['anthropic_api_key']);
        if ($provider === 'gemini') return !empty($settings['gemini_api_key']);
        return !empty($settings['openai_api_key']);
    }

    public static function yoast_keys() {
        return RANKREPAIR_AI_SEO_Adapters::keys();
    }

    public static function backup($post_id) {
        RANKREPAIR_AI_SEO_Adapters::backup($post_id);
    }

    public static function update_yoast($post_id, $values) {
        return RANKREPAIR_AI_SEO_Adapters::update($post_id, $values);
    }

    public static function refresh_yoast_after_update($post_id, $values = []) {
        return RANKREPAIR_AI_SEO_Adapters::refresh_after_update($post_id, $values);
    }

    public static function render_pattern($pattern, $post_id, $current_value = '') {
        $post = get_post($post_id);
        $settings = self::settings();
        $categories = get_the_category($post_id);
        $category = !empty($categories) ? $categories[0]->name : '';
        $replacements = [
            '{post_title}' => get_the_title($post_id),
            '{site_name}' => get_bloginfo('name'),
            '{brand_name}' => $settings['brand_name'],
            '{category}' => $category,
            '{primary_category}' => $category,
            '{current_value}' => $current_value,
            '{current_seo_title}' => RANKREPAIR_AI_SEO_Adapters::get_values($post_id)['seo_title'],
            '{current_focus_keyword}' => RANKREPAIR_AI_SEO_Adapters::get_values($post_id)['focus_keyword'],
            '{excerpt}' => $post ? wp_strip_all_tags($post->post_excerpt) : '',
        ];
        return strtr($pattern, $replacements);
    }
}
