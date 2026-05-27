<?php
if (!defined('ABSPATH')) exit;

class RANKREPAIR_AI_AI {
    public static function provider_models() {
        return [
            'openai' => ['gpt-4o-mini', 'gpt-4.1-mini', 'gpt-4.1', 'gpt-4o'],
            'anthropic' => ['claude-3-5-haiku-latest', 'claude-sonnet-4-5', 'claude-opus-4-1'],
            'gemini' => ['gemini-2.5-flash', 'gemini-2.5-pro', 'gemini-2.0-flash'],
        ];
    }

    public static function generate($post_id) {
        $settings = RANKREPAIR_AI_Plugin::settings();
        $provider = self::normalize_provider($settings['ai_provider'] ?? 'openai');
        $api_key = self::api_key_for($provider, $settings);
        if (empty($api_key)) {
            return new WP_Error('missing_key', sprintf('%s API key is missing. Add it in Step 1 and click Save settings.', self::provider_label($provider)));
        }

        $post = get_post($post_id);
        if (!$post) return new WP_Error('missing_post', 'Post not found.');

        $max_content_chars = max(1000, min(20000, intval($settings['max_content_chars'] ?? 3500)));
        $content = wp_strip_all_tags($post->post_content);
        $content = mb_substr(preg_replace('/\s+/', ' ', $content), 0, $max_content_chars);
        $excerpt = wp_strip_all_tags($post->post_excerpt);
        if (!$excerpt) $excerpt = mb_substr($content, 0, 500);

        $current = RANKREPAIR_AI_SEO_Adapters::get_values($post_id);

        $prompt = self::prompt($post, $excerpt, $content, $current, $settings);
        $response = self::request_with_retries($provider, $prompt, $api_key, $settings);
        if (is_wp_error($response)) return $response;

        $json = self::parse_json($response);
        if (!is_array($json)) {
            return new WP_Error('bad_json', 'AI returned a response, but it was not valid JSON. Try a smaller content limit or a different model.');
        }

        $after = [
            'seo_title' => sanitize_text_field($json['seo_title'] ?? ''),
            'meta_description' => sanitize_textarea_field($json['meta_description'] ?? ''),
            'focus_keyword' => sanitize_text_field($json['focus_keyword'] ?? ''),
        ];

        $after['seo_title'] = RANKREPAIR_AI_SEO_Adapters::enforce_title_template_suffix(self::limit_text($after['seo_title'], intval($settings['max_title_chars'] ?? 60)));
        $after['meta_description'] = self::limit_text($after['meta_description'], intval($settings['max_description_chars'] ?? 155));
        $after['focus_keyword'] = self::limit_text($after['focus_keyword'], 70);

        if ($after['seo_title'] === '' && $after['meta_description'] === '' && $after['focus_keyword'] === '') {
            return new WP_Error('empty_ai_result', 'AI returned empty SEO fields. Check the provider, model, and API key.');
        }

        return [
            'post_id' => $post_id,
            'provider' => $provider,
            'model' => self::model_for($provider, $settings),
            'before' => $current,
            'after' => $after,
            'reason_for_change' => sanitize_textarea_field($json['reason_for_change'] ?? 'Generated from post content.'),
        ];
    }


