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
        wp_localize_script('tada-site-agent-admin', 'TSA', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce(Tada_Site_Agent_Ajax::NONCE),
            'i18n'    => [
                'scanning'  => Tada_Site_Agent_I18n::t('scanning'),
                'scan_all'  => Tada_Site_Agent_I18n::t('scan_all'),
                'scan_done' => Tada_Site_Agent_I18n::t('scan_done'),
            ],
        ]);
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
                self::render_seo();
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

    /** Tổng quan — dữ liệu THẬT từ điểm đã chấm + trạng thái. */
    private static function render_overview(): void {
        $t = ['Tada_Site_Agent_I18n', 't'];
        $rm_active = function_exists('is_plugin_active')
            ? is_plugin_active('seo-by-rank-math/rank-math.php')
            : defined('RANK_MATH_VERSION');
        $s   = Tada_Site_Agent_Seo_Scorer::stats();
        $avg = $s['avg'];

        echo '<p class="tsa-sec-label">' . esc_html(call_user_func($t, 'health_label')) . '</p>';

        echo '<div class="tsa-cards">';
        echo '<div class="tsa-card tsa-hero">';
        if ($avg === null) {
            echo '<div class="tsa-score-ring" style="background:var(--tsa-bg-soft);color:var(--tsa-faint);">—</div>';
        } else {
            echo '<div class="tsa-score-ring ' . esc_attr(self::score_class((int) $avg)) . '">' . (int) $avg . '</div>';
        }
        echo '<div><div style="font-size:13px;color:var(--tsa-muted);">' . esc_html(call_user_func($t, 'avg_score')) . '</div>';
        echo '<div style="font-size:12.5px;color:var(--tsa-faint);margin-top:2px;">' . ($avg === null ? esc_html(call_user_func($t, 'coming_soon')) : esc_html($s['scored'] . '/' . $s['total'])) . '</div></div></div>';

        echo '<div class="tsa-tile"><div class="k">' . esc_html(call_user_func($t, 'pages_scored')) . '</div><div class="v">' . (int) $s['scored'] . '<small> / ' . (int) $s['total'] . '</small></div></div>';
        echo '<div class="tsa-tile"><div class="k">' . esc_html(call_user_func($t, 'critical_issues')) . '</div><div class="v"' . ($s['critical'] > 0 ? ' style="color:var(--tsa-red);"' : '') . '>' . (int) $s['critical'] . '</div></div>';

        echo '<div class="tsa-tile"><div class="k">' . esc_html(call_user_func($t, 'rank_math')) . '</div>';
        $rm_txt = call_user_func($t, $rm_active ? 'rm_active' : 'rm_inactive');
        $rm_col = $rm_active ? 'var(--tsa-green)' : 'var(--tsa-faint)';
        echo '<div class="v" style="font-size:15px;color:' . esc_attr($rm_col) . ';margin-top:6px;">' . esc_html($rm_txt) . '</div></div>';
        echo '</div>';

        echo '<div class="tsa-sec-head"><h2>' . esc_html(call_user_func($t, 'top_pages')) . '</h2><span class="tsa-sec-note">' . esc_html(call_user_func($t, 'sorted_by')) . '</span></div>';
        $rows = Tada_Site_Agent_Seo_Scorer::scored_pages(5);
        if (empty($rows)) {
            echo '<div class="tsa-stub">' . esc_html(call_user_func($t, 'no_scored')) . '</div>';
        } else {
            self::pages_table($rows, false);
        }
        echo '<p class="tsa-source">' . esc_html(call_user_func($t, 'source')) . ': TADA SEO Score (Edge) · Rank Math</p>';
    }

    /** Tab Điểm SEO — bảng per-page thật + nút chấm tất cả. */
    private static function render_seo(): void {
        $t = ['Tada_Site_Agent_I18n', 't'];
        echo '<p class="tsa-sec-label">' . esc_html(call_user_func($t, 'seo_intro')) . '</p>';
        echo '<div class="tsa-sec-head">';
        echo '<button type="button" class="button button-primary tsa-btn-primary" id="tsa-scan-all">' . esc_html(call_user_func($t, 'scan_all')) . '</button>';
        echo '<span class="tsa-sec-note" id="tsa-scan-progress"></span></div>';
        $rows = Tada_Site_Agent_Seo_Scorer::scored_pages(100);
        if (empty($rows)) {
            echo '<div class="tsa-stub">' . esc_html(call_user_func($t, 'no_scored')) . '</div>';
        } else {
            self::pages_table($rows, true);
        }
        echo '<p class="tsa-source">' . esc_html(call_user_func($t, 'source')) . ': TADA SEO Score (Edge)</p>';
    }

    private static function pages_table(array $rows, bool $with_kw): void {
        $t    = ['Tada_Site_Agent_I18n', 't'];
        $cols = $with_kw ? '1fr 60px 60px 1.3fr' : '1fr 60px 1.3fr';
        echo '<div class="tsa-table">';
        echo '<div class="tsa-row head" style="grid-template-columns:' . esc_attr($cols) . ';"><span>' . esc_html(call_user_func($t, 'col_page')) . '</span><span style="text-align:center;">' . esc_html(call_user_func($t, 'col_score')) . '</span>';
        if ($with_kw) {
            echo '<span style="text-align:center;">' . esc_html(call_user_func($t, 'col_kw')) . '</span>';
        }
        echo '<span>' . esc_html(call_user_func($t, 'col_issue')) . '</span></div>';
        foreach ($rows as $r) {
            $issues = is_array($r['issues']) ? implode(' · ', $r['issues']) : '';
            $edit   = !empty($r['edit']) ? $r['edit'] : '#';
            echo '<div class="tsa-row" style="grid-template-columns:' . esc_attr($cols) . ';">';
            echo '<span class="tsa-ellip"><a href="' . esc_url($edit) . '">' . esc_html($r['title'] !== '' ? $r['title'] : '(no title)') . '</a></span>';
            echo '<span style="text-align:center;"><span class="tsa-badge ' . esc_attr(self::score_class((int) $r['score'])) . '">' . (int) $r['score'] . '</span></span>';
            if ($with_kw) {
                $kw = ($r['kw'] === '' || $r['kw'] === null) ? '—' : (string) ((int) $r['kw']);
                echo '<span style="text-align:center;color:var(--tsa-muted);">' . esc_html($kw) . '</span>';
            }
            echo '<span class="tsa-ellip" style="color:var(--tsa-muted);">' . esc_html($issues) . '</span>';
            echo '</div>';
        }
        echo '</div>';
    }

    private static function score_class(int $s): string {
        return $s >= 80 ? 'tsa-good' : ($s >= 50 ? 'tsa-mid' : 'tsa-bad');
    }

    private static function stub(string $title): void {
        echo '<div class="tsa-stub"><span class="dashicons dashicons-hammer" style="font-size:22px;width:22px;height:22px;opacity:.6;"></span><br>'
            . esc_html($title) . ' — ' . esc_html(Tada_Site_Agent_I18n::t('coming_soon')) . '</div>';
    }
}
