<?php
/**
 * Tada_Site_Agent_Plugin — singleton bootstrap.
 * Giữ main file mỏng: nạp component + đăng ký hook ở đây.
 *
 * @since 1.4.0
 */

defined('ABSPATH') || exit;

final class Tada_Site_Agent_Plugin {

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
        $inc = TADA_SITE_AGENT_PATH . 'includes/';
        require_once $inc . 'class-i18n.php';
        require_once $inc . 'class-rankmath-reader.php';
        require_once $inc . 'class-seo-scorer.php';
        require_once $inc . 'class-security.php';
        require_once $inc . 'class-rest-controller.php';
        require_once $inc . 'class-admin-menu.php';
        require_once $inc . 'class-settings-page.php';
        require_once $inc . 'update-checker.php';
    }

    /** Đăng ký hook của từng thành phần. */
    private function boot(): void {
        Tada_Site_Agent_Rest_Controller::init();
        Tada_Site_Agent_Settings_Page::init();
        Tada_Site_Agent_Admin_Menu::init();

        // Self-update (no-op nếu chưa vendor PUC — không fatal).
        if (function_exists('tada_site_agent_setup_updates')) {
            tada_site_agent_setup_updates(TADA_SITE_AGENT_FILE, 'tada-site-agent');
        }
    }
}
