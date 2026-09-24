<?php
/**
 * Remove Media Bridge-owned data when the administrator opted in.
 *
 * @package MediaBridgeForEtch
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-data-cleanup.php';

$uplink_mbe_network_settings = UplinkPress\MediaBridgeForEtch\Data_Cleanup::network_settings();
if ( is_multisite() && ! empty( $uplink_mbe_network_settings['delete_data_on_network_uninstall'] ) ) {
	UplinkPress\MediaBridgeForEtch\Data_Cleanup::delete_all_sites();
} else {
	UplinkPress\MediaBridgeForEtch\Data_Cleanup::delete_all_sites_if_enabled( 'delete_data_on_uninstall' );
}
