<?php
if (!defined('ABSPATH')) exit;

class RANKREPAIR_AI_Admin {
    public function __construct() {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
        add_action('admin_init', [$this, 'maybe_redirect_to_launch']);
        add_action('wp_ajax_rankrepair_ai_save_settings', [$this, 'save_settings']);
        add_action('wp_ajax_rankrepair_ai_scan', [$this, 'scan']);
        add_action('wp_ajax_rankrepair_ai_bulk_preview', [$this, 'bulk_preview']);
        add_action('wp_ajax_rankrepair_ai_bulk_apply', [$this, 'bulk_apply']);
        add_action('wp_ajax_rankrepair_ai_ai_generate', [$this, 'ai_generate']);
        add_action('wp_ajax_rankrepair_ai_ai_apply', [$this, 'ai_apply']);
        add_action('wp_ajax_rankrepair_ai_rollback', [$this, 'rollback']);
        add_action('wp_ajax_rankrepair_ai_quick_ai_improve', [$this, 'quick_ai_improve']);
        add_action('wp_ajax_rankrepair_ai_content_blueprint', [$this, 'content_blueprint']);
        add_action('wp_ajax_rankrepair_ai_create_draft_post', [$this, 'create_draft_post']);
        add_action('wp_ajax_rankrepair_ai_internal_links', [$this, 'internal_links']);
        add_action('wp_ajax_rankrepair_ai_image_seo', [$this, 'image_seo']);
        add_action('wp_ajax_rankrepair_ai_apply_image_seo', [$this, 'apply_image_seo']);
        add_action('wp_ajax_rankrepair_ai_bulk_image_seo', [$this, 'bulk_image_seo']);
        add_action('wp_ajax_rankrepair_ai_image_recovery_scan', [$this, 'image_recovery_scan']);
        add_action('wp_ajax_rankrepair_ai_image_recovery_suggest', [$this, 'image_recovery_suggest']);
        add_action('wp_ajax_rankrepair_ai_image_recovery_apply', [$this, 'image_recovery_apply']);
        add_action('wp_ajax_rankrepair_ai_divi_scan', [$this, 'divi_scan']);
        add_action('wp_ajax_rankrepair_ai_divi_preview', [$this, 'divi_preview']);
        add_action('wp_ajax_rankrepair_ai_divi_apply', [$this, 'divi_apply']);
        add_action('wp_ajax_rankrepair_ai_content_audit', [$this, 'content_audit']);
        add_action('wp_ajax_rankrepair_ai_rewrite_preview', [$this, 'rewrite_preview']);
        add_action('wp_ajax_rankrepair_ai_rewrite_apply', [$this, 'rewrite_apply']);
        add_action('wp_ajax_rankrepair_ai_schema_generate', [$this, 'schema_generate']);
        add_action('wp_ajax_rankrepair_ai_schema_apply', [$this, 'schema_apply']);
        add_action('wp_ajax_rankrepair_ai_content_plan', [$this, 'content_plan']);
        add_action('wp_ajax_rankrepair_ai_editor_audit', [$this, 'editor_audit']);
        add_action('wp_ajax_rankrepair_ai_editor_generate_schema', [$this, 'editor_generate_schema']);
        add_action('wp_ajax_rankrepair_ai_editor_rewrite_preview', [$this, 'editor_rewrite_preview']);
        add_action('wp_ajax_rankrepair_ai_editor_recovery_preview', [$this, 'editor_recovery_preview']);
        add_action('add_meta_boxes', [$this, 'add_editor_metabox']);
        add_action('admin_init', [$this, 'register_list_table_integrations']);
        add_action('pre_get_posts', [$this, 'maybe_sort_posts_by_seo_health']);
        add_action('wp_head', [$this, 'render_frontend_schema'], 30);
    }

    public function menu() {
        add_menu_page('RankRepair AI', 'RankRepair AI', 'manage_options', 'rankrepair-ai', [$this, 'page'], 'dashicons-chart-area', 58);
        add_submenu_page('rankrepair-ai', 'Dashboard', 'Dashboard', 'manage_options', 'rankrepair-ai', [$this, 'page']);
        add_submenu_page('rankrepair-ai', 'Launch Setup', 'Launch Setup', 'manage_options', 'rankrepair-ai-setup', [$this, 'page']);
        add_submenu_page('rankrepair-ai', 'SEO Repair', 'SEO Repair', 'manage_options', 'rankrepair-ai-scan', [$this, 'page']);
        add_submenu_page('rankrepair-ai', 'Content Tools', 'Content Tools', 'manage_options', 'rankrepair-ai-content', [$this, 'page']);
        add_submenu_page('rankrepair-ai', 'Images & Recovery', 'Images & Recovery', 'manage_options', 'rankrepair-ai-images', [$this, 'page']);
        add_submenu_page('rankrepair-ai', 'Settings', 'Settings', 'manage_options', 'rankrepair-ai-settings', [$this, 'page']);
    }

    private function current_module() {
        global $plugin_page;
        $page = $plugin_page ? sanitize_key($plugin_page) : 'rankrepair-ai';
        $map = [
            'rankrepair-ai' => 'dashboard',
            'rankrepair-ai-setup' => 'setup',
            'rankrepair-ai-scan' => 'scan',
            'rankrepair-ai-audit' => 'content',
            'rankrepair-ai-content' => 'content',
            'rankrepair-ai-images' => 'images',
            'rankrepair-ai-recovery' => 'images',
            'rankrepair-ai-links' => 'content',
            'rankrepair-ai-settings' => 'settings',
        ];
        return $map[$page] ?? 'dashboard';
    }



    private function credentials_missing() {
        return !RANKREPAIR_AI_Plugin::has_ai_credentials();
    }

    public function maybe_redirect_to_launch() {
        if (!is_admin() || wp_doing_ajax() || !current_user_can('manage_options')) return;
        global $plugin_page;
        $page = $plugin_page ? sanitize_key($plugin_page) : '';
        if (strpos($page, 'rankrepair-ai') !== 0) return;
        if ($page === 'rankrepair-ai-setup' || $page === 'rankrepair-ai-settings') return;
        if ($this->credentials_missing()) {
            wp_safe_redirect(admin_url('admin.php?page=rankrepair-ai-setup'));
            exit;
        }
    }

    private function render_launch_setup($s, $post_types) {
        ?>
        <div class="wrap rankrepair-ai rankrepair-ai-launch">
            <div class="rankrepair-ai-launch-hero">
                <p class="rankrepair-ai-kicker">First run setup</p>
                <h1><?php esc_html_e('Launch RankRepair AI', 'rankrepair-ai'); ?></h1>
                <p class="rankrepair-ai-lead"><?php esc_html_e('Connect an AI provider before running repairs. RankRepair will use these credentials to generate SEO metadata, image metadata, content audits, and recovery suggestions.', 'rankrepair-ai'); ?></p>
            </div>
            <section class="rankrepair-ai-card rankrepair-ai-launch-card">
                <div class="rankrepair-ai-section-heading"><div><p class="rankrepair-ai-kicker">Required</p><h2><?php esc_html_e('AI credentials', 'rankrepair-ai'); ?></h2></div><span class="rankrepair-ai-pill">Private to this WordPress site</span></div>
                <div class="rankrepair-ai-settings-groups rankrepair-ai-settings-rows">
                    <section class="rankrepair-ai-settings-group">
                        <div class="rankrepair-ai-settings-group-head"><h3><?php esc_html_e('Choose your AI provider', 'rankrepair-ai'); ?></h3><p><?php esc_html_e('You can change this later in Settings. Only the selected provider key is required.', 'rankrepair-ai'); ?></p></div>
                        <div class="rankrepair-ai-form-grid rankrepair-ai-form-rows">
                            <label>AI provider<select id="rankrepair_ai_ai_provider">
                                <option value="openai" <?php selected($s['ai_provider'], 'openai'); ?>>OpenAI</option>
                                <option value="anthropic" <?php selected($s['ai_provider'], 'anthropic'); ?>>Claude / Anthropic</option>
                                <option value="gemini" <?php selected($s['ai_provider'], 'gemini'); ?>>Gemini / Google</option>
                            </select></label>
                            <label class="rankrepair-ai-provider-field rankrepair-ai-provider-openai">OpenAI API key<input id="rankrepair_ai_openai_api_key" type="password" value="<?php echo esc_attr($s['openai_api_key']); ?>" placeholder="sk-..."></label>
                            <label class="rankrepair-ai-provider-field rankrepair-ai-provider-openai">OpenAI model<select id="rankrepair_ai_openai_model"><option value="gpt-4o-mini" <?php selected($s['openai_model'], 'gpt-4o-mini'); ?>>gpt-4o-mini</option><option value="gpt-4o" <?php selected($s['openai_model'], 'gpt-4o'); ?>>gpt-4o</option></select></label>
                            <label class="rankrepair-ai-provider-field rankrepair-ai-provider-anthropic">Claude API key<input id="rankrepair_ai_anthropic_api_key" type="password" value="<?php echo esc_attr($s['anthropic_api_key']); ?>" placeholder="sk-ant-..."></label>
                            <label class="rankrepair-ai-provider-field rankrepair-ai-provider-anthropic">Claude model<select id="rankrepair_ai_anthropic_model"><option value="claude-3-5-haiku-latest" <?php selected($s['anthropic_model'], 'claude-3-5-haiku-latest'); ?>>Claude 3.5 Haiku</option><option value="claude-3-5-sonnet-latest" <?php selected($s['anthropic_model'], 'claude-3-5-sonnet-latest'); ?>>Claude 3.5 Sonnet</option></select></label>
                            <label class="rankrepair-ai-provider-field rankrepair-ai-provider-gemini">Gemini API key<input id="rankrepair_ai_gemini_api_key" type="password" value="<?php echo esc_attr($s['gemini_api_key']); ?>" placeholder="AIza..."></label>
                            <label class="rankrepair-ai-provider-field rankrepair-ai-provider-gemini">Gemini model<select id="rankrepair_ai_gemini_model"><option value="gemini-2.5-flash" <?php selected($s['gemini_model'], 'gemini-2.5-flash'); ?>>Gemini 2.5 Flash</option><option value="gemini-1.5-pro" <?php selected($s['gemini_model'], 'gemini-1.5-pro'); ?>>Gemini 1.5 Pro</option></select></label>
                        </div>
                    </section>
                    <section class="rankrepair-ai-settings-group">
                        <div class="rankrepair-ai-settings-group-head"><h3><?php esc_html_e('Site basics', 'rankrepair-ai'); ?></h3><p><?php esc_html_e('This helps the AI avoid old-domain metadata and write in the correct language.', 'rankrepair-ai'); ?></p></div>
                        <div class="rankrepair-ai-form-grid rankrepair-ai-form-rows">
                            <label>Old domain<input id="rankrepair_ai_old_domain" value="<?php echo esc_attr($s['old_domain']); ?>" placeholder="oldsite.com"></label>
                            <label>New domain<input id="rankrepair_ai_new_domain" value="<?php echo esc_attr($s['new_domain']); ?>" placeholder="<?php echo esc_attr(home_url()); ?>"></label>
                            <label>Brand name<input id="rankrepair_ai_brand_name" value="<?php echo esc_attr($s['brand_name']); ?>"></label>
                            <label>Language<input id="rankrepair_ai_language" value="<?php echo esc_attr($s['language']); ?>"></label>
                            <input type="hidden" id="rankrepair_ai_seo_plugin" value="<?php echo esc_attr($s['seo_plugin']); ?>">
                            <input type="hidden" id="rankrepair_ai_custom_title_key" value="<?php echo esc_attr($s['custom_title_key']); ?>">
                            <input type="hidden" id="rankrepair_ai_custom_description_key" value="<?php echo esc_attr($s['custom_description_key']); ?>">
                            <input type="hidden" id="rankrepair_ai_custom_keyword_key" value="<?php echo esc_attr($s['custom_keyword_key']); ?>">
                            <input type="hidden" id="rankrepair_ai_ai_batch_size" value="<?php echo esc_attr($s['ai_batch_size']); ?>">
                            <input type="hidden" id="rankrepair_ai_max_content_chars" value="<?php echo esc_attr($s['max_content_chars']); ?>">
                            <textarea id="rankrepair_ai_brand_voice" style="display:none"><?php echo esc_textarea($s['brand_voice'] ?? ''); ?></textarea>
                            <input type="hidden" id="rankrepair_ai_content_status" value="<?php echo esc_attr($s['default_content_status']); ?>">
                            <span style="display:none">
                                <input id="rankrepair_ai_refresh_yoast_after_update" type="checkbox" checked>
                                <input id="rankrepair_ai_safe_compatibility_mode" type="checkbox" checked>
                                <input id="rankrepair_ai_show_editor_box" type="checkbox" checked>
                                <input id="rankrepair_ai_show_list_table_tools" type="checkbox" checked>
                                <input id="rankrepair_ai_disable_builder_content_overwrite" type="checkbox" checked>
                                <input id="rankrepair_ai_render_schema_frontend" type="checkbox">
                                <input class="rankrepair_ai_enabled_post_type" type="checkbox" value="post" checked>
                                <input class="rankrepair_ai_enabled_post_type" type="checkbox" value="page" checked>
                            </span>
                        </div>
                    </section>
                </div>
                <div class="rankrepair-ai-card-actions"><button class="button button-primary button-hero" id="rankrepair_ai_save_settings">Save and start using RankRepair</button><span id="rankrepair_ai_settings_status"></span></div>
                <p class="description">RankRepair does not send content to an AI provider until you run an AI action.</p>
            </section>
        </div>
        <?php
    }