    public static function generate_post_blueprint($args) {
        $settings = RANKREPAIR_AI_Plugin::settings();
        $provider = self::normalize_provider($settings['ai_provider'] ?? 'openai');
        $api_key = self::api_key_for($provider, $settings);
        if (!$api_key) return new WP_Error('missing_api_key', self::provider_label($provider) . ' API key is missing.');
        $topic = sanitize_text_field($args['topic'] ?? '');
        if ($topic === '') return new WP_Error('missing_topic', 'Please add a topic or prompt.');
        $post_type = sanitize_key($args['post_type'] ?? 'post');
        $audience = sanitize_text_field($args['audience'] ?? '');
        $tone = sanitize_text_field($args['tone'] ?? 'professional');
        $language = sanitize_text_field($args['language'] ?? ($settings['language'] ?? 'English'));
        $brand = sanitize_text_field($settings['brand_name'] ?? get_bloginfo('name'));
        $prompt = "Create a WordPress-ready SEO content blueprint. Return ONLY valid JSON.\n\n" .
            "Language: {$language}\nBrand: {$brand}\nPost type: {$post_type}\nAudience: {$audience}\nTone: {$tone}\nTopic/prompt: {$topic}\n\n" .
            "Create useful, non-spammy content following WordPress SEO best practices. Do not invent statistics, legal/medical claims, prices, or unavailable product facts.\n" .
            "Return JSON with this shape: {\"post_title\":\"\",\"slug\":\"\",\"excerpt\":\"\",\"content_html\":\"\",\"seo_title\":\"\",\"meta_description\":\"\",\"focus_keyword\":\"\",\"category_suggestions\":[\"\"],\"tag_suggestions\":[\"\"],\"featured_image_prompt\":\"\",\"featured_image_alt\":\"\",\"image_title\":\"\",\"image_caption\":\"\",\"faq\":[{\"question\":\"\",\"answer\":\"\"}],\"schema_json_ld\":{} }\n" .
            "Content requirements: use one H2-led structure, short paragraphs, helpful intro, practical sections, FAQ when useful, and a clear CTA. Keep content_html safe for WordPress post content.";
        $response = self::request_with_retries($provider, $prompt, $api_key, $settings);
        if (is_wp_error($response)) return $response;
        $json = self::parse_json($response);
        if (!is_array($json)) return new WP_Error('bad_json', 'AI returned invalid JSON for the content blueprint.');
        $json['seo_title'] = RANKREPAIR_AI_SEO_Adapters::enforce_title_template_suffix(self::limit_text(sanitize_text_field($json['seo_title'] ?? ''), intval($settings['max_title_chars'] ?? 60)));
        $json['meta_description'] = self::limit_text(sanitize_textarea_field($json['meta_description'] ?? ''), intval($settings['max_description_chars'] ?? 155));
        $json['focus_keyword'] = self::limit_text(sanitize_text_field($json['focus_keyword'] ?? ''), 70);
        $json['post_title'] = sanitize_text_field($json['post_title'] ?? $topic);
        $json['slug'] = sanitize_title($json['slug'] ?? $json['post_title']);
        $json['excerpt'] = sanitize_textarea_field($json['excerpt'] ?? '');
        $json['featured_image_alt'] = sanitize_text_field($json['featured_image_alt'] ?? '');
        return $json;
    }

    public static function suggest_internal_links($post_id, $limit = 12) {
        $post = get_post($post_id);
        if (!$post) return new WP_Error('missing_post', 'Post not found.');
        $settings = RANKREPAIR_AI_Plugin::settings();
        $provider = self::normalize_provider($settings['ai_provider'] ?? 'openai');
        $api_key = self::api_key_for($provider, $settings);
        if (!$api_key) return new WP_Error('missing_api_key', self::provider_label($provider) . ' API key is missing.');
        // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- A small exclusion list avoids linking a post to itself.
        $candidates = get_posts(['post_type' => get_post_types(['public'=>true]), 'post_status'=>'publish', 'posts_per_page'=>max(5, min(30, intval($limit))), 'post__not_in'=>[$post_id], 'orderby'=>'date', 'order'=>'DESC']);
        $list = [];
        foreach ($candidates as $candidate) {
            $list[] = ['id'=>$candidate->ID, 'title'=>get_the_title($candidate), 'url'=>get_permalink($candidate), 'excerpt'=>wp_trim_words(wp_strip_all_tags($candidate->post_content), 24)];
        }
        $content = wp_trim_words(wp_strip_all_tags($post->post_content), 220);
        $prompt = "Suggest the best internal links for this WordPress post. Return ONLY JSON.\n\nPost title: {$post->post_title}\nPost content summary: {$content}\n\nCandidate posts JSON: " . wp_json_encode($list) . "\n\nReturn {\"links\":[{\"post_id\":0,\"anchor_text\":\"\",\"reason\":\"\"}]} . Pick only relevant links. Do not invent post IDs.";
        $response = self::request_with_retries($provider, $prompt, $api_key, $settings);
        if (is_wp_error($response)) return $response;
        $json = self::parse_json($response);
        if (!is_array($json)) return new WP_Error('bad_json', 'AI returned invalid JSON for internal links.');
        return $json;
    }

