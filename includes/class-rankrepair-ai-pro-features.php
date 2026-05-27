<?php
if (!defined('ABSPATH')) exit;

/**
 * Professional safety and agency features for RankRepair AI.
 * These tools are intentionally conservative: they scan, preview, export, and log
 * instead of silently changing large parts of a site.
 */
class RANKREPAIR_AI_Pro_Features {
    const LOG_OPTION = 'rankrepair_ai_activity_log';
    const ADV_OPTION = 'rankrepair_ai_advanced_settings';
    const REDIRECT_OPTION = 'rankrepair_ai_redirect_rules';

    public static function init() {
        $self = new self();
        add_action('admin_menu', [$self, 'menu'], 80);
        add_action('wp_ajax_rankrepair_ai_export_backup', [$self, 'ajax_export_backup']);
        add_action('wp_ajax_rankrepair_ai_restore_snapshot', [$self, 'ajax_restore_snapshot']);
        add_action('wp_ajax_rankrepair_ai_link_scan', [$self, 'ajax_link_scan']);
        add_action('wp_ajax_rankrepair_ai_redirect_save', [$self, 'ajax_redirect_save']);
        add_action('wp_ajax_rankrepair_ai_media_scan', [$self, 'ajax_media_scan']);
        add_action('wp_ajax_rankrepair_ai_cost_estimate', [$self, 'ajax_cost_estimate']);
        add_action('wp_ajax_rankrepair_ai_save_advanced', [$self, 'ajax_save_advanced']);
        add_action('updated_post_meta', [$self, 'capture_seo_meta_update'], 10, 4);
        add_action('added_post_meta', [$self, 'capture_seo_meta_update'], 10, 4);
        add_action('init', [$self, 'register_wp_cli']);
        add_action('admin_enqueue_scripts', [$self, 'enqueue_assets']);
    }

    public static function defaults() {
        return [
            'allowed_roles' => ['administrator'],
            'multilingual_safe_mode' => 1,
            'protect_dynamic_builder_meta' => 1,
            'enable_activity_log' => 1,
            'production_warning' => 1,
            'max_log_items' => 500,
        ];
    }

    public static function settings() {
        return wp_parse_args(get_option(self::ADV_OPTION, []), self::defaults());
    }

    public static function can_manage() {
        if (current_user_can('manage_options')) return true;
        $settings = self::settings();
        $user = wp_get_current_user();
        foreach ((array) $settings['allowed_roles'] as $role) {
            if (in_array($role, (array) $user->roles, true)) return current_user_can('edit_posts');
        }
        return false;
    }

    private function guard() {
        check_ajax_referer('rankrepair_ai_nonce', 'nonce');
        if (!self::can_manage()) wp_send_json_error(['message' => 'Forbidden'], 403);
    }

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

    public function menu() {
        // v3.3.0: keep the WordPress sidebar simple. These tools are grouped inside
        // RankRepair AI > Scan & Repair and Settings instead of separate submenu pages.
    }

    private function nonce_data() {
        return ' data-nonce="' . esc_attr(wp_create_nonce('rankrepair_ai_nonce')) . '" data-ajax="' . esc_url(admin_url('admin-ajax.php')) . '"';
    }

    private function page_header($title, $description) {
        echo '<div class="wrap rankrepair-ai-pro-wrap"' . wp_kses_data($this->nonce_data()) . '>'; 
        echo '<h1>' . esc_html($title) . '</h1>';
        echo '<p class="description">' . esc_html($description) . '</p>';
        if ($this->is_production_like() && !empty(self::settings()['production_warning'])) {
            echo '<div class="notice notice-warning"><p><strong>RankRepair Safe Mode:</strong> For large AI/content/image actions, test on staging first and export a backup before applying changes.</p></div>';
        }
    }

    private function page_footer() {
        echo '</div>';
    }

