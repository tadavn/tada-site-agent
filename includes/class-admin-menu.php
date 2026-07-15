<?php
/**
 * Site_Health_Agent_Admin_Menu — menu top-level RIÊNG của SHA.
 *
 * TADA là sản phẩm độc lập → KHÔNG gom vào menu cha chung "VIG Toolkit".
 * SHA có menu riêng trên sidebar (chỗ chứa dashboard, TADA SEO Score, Site Kit… về sau).
 *
 * @since 1.4.0
 */

defined('ABSPATH') || exit;

class Site_Health_Agent_Admin_Menu {

    public static function init(): void {
        add_action('admin_menu', [self::class, 'register']);
    }

    public static function register(): void {
        add_menu_page(
            'Site Health Agent',                                  // page title
            'Site Health Agent',                                  // menu label
            'manage_options',
            'site-health-agent',                                  // slug top-level (khớp do_settings_sections)
            ['Site_Health_Agent_Settings_Page', 'render_page'],   // landing tạm = trang settings/kết nối
            'dashicons-chart-area',                               // icon (đổi sang SVG logo TADA sau)
            58                                                    // vị trí (trên Settings)
        );
    }
}
