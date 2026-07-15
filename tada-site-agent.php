<?php
/**
 * Plugin Name: TADA Site Agent
 * Plugin URI:  https://webkit.tada.vn
 * Description: Edge agent thu thập tín hiệu sức khỏe website (SEO, cấu hình Rank Math) qua REST API xác thực HMAC-SHA256, phục vụ nền tảng phân tích của TADA. Read-only. Built by TADA.
 * Version:     1.5.1
 * Requires PHP: 7.4
 * Author:      TADA
 * Author URI:  https://tada.vn
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tada-site-agent
 * Update URI:  https://github.com/tadavn/tada-site-agent
 */

defined('ABSPATH') || exit;

define('TADA_SITE_AGENT_VERSION', '1.5.1');
define('TADA_SITE_AGENT_FILE', __FILE__);
define('TADA_SITE_AGENT_PATH', plugin_dir_path(__FILE__));
define('TADA_SITE_AGENT_URL', plugin_dir_url(__FILE__));
define('TADA_SITE_AGENT_MIN_KEY_LENGTH', 16);
define('TADA_SITE_AGENT_TIMESTAMP_WINDOW', 300); // 5 minutes
define('TADA_SITE_AGENT_RATE_LIMIT', 10);         // max requests per minute
define('TADA_SITE_AGENT_RATE_WINDOW', 60);        // rate limit window (seconds)

require_once TADA_SITE_AGENT_PATH . 'includes/class-plugin.php';

Tada_Site_Agent_Plugin::instance();