    public function enqueue_assets() {
        if (!function_exists('get_current_screen')) {
            return;
        }
        $screen = get_current_screen();
        if (!$screen || false === strpos((string) $screen->id, 'rankrepair-ai')) {
            return;
        }
        wp_enqueue_style('rankrepair-pro-features', RANKREPAIR_AI_URL . 'assets/pro-features.css', [], RANKREPAIR_AI_VERSION);
        wp_enqueue_script('rankrepair-pro-features', RANKREPAIR_AI_URL . 'assets/pro-features.js', ['jquery'], RANKREPAIR_AI_VERSION, true);
    }

    public function page_backups() {
        $this->page_header('RankRepair Backup & Rollback Center', 'Export SEO/content/media snapshots and restore previous RankRepair changes safely.');
        ?>
        <div class="card"><h2>Backup export</h2><p>Create a JSON backup containing post titles, content, excerpts, SEO metadata, featured images, schema, and RankRepair settings for the selected range.</p>
            <p><label>Post type <select id="rr_backup_post_type"><option value="any">Any public post type</option><?php foreach (get_post_types(['public'=>true], 'objects') as $pt) echo '<option value="'.esc_attr($pt->name).'">'.esc_html($pt->label).'</option>'; ?></select></label>
            <label>Limit <input id="rr_backup_limit" type="number" min="1" max="1000" value="250"></label>
            <button class="button button-primary" id="rr_export_backup">Export backup JSON</button></p>
            <textarea id="rr_backup_output" class="large-text code" rows="8" readonly placeholder="Backup JSON will appear here. Copy and store it safely."></textarea>
        </div>
        <div class="card"><h2>Restore from snapshot</h2><p>Paste a RankRepair backup JSON object. Restore only on staging or after confirming the target site is the same site.</p>
            <textarea id="rr_restore_input" class="large-text code" rows="8" placeholder='{"items":[...]}'></textarea>
            <p><label><input type="checkbox" id="rr_restore_content"> Restore content</label> <label><input type="checkbox" id="rr_restore_seo" checked> Restore SEO metadata</label> <label><input type="checkbox" id="rr_restore_images"> Restore featured images</label></p>
            <button class="button" id="rr_restore_snapshot">Restore selected data</button>
            <div id="rr_restore_status" class="rr-status"></div>
        </div>
        <?php $this->page_footer();
    }

    public function page_links() {
        $this->page_header('Links & Redirects', 'Find old-domain/broken links and store redirect suggestions for migration cleanup.');
        ?>
        <div class="card"><h2>Broken/old-domain link scan</h2><p>This scanner checks stored content for old domains, malformed URLs, missing internal posts, empty href attributes, and obvious broken media paths without making external HTTP requests.</p>
            <p><label>Post type <select id="rr_link_post_type"><option value="any">Any public post type</option><?php foreach (get_post_types(['public'=>true], 'objects') as $pt) echo '<option value="'.esc_attr($pt->name).'">'.esc_html($pt->label).'</option>'; ?></select></label>
            <label>Limit <input id="rr_link_limit" type="number" min="1" max="1000" value="250"></label>
            <label>Old domain/URL <input id="rr_old_domain" type="text" value="<?php echo esc_attr(RANKREPAIR_AI_Plugin::settings()['old_domain'] ?? ''); ?>" placeholder="oldsite.com"></label>
            <button class="button button-primary" id="rr_link_scan">Scan links</button></p>
            <div id="rr_link_results"></div>
        </div>
        <div class="card"><h2>Redirect helper</h2><p>Store redirect recommendations created during cleanup. RankRepair does not force front-end redirects yet; export these into your preferred redirects plugin or server config.</p>
            <p><input id="rr_redirect_from" type="text" class="regular-text" placeholder="/old-url/"> → <input id="rr_redirect_to" type="text" class="regular-text" placeholder="/new-url/"><button class="button" id="rr_redirect_save">Save recommendation</button></p>
            <pre class="rr-pre"><?php echo esc_html(wp_json_encode(get_option(self::REDIRECT_OPTION, []), JSON_PRETTY_PRINT)); ?></pre>
        </div>
        <?php $this->page_footer();
    }

