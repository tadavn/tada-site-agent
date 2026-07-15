<?php
/**
 * Dọn dẹp khi gỡ plugin (WordPress tự chạy file này khi xóa qua Admin).
 *
 * @since 1.4.0 (thay register_uninstall_hook)
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

delete_option('tada_site_agent_secret_key');
delete_option('tada_site_agent_auth_log');

// Dọn transient (rate limit + replay).
global $wpdb;
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '_transient_tada_site_agent_%'
        OR option_name LIKE '_transient_timeout_tada_site_agent_%'"
);