    public static function generate_image_seo($attachment_id, $context = '') {
        $attachment = get_post($attachment_id);
        if (!$attachment || $attachment->post_type !== 'attachment') return new WP_Error('missing_attachment', 'Image attachment not found.');
        $settings = RANKREPAIR_AI_Plugin::settings();
        $provider = self::normalize_provider($settings['ai_provider'] ?? 'openai');
        $api_key = self::api_key_for($provider, $settings);
        if (!$api_key) return new WP_Error('missing_api_key', self::provider_label($provider) . ' API key is missing.');
        $filename = basename(get_attached_file($attachment_id));
        $prompt = "Create SEO-friendly image metadata for WordPress. Return ONLY JSON.\n\nLanguage: " . sanitize_text_field($settings['language'] ?? 'English') . "\nBrand: " . sanitize_text_field($settings['brand_name'] ?? '') . "\nFilename: {$filename}\nCurrent title: {$attachment->post_title}\nContext: " . sanitize_textarea_field($context) . "\n\nReturn {\"alt_text\":\"\",\"title\":\"\",\"caption\":\"\",\"description\":\"\",\"recommended_filename\":\"\"}. Avoid keyword stuffing and describe the image clearly.";
        $response = self::request_with_retries($provider, $prompt, $api_key, $settings);
        if (is_wp_error($response)) return $response;
        $json = self::parse_json($response);
        if (!is_array($json)) return new WP_Error('bad_json', 'AI returned invalid JSON for image SEO.');
        return array_map('sanitize_text_field', $json);
    }



    public static function audit_post($post_id) {
        $post = get_post($post_id);
        if (!$post) return new WP_Error('missing_post', 'Post not found.');
        $settings = RANKREPAIR_AI_Plugin::settings();
        $provider = self::normalize_provider($settings['ai_provider'] ?? 'openai');
        $api_key = self::api_key_for($provider, $settings);
        if (!$api_key) return new WP_Error('missing_api_key', self::provider_label($provider) . ' API key is missing.');
        $content = mb_substr(preg_replace('/\s+/', ' ', wp_strip_all_tags($post->post_content)), 0, max(1200, intval($settings['max_content_chars'] ?? 3500)));
        $seo = RANKREPAIR_AI_SEO_Adapters::get_values($post_id);
        $images = preg_match_all('/<img\s[^>]*>/i', $post->post_content, $m);
        $headings = preg_match_all('/<h([1-6])[^>]*>(.*?)<\/h\1>/is', $post->post_content, $hm, PREG_SET_ORDER);
        $heading_list = [];
        foreach ($hm as $h) { $heading_list[] = 'H' . $h[1] . ': ' . wp_strip_all_tags($h[2]); }
        $prompt = "Audit this WordPress post for SEO and content quality. Return ONLY valid JSON.\n\n" .
            "Language: " . sanitize_text_field($settings['language'] ?? 'English') . "\nBrand voice: " . sanitize_textarea_field($settings['brand_voice'] ?? '') . "\n" .
            "Post title: {$post->post_title}\nSEO JSON: " . wp_json_encode($seo) . "\nImage count: {$images}\nHeadings: " . implode(' | ', array_slice($heading_list,0,25)) . "\nContent: {$content}\n\n" .
            "Return {\"score\":0,\"priority\":\"low|medium|high\",\"issues\":[{\"type\":\"\",\"severity\":\"low|medium|high\",\"message\":\"\",\"recommended_action\":\"\"}],\"quick_wins\":[\"\"],\"recommended_modules\":[\"metadata|rewrite|schema|internal_links|image_seo|recovery\"]}. Be practical and avoid generic advice.";
        $response = self::request_with_retries($provider, $prompt, $api_key, $settings);
        if (is_wp_error($response)) return $response;
        $json = self::parse_json($response);
        if (!is_array($json)) return new WP_Error('bad_json', 'AI returned invalid JSON for the audit.');
        return $json;
    }