    public function page_media() {
        $this->page_header('Media Optimizer', 'Find images that need alt text, captions, filename cleanup, featured image repair, or replacement.');
        ?>
        <div class="card"><h2>Media library audit</h2><p>Scan media attachments for missing SEO/accessibility fields and migration issues.</p>
            <p><label>Limit <input id="rr_media_limit" type="number" min="1" max="1000" value="300"></label>
            <label>Old domain/URL <input id="rr_media_old_domain" type="text" value="<?php echo esc_attr(RANKREPAIR_AI_Plugin::settings()['old_domain'] ?? ''); ?>" placeholder="oldsite.com"></label>
            <button class="button button-primary" id="rr_media_scan">Scan media</button></p>
            <div id="rr_media_results"></div>
        </div>
        <?php $this->page_footer();
    }

    public function page_safety() {
        $settings = self::settings();
        $this->page_header('Cost & Safety Center', 'Estimate AI usage and configure safe behavior for complex WordPress sites.');
        ?>
        <div class="card"><h2>AI cost/token estimator</h2><p>Estimate token usage before running large batches. This is approximate, but it helps avoid surprises and rate-limit spikes.</p>
            <p><label>Posts <input id="rr_est_posts" type="number" min="1" value="500"></label> <label>Avg content chars <input id="rr_est_chars" type="number" min="500" value="3500"></label> <label>Output chars <input id="rr_est_output" type="number" min="300" value="900"></label> <button class="button" id="rr_cost_estimate">Estimate</button></p>
            <div id="rr_cost_results" class="rr-status"></div>
        </div>
        <div class="card"><h2>Compatibility safeguards</h2>
            <p><label><input type="checkbox" id="rr_multilingual_safe" <?php checked(!empty($settings['multilingual_safe_mode'])); ?>> Multilingual safe mode for WPML/Polylang</label></p>
            <p><label><input type="checkbox" id="rr_protect_builder" <?php checked(!empty($settings['protect_dynamic_builder_meta'])); ?>> Protect dynamic builder metadata from Crocoblock/Elementor/JetEngine-heavy sites</label></p>
            <p><label><input type="checkbox" id="rr_activity_log" <?php checked(!empty($settings['enable_activity_log'])); ?>> Enable activity log</label></p>
            <p><label><input type="checkbox" id="rr_production_warning" <?php checked(!empty($settings['production_warning'])); ?>> Show staging/safe-mode warnings</label></p>
            <p><label>Max log items <input id="rr_max_log_items" type="number" min="50" max="5000" value="<?php echo esc_attr($settings['max_log_items']); ?>"></label></p>
            <button class="button button-primary" id="rr_save_advanced">Save safeguards</button>
            <div class="rr-status"><?php echo esc_html($this->environment_summary()); ?></div>
        </div>
        <?php $this->page_footer();
    }

    public function page_log() {
        $this->page_header('Activity Log', 'Review RankRepair-related changes and safety events.');
        $log = get_option(self::LOG_OPTION, []);
        echo '<div class="card"><h2>Recent activity</h2><table class="widefat striped"><thead><tr><th>Time</th><th>User</th><th>Action</th><th>Post</th><th>Details</th></tr></thead><tbody>';
        if (!$log) echo '<tr><td colspan="5">No activity recorded yet.</td></tr>';
        foreach (array_reverse(array_slice((array) $log, -250)) as $row) {
            echo '<tr><td>'.esc_html($row['time'] ?? '').'</td><td>'.esc_html($row['user'] ?? '').'</td><td>'.esc_html($row['action'] ?? '').'</td><td>'.esc_html($row['post_id'] ?? '').'</td><td><code>'.esc_html(wp_json_encode($row['details'] ?? [])).'</code></td></tr>';
        }
        echo '</tbody></table></div>';
        $this->page_footer();
    }

