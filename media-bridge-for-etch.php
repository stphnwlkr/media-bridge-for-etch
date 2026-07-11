<?php
/**
 * Plugin Name: Media Bridge for Etch
 * Description: Keeps Etch media collections synchronized with supported WordPress media folder plugins.
 * Version: 1.0.0
 * Plugin URI: https://uplink.press/code/media-bridge-for-etch/
 * Author: UplinkPress
 * Author URI: https://uplink.press
 * License: GPL-2.0-or-later
 * Requires at least: 7.0
 * Requires PHP: 8.3
 * Text Domain: media-bridge-for-etch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'UPLINK_MBE_VERSION', '1.0.0' );
define( 'UPLINK_MBE_FILE', __FILE__ );
define( 'UPLINK_MBE_PATH', plugin_dir_path( __FILE__ ) );
define( 'UPLINK_MBE_URL', plugin_dir_url( __FILE__ ) );

require_once UPLINK_MBE_PATH . 'includes/class-provider-interface.php';
require_once UPLINK_MBE_PATH . 'includes/class-taxonomy-provider.php';
require_once UPLINK_MBE_PATH . 'includes/Providers/class-etch-provider.php';
require_once UPLINK_MBE_PATH . 'includes/Providers/class-wicked-provider.php';
require_once UPLINK_MBE_PATH . 'includes/Providers/class-happyfiles-provider.php';
require_once UPLINK_MBE_PATH . 'includes/class-logger.php';
require_once UPLINK_MBE_PATH . 'includes/class-sync-engine.php';
require_once UPLINK_MBE_PATH . 'includes/class-admin.php';
require_once UPLINK_MBE_PATH . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'UplinkPress\\MediaBridgeForEtch\\Plugin', 'activate' ) );
add_action( 'plugins_loaded', array( 'UplinkPress\\MediaBridgeForEtch\\Plugin', 'instance' ) );