    public static function rewrite_post($post_id, $mode = 'seo_readability') {
        $post = get_post($post_id);
        if (!$post) return new WP_Error('missing_post', 'Post not found.');
        $settings = RANKREPAIR_AI_Plugin::settings();
        $provider = self::normalize_provider($settings['ai_provider'] ?? 'openai');
        $api_key = self::api_key_for($provider, $settings);
        if (!$api_key) return new WP_Error('missing_api_key', self::provider_label($provider) . ' API key is missing.');
        $content = mb_substr($post->post_content, 0, max(2000, intval($settings['max_content_chars'] ?? 3500) * 2));
        $seo = RANKREPAIR_AI_SEO_Adapters::get_values($post_id);
        $mode_label = sanitize_text_field($mode);
        $prompt = "Rewrite this WordPress post. Return ONLY valid JSON.\n\n" .
            "Mode: {$mode_label}\nLanguage: " . sanitize_text_field($settings['language'] ?? 'English') . "\nBrand voice: " . sanitize_textarea_field($settings['brand_voice'] ?? '') . "\n" .
            "Rules: preserve facts, do not invent statistics, keep a logical heading structure, use Gutenberg-safe HTML, short paragraphs, and keep the original intent. Do not add fake prices, legal/medical claims, or unavailable product details.\n\n" .
            "Post title: {$post->post_title}\nCurrent SEO: " . wp_json_encode($seo) . "\nCurrent HTML content: {$content}\n\n" .
            "Return {\"content_html\":\"\",\"excerpt\":\"\",\"seo\":{\"seo_title\":\"\",\"meta_description\":\"\",\"focus_keyword\":\"\"},\"change_summary\":[\"\"]}.";
        $response = self::request_with_retries($provider, $prompt, $api_key, $settings);
        if (is_wp_error($response)) return $response;
        $json = self::parse_json($response);
        if (!is_array($json)) return new WP_Error('bad_json', 'AI returned invalid JSON for rewrite.');
        if (!empty($json['seo']) && is_array($json['seo'])) {
            $json['seo']['seo_title'] = RANKREPAIR_AI_SEO_Adapters::enforce_title_template_suffix(self::limit_text(sanitize_text_field($json['seo']['seo_title'] ?? ''), intval($settings['max_title_chars'] ?? 60)));
            $json['seo']['meta_description'] = self::limit_text(sanitize_textarea_field($json['seo']['meta_description'] ?? ''), intval($settings['max_description_chars'] ?? 155));
            $json['seo']['focus_keyword'] = self::limit_text(sanitize_text_field($json['seo']['focus_keyword'] ?? ''), 70);
        }
        return $json;
    }

    public static function generate_schema($post_id, $schema_type = 'auto') {
        $post = get_post($post_id);
        if (!$post) return new WP_Error('missing_post', 'Post not found.');
        $settings = RANKREPAIR_AI_Plugin::settings();
        $provider = self::normalize_provider($settings['ai_provider'] ?? 'openai');
        $api_key = self::api_key_for($provider, $settings);
        if (!$api_key) return new WP_Error('missing_api_key', self::provider_label($provider) . ' API key is missing.');
        $content = mb_substr(preg_replace('/\s+/', ' ', wp_strip_all_tags($post->post_content)), 0, max(1500, intval($settings['max_content_chars'] ?? 3500)));
        $type = sanitize_text_field($schema_type ?: 'auto');
        $prompt = "Create safe JSON-LD schema for this WordPress content. Return ONLY valid JSON.\n\n" .
            "Requested schema type: {$type}\nSite: " . get_bloginfo('name') . "\nURL: " . get_permalink($post_id) . "\nTitle: {$post->post_title}\nContent: {$content}\n\n" .
            "Rules: do not invent reviews, ratings, prices, availability, addresses, authors, or dates. If facts are missing, omit the property. Return {\"schema_type\":\"\",\"schema_json_ld\":{},\"notes\":[\"\"]}.";
        $response = self::request_with_retries($provider, $prompt, $api_key, $settings);
        if (is_wp_error($response)) return $response;
        $json = self::parse_json($response);
        if (!is_array($json)) return new WP_Error('bad_json', 'AI returned invalid JSON for schema.');
        return $json;
    }

