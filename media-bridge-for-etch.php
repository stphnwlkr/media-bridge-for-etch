<?php
/**
 * Plugin Name: Media Bridge for Etch
 * Description: Keeps Etch media collections synchronized with supported WordPress media folder plugins.
 * Version: 1.0.0
 * Author: FlyingW Creative Services
 * License: GPL-2.0-or-later
 * Requires at least: 7.0
 * Requires PHP: 8.3
 * Text Domain: media-bridge-for-etch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FLYW_MBE_VERSION', '1.0.0' );
define( 'FLYW_MBE_FILE', __FILE__ );
define( 'FLYW_MBE_PATH', plugin_dir_path( __FILE__ ) );
define( 'FLYW_MBE_URL', plugin_dir_url( __FILE__ ) );

require_once FLYW_MBE_PATH . 'includes/class-provider-interface.php';
require_once FLYW_MBE_PATH . 'includes/class-taxonomy-provider.php';
require_once FLYW_MBE_PATH . 'includes/Providers/class-etch-provider.php';
require_once FLYW_MBE_PATH . 'includes/Providers/class-wicked-provider.php';
require_once FLYW_MBE_PATH . 'includes/Providers/class-happyfiles-provider.php';
require_once FLYW_MBE_PATH . 'includes/class-logger.php';
require_once FLYW_MBE_PATH . 'includes/class-sync-engine.php';
require_once FLYW_MBE_PATH . 'includes/class-admin.php';
require_once FLYW_MBE_PATH . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'MediaBridgeForEtch\\Plugin', 'activate' ) );
add_action( 'plugins_loaded', array( 'MediaBridgeForEtch\\Plugin', 'instance' ) );
