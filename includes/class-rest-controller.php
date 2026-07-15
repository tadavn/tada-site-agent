<?php
/**
 * Tada_Site_Agent_Rest_Controller — REST endpoints (scan + ping).
 * Tách từ main file (Nhịp OOP 1.4.0).
 *
 * @since 1.4.0
 */

defined('ABSPATH') || exit;

class Tada_Site_Agent_Rest_Controller {

    public static function init(): void {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes(): void {
        register_rest_route('tada-site-agent/v1', '/scan', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'handle_scan'],
            'permission_callback' => ['Tada_Site_Agent_Security', 'verify_request'],
        ]);

        // Health check — không cần auth, trả thông tin tối thiểu.
        register_rest_route('tada-site-agent/v1', '/ping', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'handle_ping'],
            'permission_callback' => '__return_true',
        ]);
    }

    /** Thu thập cấu hình Rank Math và trả về (read-only). */
    public static function handle_scan(WP_REST_Request $request): WP_REST_Response {
        $data = Tada_Site_Agent_RankMath_Reader::collect();

        $data['pluginVersion'] = TADA_SITE_AGENT_VERSION;
        $data['wpVersion']     = get_bloginfo('version');
        $data['siteUrl']       = get_site_url();
        $data['scannedAt']     = gmdate('c');
        // Ghi chú: KHÔNG lộ phiên bản PHP vì lý do bảo mật.

        return new WP_REST_Response($data, 200);
    }

    public static function handle_ping(): WP_REST_Response {
        return new WP_REST_Response([
            'status'  => 'ok',
            'plugin'  => 'tada-site-agent',
            'version' => TADA_SITE_AGENT_VERSION,
        ], 200);
    }
}
