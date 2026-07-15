<?php
/**
 * Tada_Site_Agent_Settings_Page — nội dung tab "Cài đặt" (render trong shell).
 *
 * - Secret Key (Settings API + nonce + sanitize)
 * - Chọn ngôn ngữ hiển thị (vi/en)
 * - Nhật ký xác thực
 *
 * @since 1.0.0 · 1.5.0 (chuyển thành tab + design system + i18n)
 */

defined('ABSPATH') || exit;

class Tada_Site_Agent_Settings_Page {

    public static function init(): void {
        add_action('admin_init', [self::class, 'register_settings']);
    }

    public static function register_settings(): void {
        register_setting('tada_site_agent', 'tada_site_agent_secret_key', [
            'type'              => 'string',
            'sanitize_callback' => [self::class, 'sanitize_secret_key'],
            'default'           => '',
        ]);
        register_setting('tada_site_agent', Tada_Site_Agent_I18n::OPTION, [
            'type'              => 'string',
            'sanitize_callback' => [self::class, 'sanitize_lang'],
            'default'           => '',
        ]);
    }

    public static function sanitize_secret_key(string $value): string {
        $value = sanitize_text_field(trim($value));
        if (!empty($value) && strlen($value) < TADA_SITE_AGENT_MIN_KEY_LENGTH) {
            add_settings_error(
                'tada_site_agent_secret_key',
                'tada_site_agent_key_too_short',
                sprintf('Secret key must be at least %d characters long.', TADA_SITE_AGENT_MIN_KEY_LENGTH),
                'error'
            );
            return get_option('tada_site_agent_secret_key', '');
        }
        return $value;
    }

    public static function sanitize_lang(string $value): string {
        $value = sanitize_key($value);
        return in_array($value, ['vi', 'en'], true) ? $value : 'vi';
    }

    /** Nội dung tab Cài đặt (không có <div class="wrap"> — shell đã bọc). */
    public static function render_tab(): void {
        $t     = ['Tada_Site_Agent_I18n', 't'];
        $key   = get_option('tada_site_agent_secret_key', '');
        $lang  = Tada_Site_Agent_I18n::lang();
        $min   = TADA_SITE_AGENT_MIN_KEY_LENGTH;

        settings_errors();
        ?>
        <form method="post" action="options.php" class="tsa-card" style="max-width:640px;">
            <?php settings_fields('tada_site_agent'); ?>

            <div class="tsa-field">
                <label for="tsa_key"><?php echo esc_html(call_user_func($t, 'secret_key')); ?></label>
                <div style="display:flex; gap:8px; align-items:center;">
                    <input type="password" id="tsa_key" name="tada_site_agent_secret_key"
                           value="<?php echo esc_attr($key); ?>" class="regular-text"
                           autocomplete="new-password" minlength="<?php echo (int) $min; ?>" style="flex:1;">
                    <button type="button" class="button" id="tsa_toggle"><?php echo esc_html(call_user_func($t, 'show')); ?></button>
                    <button type="button" class="button" id="tsa_gen"><?php echo esc_html(call_user_func($t, 'generate_key')); ?></button>
                </div>
                <p class="hint">
                    <?php if ($key !== '') { echo esc_html(call_user_func($t, 'key_set')) . ' (' . strlen($key) . ' ' . esc_html($lang === 'vi' ? 'ký tự' : 'chars') . ').'; } ?>
                    <?php echo esc_html(call_user_func($t, 'secret_key_hint')); ?>
                </p>
            </div>

            <div class="tsa-field">
                <label for="tsa_lang"><?php echo esc_html(call_user_func($t, 'language')); ?></label>
                <select id="tsa_lang" name="<?php echo esc_attr(Tada_Site_Agent_I18n::OPTION); ?>">
                    <?php foreach (Tada_Site_Agent_I18n::options() as $code => $name) : ?>
                        <option value="<?php echo esc_attr($code); ?>" <?php selected($lang, $code); ?>><?php echo esc_html($name); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="hint"><?php echo esc_html(call_user_func($t, 'language_hint')); ?></p>
            </div>

            <?php submit_button(call_user_func($t, 'save'), 'primary tsa-btn-primary'); ?>
        </form>

        <?php self::render_auth_log($t); ?>

        <script>
        (function(){
            var f = document.getElementById('tsa_key');
            var tog = document.getElementById('tsa_toggle'), gen = document.getElementById('tsa_gen');
            var SHOW = <?php echo wp_json_encode(call_user_func($t, 'show')); ?>, HIDE = <?php echo wp_json_encode(call_user_func($t, 'hide')); ?>;
            if (tog) tog.addEventListener('click', function(){ var p = f.type === 'password'; f.type = p ? 'text' : 'password'; tog.textContent = p ? HIDE : SHOW; });
            if (gen) gen.addEventListener('click', function(){
                var c = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789', a = new Uint8Array(32), s = '';
                window.crypto.getRandomValues(a);
                for (var i = 0; i < 32; i++) s += c[a[i] % c.length];
                f.value = s; f.type = 'text'; if (tog) tog.textContent = HIDE;
            });
        })();
        </script>
        <?php
    }

    private static function render_auth_log(callable $t): void {
        $logs = get_option('tada_site_agent_auth_log', []);
        if (!is_array($logs) || empty($logs)) {
            return;
        }
        echo '<div class="tsa-sec-head" style="margin-top:24px;"><h2>' . esc_html(call_user_func($t, 'auth_log')) . '</h2></div>';
        echo '<div class="tsa-table" style="max-width:640px;">';
        echo '<div class="tsa-row head" style="grid-template-columns:1.4fr 1.4fr 1fr;"><span>Time (UTC)</span><span>IP</span><span>Result</span></div>';
        foreach (array_slice($logs, 0, 20) as $log) {
            $result = $log['result'] ?? '';
            $ok  = $result === 'success';
            $col = $ok ? 'var(--tsa-green)' : 'var(--tsa-red)';
            echo '<div class="tsa-row" style="grid-template-columns:1.4fr 1.4fr 1fr;">';
            echo '<span><code>' . esc_html($log['time'] ?? '') . '</code></span>';
            echo '<span><code>' . esc_html($log['ip'] ?? '') . '</code></span>';
            echo '<span style="color:' . esc_attr($col) . ';font-weight:600;">' . esc_html(strtoupper(str_replace('_', ' ', $result))) . '</span>';
            echo '</div>';
        }
        echo '</div>';
    }
}
