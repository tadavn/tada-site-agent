<?php
/**
 * Plugin Name: Site Health Agent
 * Plugin URI:  https://webkit.tada.vn
 * Description: Edge agent thu thập tín hiệu sức khỏe website (SEO, cấu hình Rank Math) qua REST API xác thực HMAC-SHA256, phục vụ nền tảng phân tích của TADA. Read-only. Built by TADA.
 * Version:     1.4.0
 * Requires PHP: 7.4
 * Author:      TADA
 * Author URI:  https://tada.vn
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: site-health-agent
 * Update URI:  https://github.com/tadavn/site-health-agent
 */

defined('ABSPATH') || exit;

define('SITE_HEALTH_AGENT_VERSION', '1.4.0');
define('SITE_HEALTH_AGENT_FILE', __FILE__);
define('SITE_HEALTH_AGENT_PATH', plugin_dir_path(__FILE__));
define('SITE_HEALTH_AGENT_URL', plugin_dir_url(__FILE__));
define('SITE_HEALTH_AGENT_MIN_KEY_LENGTH', 16);
define('SITE_HEALTH_AGENT_TIMESTAMP_WINDOW', 300); // 5 minutes
define('SITE_HEALTH_AGENT_RATE_LIMIT', 10);         // max requests per minute
define('SITE_HEALTH_AGENT_RATE_WINDOW', 60);        // rate limit window (seconds)

require_once SITE_HEALTH_AGENT_PATH . 'includes/class-plugin.php';

Site_Health_Agent_Plugin::instance();
