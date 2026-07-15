<?php
/**
 * Site_Health_Agent_Plugin — singleton bootstrap.
 * Giữ main file mỏng: nạp component + đăng ký hook ở đây.
 *
 * @since 1.4.0
 */

defined('ABSPATH') || exit;

final class Site_Health_Agent_Plugin {

    private static ?self $instance = null;

    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load();
        $this->boot();
    }

    /** Nạp các lớp thành phần. */
    private function load(): void {
        $inc = SITE_HEALTH_AGENT_PATH . 'includes/';
        require_once $inc . 'class-rankmath-reader.php';
        require_once $inc . 'class-security.php';
        require_once $inc . 'class-rest-controller.php';
        require_once $inc . 'class-admin-menu.php';
        require_once $inc . 'class-settings-page.php';
        require_once $inc . 'update-checker.php';
    }

    /** Đăng ký hook của từng thành phần. */
    private function boot(): void {
        Site_Health_Agent_Rest_Controller::init();
        Site_Health_Agent_Settings_Page::init();
        Site_Health_Agent_Admin_Menu::init();

        // Self-update (no-op nếu chưa vendor PUC — không fatal).
        if (function_exists('site_health_agent_setup_updates')) {
            site_health_agent_setup_updates(SITE_HEALTH_AGENT_FILE, 'site-health-agent');
        }
    }
}
