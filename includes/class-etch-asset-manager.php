<?php
namespace UplinkPress\MediaBridgeForEtch;

/**
 * Add optional Media Bridge controls to Etch's builder Asset Manager.
 */
final class Etch_Asset_Manager {
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 125 );
	}

	/**
	 * Load the uniform-grid control only in the Etch builder.
	 */
	public function enqueue_assets(): void {
		$etch_mode = isset( $_GET['etch'] ) ? sanitize_key( wp_unslash( $_GET['etch'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$settings  = Plugin::settings();
		if ( 'magic' !== $etch_mode || ! current_user_can( 'upload_files' ) || empty( $settings['etch_asset_manager_controls'] ) ) {
			return;
		}
		$asset_version = (string) max(
			(int) filemtime( UPLINK_MBE_PATH . 'assets/etch-asset-manager.css' ),
			(int) filemtime( UPLINK_MBE_PATH . 'assets/etch-asset-manager.js' )
		);

		wp_enqueue_style(
			'uplink-mbe-etch-asset-manager',
			UPLINK_MBE_URL . 'assets/etch-asset-manager.css',
			array(),
			$asset_version
		);
		wp_enqueue_script(
			'uplink-mbe-etch-asset-manager',
			UPLINK_MBE_URL . 'assets/etch-asset-manager.js',
			array(),
			$asset_version,
			true
		);
		wp_localize_script(
			'uplink-mbe-etch-asset-manager',
			'uplinkMbeEtchAssetManager',
			array(
				'uniformGridLabel'    => __( 'Uniform grid', 'media-bridge-for-etch' ),
				'thumbnailWidthLabel' => __( 'Thumbnail width', 'media-bridge-for-etch' ),
			)
		);
	}
}
