<?php
/**
 * Plugin Name: Media Bridge for Etch
 * Description: Keeps Etch media collections synchronized with supported WordPress media folder plugins.
 * Version: 0.1.1
 * Author: FlyingW Creative Services
 * Requires at least: 7.0
 * Requires PHP: 8.3
 * Text Domain: media-bridge-for-etch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MBE_VERSION', '0.1.1' );
define( 'MBE_FILE', __FILE__ );
define( 'MBE_PATH', plugin_dir_path( __FILE__ ) );
define( 'MBE_URL', plugin_dir_url( __FILE__ ) );

require_once MBE_PATH . 'includes/class-provider-interface.php';
require_once MBE_PATH . 'includes/class-taxonomy-provider.php';
require_once MBE_PATH . 'includes/Providers/class-etch-provider.php';
require_once MBE_PATH . 'includes/Providers/class-wicked-provider.php';
require_once MBE_PATH . 'includes/Providers/class-happyfiles-provider.php';
require_once MBE_PATH . 'includes/class-logger.php';
require_once MBE_PATH . 'includes/class-sync-engine.php';
require_once MBE_PATH . 'includes/class-admin.php';
require_once MBE_PATH . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'MediaBridgeForEtch\\Plugin', 'activate' ) );
add_action( 'plugins_loaded', array( 'MediaBridgeForEtch\\Plugin', 'instance' ) );