    public function ajax_export_backup() {
        $this->guard();
        $post_type = sanitize_key($this->post_value('post_type', 'any'));
        $limit = max(1, min(1000, intval($this->post_value('limit', 250))));
        $args = ['post_type' => $post_type === 'any' ? get_post_types(['public'=>true], 'names') : $post_type, 'post_status' => 'any', 'posts_per_page' => $limit, 'orderby' => 'ID', 'order' => 'ASC'];
        $posts = get_posts($args);
        $items = [];
        foreach ($posts as $post) {
            $items[] = [
                'id' => $post->ID,
                'type' => $post->post_type,
                'title' => get_the_title($post),
                'content' => $post->post_content,
                'excerpt' => $post->post_excerpt,
                'seo' => RANKREPAIR_AI_SEO_Adapters::get_values($post->ID),
                'featured_image_id' => get_post_thumbnail_id($post->ID),
                'schema' => get_post_meta($post->ID, '_rankrepair_schema_json_ld', true),
            ];
        }
        self::log('backup_exported', 0, ['count' => count($items)]);
        wp_send_json_success(['backup' => ['created_at' => current_time('mysql'), 'site' => home_url(), 'plugin' => 'RankRepair AI', 'version' => RANKREPAIR_AI_VERSION, 'items' => $items, 'settings' => RANKREPAIR_AI_Plugin::settings()]]);
    }

    public function ajax_restore_snapshot() {
        $this->guard();
        $backup = json_decode($this->post_value('backup', ''), true);
        if (!is_array($backup) || empty($backup['items'])) wp_send_json_error(['message' => 'Invalid RankRepair backup JSON.']);
        $restore_content = !empty($this->post_value('restore_content', ''));
        $restore_seo = !empty($this->post_value('restore_seo', ''));
        $restore_images = !empty($this->post_value('restore_images', ''));
        $count = 0;
        foreach ((array) $backup['items'] as $item) {
            $post_id = intval($item['id'] ?? 0);
            if (!$post_id || !current_user_can('edit_post', $post_id) || !get_post($post_id)) continue;
            if ($restore_content) wp_update_post(['ID' => $post_id, 'post_content' => wp_kses_post($item['content'] ?? ''), 'post_excerpt' => sanitize_textarea_field($item['excerpt'] ?? '')]);
            if ($restore_seo && !empty($item['seo']) && is_array($item['seo'])) RANKREPAIR_AI_SEO_Adapters::update($post_id, $item['seo']);
            if ($restore_images && !empty($item['featured_image_id'])) set_post_thumbnail($post_id, intval($item['featured_image_id']));
            if (!empty($item['schema'])) update_post_meta($post_id, '_rankrepair_schema_json_ld', wp_kses_post($item['schema']));
            clean_post_cache($post_id);
            self::log('snapshot_restored', $post_id, ['content'=>$restore_content, 'seo'=>$restore_seo, 'images'=>$restore_images]);
            $count++;
        }
        wp_send_json_success(['message' => sprintf('Restored %d posts from snapshot.', $count)]);
    }

    public function ajax_link_scan() {
        $this->guard();
        $post_type = sanitize_key($this->post_value('post_type', 'any'));
        $limit = max(1, min(1000, intval($this->post_value('limit', 250))));
        $old_domain = trim(sanitize_text_field($this->post_value('old_domain', '')));
        $posts = get_posts(['post_type' => $post_type === 'any' ? get_post_types(['public'=>true], 'names') : $post_type, 'post_status' => 'any', 'posts_per_page' => $limit]);
        $rows = [];
        foreach ($posts as $post) {
            $issues = self::scan_content_links($post->post_content, $old_domain);
            if ($issues) $rows[] = ['id'=>$post->ID, 'title'=>get_the_title($post), 'edit'=>get_edit_post_link($post->ID, 'raw'), 'issues'=>$issues];
        }
        wp_send_json_success(['items' => $rows, 'count' => count($rows)]);
    }