    public static function content_plan($args) {
        $settings = RANKREPAIR_AI_Plugin::settings();
        $provider = self::normalize_provider($settings['ai_provider'] ?? 'openai');
        $api_key = self::api_key_for($provider, $settings);
        if (!$api_key) return new WP_Error('missing_api_key', self::provider_label($provider) . ' API key is missing.');
        $topic = sanitize_textarea_field($args['topic'] ?? '');
        if ($topic === '') return new WP_Error('missing_topic', 'Please add a main topic or niche.');
        $audience = sanitize_text_field($args['audience'] ?? '');
        $timeframe = sanitize_text_field($args['timeframe'] ?? '90 days');
        $prompt = "Create a WordPress SEO content plan. Return ONLY valid JSON.\n\n" .
            "Language: " . sanitize_text_field($settings['language'] ?? 'English') . "\nBrand: " . sanitize_text_field($settings['brand_name'] ?? '') . "\nBrand voice: " . sanitize_textarea_field($settings['brand_voice'] ?? '') . "\nTopic/niche: {$topic}\nAudience: {$audience}\nTimeframe: {$timeframe}\n\n" .
            "Return {\"pillar_pages\":[{\"title\":\"\",\"intent\":\"\",\"target_keyword\":\"\"}],\"supporting_posts\":[{\"title\":\"\",\"cluster\":\"\",\"intent\":\"\",\"target_keyword\":\"\",\"internal_links_to\":[\"\"]}],\"schema_opportunities\":[\"\"],\"image_opportunities\":[\"\"],\"publishing_schedule\":[{\"week\":\"\",\"task\":\"\"}]}. Keep it realistic and non-spammy.";
        $response = self::request_with_retries($provider, $prompt, $api_key, $settings);
        if (is_wp_error($response)) return $response;
        $json = self::parse_json($response);
        if (!is_array($json)) return new WP_Error('bad_json', 'AI returned invalid JSON for the content plan.');
        return $json;
    }


    public static function suggest_replacement_image($post_id, $issue = []) {
        $post = get_post($post_id);
        if (!$post) return new WP_Error('missing_post', 'Post not found.');
        $settings = RANKREPAIR_AI_Plugin::settings();
        $provider = self::normalize_provider($settings['ai_provider'] ?? 'openai');
        $api_key = self::api_key_for($provider, $settings);
        if (!$api_key) return new WP_Error('missing_api_key', self::provider_label($provider) . ' API key is missing.');
        $content = mb_substr(preg_replace('/\s+/', ' ', wp_strip_all_tags($post->post_content)), 0, max(1200, intval($settings['max_content_chars'] ?? 3500)));
        $prompt = "Suggest a safe replacement image strategy for this WordPress post. Return ONLY valid JSON.\n\n" .
            "Language: " . sanitize_text_field($settings['language'] ?? 'English') . "\nBrand: " . sanitize_text_field($settings['brand_name'] ?? '') . "\n" .
            "Post title: {$post->post_title}\nImage issue: " . wp_json_encode($issue) . "\nContent summary: {$content}\n\n" .
            "Return {\"image_search_query\":\"\",\"image_prompt\":\"\",\"alt_text\":\"\",\"title\":\"\",\"caption\":\"\",\"recommended_filename\":\"\",\"usage_notes\":[\"\"]}. " .
            "Do not claim an image exists. The search query should help an admin find a legal stock/original image. Avoid trademarked people/logos unless the post requires it.";
        $response = self::request_with_retries($provider, $prompt, $api_key, $settings);
        if (is_wp_error($response)) return $response;
        $json = self::parse_json($response);
        if (!is_array($json)) return new WP_Error('bad_json', 'AI returned invalid JSON for image replacement.');
        foreach (['image_search_query','image_prompt','alt_text','title','caption','recommended_filename'] as $key) {
            $json[$key] = sanitize_text_field($json[$key] ?? '');
        }
        $json['usage_notes'] = isset($json['usage_notes']) && is_array($json['usage_notes']) ? array_map('sanitize_text_field', $json['usage_notes']) : [];
        return $json;
    }


