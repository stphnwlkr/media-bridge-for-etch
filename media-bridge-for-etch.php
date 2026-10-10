<?php
/**
 * Plugin Name: Uplink Media Bridge for Etch
 * Description: Manage Etch media collections in WordPress, with optional Wicked Folders or HappyFiles synchronization.
 * Version: 2.5.4
 * Plugin URI: https://uplinkplugins.com/articles/uplink-media-bridge-for-etch-version-2/
 * Author: Stephen Walker
 * License: GPL-2.0-or-later
 * Requires at least: 7.0
 * Requires PHP: 8.3
 * Text Domain: media-bridge-for-etch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'UPLINK_MBE_VERSION', '2.5.4' );
define( 'UPLINK_MBE_ASSET_VERSION', '2.5.4' );
define( 'UPLINK_MBE_FILE', __FILE__ );
define( 'UPLINK_MBE_PATH', plugin_dir_path( __FILE__ ) );
define( 'UPLINK_MBE_URL', plugin_dir_url( __FILE__ ) );

/**
 * Return a Media Bridge library URL for integrations.
 *
 * @param int    $attachment_id Attachment to open in the active media experience.
 * @param string $action        Supported interface action. Currently "upload".
 */
function uplink_mbe_get_library_url( int $attachment_id = 0, string $action = '' ): string {
	$attachment_id = absint( $attachment_id );
	$action        = 'upload' === sanitize_key( $action ) ? 'upload' : '';
	$settings      = wp_parse_args(
		get_option( 'uplink_mbe_settings', array() ),
		array(
			'provider'             => 'native',
			'default_media_screen' => 0,
		)
	);
	$enhanced      = 'native' === $settings['provider'] && ! empty( $settings['default_media_screen'] );

	if ( $enhanced ) {
		$args = array( 'page' => 'etch-collections' );
		if ( $attachment_id ) {
			$args['uplink_mbe_attachment'] = $attachment_id;
		}
		if ( $action ) {
			$args['uplink_mbe_action'] = $action;
		}
		$url = add_query_arg( $args, admin_url( 'upload.php' ) );
	} elseif ( $attachment_id ) {
		$url = add_query_arg(
			array(
				'post'   => $attachment_id,
				'action' => 'edit',
			),
			admin_url( 'post.php' )
		);
	} else {
		$url = admin_url( $action ? 'media-new.php' : 'upload.php' );
	}

	/**
	 * Filter the Media Bridge library URL used by integrations.
	 *
	 * @param string $url           Generated library URL.
	 * @param int    $attachment_id Attachment requested by the caller, or zero.
	 * @param string $action        Normalized interface action, or an empty string.
	 */
	return (string) apply_filters( 'uplink_mbe_media_library_url', $url, $attachment_id, $action );
}

require_once UPLINK_MBE_PATH . 'includes/class-provider-interface.php';
require_once UPLINK_MBE_PATH . 'includes/class-taxonomy-provider.php';
require_once UPLINK_MBE_PATH . 'includes/Providers/class-etch-provider.php';
require_once UPLINK_MBE_PATH . 'includes/Providers/class-wicked-provider.php';
require_once UPLINK_MBE_PATH . 'includes/Providers/class-happyfiles-provider.php';
require_once UPLINK_MBE_PATH . 'includes/class-logger.php';
require_once UPLINK_MBE_PATH . 'includes/class-data-cleanup.php';
require_once UPLINK_MBE_PATH . 'includes/class-sync-engine.php';
require_once UPLINK_MBE_PATH . 'includes/class-upload-workspace.php';
require_once UPLINK_MBE_PATH . 'includes/class-file-replacement.php';
require_once UPLINK_MBE_PATH . 'includes/class-native-media-library.php';
require_once UPLINK_MBE_PATH . 'includes/class-etch-asset-manager.php';
require_once UPLINK_MBE_PATH . 'includes/class-collection-gallery.php';
require_once UPLINK_MBE_PATH . 'includes/class-etch-dynamic-data.php';
require_once UPLINK_MBE_PATH . 'includes/class-loop-generator.php';
require_once UPLINK_MBE_PATH . 'includes/class-admin.php';
require_once UPLINK_MBE_PATH . 'includes/class-network-admin.php';
require_once UPLINK_MBE_PATH . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'UplinkPress\\MediaBridgeForEtch\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'UplinkPress\\MediaBridgeForEtch\\Data_Cleanup', 'deactivate' ) );
add_action( 'plugins_loaded', array( 'UplinkPress\\MediaBridgeForEtch\\Plugin', 'bootstrap' ) );
