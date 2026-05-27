<?php
/**
 * Plugin Name: RankRepair AI
 * Description: Scan, preview, bulk edit, AI repair, backup, and rollback SEO metadata across popular WordPress SEO plugins.
 * Version: 3.4.8
 * Author: Daniel Rosa
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * Text Domain: rankrepair-ai
 */

if (!defined('ABSPATH')) exit;

define('RANKREPAIR_AI_VERSION', '3.4.8');
define('RANKREPAIR_AI_PATH', plugin_dir_path(__FILE__));
define('RANKREPAIR_AI_URL', plugin_dir_url(__FILE__));

require_once RANKREPAIR_AI_PATH . 'includes/class-rankrepair-ai-plugin.php';
require_once RANKREPAIR_AI_PATH . 'includes/class-rankrepair-ai-scanner.php';
require_once RANKREPAIR_AI_PATH . 'includes/class-rankrepair-ai-ai.php';
require_once RANKREPAIR_AI_PATH . 'includes/class-rankrepair-ai-seo-adapters.php';
require_once RANKREPAIR_AI_PATH . 'includes/class-rankrepair-ai-divi-recovery.php';
require_once RANKREPAIR_AI_PATH . 'includes/class-rankrepair-ai-image-recovery.php';
require_once RANKREPAIR_AI_PATH . 'includes/class-rankrepair-ai-pro-features.php';
require_once RANKREPAIR_AI_PATH . 'includes/class-rankrepair-ai-admin.php';

register_activation_hook(__FILE__, ['RANKREPAIR_AI_Plugin', 'activate']);
add_action('plugins_loaded', ['RANKREPAIR_AI_Plugin', 'init']);
add_action('plugins_loaded', ['RANKREPAIR_AI_Pro_Features', 'init'], 12);
