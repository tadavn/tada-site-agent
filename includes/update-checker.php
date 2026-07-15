<?php
/**
 * Self-update qua GitHub Releases + Plugin Update Checker (PUC).
 * PUC vendor qua composer → nạp bằng `vendor/autoload.php` (fallback thư mục standalone).
 * Repo: https://github.com/tadavn/tada-site-agent (PUBLIC → không cần token).
 * No-op nếu chưa vendor PUC (không fatal).
 *
 * @since 1.4.0
 */

defined('ABSPATH') || exit;

if (!function_exists('tada_site_agent_setup_updates')) {
    function tada_site_agent_setup_updates(string $main_file, string $slug): void {
        $dir = plugin_dir_path($main_file);

        // Nạp PUC: ưu tiên composer vendor, fallback thư mục standalone.
        if (file_exists($dir . 'vendor/autoload.php')) {
            require_once $dir . 'vendor/autoload.php';
        } elseif (file_exists($dir . 'plugin-update-checker/plugin-update-checker.php')) {
            require_once $dir . 'plugin-update-checker/plugin-update-checker.php';
        }

        if (!class_exists('\YahnisElsts\PluginUpdateChecker\v5\PucFactory')) {
            return; // chưa vendor PUC -> bỏ qua, không fatal
        }

        $checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            'https://github.com/tadavn/' . $slug . '/',
            $main_file,
            $slug
        );
        $checker->setBranch('main');
        $checker->getVcsApi()->enableReleaseAssets(); // dùng .zip đính kèm Release

        // Repo PRIVATE mới cần token (đặt trong wp-config mỗi site). PUBLIC bỏ qua.
        if (defined('TADA_SITE_AGENT_GH_TOKEN') && TADA_SITE_AGENT_GH_TOKEN) {
            $checker->setAuthentication(TADA_SITE_AGENT_GH_TOKEN);
        }
    }
}
