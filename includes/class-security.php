<?php
/**
 * Site_Health_Agent_Security — xác thực HMAC, rate limit, chống replay, log auth.
 * Tách từ main file (Nhịp OOP 1.4.0); logic giữ nguyên.
 *
 * @since 1.4.0
 */

defined('ABSPATH') || exit;

class Site_Health_Agent_Security {

    /**
     * Xác thực đầy đủ: rate limit + HMAC + chống replay.
     * Dùng làm permission_callback cho REST /scan.
     *
     * @return bool|WP_Error
     */
    public static function verify_request(WP_REST_Request $request) {
        $ip = self::get_client_ip();

        // 1. Rate limiting
        $rate_check = self::check_rate_limit($ip);
        if (is_wp_error($rate_check)) {
            self::log_attempt($ip, 'rate_limited');
            return $rate_check;
        }

        // 2. Secret key phải được cấu hình
        $secret_key = get_option('site_health_agent_secret_key', '');
        if (empty($secret_key) || strlen($secret_key) < SITE_HEALTH_AGENT_MIN_KEY_LENGTH) {
            self::log_attempt($ip, 'no_key');
            return new WP_Error(
                'site_health_agent_no_key',
                'Site Health Agent secret key is not configured or too short.',
                ['status' => 403]
            );
        }

        // 3. Header bắt buộc
        $timestamp = $request->get_header('X-Site-Health-Agent-Timestamp');
        $signature = $request->get_header('X-Site-Health-Agent-Signature');
        if (empty($timestamp) || empty($signature)) {
            self::log_attempt($ip, 'missing_headers');
            return new WP_Error(
                'site_health_agent_missing_headers',
                'Missing authentication headers.',
                ['status' => 401]
            );
        }

        // 4. Timestamp phải là số
        if (!ctype_digit($timestamp)) {
            self::log_attempt($ip, 'invalid_timestamp');
            return new WP_Error(
                'site_health_agent_invalid_timestamp',
                'Invalid timestamp format.',
                ['status' => 401]
            );
        }

        // 5. Timestamp trong cửa sổ cho phép
        $time_diff = abs(time() - intval($timestamp));
        if ($time_diff > SITE_HEALTH_AGENT_TIMESTAMP_WINDOW) {
            self::log_attempt($ip, 'expired');
            return new WP_Error(
                'site_health_agent_expired',
                'Request timestamp expired.',
                ['status' => 401]
            );
        }

        // 6. Chống replay — từ chối timestamp tái sử dụng
        $replay_check = self::check_replay($timestamp);
        if (is_wp_error($replay_check)) {
            self::log_attempt($ip, 'replay');
            return $replay_check;
        }

        // 7. Verify HMAC-SHA256 (so sánh timing-safe)
        $expected = hash_hmac('sha256', $timestamp, $secret_key);
        if (!hash_equals($expected, $signature)) {
            self::log_attempt($ip, 'invalid_signature');
            return new WP_Error(
                'site_health_agent_invalid_sig',
                'Invalid signature.',
                ['status' => 401]
            );
        }

        // 8. Đánh dấu timestamp đã dùng (chống replay)
        self::mark_timestamp_used($timestamp);

        self::log_attempt($ip, 'success');
        return true;
    }

    /** Lấy IP client (hỗ trợ reverse proxy). */
    public static function get_client_ip(): string {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = explode(',', sanitize_text_field(wp_unslash($_SERVER[$header])))[0];
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    /**
     * Rate limiting bằng transient.
     *
     * @return bool|WP_Error
     */
    private static function check_rate_limit(string $ip) {
        $key = 'site_health_agent_rate_' . md5($ip);
        $count = (int) get_transient($key);

        if ($count >= SITE_HEALTH_AGENT_RATE_LIMIT) {
            return new WP_Error(
                'site_health_agent_rate_limited',
                'Too many requests. Please try again later.',
                ['status' => 429]
            );
        }

        set_transient($key, $count + 1, SITE_HEALTH_AGENT_RATE_WINDOW);
        return true;
    }

    /**
     * Chống replay — kiểm tra timestamp đã dùng chưa.
     *
     * @return bool|WP_Error
     */
    private static function check_replay(string $timestamp) {
        $key = 'site_health_agent_ts_' . $timestamp;
        if (get_transient($key)) {
            return new WP_Error(
                'site_health_agent_replay',
                'Duplicate request detected.',
                ['status' => 401]
            );
        }
        return true;
    }

    /** Đánh dấu timestamp đã dùng (giữ trong suốt cửa sổ timestamp). */
    private static function mark_timestamp_used(string $timestamp): void {
        $key = 'site_health_agent_ts_' . $timestamp;
        set_transient($key, 1, SITE_HEALTH_AGENT_TIMESTAMP_WINDOW + 60);
    }

    /** Ghi log auth (lưu option, giữ 50 dòng gần nhất). */
    public static function log_attempt(string $ip, string $result): void {
        $logs = get_option('site_health_agent_auth_log', []);
        if (!is_array($logs)) {
            $logs = [];
        }

        array_unshift($logs, [
            'time'   => gmdate('Y-m-d H:i:s'),
            'ip'     => $ip,
            'result' => $result,
        ]);

        $logs = array_slice($logs, 0, 50);
        update_option('site_health_agent_auth_log', $logs, false); // autoload = false
    }
}
