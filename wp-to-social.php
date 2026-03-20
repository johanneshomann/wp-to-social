<?php
/**
 * Plugin Name: WP to Social
 * Plugin URI:  https://github.com/johanneshomann/wp-to-social
 * Description: Post WordPress content to social media platforms. Currently supports LinkedIn.
 * Version:     1.0.0
 * Author:      Johannes Homann
 * License:     GPL-2.0-or-later
 * Text Domain: wp-to-social
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

define( 'WPTS_VERSION', '1.0.0' );
define( 'WPTS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPTS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPTS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once WPTS_PLUGIN_DIR . 'includes/class-encryption.php';
require_once WPTS_PLUGIN_DIR . 'includes/class-module-registry.php';
require_once WPTS_PLUGIN_DIR . 'includes/class-field-mapper.php';
require_once WPTS_PLUGIN_DIR . 'includes/class-activity-logger.php';
require_once WPTS_PLUGIN_DIR . 'includes/class-post-handler.php';
require_once WPTS_PLUGIN_DIR . 'includes/modules/class-linkedin-module.php';
require_once WPTS_PLUGIN_DIR . 'includes/modules/class-instagram-module.php';
require_once WPTS_PLUGIN_DIR . 'includes/admin/class-admin.php';
require_once WPTS_PLUGIN_DIR . 'includes/admin/class-settings-page.php';
require_once WPTS_PLUGIN_DIR . 'includes/admin/class-activity-page.php';
require_once WPTS_PLUGIN_DIR . 'includes/admin/class-meta-box.php';
require_once WPTS_PLUGIN_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'WPTS_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WPTS_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'WPTS_Plugin', 'instance' ) );
