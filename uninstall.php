<?php
/**
 * Dọn dẹp khi gỡ plugin (WordPress tự chạy file này khi xóa qua Admin).
 *
 * @since 1.4.0 (thay register_uninstall_hook)
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

delete_option('site_health_agent_secret_key');
delete_option('site_health_agent_auth_log');

// Dọn transient (rate limit + replay).
global $wpdb;
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '_transient_site_health_agent_%'
        OR option_name LIKE '_transient_timeout_site_health_agent_%'"
);
