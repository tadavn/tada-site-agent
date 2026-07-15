<?php
/**
 * Tada_Site_Agent_Admin_Menu — VỎ (shell) admin: menu top-level riêng + tab nav.
 *
 * TADA độc lập → KHÔNG gom vào "VIG Toolkit". Mọi tính năng lắp vào cùng 1 vỏ
 * (tab) để không chắp vá: thêm tính năng = thêm 1 tab + card cùng khuôn.
 *
 * @since 1.4.0 (menu) · 1.5.0 (shell + tab + design system)
 */

defined('ABSPATH') || exit;

class Tada_Site_Agent_Admin_Menu {

    const SLUG = 'tada-site-agent';

    public static function init(): void {
        add_action('admin_menu', [self::class, 'register']);
        add_action('admin_enqueue_scripts', [self::class, 'assets']);
    }

    public static function register(): void {
        add_menu_page(
            'TADA Site Agent',
            'TADA Site Agent',
            'manage_options',
            self::SLUG,
            [self::class, 'render'],
            'dashicons-chart-area',
            58
        );
    }

    public static function assets(string $hook): void {
        if ($hook !== 'toplevel_page_' . self::SLUG) {
            return;
        }
        wp_enqueue_style('tada-site-agent-admin', TADA_SITE_AGENT_URL . 'assets/admin.css', [], TADA_SITE_AGENT_VERSION);
        wp_enqueue_script('tada-site-agent-admin', TADA_SITE_AGENT_URL . 'assets/admin.js', [], TADA_SITE_AGENT_VERSION, true);
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        $t    = ['Tada_Site_Agent_I18n', 't'];
        $tabs = [
            'overview'   => call_user_func($t, 'tab_overview'),
            'seo'        => call_user_func($t, 'tab_seo'),
            'connection' => call_user_func($t, 'tab_connection'),
            'settings'   => call_user_func($t, 'tab_settings'),
        ];
        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'overview';
        if (!isset($tabs[$tab])) {
            $tab = 'overview';
        }

        echo '<div class="wrap tsa">';
        self::header();
        self::nav($tab, $tabs);

        switch ($tab) {
            case 'settings':
                Tada_Site_Agent_Settings_Page::render_tab();
                break;
            case 'seo':
                self::stub(call_user_func($t, 'tab_seo'));
                break;
            case 'connection':
                self::stub(call_user_func($t, 'tab_connection'));
                break;
            default:
                self::render_overview();
        }
        echo '</div>';
    }

    private static function header(): void {
        $connected = !empty(get_option('tada_site_agent_secret_key', ''));
        $cls   = $connected ? 'ok' : 'off';
        $icon  = $connected ? 'dashicons-yes-alt' : 'dashicons-warning';
        $label = Tada_Site_Agent_I18n::t($connected ? 'connected' : 'not_connected');
        echo '<div class="tsa-head">';
        echo '<div class="tsa-logo">T</div>';
        echo '<h1 class="tsa-title">TADA Site Agent</h1>';
        echo '<span class="tsa-conn ' . esc_attr($cls) . '"><span class="dashicons ' . esc_attr($icon) . '" style="font-size:15px;width:15px;height:15px;"></span>' . esc_html($label) . '</span>';
        echo '</div>';
    }

    private static function nav(string $current, array $tabs): void {
        echo '<nav class="tsa-tabs">';
        foreach ($tabs as $slug => $label) {
            $url = add_query_arg(['page' => self::SLUG, 'tab' => $slug], admin_url('admin.php'));
            $cls = $slug === $current ? 'tsa-tab active' : 'tsa-tab';
            echo '<a class="' . esc_attr($cls) . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
        }
        echo '</nav>';
    }

    /** Tổng quan — trạng thái THẬT + empty-state cho phần chưa có dữ liệu chấm. */
    private static function render_overview(): void {
        $t = ['Tada_Site_Agent_I18n', 't'];
        $rm_active = function_exists('is_plugin_active')
            ? is_plugin_active('seo-by-rank-math/rank-math.php')
            : defined('RANK_MATH_VERSION');

        echo '<p class="tsa-sec-label">' . esc_html(call_user_func($t, 'health_label')) . '</p>';

        echo '<div class="tsa-cards">';
        echo '<div class="tsa-card tsa-hero">';
        echo '<div class="tsa-score-ring" style="background:var(--tsa-bg-soft);color:var(--tsa-faint);">—</div>';
        echo '<div><div style="font-size:13px;color:var(--tsa-muted);">' . esc_html(call_user_func($t, 'avg_score')) . '</div>';
        echo '<div style="font-size:12.5px;color:var(--tsa-faint);margin-top:2px;">' . esc_html(call_user_func($t, 'coming_soon')) . '</div></div></div>';

        echo '<div class="tsa-tile"><div class="k">' . esc_html(call_user_func($t, 'pages_scored')) . '</div><div class="v">—</div></div>';
        echo '<div class="tsa-tile"><div class="k">' . esc_html(call_user_func($t, 'critical_issues')) . '</div><div class="v">—</div></div>';

        echo '<div class="tsa-tile"><div class="k">' . esc_html(call_user_func($t, 'rank_math')) . '</div>';
        $rm_txt = call_user_func($t, $rm_active ? 'rm_active' : 'rm_inactive');
        $rm_col = $rm_active ? 'var(--tsa-green)' : 'var(--tsa-faint)';
        echo '<div class="v" style="font-size:15px;color:' . esc_attr($rm_col) . ';margin-top:6px;">' . esc_html($rm_txt) . '</div></div>';
        echo '</div>';

        echo '<div class="tsa-stub">' . esc_html(call_user_func($t, 'top_pages')) . ' — ' . esc_html(call_user_func($t, 'coming_soon')) . '</div>';

        echo '<p class="tsa-source">' . esc_html(call_user_func($t, 'source')) . ': TADA SEO Score (Edge) · Rank Math</p>';
    }

    private static function stub(string $title): void {
        echo '<div class="tsa-stub"><span class="dashicons dashicons-hammer" style="font-size:22px;width:22px;height:22px;opacity:.6;"></span><br>'
            . esc_html($title) . ' — ' . esc_html(Tada_Site_Agent_I18n::t('coming_soon')) . '</div>';
    }
}