    public static function recommended_batch_delay_ms($provider, $model = '', $batch_size = 1) {
        $provider = self::normalize_provider($provider);
        $model = strtolower((string) $model);
        $base = 900;
        if ($provider === 'openai') $base = (strpos($model, 'gpt-4.1') !== false || strpos($model, 'gpt-4o') !== false) ? 1400 : 900;
        if ($provider === 'anthropic') $base = strpos($model, 'opus') !== false ? 2200 : (strpos($model, 'sonnet') !== false ? 1700 : 1200);
        if ($provider === 'gemini') $base = strpos($model, 'pro') !== false ? 1800 : 1000;
        return max(600, min(12000, $base + max(0, intval($batch_size) - 1) * 350));
    }

    private static function normalize_provider($provider) {
        $provider = sanitize_key($provider);
        return in_array($provider, ['openai','anthropic','gemini'], true) ? $provider : 'openai';
    }

    private static function provider_label($provider) {
        if ($provider === 'anthropic') return 'Claude / Anthropic';
        if ($provider === 'gemini') return 'Gemini / Google';
        return 'OpenAI';
    }

    private static function api_key_for($provider, $settings) {
        if ($provider === 'anthropic') return trim($settings['anthropic_api_key'] ?? '');
        if ($provider === 'gemini') return trim($settings['gemini_api_key'] ?? '');
        return trim($settings['openai_api_key'] ?? '');
    }

    private static function model_for($provider, $settings) {
        if ($provider === 'anthropic') return trim($settings['anthropic_model'] ?? 'claude-3-5-haiku-latest');
        if ($provider === 'gemini') return trim($settings['gemini_model'] ?? 'gemini-2.5-flash');
        return trim($settings['openai_model'] ?? 'gpt-4o-mini');
    }

    private static function prompt($post, $excerpt, $content, $current, $settings) {
        return "Rewrite this post's SEO metadata. Use the real post title, excerpt, and content as the source of truth. Ignore broken old-domain data and weird current metadata.\n\n" .
            "Language: " . sanitize_text_field($settings['language'] ?? 'English') . "\n" .
            "Brand: " . sanitize_text_field($settings['brand_name'] ?? '') . "\n" .
            "New domain: " . esc_url_raw($settings['new_domain'] ?? '') . "\n" .
            "Maximum SEO title length: " . intval($settings['max_title_chars'] ?? 60) . " characters\n" .
            "Maximum meta description length: " . intval($settings['max_description_chars'] ?? 155) . " characters\n" .
            "SEO plugin title variables: " . RANKREPAIR_AI_SEO_Adapters::title_template_instruction() . "\n\n" .
            "Return ONLY JSON in this exact shape:\n" .
            '{"seo_title":"","meta_description":"","focus_keyword":"","reason_for_change":""}' . "\n\n" .
            "Rules:\n- Do not invent facts.\n- Do not include old domains or raw URLs unless the post is specifically about a URL.\n- Make the focus keyword natural, short, and relevant.\n- The seo_title MUST include the active SEO plugin suffix variables exactly once.\n\n" .
            "Post title: {$post->post_title}\n" .
            "Excerpt: {$excerpt}\n" .
            "Content: {$content}\n\n" .
            "Current SEO title: {$current['seo_title']}\n" .
            "Current meta description: {$current['meta_description']}\n" .
            "Current focus keyword: {$current['focus_keyword']}";
    }

    private static function request_with_retries($provider, $prompt, $api_key, $settings) {
        $attempts = max(1, min(5, intval($settings['retry_attempts'] ?? 3)));
        $model = self::model_for($provider, $settings);
        $base_delay_ms = self::recommended_batch_delay_ms($provider, $model, intval($settings['ai_batch_size'] ?? 1));
        $last = null;
        for ($i = 0; $i < $attempts; $i++) {
            $result = self::request_once($provider, $prompt, $api_key, $settings);
            if (!is_wp_error($result)) return $result;
            $last = $result;
            $code = $result->get_error_code();
            if (!preg_match('/(429|rate|timeout|temporar|503|500)/i', $code . ' ' . $result->get_error_message())) break;
            $retry_after = intval($result->get_error_data('retry_after_ms'));
            $delay_ms = $retry_after > 0 ? $retry_after : ($base_delay_ms * ($i + 1));
            if ($delay_ms > 0) usleep(min(20000, $delay_ms) * 1000);
        }
        return $last ?: new WP_Error('ai_error', 'AI request failed.');
    }