    public function ajax_redirect_save() {
        $this->guard();
        $from = sanitize_text_field($this->post_value('from', ''));
        $to = sanitize_text_field($this->post_value('to', ''));
        if (!$from || !$to) wp_send_json_error(['message'=>'Both source and destination are required.']);
        $rules = get_option(self::REDIRECT_OPTION, []);
        $rules[] = ['from'=>$from, 'to'=>$to, 'status'=>301, 'created_at'=>current_time('mysql')];
        update_option(self::REDIRECT_OPTION, $rules, false);
        self::log('redirect_recommendation_saved', 0, ['from'=>$from, 'to'=>$to]);
        wp_send_json_success(['message'=>'Redirect recommendation saved.', 'rules'=>$rules]);
    }

    public function ajax_media_scan() {
        $this->guard();
        $limit = max(1, min(1000, intval($this->post_value('limit', 300))));
        $old_domain = trim(sanitize_text_field($this->post_value('old_domain', '')));
        $attachments = get_posts(['post_type'=>'attachment','post_mime_type'=>'image','post_status'=>'inherit','posts_per_page'=>$limit]);
        $items = [];
        foreach ($attachments as $att) {
            $url = wp_get_attachment_url($att->ID);
            $alt = get_post_meta($att->ID, '_wp_attachment_image_alt', true);
            $issues = [];
            if (!$alt) $issues[] = 'Missing alt text';
            if (!$att->post_excerpt) $issues[] = 'Missing caption';
            if (!$att->post_content) $issues[] = 'Missing description';
            if ($old_domain && $url && stripos($url, $old_domain) !== false) $issues[] = 'URL references old domain';
            if ($url && preg_match('/\s|%20|image\d+|untitled|screenshot/i', basename(wp_parse_url($url, PHP_URL_PATH)))) $issues[] = 'Filename could be more descriptive';
            if ($issues) $items[] = ['id'=>$att->ID,'title'=>$att->post_title,'url'=>$url,'thumb'=>wp_get_attachment_image_url($att->ID, 'thumbnail'),'alt'=>$alt,'issues'=>$issues,'edit'=>get_edit_post_link($att->ID, 'raw')];
        }
        wp_send_json_success(['items'=>$items, 'count'=>count($items)]);
    }

    public function ajax_cost_estimate() {
        $this->guard();
        $posts = max(1, intval($this->post_value('posts', 1)));
        $chars = max(1, intval($this->post_value('chars', 3500)));
        $output = max(1, intval($this->post_value('output', 900)));
        $input_tokens = (int) ceil(($posts * $chars) / 4);
        $output_tokens = (int) ceil(($posts * $output) / 4);
        $total = $input_tokens + $output_tokens;
        wp_send_json_success(['input_tokens'=>$input_tokens, 'output_tokens'=>$output_tokens, 'total_tokens'=>$total, 'note'=>'Approximate only. Real cost depends on provider/model pricing and prompt length.']);
    }

    public function ajax_save_advanced() {
        $this->guard();
        $settings = self::settings();
        $settings['multilingual_safe_mode'] = !empty($this->post_value('multilingual_safe_mode', '')) ? 1 : 0;
        $settings['protect_dynamic_builder_meta'] = !empty($this->post_value('protect_dynamic_builder_meta', '')) ? 1 : 0;
        $settings['enable_activity_log'] = !empty($this->post_value('enable_activity_log', '')) ? 1 : 0;
        $settings['production_warning'] = !empty($this->post_value('production_warning', '')) ? 1 : 0;
        $settings['max_log_items'] = max(50, min(5000, intval($this->post_value('max_log_items', 500))));
        update_option(self::ADV_OPTION, $settings, false);
        wp_send_json_success(['message'=>'Advanced safeguards saved.']);
    }

