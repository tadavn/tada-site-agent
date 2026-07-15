<?php
/**
 * Tada_Site_Agent_I18n — i18n nhẹ cho admin (vi/en) chọn được trong Cài đặt.
 *
 * Cố ý KHÔNG dùng .po/.mo để tự chứa, không cần tooling dịch. Nếu sau này
 * cần cộng đồng dịch → chuyển sang __() + text domain chuẩn WP.
 *
 * @since 1.5.0
 */

defined('ABSPATH') || exit;

class Tada_Site_Agent_I18n {

    const OPTION = 'tada_site_agent_lang';

    /** @var array<string,array<string,string>> key => [vi, en] */
    private static $strings = [
        'connected'        => ['vi' => 'Đã kết nối Brain',        'en' => 'Connected to Brain'],
        'not_connected'    => ['vi' => 'Chưa kết nối',            'en' => 'Not connected'],
        'tab_overview'     => ['vi' => 'Tổng quan',               'en' => 'Overview'],
        'tab_seo'          => ['vi' => 'Điểm SEO',                'en' => 'SEO Score'],
        'tab_connection'   => ['vi' => 'Kết nối Brain',           'en' => 'Brain Connection'],
        'tab_settings'     => ['vi' => 'Cài đặt',                 'en' => 'Settings'],
        'health_label'     => ['vi' => 'Sức khỏe SEO của website — chấm trên HTML thật, phủ mọi trang', 'en' => 'Site SEO health — scored on real HTML, every page covered'],
        'avg_score'        => ['vi' => 'Điểm SEO trung bình',     'en' => 'Average SEO score'],
        'pages_scored'     => ['vi' => 'Trang đã chấm',           'en' => 'Pages scored'],
        'critical_issues'  => ['vi' => 'Lỗi nghiêm trọng',        'en' => 'Critical issues'],
        'rank_math'        => ['vi' => 'Rank Math',               'en' => 'Rank Math'],
        'rm_active'        => ['vi' => 'Hoạt động',               'en' => 'Active'],
        'rm_inactive'      => ['vi' => 'Không có',                'en' => 'Not detected'],
        'top_pages'        => ['vi' => 'Top trang cần tối ưu',    'en' => 'Top pages to optimize'],
        'sorted_by'        => ['vi' => 'xếp theo tác động × khoảng hở', 'en' => 'sorted by impact × gap'],
        'col_page'         => ['vi' => 'Trang',                   'en' => 'Page'],
        'col_score'        => ['vi' => 'Điểm',                    'en' => 'Score'],
        'col_issue'        => ['vi' => 'Vấn đề chính',            'en' => 'Main issue'],
        'coming_soon'      => ['vi' => 'Sắp có — đang dựng.',     'en' => 'Coming soon — under construction.'],
        // Settings
        'connection_settings' => ['vi' => 'Kết nối', 'en' => 'Connection'],
        'secret_key'       => ['vi' => 'Secret Key',             'en' => 'Secret Key'],
        'secret_key_hint'  => ['vi' => 'Bấm "Tạo Key", rồi copy sang dashboard TADA. Tối thiểu 16 ký tự.', 'en' => 'Click "Generate Key", then copy to your TADA dashboard. Minimum 16 characters.'],
        'generate_key'     => ['vi' => 'Tạo Key',                'en' => 'Generate Key'],
        'show'             => ['vi' => 'Hiện',                    'en' => 'Show'],
        'hide'             => ['vi' => 'Ẩn',                      'en' => 'Hide'],
        'key_set'          => ['vi' => 'Đã đặt key',             'en' => 'Key is set'],
        'language'         => ['vi' => 'Ngôn ngữ hiển thị',      'en' => 'Display language'],
        'language_hint'    => ['vi' => 'Ngôn ngữ giao diện quản trị của plugin.', 'en' => 'Admin interface language for this plugin.'],
        'save'             => ['vi' => 'Lưu thay đổi',           'en' => 'Save changes'],
        'auth_log'         => ['vi' => 'Nhật ký xác thực gần đây', 'en' => 'Recent authentication log'],
        'source'           => ['vi' => 'Nguồn', 'en' => 'Source'],
        // SEO tab
        'scan_all'         => ['vi' => 'Chấm tất cả trang',       'en' => 'Score all pages'],
        'scanning'         => ['vi' => 'Đang chấm',               'en' => 'Scoring'],
        'scan_done'        => ['vi' => 'Đã chấm xong',            'en' => 'Done'],
        'no_scored'        => ['vi' => 'Chưa chấm trang nào. Bấm "Chấm tất cả trang" để bắt đầu.', 'en' => 'No pages scored yet. Click "Score all pages" to start.'],
        'col_kw'           => ['vi' => 'Keyword',                 'en' => 'Keyword'],
        'seo_intro'        => ['vi' => 'Điểm SEO từng trang — chấm trên HTML render, phủ cả trang template riêng.', 'en' => 'Per-page SEO score — scored on rendered HTML, covers custom-template pages too.'],
    ];

    public static function lang(): string {
        $l = get_option(self::OPTION, '');
        if ($l === 'vi' || $l === 'en') {
            return $l;
        }
        // mặc định theo locale site
        return strpos((string) get_locale(), 'vi') === 0 ? 'vi' : 'en';
    }

    /** Dịch 1 key; fallback vi rồi tới chính key. */
    public static function t(string $key): string {
        $lang = self::lang();
        if (isset(self::$strings[$key][$lang])) {
            return self::$strings[$key][$lang];
        }
        return self::$strings[$key]['vi'] ?? $key;
    }

    /** Danh sách ngôn ngữ cho dropdown. */
    public static function options(): array {
        return ['vi' => 'Tiếng Việt', 'en' => 'English'];
    }
}