    private static function request_once($provider, $prompt, $api_key, $settings) {
        if ($provider === 'anthropic') return self::request_anthropic($prompt, $api_key, $settings);
        if ($provider === 'gemini') return self::request_gemini($prompt, $api_key, $settings);
        return self::request_openai($prompt, $api_key, $settings);
    }

    private static function request_openai($prompt, $api_key, $settings) {
        $body = [
            'model' => self::model_for('openai', $settings),
            'temperature' => 0.2,
            'messages' => [
                ['role' => 'system', 'content' => 'You are a careful SEO editor for WordPress SEO. Return valid JSON only. Do not invent facts.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'response_format' => ['type' => 'json_object'],
        ];
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 60,
            'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . trim($api_key)],
            'body' => wp_json_encode($body),
        ]);
        return self::decode_chat_response($response, 'OpenAI', function($decoded) {
            return $decoded['choices'][0]['message']['content'] ?? '';
        });
    }

    private static function request_anthropic($prompt, $api_key, $settings) {
        $body = [
            'model' => self::model_for('anthropic', $settings),
            'max_tokens' => 700,
            'temperature' => 0.2,
            'system' => 'You are a careful SEO editor for WordPress SEO. Return valid JSON only. Do not invent facts.',
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ];
        $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'timeout' => 60,
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-key' => trim($api_key),
                'anthropic-version' => '2023-06-01',
            ],
            'body' => wp_json_encode($body),
        ]);
        return self::decode_chat_response($response, 'Anthropic', function($decoded) {
            if (empty($decoded['content']) || !is_array($decoded['content'])) return '';
            $text = '';
            foreach ($decoded['content'] as $block) {
                if (($block['type'] ?? '') === 'text') $text .= $block['text'] ?? '';
            }
            return $text;
        });
    }

    private static function request_gemini($prompt, $api_key, $settings) {
        $model = rawurlencode(self::model_for('gemini', $settings));
        $body = [
            'systemInstruction' => ['parts' => [['text' => 'You are a careful SEO editor for WordPress SEO. Return valid JSON only. Do not invent facts.']]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
            'generationConfig' => ['temperature' => 0.2, 'responseMimeType' => 'application/json'],
        ];
        $response = wp_remote_post('https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent', [
            'timeout' => 60,
            'headers' => ['Content-Type' => 'application/json', 'x-goog-api-key' => trim($api_key)],
            'body' => wp_json_encode($body),
        ]);
        return self::decode_chat_response($response, 'Gemini', function($decoded) {
            return $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
        });
    }

    private static function decode_chat_response($response, $label, $extractor) {
        if (is_wp_error($response)) return $response;
        $code = intval(wp_remote_retrieve_response_code($response));
        $raw = wp_remote_retrieve_body($response);
        $decoded = json_decode($raw, true);
        if ($code < 200 || $code >= 300) {
            $message = $decoded['error']['message'] ?? ($label . ' request failed with HTTP ' . $code . '.');
            return new WP_Error(strtolower($label) . '_error_' . $code, $message);
        }
        if (!is_array($decoded)) return new WP_Error(strtolower($label) . '_invalid_response', $label . ' returned an invalid response.');
        $content = call_user_func($extractor, $decoded);
        if (!is_string($content) || trim($content) === '') return new WP_Error(strtolower($label) . '_empty_response', $label . ' returned an empty response.');
        return $content;
    }

    private static function parse_json($content) {
        $content = trim((string) $content);
        $json = json_decode($content, true);
        if (is_array($json)) return $json;
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/is', $content, $m)) {
            $json = json_decode($m[1], true);
            if (is_array($json)) return $json;
        }
        if (preg_match('/\{.*\}/s', $content, $m)) {
            $json = json_decode($m[0], true);
            if (is_array($json)) return $json;
        }
        return null;
    }

    private static function limit_text($value, $limit) {
        $value = trim(preg_replace('/\s+/', ' ', (string) $value));
        if ($limit > 0 && mb_strlen($value) > $limit) {
            $value = rtrim(mb_substr($value, 0, $limit - 1), " \t\n\r\0\x0B.,;:-") . '…';
        }
        return $value;
    }
}