    public function capture_seo_meta_update($meta_id, $object_id, $meta_key, $meta_value) {
        $settings = self::settings();
        if (empty($settings['enable_activity_log'])) return;
        if (!is_string($meta_key)) return;
        $interesting = ['_yoast_wpseo_title','_yoast_wpseo_metadesc','_yoast_wpseo_focuskw','rank_math_title','rank_math_description','rank_math_focus_keyword','_seopress_titles_title','_seopress_titles_desc','_aioseo_title','_aioseo_description','_rankrepair_schema_json_ld'];
        if (!in_array($meta_key, $interesting, true) && strpos($meta_key, 'rankrepair') === false) return;
        self::log('seo_meta_updated', intval($object_id), ['key'=>$meta_key]);
    }

    public static function log($action, $post_id = 0, $details = []) {
        $settings = self::settings();
        if (empty($settings['enable_activity_log'])) return;
        $user = wp_get_current_user();
        $log = get_option(self::LOG_OPTION, []);
        $log[] = ['time'=>current_time('mysql'), 'user'=>$user && $user->exists() ? $user->user_login : 'system', 'action'=>sanitize_key($action), 'post_id'=>intval($post_id), 'details'=>$details];
        $max = max(50, intval($settings['max_log_items']));
        if (count($log) > $max) $log = array_slice($log, -$max);
        update_option(self::LOG_OPTION, $log, false);
    }

    public static function scan_content_links($content, $old_domain = '') {
        $issues = [];
        if ($old_domain && stripos($content, $old_domain) !== false) $issues[] = 'Contains old domain: ' . $old_domain;
        if (preg_match_all('/href=["\']([^"\']*)["\']/i', (string) $content, $m)) {
            foreach ($m[1] as $href) {
                if ($href === '' || $href === '#') $issues[] = 'Empty or placeholder link';
                if (stripos($href, home_url()) === 0) {
                    $path = wp_parse_url($href, PHP_URL_PATH);
                    if ($path && !url_to_postid($href) && !preg_match('/\.(jpg|jpeg|png|gif|webp|pdf|zip)$/i', $path)) $issues[] = 'Internal URL may not resolve: ' . esc_url_raw($href);
                }
            }
        }
        if (preg_match_all('/src=["\']([^"\']*)["\']/i', (string) $content, $m2)) {
            foreach ($m2[1] as $src) {
                if ($src === '' || stripos($src, 'missing') !== false) $issues[] = 'Possible broken media source';
                if ($old_domain && stripos($src, $old_domain) !== false) $issues[] = 'Image source uses old domain';
            }
        }
        return array_values(array_unique($issues));
    }

    private function environment_summary() {
        $parts = [];
        if (defined('ICL_SITEPRESS_VERSION')) $parts[] = 'WPML detected';
        if (defined('POLYLANG_VERSION')) $parts[] = 'Polylang detected';
        if (defined('ELEMENTOR_VERSION')) $parts[] = 'Elementor detected';
        if (defined('JET_ENGINE_VERSION') || defined('JET_SM_VERSION') || defined('JET_WOO_BUILDER_VERSION')) $parts[] = 'Crocoblock/Jet plugin detected';
        if (!$parts) $parts[] = 'No major multilingual/builder constants detected at load time.';
        return implode(' · ', $parts);
    }

    private function is_production_like() {
        if (function_exists('wp_get_environment_type')) return wp_get_environment_type() === 'production';
        return true;
    }

    public function register_wp_cli() {
        if (!defined('WP_CLI') || !WP_CLI) return;
        WP_CLI::add_command('rankrepair backup', function($args, $assoc_args){ WP_CLI::success('Use the admin Backup Center for JSON exports in this version.'); });
        WP_CLI::add_command('rankrepair scan-links', function($args, $assoc_args){ WP_CLI::success('RankRepair link scanning is available in wp-admin > RankRepair AI > Links & Redirects.'); });
        WP_CLI::add_command('rankrepair media-audit', function($args, $assoc_args){ WP_CLI::success('RankRepair media audit is available in wp-admin > RankRepair AI > Media Optimizer.'); });
    }

}
