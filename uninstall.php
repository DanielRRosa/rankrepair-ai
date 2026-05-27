<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove RankRepair options.
delete_option('rankrepair_ai_settings');
delete_option('rankrepair_ai_activity_log');
delete_option('rankrepair_ai_advanced_settings');
delete_option('rankrepair_ai_redirect_rules');

// Remove RankRepair-owned post meta. This intentionally does not remove SEO
// plugin metadata created by Yoast/Rank Math/etc., because those fields belong
// to the user's SEO plugin and may be valuable after RankRepair is removed.
foreach ([
    '_rankrepair_ai_backup',
    '_rankrepair_ai_last_backup',
    '_rankrepair_ai_last_updated',
    '_rankrepair_ai_last_provider',
    '_rankrepair_schema_json_ld',
    '_rankrepair_recovered_content_backup',
    '_rankrepair_featured_image_backup',
] as $rankrepair_ai_meta_key) {
    delete_post_meta_by_key($rankrepair_ai_meta_key);
}

// Site-specific transients are left to WordPress expiration cleanup to avoid
// direct database queries in the uninstall routine.
