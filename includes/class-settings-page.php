<?php
/**
 * Site Health Agent Settings Page
 *
 * Admin page under Settings menu with:
 * - Secret Key configuration (password field)
 * - Key generation helper
 * - Connection status indicator
 * - Authentication log viewer
 *
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

class Site_Health_Agent_Settings_Page {

    public static function init(): void {
        // Menu do Site_Health_Agent_Admin_Menu quản lý (menu top-level riêng).
        add_action('admin_init', [self::class, 'register_settings']);
    }

    public static function register_settings(): void {
        register_setting('site_health_agent', 'site_health_agent_secret_key', [
            'type'              => 'string',
            'sanitize_callback' => [self::class, 'sanitize_secret_key'],
            'default'           => '',
        ]);

        add_settings_section(
            'site_health_agent_main',
            'Connection Settings',
            function () {
                echo '<p>Configure the secret key to allow the <strong>Site Health Agent</strong> platform to securely read your Rank Math SEO configuration.</p>';
            },
            'site-health-agent'
        );

        add_settings_field(
            'site_health_agent_secret_key',
            'Secret Key',
            [self::class, 'render_secret_key_field'],
            'site-health-agent',
            'site_health_agent_main'
        );
    }

    /**
     * Validate and sanitize secret key
     */
    public static function sanitize_secret_key(string $value): string {
        $value = sanitize_text_field(trim($value));

        if (!empty($value) && strlen($value) < SITE_HEALTH_AGENT_MIN_KEY_LENGTH) {
            add_settings_error(
                'site_health_agent_secret_key',
                'site_health_agent_key_too_short',
                sprintf(
                    'Secret key must be at least %d characters long for security.',
                    SITE_HEALTH_AGENT_MIN_KEY_LENGTH
                ),
                'error'
            );
            // Return the old value if validation fails
            return get_option('site_health_agent_secret_key', '');
        }

        return $value;
    }

    /**
     * Render secret key input field (password type for security)
     */
    public static function render_secret_key_field(): void {
        $value = get_option('site_health_agent_secret_key', '');
        $has_key = !empty($value);
        ?>
        <div style="display:flex; align-items:center; gap:8px;">
            <input
                type="password"
                id="site_health_agent_secret_key"
                name="site_health_agent_secret_key"
                value="<?php echo esc_attr($value); ?>"
                class="regular-text"
                autocomplete="new-password"
                minlength="<?php echo SITE_HEALTH_AGENT_MIN_KEY_LENGTH; ?>"
            />
            <button type="button" onclick="
                var f = document.getElementById('site_health_agent_secret_key');
                f.type = f.type === 'password' ? 'text' : 'password';
                this.textContent = f.type === 'password' ? 'Show' : 'Hide';
            " class="button button-secondary">Show</button>
            <button type="button" onclick="
                var key = '';
                var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
                var arr = new Uint8Array(32);
                window.crypto.getRandomValues(arr);
                for(var i=0;i<32;i++) key += chars[arr[i] % chars.length];
                document.getElementById('site_health_agent_secret_key').value = key;
                document.getElementById('site_health_agent_secret_key').type = 'text';
            " class="button button-secondary">Generate Key</button>
        </div>
        <p class="description">
            <?php if ($has_key): ?>
                Key is set (<?php echo strlen($value); ?> characters).
            <?php else: ?>
                Click <strong>Generate Key</strong>, then copy this same key to your Site Health Agent dashboard.
            <?php endif; ?>
            Minimum <?php echo SITE_HEALTH_AGENT_MIN_KEY_LENGTH; ?> characters.
        </p>
        <?php
    }

    /**
     * Render the full settings page
     */
    public static function render_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $key_set   = !empty(get_option('site_health_agent_secret_key', ''));
        $rm_active = false;
        if (function_exists('is_plugin_active')) {
            $rm_active = is_plugin_active('seo-by-rank-math/rank-math.php');
        }
        $auth_logs = get_option('site_health_agent_auth_log', []);
        if (!is_array($auth_logs)) {
            $auth_logs = [];
        }
        ?>
        <div class="wrap">
            <h1>Site Health Agent</h1>

            <!-- Connection Status -->
            <div style="background:#fff; border:1px solid #ccd0d4; border-radius:4px; padding:16px 20px; margin:20px 0; max-width:700px;">
                <h3 style="margin-top:0;">Connection Status</h3>
                <table class="form-table" role="presentation" style="margin:0;">
                    <tr>
                        <th style="width:200px;">Secret Key</th>
                        <td>
                            <?php if ($key_set): ?>
                                <span style="color:#00a32a;">&#10003; Configured</span>
                            <?php else: ?>
                                <span style="color:#d63638;">&#10007; Not set</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Rank Math SEO</th>
                        <td>
                            <?php if ($rm_active): ?>
                                <span style="color:#00a32a;">&#10003; Active</span>
                            <?php else: ?>
                                <span style="color:#d63638;">&#10007; Not detected — Deep Scan requires Rank Math</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>REST API Endpoint</th>
                        <td><code><?php echo esc_url(rest_url('site-health-agent/v1/scan')); ?></code></td>
                    </tr>
                    <tr>
                        <th>Health Check</th>
                        <td><code><?php echo esc_url(rest_url('site-health-agent/v1/ping')); ?></code></td>
                    </tr>
                    <tr>
                        <th>Security</th>
                        <td>
                            HMAC-SHA256 &bull;
                            Rate limit: <?php echo SITE_HEALTH_AGENT_RATE_LIMIT; ?> req/min &bull;
                            Replay protection: on
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Settings Form -->
            <form method="post" action="options.php">
                <?php
                settings_fields('site_health_agent');
                do_settings_sections('site-health-agent');
                submit_button('Save Settings');
                ?>
            </form>

            <!-- Auth Log -->
            <?php if (!empty($auth_logs)): ?>
            <div style="background:#fff; border:1px solid #ccd0d4; border-radius:4px; padding:16px 20px; margin:20px 0; max-width:700px;">
                <h3 style="margin-top:0;">Recent Authentication Log</h3>
                <table class="widefat striped" style="max-width:100%;">
                    <thead>
                        <tr>
                            <th>Time (UTC)</th>
                            <th>IP Address</th>
                            <th>Result</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($auth_logs, 0, 20) as $log): ?>
                        <tr>
                            <td><code><?php echo esc_html($log['time'] ?? ''); ?></code></td>
                            <td><code><?php echo esc_html($log['ip'] ?? ''); ?></code></td>
                            <td>
                                <?php
                                $result = $log['result'] ?? '';
                                $color = $result === 'success' ? '#00a32a' : '#d63638';
                                $label = strtoupper(str_replace('_', ' ', $result));
                                ?>
                                <span style="color:<?php echo $color; ?>; font-weight:600;">
                                    <?php echo esc_html($label); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (count($auth_logs) > 20): ?>
                    <p class="description" style="margin-top:8px;">Showing 20 of <?php echo count($auth_logs); ?> entries.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
}