    public function assets($hook) {
        if (strpos($hook, 'rankrepair-ai') === false && $hook !== 'edit.php' && !in_array($hook, ['post.php','post-new.php'], true)) return;
        wp_enqueue_style('rankrepair-ai-admin', RANKREPAIR_AI_URL . 'assets/admin.css', [], RANKREPAIR_AI_VERSION);
        wp_enqueue_script('rankrepair-ai-admin', RANKREPAIR_AI_URL . 'assets/admin.js', ['jquery'], RANKREPAIR_AI_VERSION, true);
        wp_localize_script('rankrepair-ai-admin', 'RANKREPAIR_AI', [
            'ajax' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rankrepair_ai_nonce'),
            'quickNonce' => wp_create_nonce('rankrepair_ai_quick_nonce'),
            'currentPostId' => get_the_ID() ? intval(get_the_ID()) : 0,
        ]);
    }

    private function guard() {
        check_ajax_referer('rankrepair_ai_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Forbidden'], 403);
    }

    /**
     * Read a POST value after the AJAX nonce has been verified by guard().
     *
     * @param string $key Request key.
     * @param mixed  $default Default value.
     * @return mixed
     */
    private function sanitize_request_value_recursive($value) {
        if (is_array($value)) {
            return array_map([$this, 'sanitize_request_value_recursive'], $value);
        }
        return sanitize_textarea_field((string) $value);
    }

    private function post_value($key, $default = '') {
        $key = sanitize_key($key);
        if ('' === $key) {
            return $default;
        }

        $value = filter_input(INPUT_POST, $key, FILTER_UNSAFE_RAW, FILTER_REQUIRE_SCALAR);
        if (null === $value || false === $value) {
            return $default;
        }

        return $this->sanitize_request_value_recursive($value);
    }

    private function post_json($key, $default = []) {
        $raw = $this->post_value($key, '');
        if ('' === $raw || null === $raw) {
            return $default;
        }
        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? $decoded : $default;
    }

    private function post_array($key, $default = []) {
        $value = $this->post_value($key, $default);
        if (is_string($value)) {
            $value = explode(',', $value);
        }
        return is_array($value) ? $value : $default;
    }



    private function enabled_post_types() {
        $s = RANKREPAIR_AI_Plugin::settings();
        $enabled = isset($s['enabled_post_types']) && is_array($s['enabled_post_types']) ? $s['enabled_post_types'] : ['post','page'];
        $public = array_keys(get_post_types(['public' => true], 'names'));
        $enabled = array_values(array_intersect(array_map('sanitize_key', $enabled), $public));
        return $enabled ? $enabled : ['post','page'];
    }

    private function is_builder_managed($post_id) {
        if (get_post_meta($post_id, '_elementor_edit_mode', true) || get_post_meta($post_id, '_elementor_data', true)) return 'Elementor';
        if (get_post_meta($post_id, '_et_pb_use_builder', true) === 'on' || has_shortcode((string) get_post_field('post_content', $post_id), 'et_pb_section')) return 'Divi';
        if (get_post_meta($post_id, '_wpb_vc_js_status', true)) return 'WPBakery';
        if (get_post_meta($post_id, '_fl_builder_enabled', true)) return 'Beaver Builder';
        if (get_post_meta($post_id, 'jet_engine_listing_source', true) || get_post_meta($post_id, '_elementor_template_type', true)) return 'Crocoblock/Builder template';
        return '';
    }

    private function compatibility_guard_for_content_write($post_id) {
        $s = RANKREPAIR_AI_Plugin::settings();
        if (empty($s['safe_compatibility_mode'])) return true;
        $builder = $this->is_builder_managed($post_id);
        if ($builder && !empty($s['disable_builder_content_overwrite'])) {
            return new WP_Error('builder_managed_content', sprintf('This post appears to be managed by %s. RankRepair blocked direct content overwrite in Safe Compatibility Mode. Use preview/export or disable the guard in settings only on staging.', $builder));
        }
        return true;
    }

    public function register_list_table_integrations() {
        $s = RANKREPAIR_AI_Plugin::settings();
        if (empty($s['show_list_table_tools'])) return;
        add_filter('post_row_actions', [$this, 'post_row_action'], 10, 2);
        add_filter('page_row_actions', [$this, 'post_row_action'], 10, 2);
        foreach ($this->enabled_post_types() as $post_type) {
            add_filter("manage_{$post_type}_posts_columns", [$this, 'add_rankrepair_column']);
            add_filter("manage_edit-{$post_type}_sortable_columns", [$this, 'add_rankrepair_sortable_column']);
            add_action("manage_{$post_type}_posts_custom_column", [$this, 'render_rankrepair_column'], 10, 2);
        }
    }

    public function add_editor_metabox() {
        $s = RANKREPAIR_AI_Plugin::settings();
        if (empty($s['show_editor_box'])) return;
        foreach ($this->enabled_post_types() as $post_type) {
            add_meta_box(
                'rankrepair_ai_assistant',
                __('RankRepair AI SEO Assistant', 'rankrepair-ai'),
                [$this, 'render_editor_metabox'],
                $post_type,
                'side',
                'high'
            );
        }
    }

    public function render_editor_metabox($post) {
        if (!$post || !current_user_can('edit_post', $post->ID)) return;
        $builder = $this->is_builder_managed($post->ID);
        $values = RANKREPAIR_AI_SEO_Adapters::get_values($post->ID);
        ?>
        <div class="rankrepair-ai-editor-box" data-post-id="<?php echo esc_attr($post->ID); ?>">
            <p class="rankrepair-ai-editor-muted"><?php esc_html_e('Use AI tools without leaving this editor. Metadata actions are safe; content rewrites always preview first.', 'rankrepair-ai'); ?></p>
            <?php if ($builder): ?>
                <div class="notice notice-warning inline"><p><?php echo esc_html(sprintf('Builder detected: %s. Safe Mode protects builder/Crocoblock data from direct overwrite.', $builder)); ?></p></div>
            <?php endif; ?>
            <ul class="rankrepair-ai-editor-current">
                <li><strong><?php esc_html_e('SEO title:', 'rankrepair-ai'); ?></strong> <?php echo esc_html($values['seo_title'] ?: 'Empty'); ?></li>
                <li><strong><?php esc_html_e('Description:', 'rankrepair-ai'); ?></strong> <?php echo esc_html($values['meta_description'] ?: 'Empty'); ?></li>
            </ul>
            <p><button type="button" class="button button-primary rankrepair-ai-editor-improve" data-post-id="<?php echo esc_attr($post->ID); ?>"><?php esc_html_e('Improve SEO with AI', 'rankrepair-ai'); ?></button></p>
            <p><button type="button" class="button rankrepair-ai-editor-audit" data-post-id="<?php echo esc_attr($post->ID); ?>"><?php esc_html_e('Run content audit', 'rankrepair-ai'); ?></button></p>
            <p><button type="button" class="button rankrepair-ai-editor-schema" data-post-id="<?php echo esc_attr($post->ID); ?>"><?php esc_html_e('Generate schema', 'rankrepair-ai'); ?></button></p>
            <p><button type="button" class="button rankrepair-ai-editor-image-audit" data-post-id="<?php echo esc_attr($post->ID); ?>"><?php esc_html_e('Audit images', 'rankrepair-ai'); ?></button></p>
            <hr class="rankrepair-ai-editor-divider"><p><button type="button" class="button rankrepair-ai-editor-rewrite" data-post-id="<?php echo esc_attr($post->ID); ?>"><?php esc_html_e('Preview AI rewrite', 'rankrepair-ai'); ?></button></p>
            <p><button type="button" class="button rankrepair-ai-editor-recovery" data-post-id="<?php echo esc_attr($post->ID); ?>"><?php esc_html_e('Preview builder/HTML recovery', 'rankrepair-ai'); ?></button></p>
            <div class="rankrepair-ai-editor-output" id="rankrepair_ai_editor_output_<?php echo esc_attr($post->ID); ?>"></div>
        </div>
        <?php
    }

    public function render_frontend_schema() {
        $s = RANKREPAIR_AI_Plugin::settings();
        if (empty($s['render_schema_frontend']) || !is_singular()) return;
        $post_id = get_queried_object_id();
        if (!$post_id) return;
        $json = get_post_meta($post_id, '_rankrepair_schema_json_ld', true);
        if (!$json) return;
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) return;
        echo "\n<script type=\"application/ld+json\" class=\"rankrepair-schema\">";
        echo wp_json_encode($decoded);
        echo "</script>\n";
    }


    private function dashboard_seo_items($limit = 8) {
        $types = $this->enabled_post_types();
        $q = new WP_Query([
            'post_type' => $types,
            'post_status' => ['publish','draft','pending','private','future'],
            'posts_per_page' => max(1, min(20, intval($limit * 3))),
            'orderby' => 'modified',
            'order' => 'DESC',
            'no_found_rows' => true,
        ]);
        $items = [];
        foreach ($q->posts as $post) {
            $audit = RANKREPAIR_AI_Scanner::audit_post($post->ID);
            if (empty($audit['issues'])) continue;
            $items[] = $audit;
            if (count($items) >= $limit) break;
        }
        return $items;
    }

    private function dashboard_image_items($limit = 6) {
        if (!class_exists('RANKREPAIR_AI_Image_Recovery')) return [];
        $scan = RANKREPAIR_AI_Image_Recovery::scan([
            'post_type' => 'any',
            'post_status' => 'any',
            'limit' => max(6, min(40, intval($limit * 3))),
            'offset' => 0,
            'mode' => 'all',
        ]);
        $items = isset($scan['items']) && is_array($scan['items']) ? $scan['items'] : [];
        return array_slice($items, 0, $limit);
    }

    public function page() {
        $s = RANKREPAIR_AI_Plugin::settings();
        $post_types = get_post_types(['public' => true], 'objects');
        $module = $this->current_module();
        if ($module === 'setup') { $this->render_launch_setup($s, $post_types); return; }
        ?>
        <div class="wrap rankrepair-ai rankrepair-ai-module-<?php echo esc_attr($module); ?>">
            <div class="rankrepair-ai-hero">
                <div>
                    <p class="rankrepair-ai-kicker">WordPress SEO repair workspace</p>
                    <h1>RankRepair AI</h1>
                    <p class="rankrepair-ai-lead">Find and fix missing or damaged SEO metadata, broken images, and content issues across WordPress posts, pages, and media.</p>
                    <div class="rankrepair-ai-hero-actions">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=rankrepair-ai-scan')); ?>" class="button button-primary button-hero">Start SEO scan</a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=rankrepair-ai')); ?>" class="button button-secondary">How to use</a>
                    </div>
                </div>
                <div class="rankrepair-ai-hero-panel">
                    <strong>Professional workflow</strong>
                    <span>Detect plugin → Scan → Preview → AI repair → Apply → Rollback</span>
                </div>
            </div>

            <nav class="rankrepair-ai-page-nav" aria-label="RankRepair AI sections">
                <a class="<?php echo $module === 'dashboard' ? 'is-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=rankrepair-ai')); ?>">Dashboard</a>
                <a class="<?php echo $module === 'scan' ? 'is-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=rankrepair-ai-scan')); ?>">Scan & Repair</a>
                <a class="<?php echo $module === 'content' ? 'is-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=rankrepair-ai-content')); ?>">Content Tools</a>
                <a class="<?php echo $module === 'images' ? 'is-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=rankrepair-ai-images')); ?>">Recovery Tools</a>
                <a class="<?php echo $module === 'settings' ? 'is-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=rankrepair-ai-settings')); ?>">Settings</a>
            </nav>

            <?php $health = RANKREPAIR_AI_Scanner::health_summary(); $seo_items = $this->dashboard_seo_items(); $image_items = $this->dashboard_image_items(); ?>
            <section class="rankrepair-ai-card rankrepair-ai-dashboard" id="rankrepair_ai_dashboard">
                <div class="rankrepair-ai-section-heading"><div><p class="rankrepair-ai-kicker">Dashboard</p><h2>SEO repair overview</h2></div><span class="rankrepair-ai-pill"><?php echo esc_html($health['provider_label']); ?></span></div>
                <div class="rankrepair-ai-metrics">
                    <div class="rankrepair-ai-metric"><strong><?php echo esc_html($health['public_posts']); ?></strong><span>public posts/pages</span></div>
                    <div class="rankrepair-ai-metric"><strong><?php echo esc_html($health['sampled']); ?></strong><span>recent items checked</span></div>
                    <div class="rankrepair-ai-metric rankrepair-ai-danger"><strong><?php echo esc_html($health['issues']); ?></strong><span>need SEO work</span></div>
                    <div class="rankrepair-ai-metric rankrepair-ai-danger"><strong><?php echo esc_html($health['missing_any']); ?></strong><span>missing title/description/keyword</span></div>
                </div>
                <div class="rankrepair-ai-dashboard-actions">
                    <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=rankrepair-ai-scan')); ?>">Fix posts and pages</a>
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=rankrepair-ai-images')); ?>">Fix images</a>
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=rankrepair-ai-settings')); ?>">Check settings</a>
                </div>
                <div class="rankrepair-ai-dashboard-panels">
                    <div class="rankrepair-ai-dashboard-panel">
                        <h3>Posts/pages that need SEO work</h3>
                        <?php if (empty($seo_items)): ?>
                            <p class="rankrepair-ai-muted">No obvious SEO issues found in the recent sample.</p>
                        <?php else: ?>
                            <div class="rankrepair-ai-mini-list">
                                <?php foreach ($seo_items as $item): ?>
                                    <div class="rankrepair-ai-mini-row">
                                        <div><a href="<?php echo esc_url($item['edit_link']); ?>"><strong><?php echo esc_html($item['post_title']); ?></strong></a><span><?php echo esc_html(implode(' · ', array_slice($item['issues'], 0, 3))); ?></span></div>
                                        <button type="button" class="button button-small button-primary rankrepair-ai-dashboard-fix rankrepair-ai-quick-ai-inline" data-post-id="<?php echo esc_attr($item['id']); ?>">Improve now</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="rankrepair-ai-dashboard-panel">
                        <h3>Images that need attention</h3>
                        <?php if (empty($image_items)): ?>
                            <p class="rankrepair-ai-muted">No image issues found in the recent sample.</p>
                        <?php else: ?>
                            <div class="rankrepair-ai-media-list">
                                <?php foreach ($image_items as $item): ?>
                                    <div class="rankrepair-ai-media-row">
                                        <div class="rankrepair-ai-thumb"><?php if (!empty($item['featured_image_thumb'])): ?><img src="<?php echo esc_url($item['featured_image_thumb']); ?>" alt=""><?php else: ?><span>No image</span><?php endif; ?></div>
                                        <div><a href="<?php echo esc_url($item['edit_link']); ?>"><strong><?php echo esc_html($item['post_title']); ?></strong></a><span><?php echo esc_html(implode(' · ', array_map(function($i){ return $i['label'] ?? ''; }, array_slice($item['issues'], 0, 2)))); ?></span></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="rankrepair-ai-card rankrepair-ai-how" id="rankrepair_ai_how_to_use">
                <div class="rankrepair-ai-section-heading">
                    <div>
                        <p class="rankrepair-ai-kicker">App description</p>
                        <h2>What this plugin does</h2>
                    </div>
                    <span class="rankrepair-ai-pill">Built for 1,000+ posts</span>
                </div>
                <p class="rankrepair-ai-muted">This tool helps an admin audit existing SEO plugin fields and repair them in bulk. It does not only look for missing fields — it finds suspicious metadata that may have been damaged during migration, such as old domains, duplicated site names, strange variables, too-long titles, or irrelevant keywords.</p>

                <div class="rankrepair-ai-steps">
                    <div class="rankrepair-ai-step"><span>1</span><h3>Configure</h3><p>Add your AI provider credentials, old domain, new domain, brand name, and language.</p></div>
                    <div class="rankrepair-ai-step"><span>2</span><h3>Scan</h3><p>Choose the post type and scan only suspicious posts or all posts for review.</p></div>
                    <div class="rankrepair-ai-step"><span>3</span><h3>Bulk edit</h3><p>Use rules like replace text, remove suffix, set title pattern, copy post title, or limit characters.</p></div>
                    <div class="rankrepair-ai-step"><span>4</span><h3>AI repair</h3><p>Select posts and generate SEO titles, meta descriptions, and focus keywords based on post content.</p></div>
                    <div class="rankrepair-ai-step"><span>5</span><h3>Preview</h3><p>Every manual or AI change appears as a before/after preview before saving.</p></div>
                    <div class="rankrepair-ai-step"><span>6</span><h3>Rollback</h3><p>Each update stores a backup so an admin can restore the latest previous SEO values.</p></div>
                </div>
            </section>

            <div class="rankrepair-ai-grid">
                <section class="rankrepair-ai-card rankrepair-ai-settings-card">
                    <div class="rankrepair-ai-section-heading"><div><p class="rankrepair-ai-kicker">Settings</p><h2>Plugin setup</h2></div><span class="rankrepair-ai-pill">Required before AI</span></div>
                    <div class="rankrepair-ai-main-panel" id="rankrepair_ai_settings_section">
                        <div class="rankrepair-ai-settings-groups">
                            <section class="rankrepair-ai-settings-group">
                                <div class="rankrepair-ai-settings-group-head"><h3>Site context</h3><p>Used by AI to avoid old-domain metadata and keep the brand voice consistent.</p></div>
                                <div class="rankrepair-ai-form-grid">
                                    <label>Old domain<input id="rankrepair_ai_old_domain" value="<?php echo esc_attr($s['old_domain']); ?>" placeholder="oldsite.com"></label>
                                    <label>New domain<input id="rankrepair_ai_new_domain" value="<?php echo esc_attr($s['new_domain']); ?>" placeholder="newsite.com"></label>
                                    <label>Brand name<input id="rankrepair_ai_brand_name" value="<?php echo esc_attr($s['brand_name']); ?>" placeholder="Your brand"></label>
                                    <label>Language<input id="rankrepair_ai_language" value="<?php echo esc_attr($s['language']); ?>" placeholder="English, Portuguese..."></label>
                                    <label class="rankrepair-ai-wide-field">Brand voice<textarea id="rankrepair_ai_brand_voice" rows="3" placeholder="Professional, helpful, trustworthy..."><?php echo esc_textarea($s['brand_voice'] ?? ""); ?></textarea></label>
                                </div>
                            </section>

                            <section class="rankrepair-ai-settings-group">
                                <div class="rankrepair-ai-settings-group-head"><h3>SEO plugin integration</h3><p>Choose the SEO plugin RankRepair should read/write. Use custom keys only for unsupported plugins.</p></div>
                                <div class="rankrepair-ai-form-grid">
                                    <label>SEO plugin<select id="rankrepair_ai_seo_plugin"><?php foreach (RANKREPAIR_AI_SEO_Adapters::providers() as $provider_key => $provider): ?><option value="<?php echo esc_attr($provider_key); ?>" <?php selected($s['seo_plugin'], $provider_key); ?>><?php echo esc_html($provider['label']); ?></option><?php endforeach; ?></select></label>
                                    <label class="rankrepair-ai-custom-meta-field">Custom title meta key<input id="rankrepair_ai_custom_title_key" value="<?php echo esc_attr($s['custom_title_key']); ?>" placeholder="_seo_title"></label>
                                    <label class="rankrepair-ai-custom-meta-field">Custom description meta key<input id="rankrepair_ai_custom_description_key" value="<?php echo esc_attr($s['custom_description_key']); ?>" placeholder="_seo_description"></label>
                                    <label class="rankrepair-ai-custom-meta-field">Custom keyword meta key<input id="rankrepair_ai_custom_keyword_key" value="<?php echo esc_attr($s['custom_keyword_key']); ?>" placeholder="_seo_keyword"></label>
                                </div>
                            </section>

                            <section class="rankrepair-ai-settings-group">
                                <div class="rankrepair-ai-settings-group-head"><h3>AI provider</h3><p>Set your provider, model, and rate-limit controls. Smaller batches are safer for large sites.</p></div>
                                <div class="rankrepair-ai-form-grid">
                                    <label>AI provider<select id="rankrepair_ai_ai_provider">
                                        <option value="openai" <?php selected($s['ai_provider'], 'openai'); ?>>OpenAI</option>
                                        <option value="anthropic" <?php selected($s['ai_provider'], 'anthropic'); ?>>Claude / Anthropic</option>
                                        <option value="gemini" <?php selected($s['ai_provider'], 'gemini'); ?>>Gemini / Google</option>
                                    </select></label>
                                    <label class="rankrepair-ai-provider-field rankrepair-ai-provider-openai">OpenAI API key<input id="rankrepair_ai_openai_api_key" type="password" value="<?php echo esc_attr($s['openai_api_key']); ?>" placeholder="sk-..."></label>
                                    <label class="rankrepair-ai-provider-field rankrepair-ai-provider-openai">OpenAI model<select id="rankrepair_ai_openai_model">
                                        <?php foreach (RANKREPAIR_AI_AI::provider_models()['openai'] as $model): ?><option value="<?php echo esc_attr($model); ?>" <?php selected($s['openai_model'], $model); ?>><?php echo esc_html($model); ?></option><?php endforeach; ?>
                                    </select></label>
                                    <label class="rankrepair-ai-provider-field rankrepair-ai-provider-anthropic">Claude API key<input id="rankrepair_ai_anthropic_api_key" type="password" value="<?php echo esc_attr($s['anthropic_api_key']); ?>" placeholder="sk-ant-..."></label>
                                    <label class="rankrepair-ai-provider-field rankrepair-ai-provider-anthropic">Claude model<select id="rankrepair_ai_anthropic_model">
                                        <?php foreach (RANKREPAIR_AI_AI::provider_models()['anthropic'] as $model): ?><option value="<?php echo esc_attr($model); ?>" <?php selected($s['anthropic_model'], $model); ?>><?php echo esc_html($model); ?></option><?php endforeach; ?>
                                    </select></label>
                                    <label class="rankrepair-ai-provider-field rankrepair-ai-provider-gemini">Gemini API key<input id="rankrepair_ai_gemini_api_key" type="password" value="<?php echo esc_attr($s['gemini_api_key']); ?>" placeholder="AIza..."></label>
                                    <label class="rankrepair-ai-provider-field rankrepair-ai-provider-gemini">Gemini model<select id="rankrepair_ai_gemini_model">
                                        <?php foreach (RANKREPAIR_AI_AI::provider_models()['gemini'] as $model): ?><option value="<?php echo esc_attr($model); ?>" <?php selected($s['gemini_model'], $model); ?>><?php echo esc_html($model); ?></option><?php endforeach; ?>
                                    </select></label>
                                    <label>AI batch size<input id="rankrepair_ai_ai_batch_size" type="number" min="1" max="10" value="<?php echo esc_attr($s['ai_batch_size']); ?>"></label>
                                    <label>Content limit per post<input id="rankrepair_ai_max_content_chars" type="number" min="1000" max="20000" value="<?php echo esc_attr($s['max_content_chars']); ?>"></label>
                                    <div class="rankrepair-ai-auto-throttle-card"><strong>Auto AI throttle is enabled</strong><span>RankRepair now chooses safe pauses and retry backoff based on the selected provider/model, response rate-limit headers, and batch size. Manual delay controls were removed to avoid accidental API bursts.</span></div>
                                </div>
                            </section>

                            <section class="rankrepair-ai-settings-group">
                                <div class="rankrepair-ai-settings-group-head"><h3>Features & safety</h3><p>Keep Safe Compatibility Mode enabled on plugin-heavy sites, especially with Elementor, Divi, Crocoblock, and dynamic templates.</p></div>
                                <div class="rankrepair-ai-toggle-list">
                                    <label class="rankrepair-ai-checkbox-label"><input id="rankrepair_ai_refresh_yoast_after_update" type="checkbox" value="1" <?php checked(!empty($s['refresh_yoast_after_update'])); ?>> Refresh SEO scores/cache after saving</label>
                                    <label class="rankrepair-ai-checkbox-label"><input id="rankrepair_ai_safe_compatibility_mode" type="checkbox" value="1" <?php checked(!empty($s['safe_compatibility_mode'])); ?>> Safe Compatibility Mode for builder/plugin-heavy sites</label>
                                    <label class="rankrepair-ai-checkbox-label"><input id="rankrepair_ai_show_editor_box" type="checkbox" value="1" <?php checked(!empty($s['show_editor_box'])); ?>> Show RankRepair AI box inside editors</label>
                                    <label class="rankrepair-ai-checkbox-label"><input id="rankrepair_ai_show_list_table_tools" type="checkbox" value="1" <?php checked(!empty($s['show_list_table_tools'])); ?>> Show Improve SEO tools in post/page lists</label>
                                    <label class="rankrepair-ai-checkbox-label"><input id="rankrepair_ai_disable_builder_content_overwrite" type="checkbox" value="1" <?php checked(!empty($s['disable_builder_content_overwrite'])); ?>> Block direct content overwrite on Elementor/Divi/Crocoblock-managed posts</label>
                                    <label class="rankrepair-ai-checkbox-label"><input id="rankrepair_ai_render_schema_frontend" type="checkbox" value="1" <?php checked(!empty($s['render_schema_frontend'])); ?>> Render saved RankRepair schema on the frontend</label>
                                </div>
                            </section>

                            <section class="rankrepair-ai-settings-group">
                                <div class="rankrepair-ai-settings-group-head"><h3>Where RankRepair appears</h3><p>Enable editor boxes and post-list tools only for post types you want to manage.</p></div>
                                <div class="rankrepair-ai-checkbox-grid">
                                    <?php $enabled_types = isset($s['enabled_post_types']) && is_array($s['enabled_post_types']) ? $s['enabled_post_types'] : ['post','page']; foreach ($post_types as $pt): ?>
                                        <label class="rankrepair-ai-checkbox-label"><input class="rankrepair_ai_enabled_post_type" type="checkbox" value="<?php echo esc_attr($pt->name); ?>" <?php checked(in_array($pt->name, $enabled_types, true)); ?>> <?php echo esc_html($pt->label); ?></label>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        </div>
                        <div class="rankrepair-ai-card-actions"><button class="button button-primary" id="rankrepair_ai_save_settings">Save settings</button><span id="rankrepair_ai_settings_status"></span></div>
                    </div>
                </section>

                <section class="rankrepair-ai-card" id="rankrepair_ai_scan_section">
                    <div class="rankrepair-ai-section-heading"><div><p class="rankrepair-ai-kicker">Step 2</p><h2>Scan SEO fields</h2></div><span class="rankrepair-ai-pill">No changes yet</span></div>
                    <div class="rankrepair-ai-toolbar rankrepair-ai-toolbar-grid">
                        <label>Post type<select id="rankrepair_ai_post_type">
                            <?php foreach ($post_types as $pt): ?>
                                <option value="<?php echo esc_attr($pt->name); ?>"><?php echo esc_html($pt->label); ?></option>
                            <?php endforeach; ?>
                        </select></label>
                        <label>Status<select id="rankrepair_ai_post_status"><option value="any">Any editable status</option><option value="publish">Published</option><option value="draft">Draft</option><option value="private">Private</option><option value="pending">Pending</option><option value="future">Scheduled</option></select></label>
                        <label>Show<select id="rankrepair_ai_issue_filter"><option value="issues">Suspicious posts</option><option value="all">All posts</option><option value="missing_title">Missing SEO title</option><option value="missing_description">Missing meta description</option><option value="missing_keyword">Missing focus keyword</option></select></label>
                        <label>Search title/content<input id="rankrepair_ai_search" placeholder="Search posts..."></label>
                        <label>Post ID from<input id="rankrepair_ai_min_id" type="number" min="0" placeholder="Any"></label>
                        <label>Post ID to<input id="rankrepair_ai_max_id" type="number" min="0" placeholder="Any"></label>
                        <label>Posts to show<input id="rankrepair_ai_limit" type="number" value="100" min="1" max="1000"></label>
                        <label>Start after #<input id="rankrepair_ai_offset" type="number" value="0" min="0"></label>
                        <button class="button button-primary" id="rankrepair_ai_scan">Scan posts</button>
                    </div>
                    <p class="description rankrepair-ai-scan-note">Tip: missing SEO titles, descriptions, or keywords are always treated as issues now. Use <strong>All posts</strong> only when you want to review everything manually.</p>
                </section>
            </div>

            <section class="rankrepair-ai-card" id="rankrepair_ai_bulk_section">
                <div class="rankrepair-ai-section-heading"><div><p class="rankrepair-ai-kicker">Step 3</p><h2>Bulk Manual Edit</h2></div><span class="rankrepair-ai-pill">Preview required</span></div>
                <p class="rankrepair-ai-muted">Use this when the problem is predictable, like replacing an old domain, removing a duplicated brand suffix, or setting a consistent title pattern.</p>
                <div class="rankrepair-ai-toolbar rankrepair-ai-rule-builder">
                    <label>Field<select id="rankrepair_ai_field"><option value="seo_title">SEO Title</option><option value="meta_description">Meta Description</option><option value="focus_keyword">Focus Keyword</option></select></label>
                    <label>Action<select id="rankrepair_ai_action">
                        <option value="replace_text">Replace text</option><option value="remove_text">Remove text</option><option value="add_prefix">Add prefix</option><option value="add_suffix">Add suffix</option><option value="set_pattern">Set from pattern</option><option value="copy_post_title">Copy post title</option><option value="copy_excerpt">Copy excerpt</option><option value="limit_chars">Limit characters</option><option value="clear">Clear field</option>
                    </select></label>
                    <label>Find / Text<input id="rankrepair_ai_find" placeholder="Old text or value"></label>
                    <label>Replace / Pattern<input id="rankrepair_ai_replace" placeholder="New text, pattern, or limit"></label>
                    <button class="button" id="rankrepair_ai_preview_rule">Preview rule</button>
                    <button class="button button-primary" id="rankrepair_ai_apply_rule">Apply previewed</button>
                </div>
                <div class="rankrepair-ai-helper-box"><strong>Pattern variables:</strong> {post_title}, {brand_name}, {site_name}, {category}, {current_value}, {current_seo_title}, {current_focus_keyword}, {excerpt}</div>
            </section>

            <section class="rankrepair-ai-card" id="rankrepair_ai_results_section">
                <div class="rankrepair-ai-section-heading"><div><p class="rankrepair-ai-kicker">Step 4</p><h2>Results & approvals</h2></div><span class="rankrepair-ai-pill">Before / After</span></div>
                <div class="rankrepair-ai-actions"><button class="button" id="rankrepair_ai_select_all">Select all visible</button><button class="button" id="rankrepair_ai_clear_selection">Clear selection</button><button class="button button-primary" id="rankrepair_ai_ai_selected">Generate AI suggestions</button><button class="button" id="rankrepair_ai_apply_all_ai">Apply all AI suggestions</button><span class="rankrepair-ai-selection-count" id="rankrepair_ai_selection_count">0 selected</span></div>
                <div id="rankrepair_ai_status" class="rankrepair-ai-status-box">Run a scan to see posts here.</div>
                <div class="rankrepair-ai-table-wrap"><table class="widefat striped rankrepair-ai-results-table" id="rankrepair_ai_table"><thead><tr><th></th><th>ID</th><th>Post</th><th>Issues</th><th>Current SEO</th><th>Preview / AI Suggestion</th><th>Actions</th></tr></thead><tbody></tbody></table></div>
            </section>


            <section class="rankrepair-ai-card" id="rankrepair_ai_scan_services_section">
                <div class="rankrepair-ai-section-heading"><div><p class="rankrepair-ai-kicker">Scan services</p><h2>Media, links, and migration scans</h2></div><span class="rankrepair-ai-pill">Grouped in Scan & Repair</span></div>
                <p class="rankrepair-ai-muted">Use these only when you need them. They do not change your site until you review and apply a fix.</p>
                <div class="rankrepair-ai-service-grid">
                    <div class="rankrepair-ai-service-card">
                        <h3>Broken links / old domain links</h3>
                        <p>Find empty links, old-domain URLs, and obvious broken internal paths in post content.</p>
                        <div class="rankrepair-ai-form-grid rankrepair-ai-form-rows">
                            <label>Post type<select id="rr_link_post_type"><option value="any">Any public post type</option><?php foreach ($post_types as $pt): ?><option value="<?php echo esc_attr($pt->name); ?>"><?php echo esc_html($pt->label); ?></option><?php endforeach; ?></select></label>
                            <label>Limit<input id="rr_link_limit" type="number" min="1" max="1000" value="250"></label>
                            <label>Old domain/URL<input id="rr_old_domain" type="text" value="<?php echo esc_attr($s['old_domain'] ?? ''); ?>" placeholder="oldsite.com"></label>
                        </div>
                        <button class="button" id="rr_link_scan">Scan links</button>
                        <div id="rr_link_results" class="rankrepair-ai-inline-results"></div>
                    </div>
                    <div class="rankrepair-ai-service-card">
                        <h3>Media library SEO scan</h3>
                        <p>Find images missing alt text, captions, descriptions, or migration-related media issues.</p>
                        <div class="rankrepair-ai-form-grid rankrepair-ai-form-rows">
                            <label>Limit<input id="rr_media_limit" type="number" min="1" max="1000" value="300"></label>
                            <label>Old domain/URL<input id="rr_media_old_domain" type="text" value="<?php echo esc_attr($s['old_domain'] ?? ''); ?>" placeholder="oldsite.com"></label>
                        </div>
                        <button class="button" id="rr_media_scan">Scan media</button>
                        <div id="rr_media_results" class="rankrepair-ai-inline-results"></div>
                    </div>
                    <div class="rankrepair-ai-service-card">
                        <h3>Builder / HTML recovery</h3>
                        <p>Recover Divi, Elementor, or raw HTML content into Gutenberg-friendly paragraphs and headings.</p>
                        <a class="button" href="#rankrepair_ai_divi_recovery_section">Open recovery tool below</a>
                    </div>
                </div>
            </section>


            <section class="rankrepair-ai-card" id="rankrepair_ai_content_audit_section">
                <div class="rankrepair-ai-section-heading"><div><p class="rankrepair-ai-kicker">Audit</p><h2>AI Content Audit Center</h2></div><span class="rankrepair-ai-pill">SEO + content health</span></div>
                <p class="rankrepair-ai-muted">Audit a post for SEO, readability, headings, schema opportunities, internal links, image metadata, and migration leftovers. This is designed to become the central "fix with AI" workflow.</p>
                <div class="rankrepair-ai-toolbar rankrepair-ai-toolbar-grid">
                    <label>Post ID<input id="rankrepair_ai_audit_post_id" type="number" min="1" placeholder="123"></label>
                    <button type="button" class="button button-primary" id="rankrepair_ai_run_content_audit">Run content audit</button>
                </div>
                <div id="rankrepair_ai_audit_output" class="rankrepair-ai-ai-output">No audit generated yet.</div>
            </section>

            <section class="rankrepair-ai-card" id="rankrepair_ai_rewrite_section">
                <div class="rankrepair-ai-section-heading"><div><p class="rankrepair-ai-kicker">Rewrite</p><h2>AI Bulk Rewrite</h2></div><span class="rankrepair-ai-pill">Preview first</span></div>
                <p class="rankrepair-ai-muted">Rewrite existing content while preserving facts and the heading structure. Use this for old posts, migration cleanup, readability improvements, and SEO modernization.</p>
                <div class="rankrepair-ai-toolbar rankrepair-ai-toolbar-grid">
                    <label>Post ID<input id="rankrepair_ai_rewrite_post_id" type="number" min="1" placeholder="123"></label>
                    <label>Rewrite mode<select id="rankrepair_ai_rewrite_mode"><option value="seo_readability">SEO + readability</option><option value="modernize">Modernize old content</option><option value="simplify">Simplify language</option><option value="localize">Localize to site language</option><option value="conversion">Improve CTA/conversion</option></select></label>
                    <button type="button" class="button button-primary" id="rankrepair_ai_generate_rewrite">Generate rewrite preview</button>
                    <button type="button" class="button" id="rankrepair_ai_apply_rewrite" disabled>Apply rewrite</button>
                </div>
                <div id="rankrepair_ai_rewrite_output" class="rankrepair-ai-ai-output">No rewrite generated yet.</div>
            </section>

            <section class="rankrepair-ai-card" id="rankrepair_ai_schema_section">
                <div class="rankrepair-ai-section-heading"><div><p class="rankrepair-ai-kicker">Structured data</p><h2>AI Schema Builder</h2></div><span class="rankrepair-ai-pill">JSON-LD</span></div>
                <p class="rankrepair-ai-muted">Generate JSON-LD schema based on the post content. RankRepair stores it in post meta so themes or future integrations can render it safely.</p>
                <div class="rankrepair-ai-toolbar rankrepair-ai-toolbar-grid">
                    <label>Post ID<input id="rankrepair_ai_schema_post_id" type="number" min="1" placeholder="123"></label>
                    <label>Schema type<select id="rankrepair_ai_schema_type"><option value="auto">Auto detect</option><option value="Article">Article</option><option value="FAQPage">FAQ</option><option value="HowTo">HowTo</option><option value="Product">Product</option><option value="LocalBusiness">Local Business</option></select></label>
                    <button type="button" class="button button-primary" id="rankrepair_ai_generate_schema">Generate schema</button>
                    <button type="button" class="button" id="rankrepair_ai_apply_schema" disabled>Save schema</button>
                </div>
                <div id="rankrepair_ai_schema_output" class="rankrepair-ai-ai-output">No schema generated yet.</div>
            </section>

            <section class="rankrepair-ai-card" id="rankrepair_ai_content_plan_section">
                <div class="rankrepair-ai-section-heading"><div><p class="rankrepair-ai-kicker">Strategy</p><h2>AI Content Planner & Topic Clusters</h2></div><span class="rankrepair-ai-pill">Content roadmap</span></div>
                <p class="rankrepair-ai-muted">Create a simple SEO roadmap with clusters, article ideas, internal linking strategy, and schema opportunities.</p>
                <div class="rankrepair-ai-form-grid rankrepair-ai-content-grid">
                    <label class="rankrepair-ai-wide-field">Main topic / business niche<textarea id="rankrepair_ai_plan_topic" rows="3" placeholder="Example: modular furniture for small apartments in Brazil"></textarea></label>
                    <label>Audience<input id="rankrepair_ai_plan_audience" placeholder="Local buyers, ecommerce shoppers, B2B clients..."></label>
                    <label>Timeframe<select id="rankrepair_ai_plan_timeframe"><option value="30 days">30 days</option><option value="60 days">60 days</option><option value="90 days">90 days</option></select></label>
                    <button type="button" class="button button-primary" id="rankrepair_ai_generate_content_plan">Generate content plan</button>
                </div>
                <div id="rankrepair_ai_plan_output" class="rankrepair-ai-ai-output">No content plan generated yet.</div>
            </section>

            <section class="rankrepair-ai-card" id="rankrepair_ai_content_assistant">
                <div class="rankrepair-ai-section-heading"><div><p class="rankrepair-ai-kicker">Create</p><h2>AI Content Assistant</h2></div><span class="rankrepair-ai-pill">Draft + SEO package</span></div>
                <p class="rankrepair-ai-muted">Create a WordPress draft from a prompt with title, slug, excerpt, article body, SEO metadata, FAQ schema, tags, category ideas, and featured image metadata suggestions.</p>
                <div class="rankrepair-ai-form-grid rankrepair-ai-content-grid">
                    <label class="rankrepair-ai-wide-field">Prompt / topic<textarea id="rankrepair_ai_content_prompt" rows="4" placeholder="Example: Create a practical guide about modular furniture for small apartments..."></textarea></label>
                    <label>Content type<select id="rankrepair_ai_content_post_type"><option value="post">Blog post</option><option value="page">Page</option><option value="product">WooCommerce product draft</option></select></label>
                    <label>Audience<input id="rankrepair_ai_content_audience" placeholder="Homeowners, B2B buyers, local clients..."></label>
                    <label>Tone<input id="rankrepair_ai_content_tone" value="Professional and helpful"></label>
                    <label>Status<select id="rankrepair_ai_content_status"><option value="draft">Draft</option><option value="pending">Pending review</option></select></label>
                    <button type="button" class="button button-primary" id="rankrepair_ai_generate_content_blueprint">Generate content package</button>
                    <button type="button" class="button" id="rankrepair_ai_create_draft_post" disabled>Create WordPress draft</button>
                </div>
                <div id="rankrepair_ai_content_output" class="rankrepair-ai-ai-output">No content package generated yet.</div>
            </section>

            <section class="rankrepair-ai-card" id="rankrepair_ai_internal_links_section">
                <div class="rankrepair-ai-section-heading"><div><p class="rankrepair-ai-kicker">Optimize</p><h2>Internal Link Assistant</h2></div><span class="rankrepair-ai-pill">Anchor suggestions</span></div>
                <p class="rankrepair-ai-muted">Enter a post ID and RankRepair AI will compare it with recent published content to suggest relevant internal links and anchor text.</p>
                <div class="rankrepair-ai-toolbar">
                    <label>Post ID<input id="rankrepair_ai_links_post_id" type="number" min="1" placeholder="123"></label>
                    <label>Candidate posts<input id="rankrepair_ai_links_limit" type="number" value="12" min="5" max="30"></label>
                    <button type="button" class="button button-primary" id="rankrepair_ai_generate_internal_links">Suggest internal links</button>
                </div>
                <div id="rankrepair_ai_links_output" class="rankrepair-ai-ai-output">No internal link suggestions yet.</div>
            </section>

            <section class="rankrepair-ai-card" id="rankrepair_ai_image_seo_section">
                <div class="rankrepair-ai-section-heading"><div><p class="rankrepair-ai-kicker">Media</p><h2>Image SEO Metadata</h2></div><span class="rankrepair-ai-pill">Alt text + captions</span></div>
                <p class="rankrepair-ai-muted">Generate accessible image alt text, title, caption, description, and a recommended filename. This keeps image SEO intentional without keyword stuffing.</p>
                <div class="rankrepair-ai-form-grid">
                    <label>Attachment ID<input id="rankrepair_ai_image_attachment_id" type="number" min="1" placeholder="Media library attachment ID"></label>
                    <label class="rankrepair-ai-wide-field">Image context<textarea id="rankrepair_ai_image_context" rows="3" placeholder="Describe where this image will be used or what the page is about..."></textarea></label>
                    <button type="button" class="button button-primary" id="rankrepair_ai_generate_image_seo">Generate image SEO with AI</button>
                </div>
                <div class="rankrepair-ai-helper-box"><strong>How this works:</strong> enter a Media Library attachment ID, generate image metadata with AI, review it, then click <strong>Apply to media item</strong> to update alt text, title, caption, and description.</div><div id="rankrepair_ai_image_output" class="rankrepair-ai-ai-output">No image metadata generated yet.</div>
                <div class="rankrepair-ai-bulk-media-box">
                    <h3>Bulk improve media with AI</h3>
                    <p class="rankrepair-ai-muted">Find image attachments with missing alt text, weak titles, empty captions, or generic filenames. RankRepair generates metadata in small provider-aware batches and lets you apply all approved suggestions.</p>
                    <div class="rankrepair-ai-toolbar rankrepair-ai-toolbar-grid rankrepair-ai-compact-toolbar">
                        <label>Images to inspect<input id="rankrepair_ai_media_bulk_limit" type="number" value="30" min="1" max="100"></label>
                        <label>Only missing alt<select id="rankrepair_ai_media_bulk_missing_alt"><option value="1">Yes</option><option value="0">No, include all weak metadata</option></select></label>
                        <button type="button" class="button button-primary" id="rankrepair_ai_bulk_image_seo">Bulk improve with AI</button>
                    </div>
                    <div id="rankrepair_ai_bulk_image_output" class="rankrepair-ai-ai-output">No bulk image suggestions generated yet.</div>
                </div>
            </section>


            <section class="rankrepair-ai-card" id="rankrepair_ai_image_recovery_section">
                <div class="rankrepair-ai-section-heading"><div><p class="rankrepair-ai-kicker">Recover</p><h2>Image Recovery & Replacement</h2></div><span class="rankrepair-ai-pill">Featured + inline images</span></div>
                <p class="rankrepair-ai-muted">Find missing, broken, external/old-domain, or metadata-poor images after migrations. RankRepair can suggest replacement search queries/prompts and safely apply a new featured image from a Media Library ID or image URL.</p>
                <div class="rankrepair-ai-helper-box"><strong>Client-safe workflow:</strong> scan → review issues → generate AI replacement guidance → paste a legal stock/original image URL or Media Library attachment ID → apply. RankRepair backs up the previous featured image before replacing it.</div>
                <div class="rankrepair-ai-toolbar rankrepair-ai-toolbar-grid">
                    <label>Post type<select id="rankrepair_ai_imgrec_post_type"><option value="any">Any public post type</option>
                        <?php foreach ($post_types as $pt): ?>
                            <option value="<?php echo esc_attr($pt->name); ?>"><?php echo esc_html($pt->label); ?></option>
                        <?php endforeach; ?>
                    </select></label>
                    <label>Status<select id="rankrepair_ai_imgrec_post_status"><option value="any">Any editable status</option><option value="publish">Published</option><option value="draft">Draft</option><option value="private">Private</option><option value="pending">Pending</option></select></label>
                    <label>Issue mode<select id="rankrepair_ai_imgrec_mode"><option value="all">All image issues</option><option value="broken_only">Broken/missing only</option></select></label>
                    <label>Posts to inspect<input id="rankrepair_ai_imgrec_limit" type="number" value="100" min="1" max="300"></label>
                    <label>Start after #<input id="rankrepair_ai_imgrec_offset" type="number" value="0" min="0"></label>
                    <button type="button" class="button button-primary" id="rankrepair_ai_image_recovery_scan">Scan images</button>
                </div>
                <div id="rankrepair_ai_image_recovery_status" class="rankrepair-ai-status-box">Run an image recovery scan to find posts with missing featured images, broken attachments, old-domain image URLs, or missing alt text.</div>
                <div class="rankrepair-ai-table-wrap"><table class="widefat striped rankrepair-ai-results-table" id="rankrepair_ai_image_recovery_table"><thead><tr><th>ID</th><th>Post</th><th>Image issues</th><th>AI suggestion / replacement</th><th>Actions</th></tr></thead><tbody></tbody></table></div>
            </section>

            <section class="rankrepair-ai-card" id="rankrepair_ai_divi_recovery_section">
                <div class="rankrepair-ai-section-heading"><div><p class="rankrepair-ai-kicker">Recover</p><h2>Builder & HTML to Gutenberg Recovery</h2></div><span class="rankrepair-ai-pill">Preview before replacing</span></div>
                <p class="rankrepair-ai-muted">Recover content from Divi shortcodes, Elementor JSON data, regular HTML, or mixed builder leftovers. RankRepair converts headings, paragraphs, lists, quotes, tables, images, buttons, and common widgets into Gutenberg-friendly blocks while trying to preserve the original heading hierarchy.</p>
                <div class="rankrepair-ai-helper-box"><strong>Safe workflow:</strong> choose a source type → scan posts/revisions/builder data → preview recovered Gutenberg content → apply only the posts you approve. RankRepair stores the previous content in a rollback backup meta field before replacing post content.</div>
                <div class="rankrepair-ai-toolbar rankrepair-ai-toolbar-grid">
                    <label>Source<select id="rankrepair_ai_divi_source_type"><option value="auto">Auto detect</option><option value="divi">Divi shortcodes</option><option value="elementor">Elementor data</option><option value="html">HTML/current content</option></select></label>
                    <label>Post type<select id="rankrepair_ai_divi_post_type"><option value="any">Any public post type</option>
                        <?php foreach ($post_types as $pt): ?>
                            <option value="<?php echo esc_attr($pt->name); ?>"><?php echo esc_html($pt->label); ?></option>
                        <?php endforeach; ?>
                    </select></label>
                    <label>Status<select id="rankrepair_ai_divi_post_status"><option value="any">Any editable status</option><option value="publish">Published</option><option value="draft">Draft</option><option value="private">Private</option><option value="pending">Pending</option></select></label>
                    <label>Search<input id="rankrepair_ai_divi_search" placeholder="Search title/content..."></label>
                    <label>Posts to show<input id="rankrepair_ai_divi_limit" type="number" value="100" min="1" max="500"></label>
                    <label>Start after #<input id="rankrepair_ai_divi_offset" type="number" value="0" min="0"></label>
                    <button type="button" class="button button-primary" id="rankrepair_ai_divi_scan">Find recoverable content</button>
                </div>
                <div id="rankrepair_ai_divi_status" class="rankrepair-ai-status-box">Run a content recovery scan to find Divi, Elementor, or HTML content that can be converted to Gutenberg.</div>
                <div class="rankrepair-ai-table-wrap"><table class="widefat striped rankrepair-ai-results-table" id="rankrepair_ai_divi_table"><thead><tr><th>ID</th><th>Post</th><th>Detected source</th><th>Recoverable content</th><th>Preview</th><th>Action</th></tr></thead><tbody></tbody></table></div>
            </section>

            <section class="rankrepair-ai-card" id="rankrepair_ai_health_tips">
                <div class="rankrepair-ai-section-heading"><div><p class="rankrepair-ai-kicker">Professional safeguards</p><h2>Recommended workflow</h2></div><span class="rankrepair-ai-pill">Client-safe</span></div>
                <div class="rankrepair-ai-steps">
                    <div class="rankrepair-ai-step"><span>✓</span><h3>Use staging first</h3><p>Run a small batch, confirm the SEO plugin reads the updated fields, then move to production.</p></div>
                    <div class="rankrepair-ai-step"><span>✓</span><h3>Preview before bulk apply</h3><p>Manual rules and AI suggestions are designed to be reviewed before updating metadata.</p></div>
                    <div class="rankrepair-ai-step"><span>✓</span><h3>Use custom keys</h3><p>If your SEO plugin is not listed, choose custom meta keys and RankRepair AI can still update the correct fields.</p></div>
                </div>
            </section>
        </div>
        <?php
    }

    public function save_settings() {
        $this->guard();
        $settings = RANKREPAIR_AI_Plugin::settings();
        foreach (['old_domain','new_domain','brand_name','language','brand_voice','default_content_status','seo_plugin','custom_title_key','custom_description_key','custom_keyword_key','ai_provider','openai_api_key','openai_model','anthropic_api_key','anthropic_model','gemini_api_key','gemini_model','ai_batch_size','retry_attempts','retry_delay_ms','max_content_chars'] as $key) {
            if (null === $this->post_value($key, null)) continue;
            if (in_array($key, ['ai_batch_size','retry_attempts','retry_delay_ms','max_content_chars'], true)) {
                $settings[$key] = intval($this->post_value($key));
            } else {
                $settings[$key] = sanitize_text_field($this->post_value($key));
            }
        }
        if (!in_array($settings['seo_plugin'], array_keys(RANKREPAIR_AI_SEO_Adapters::providers()), true)) $settings['seo_plugin'] = 'auto';
        if (!in_array($settings['ai_provider'], ['openai','anthropic','gemini'], true)) $settings['ai_provider'] = 'openai';
        $settings['ai_batch_size'] = max(1, min(10, intval($settings['ai_batch_size'])));
        $settings['ai_batch_delay_ms'] = 0;
        $settings['ai_throttle_mode'] = 'auto';
        $settings['max_content_chars'] = max(1000, min(20000, intval($settings['max_content_chars'])));
        $settings['refresh_yoast_after_update'] = !empty($this->post_value('refresh_yoast_after_update', '')) ? 1 : 0;
        $settings['refresh_scores_after_update'] = $settings['refresh_yoast_after_update'];
        foreach (['safe_compatibility_mode','show_editor_box','show_list_table_tools','disable_builder_content_overwrite','render_schema_frontend'] as $flag) {
            $settings[$flag] = !empty($this->post_value($flag, '')) ? 1 : 0;
        }
        $enabled = array_map('sanitize_key', $this->post_array('enabled_post_types', ['post','page']));
        $public = array_keys(get_post_types(['public' => true], 'names'));
        $settings['enabled_post_types'] = array_values(array_intersect($enabled, $public));
        if (!$settings['enabled_post_types']) $settings['enabled_post_types'] = ['post','page'];
        $settings['onboarding_completed'] = RANKREPAIR_AI_Plugin::has_ai_credentials($settings) ? 1 : 0;
        update_option(RANKREPAIR_AI_Plugin::OPTION_KEY, $settings);
        wp_send_json_success(['message' => 'Settings saved.']);
    }

    public function scan() {
        $this->guard();
        $result = RANKREPAIR_AI_Scanner::scan([
            'post_type' => sanitize_key($this->post_value('post_type', 'post')),
            'post_status' => sanitize_key($this->post_value('post_status', 'any')),
            'limit' => intval($this->post_value('limit', 100)),
            'offset' => intval($this->post_value('offset', 0)),
            'scan_depth' => 3000,
            'min_id' => intval($this->post_value('min_id', 0)),
            'max_id' => intval($this->post_value('max_id', 0)),
            'issue_filter' => sanitize_key($this->post_value('issue_filter', 'issues')),
            'search' => sanitize_text_field($this->post_value('search', '')),
            'source_type' => sanitize_key($this->post_value('source_type', 'auto')),
        ]);
        wp_send_json_success($result);
    }

    private function selected_ids() {
        $ids = $this->post_array('post_ids', []);
        if (is_string($ids)) $ids = explode(',', $ids);
        return array_values(array_filter(array_map('intval', (array) $ids)));
    }

    private function rule_params() {
        $raw = $this->post_json('params', []);
        return is_array($raw) ? array_map('sanitize_text_field', $raw) : [];
    }

    public function bulk_preview() {
        $this->guard();
        $field = sanitize_key($this->post_value('field', 'seo_title'));
        $action = sanitize_key($this->post_value('rule_action', 'replace_text'));
        $params = $this->rule_params();
        $rows = [];
        foreach ($this->selected_ids() as $id) {
            if (!current_user_can('edit_post', $id)) continue;
            $preview = RANKREPAIR_AI_Scanner::apply_rule_preview($id, $field, $action, $params);
            if (!is_wp_error($preview)) $rows[] = $preview;
        }
        wp_send_json_success(['items' => $rows]);
    }

    public function bulk_apply() {
        $this->guard();
        $items = $this->post_json('items', []);
        $count = 0;
        foreach ((array) $items as $item) {
            $id = intval($item['post_id'] ?? 0);
            $field = sanitize_key($item['field'] ?? '');
            $after = $item['after'] ?? '';
            if (!$id || !current_user_can('edit_post', $id)) continue;
            $r = RANKREPAIR_AI_Plugin::update_yoast($id, [$field => $after]);
            if (!is_wp_error($r)) $count++;
        }
        wp_send_json_success(['message' => "Applied {$count} updates."]);
    }

    public function ai_generate() {
        $this->guard();
        $rows = [];
        foreach ($this->selected_ids() as $id) {
            if (!current_user_can('edit_post', $id)) continue;
            $gen = RANKREPAIR_AI_AI::generate($id);
            if (is_wp_error($gen)) $rows[] = ['post_id'=>$id, 'error'=>$gen->get_error_message()];
            else $rows[] = $gen;
        }
        wp_send_json_success(['items' => $rows]);
    }

    public function ai_apply() {
        $this->guard();
        $items = $this->post_json('items', []);
        $count = 0;
        foreach ((array) $items as $item) {
            $id = intval($item['post_id'] ?? 0);
            if (!$id || !current_user_can('edit_post', $id) || empty($item['after']) || !is_array($item['after'])) continue;
            $r = RANKREPAIR_AI_Plugin::update_yoast($id, $item['after']);
            if (!is_wp_error($r)) $count++;
        }
        wp_send_json_success(['message' => "Applied {$count} AI suggestions."]);
    }

    public function rollback() {
        $this->guard();
        $id = intval($this->post_value('post_id', 0));
        if (!$id || !current_user_can('edit_post', $id)) wp_send_json_error(['message'=>'Forbidden'], 403);
        $backup = json_decode(get_post_meta($id, '_rankrepair_ai_last_backup', true), true);
        if (!$backup || empty($backup['values'])) wp_send_json_error(['message'=>'No backup found.']);
        RANKREPAIR_AI_Plugin::update_yoast($id, $backup['values']);
        wp_send_json_success(['message'=>'Rolled back latest backup.']);
    }

    public function add_rankrepair_column($columns) {
        $columns['rankrepair_ai'] = 'RankRepair AI';
        return $columns;
    }

    public function add_rankrepair_sortable_column($columns) {
        $columns['rankrepair_ai'] = 'rankrepair_seo_health';
        return $columns;
    }

    public function maybe_sort_posts_by_seo_health($query) {
        if (!is_admin() || !$query->is_main_query()) return;
        if ($query->get('orderby') !== 'rankrepair_seo_health') return;
        $query->set('meta_key', '_rankrepair_issue_count');
        $query->set('orderby', 'meta_value_num');
        if (!$query->get('order')) $query->set('order', 'DESC');
    }

    public function render_rankrepair_column($column, $post_id) {
        if ($column !== 'rankrepair_ai') return;
        if (!current_user_can('edit_post', $post_id)) { echo '&mdash;'; return; }
        $audit = RANKREPAIR_AI_Scanner::audit_post($post_id);
        $count = count($audit['issues']);
        update_post_meta($post_id, '_rankrepair_issue_count', $count);
        $status_class = $count ? 'is-bad' : 'is-good';
        $label = $count ? sprintf('%d issue%s', $count, $count === 1 ? '' : 's') : 'Good';
        $title = $count && !empty($audit['issues']) ? implode(', ', array_slice($audit['issues'], 0, 4)) : 'No obvious SEO issues found';
        echo '<div class="rankrepair-ai-listing-cell"><span title="' . esc_attr($title) . '" class="rankrepair-ai-listing-status ' . esc_attr($status_class) . '">' . esc_html($label) . '</span><button type="button" class="button button-small rankrepair-ai-quick-ai-inline" data-post-id="' . esc_attr($post_id) . '">Improve with AI</button></div>';
    }

    public function post_row_action($actions, $post) {
        if (!$post || !in_array($post->post_type, $this->enabled_post_types(), true)) return $actions;
        if (!current_user_can('edit_post', $post->ID)) return $actions;
        $actions['rankrepair_ai_improve'] = '<a href="#" class="rankrepair-ai-quick-ai" data-post-id="' . esc_attr($post->ID) . '">Improve SEO with AI</a>';
        return $actions;
    }

    public function quick_ai_improve() {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message'=>'Forbidden'], 403);
        check_ajax_referer('rankrepair_ai_quick_nonce', 'quick_nonce');
        $id = intval($this->post_value('post_id', 0));
        if (!$id || !current_user_can('edit_post', $id)) wp_send_json_error(['message'=>'Forbidden'], 403);
        $gen = RANKREPAIR_AI_AI::generate($id);
        if (is_wp_error($gen)) wp_send_json_error(['message'=>$gen->get_error_message()]);
        $r = RANKREPAIR_AI_SEO_Adapters::update($id, $gen['after']);
        if (is_wp_error($r)) wp_send_json_error(['message'=>$r->get_error_message()]);
        $audit = RANKREPAIR_AI_Scanner::audit_post($id);
        $values = RANKREPAIR_AI_SEO_Adapters::get_values($id);
        $gen['after'] = $values;
        wp_send_json_success([
            'message'=>'SEO improved with AI for #' . $id,
            'item'=>$gen,
            'values'=>$values,
            'audit'=>$audit,
            'edit_link'=>get_edit_post_link($id, ''),
        ]);
    }


    public function content_blueprint() {
        $this->guard();
        $args = [
            'topic' => sanitize_textarea_field($this->post_value('topic', '')),
            'post_type' => sanitize_key($this->post_value('post_type', 'post')),
            'audience' => sanitize_text_field($this->post_value('audience', '')),
            'tone' => sanitize_text_field($this->post_value('tone', 'professional')),
            'language' => sanitize_text_field($this->post_value('language', '')),
        ];
        $result = RANKREPAIR_AI_AI::generate_post_blueprint($args);
        if (is_wp_error($result)) wp_send_json_error(['message' => $result->get_error_message()]);
        wp_send_json_success(['item' => $result]);
    }

    public function create_draft_post() {
        $this->guard();
        $item = $this->post_json('item', []);
        if (!is_array($item) || empty($item['post_title'])) wp_send_json_error(['message' => 'Missing generated content package.']);
        $post_type = sanitize_key($this->post_value('post_type', 'post'));
        if (!post_type_exists($post_type)) $post_type = 'post';
        $status = sanitize_key($this->post_value('post_status', 'draft'));
        if (!in_array($status, ['draft','pending'], true)) $status = 'draft';
        $content = wp_kses_post($item['content_html'] ?? '');
        $post_id = wp_insert_post([
            'post_title' => sanitize_text_field($item['post_title']),
            'post_name' => sanitize_title($item['slug'] ?? $item['post_title']),
            'post_excerpt' => sanitize_textarea_field($item['excerpt'] ?? ''),
            'post_content' => $content,
            'post_type' => $post_type,
            'post_status' => $status,
        ], true);
        if (is_wp_error($post_id)) wp_send_json_error(['message' => $post_id->get_error_message()]);
        RANKREPAIR_AI_SEO_Adapters::update($post_id, [
            'seo_title' => sanitize_text_field($item['seo_title'] ?? ''),
            'meta_description' => sanitize_textarea_field($item['meta_description'] ?? ''),
            'focus_keyword' => sanitize_text_field($item['focus_keyword'] ?? ''),
        ]);
        if (!empty($item['schema_json_ld'])) update_post_meta($post_id, '_rankrepair_schema_json_ld', wp_json_encode($item['schema_json_ld']));
        if (!empty($item['featured_image_prompt'])) update_post_meta($post_id, '_rankrepair_featured_image_prompt', sanitize_textarea_field($item['featured_image_prompt']));
        if (!empty($item['featured_image_alt'])) update_post_meta($post_id, '_rankrepair_featured_image_alt', sanitize_text_field($item['featured_image_alt']));
        wp_send_json_success(['message' => 'Draft created with SEO metadata.', 'post_id' => $post_id, 'edit_link' => get_edit_post_link($post_id, 'raw')]);
    }

    public function internal_links() {
        $this->guard();
        $post_id = intval($this->post_value('post_id', 0));
        $limit = intval($this->post_value('limit', 12));
        if (!$post_id || !current_user_can('edit_post', $post_id)) wp_send_json_error(['message' => 'Invalid post ID.']);
        $result = RANKREPAIR_AI_AI::suggest_internal_links($post_id, $limit);
        if (is_wp_error($result)) wp_send_json_error(['message' => $result->get_error_message()]);
        wp_send_json_success(['item' => $result]);
    }

    public function image_seo() {
        $this->guard();
        $attachment_id = intval($this->post_value('attachment_id', 0));
        $context = sanitize_textarea_field($this->post_value('context', ''));
        if (!$attachment_id) wp_send_json_error(['message' => 'Invalid attachment ID.']);
        $result = RANKREPAIR_AI_AI::generate_image_seo($attachment_id, $context);
        if (is_wp_error($result)) wp_send_json_error(['message' => $result->get_error_message()]);
        wp_send_json_success(['item' => $result]);
    }


    public function bulk_image_seo() {
        $this->guard();
        $limit = max(1, min(100, intval($this->post_value('limit', 30))));
        $missing_alt_only = !empty($this->post_value('missing_alt_only', ''));
        $query = new WP_Query([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'post_mime_type' => 'image',
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids',
        ]);
        $items = [];
        foreach ($query->posts as $attachment_id) {
            if (!current_user_can('edit_post', $attachment_id)) continue;
            $alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
            $attachment = get_post($attachment_id);
            $filename = basename((string) get_attached_file($attachment_id));
            $is_generic = preg_match('/^(img|image|dsc|screenshot|whatsapp|photo|untitled)[-_ ]?\d*/i', $filename);
            if ($missing_alt_only && trim((string) $alt) !== '') continue;
            if (!$missing_alt_only && trim((string) $alt) !== '' && !$is_generic && trim((string) $attachment->post_excerpt) !== '') continue;
            $context = 'WordPress Media Library image. Current filename: ' . $filename . '. Current title: ' . $attachment->post_title;
            $result = RANKREPAIR_AI_AI::generate_image_seo($attachment_id, $context);
            if (is_wp_error($result)) {
                $items[] = ['attachment_id' => $attachment_id, 'error' => $result->get_error_message()];
                continue;
            }
            $items[] = [
                'attachment_id' => $attachment_id,
                'filename' => $filename,
                'edit_link' => get_edit_post_link($attachment_id, 'raw'),
                'before' => [
                    'alt_text' => $alt,
                    'title' => $attachment->post_title,
                    'caption' => $attachment->post_excerpt,
                    'description' => $attachment->post_content,
                ],
                'after' => $result,
            ];
        }
        wp_send_json_success(['items' => $items, 'inspected' => count($query->posts)]);
    }

    public function apply_image_seo() {
        $this->guard();
        $attachment_id = intval($this->post_value('attachment_id', 0));
        $item = $this->post_json('item', []);
        if (!$attachment_id || !current_user_can('edit_post', $attachment_id)) wp_send_json_error(['message' => 'Invalid attachment ID.']);
        if (!is_array($item)) wp_send_json_error(['message' => 'Missing image metadata.']);
        if (!empty($item['alt_text'])) update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($item['alt_text']));
        wp_update_post([
            'ID' => $attachment_id,
            'post_title' => sanitize_text_field($item['title'] ?? ''),
            'post_excerpt' => sanitize_text_field($item['caption'] ?? ''),
            'post_content' => sanitize_textarea_field($item['description'] ?? ''),
        ]);
        if (!empty($item['recommended_filename'])) update_post_meta($attachment_id, '_rankrepair_recommended_filename', sanitize_file_name($item['recommended_filename']));
        wp_send_json_success(['message' => 'Image SEO metadata applied.']);
    }



    public function divi_scan() {
        $this->guard();
        $result = RANKREPAIR_AI_Divi_Recovery::scan([
            'post_type' => sanitize_key($this->post_value('post_type', 'any')),
            'post_status' => sanitize_key($this->post_value('post_status', 'any')),
            'limit' => intval($this->post_value('limit', 100)),
            'offset' => intval($this->post_value('offset', 0)),
            'search' => sanitize_text_field($this->post_value('search', '')),
            'source_type' => sanitize_key($this->post_value('source_type', 'auto')),
        ]);
        wp_send_json_success($result);
    }

    public function divi_preview() {
        $this->guard();
        $post_id = intval($this->post_value('post_id', 0));
        if (!$post_id || !current_user_can('edit_post', $post_id)) wp_send_json_error(['message' => 'Invalid post ID.'], 403);
        $preview = RANKREPAIR_AI_Divi_Recovery::preview($post_id, sanitize_key($this->post_value('source_type', 'auto')));
        if (is_wp_error($preview)) wp_send_json_error(['message' => $preview->get_error_message()]);
        wp_send_json_success(['item' => $preview]);
    }

    public function divi_apply() {
        $this->guard();
        $post_id = intval($this->post_value('post_id', 0));
        $content = $this->post_value('gutenberg_content', '');
        if (!$post_id || !current_user_can('edit_post', $post_id)) wp_send_json_error(['message' => 'Invalid post ID.'], 403);
        if (!$content) wp_send_json_error(['message' => 'Missing Gutenberg content.']);
        $compat = $this->compatibility_guard_for_content_write($post_id);
        if (is_wp_error($compat)) wp_send_json_error(['message' => $compat->get_error_message()]);
        $result = RANKREPAIR_AI_Divi_Recovery::apply($post_id, $content);
        if (is_wp_error($result)) wp_send_json_error(['message' => $result->get_error_message()]);
        wp_send_json_success(['message' => 'Content recovered into Gutenberg blocks.', 'post_id' => $post_id, 'edit_link' => get_edit_post_link($post_id, 'raw')]);
    }


    public function content_audit() {
        $this->guard();
        $post_id = intval($this->post_value('post_id', 0));
        if (!$post_id || !current_user_can('edit_post', $post_id)) wp_send_json_error(['message' => 'Invalid post ID.'], 403);
        $result = RANKREPAIR_AI_AI::audit_post($post_id);
        if (is_wp_error($result)) wp_send_json_error(['message' => $result->get_error_message()]);
        wp_send_json_success(['item' => $result]);
    }

    public function rewrite_preview() {
        $this->guard();
        $post_id = intval($this->post_value('post_id', 0));
        $mode = sanitize_key($this->post_value('mode', 'seo_readability'));
        if (!$post_id || !current_user_can('edit_post', $post_id)) wp_send_json_error(['message' => 'Invalid post ID.'], 403);
        $result = RANKREPAIR_AI_AI::rewrite_post($post_id, $mode);
        if (is_wp_error($result)) wp_send_json_error(['message' => $result->get_error_message()]);
        wp_send_json_success(['item' => $result]);
    }

    public function rewrite_apply() {
        $this->guard();
        $post_id = intval($this->post_value('post_id', 0));
        $item = $this->post_json('item', []);
        if (!$post_id || !current_user_can('edit_post', $post_id)) wp_send_json_error(['message' => 'Invalid post ID.'], 403);
        if (!is_array($item) || empty($item['content_html'])) wp_send_json_error(['message' => 'Missing rewrite content.']);
        $compat = $this->compatibility_guard_for_content_write($post_id);
        if (is_wp_error($compat)) wp_send_json_error(['message' => $compat->get_error_message()]);
        $post = get_post($post_id);
        update_post_meta($post_id, '_rankrepair_backup_content_' . time(), $post ? $post->post_content : '');
        wp_update_post(['ID' => $post_id, 'post_content' => wp_kses_post($item['content_html'])]);
        if (!empty($item['excerpt'])) wp_update_post(['ID' => $post_id, 'post_excerpt' => sanitize_textarea_field($item['excerpt'])]);
        if (!empty($item['seo'])) RANKREPAIR_AI_SEO_Adapters::update($post_id, $item['seo']);
        clean_post_cache($post_id);
        wp_send_json_success(['message' => 'Rewrite applied and previous content backed up.', 'edit_link' => get_edit_post_link($post_id, 'raw')]);
    }

    public function schema_generate() {
        $this->guard();
        $post_id = intval($this->post_value('post_id', 0));
        $schema_type = sanitize_text_field($this->post_value('schema_type', 'auto'));
        if (!$post_id || !current_user_can('edit_post', $post_id)) wp_send_json_error(['message' => 'Invalid post ID.'], 403);
        $result = RANKREPAIR_AI_AI::generate_schema($post_id, $schema_type);
        if (is_wp_error($result)) wp_send_json_error(['message' => $result->get_error_message()]);
        wp_send_json_success(['item' => $result]);
    }

    private function sanitize_json_ld_recursive($value) {
        if (is_array($value)) {
            $clean = [];
            foreach ($value as $key => $item) {
                $clean_key = is_string($key) ? sanitize_text_field($key) : absint($key);
                $clean[$clean_key] = $this->sanitize_json_ld_recursive($item);
            }
            return $clean;
        }
        if (is_bool($value) || is_int($value) || is_float($value) || null === $value) {
            return $value;
        }
        return sanitize_text_field((string) $value);
    }

    public function schema_apply() {
        $this->guard();
        $post_id = intval($this->post_value('post_id', 0));
        $item = $this->post_json('item', []);
        if (!$post_id || !current_user_can('edit_post', $post_id)) wp_send_json_error(['message' => 'Invalid post ID.'], 403);
        if (!is_array($item) || empty($item['schema_json_ld'])) wp_send_json_error(['message' => 'Missing schema JSON-LD.']);

        $schema = $item['schema_json_ld'];
        if (is_string($schema)) {
            $schema = json_decode($schema, true);
        }
        if (!is_array($schema)) {
            wp_send_json_error(['message' => 'Invalid schema JSON-LD.']);
        }

        $schema = $this->sanitize_json_ld_recursive($schema);
        update_post_meta($post_id, '_rankrepair_schema_json_ld', wp_json_encode($schema));
        wp_send_json_success(['message' => 'Schema saved to RankRepair meta.']);
    }

    public function content_plan() {
        $this->guard();
        $args = [
            'topic' => sanitize_textarea_field($this->post_value('topic', '')),
            'audience' => sanitize_text_field($this->post_value('audience', '')),
            'timeframe' => sanitize_text_field($this->post_value('timeframe', '90 days')),
        ];
        $result = RANKREPAIR_AI_AI::content_plan($args);
        if (is_wp_error($result)) wp_send_json_error(['message' => $result->get_error_message()]);
        wp_send_json_success(['item' => $result]);
    }



    public function image_recovery_scan() {
        $this->guard();
        $result = RANKREPAIR_AI_Image_Recovery::scan([
            'post_type' => sanitize_key($this->post_value('post_type', 'any')),
            'post_status' => sanitize_key($this->post_value('post_status', 'any')),
            'limit' => intval($this->post_value('limit', 100)),
            'offset' => intval($this->post_value('offset', 0)),
            'mode' => sanitize_key($this->post_value('mode', 'all')),
        ]);
        wp_send_json_success($result);
    }

    public function image_recovery_suggest() {
        $this->guard();
        $post_id = intval($this->post_value('post_id', 0));
        $issue = $this->post_json('issue', []);
        if (!$post_id || !current_user_can('edit_post', $post_id)) wp_send_json_error(['message' => 'Invalid post ID.'], 403);
        $result = RANKREPAIR_AI_Image_Recovery::suggest($post_id, is_array($issue) ? $issue : []);
        if (is_wp_error($result)) wp_send_json_error(['message' => $result->get_error_message()]);
        wp_send_json_success(['item' => $result]);
    }

    public function image_recovery_apply() {
        $this->guard();
        $post_id = intval($this->post_value('post_id', 0));
        if (!$post_id || !current_user_can('edit_post', $post_id)) wp_send_json_error(['message' => 'Invalid post ID.'], 403);
        $result = RANKREPAIR_AI_Image_Recovery::apply_replacement($post_id, [
            'target' => sanitize_key($this->post_value('target', 'featured_image')),
            'attachment_id' => intval($this->post_value('attachment_id', 0)),
            'image_url' => esc_url_raw($this->post_value('image_url', '')),
            'alt_text' => sanitize_text_field($this->post_value('alt_text', '')),
        ]);
        if (is_wp_error($result)) wp_send_json_error(['message' => $result->get_error_message()]);
        wp_send_json_success($result);
    }

    public function editor_audit() {
        $this->content_audit();
    }

    public function editor_generate_schema() {
        // Default handled by post_value() in schema_generate().
        $this->schema_generate();
    }

    public function editor_rewrite_preview() {
        // Default handled by post_value() in rewrite_preview().
        $this->rewrite_preview();
    }

    public function editor_recovery_preview() {
        // Default handled by post_value() in divi_preview().
        $this->divi_preview();
    }

}
