<?php
namespace UplinkPress\MediaBridgeForEtch;

/**
 * Add optional Media Bridge controls to Etch's builder Asset Manager.
 */
final class Etch_Asset_Manager {
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 125 );
		add_action( 'wp_ajax_uplink_mbe_etch_asset_manifest', array( $this, 'asset_manifest' ) );
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
			(int) filemtime( UPLINK_MBE_PATH . 'assets/etch-asset-index.js' ),
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
			'uplink-mbe-etch-asset-index',
			UPLINK_MBE_URL . 'assets/etch-asset-index.js',
			array(),
			$asset_version,
			true
		);
		wp_enqueue_script(
			'uplink-mbe-etch-asset-manager',
			UPLINK_MBE_URL . 'assets/etch-asset-manager.js',
			array( 'uplink-mbe-etch-asset-index' ),
			$asset_version,
			true
		);
		wp_localize_script(
			'uplink-mbe-etch-asset-index',
			'uplinkMbeEtchAssetManager',
			array(
				'ajaxUrl'             => admin_url( 'admin-ajax.php' ),
				'nonce'               => wp_create_nonce( 'uplink_mbe_etch_asset_manifest' ),
				'uniformGridLabel'    => __( 'Uniform grid', 'media-bridge-for-etch' ),
				'thumbnailWidthLabel' => __( 'Thumbnail width', 'media-bridge-for-etch' ),
				'thumbnailRatioLabel' => __( 'Aspect ratio', 'media-bridge-for-etch' ),
				'resizeSidebarLabel'   => __( 'Resize collections panel', 'media-bridge-for-etch' ),
			)
		);
	}

	/**
	 * Return the lightweight attachment index needed to match Etch's rendered tiles.
	 */
	public function asset_manifest(): void {
		check_ajax_referer( 'uplink_mbe_etch_asset_manifest', 'nonce' );
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot access the media library.', 'media-bridge-for-etch' ) ), 403 );
		}

		$attachments = get_posts(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'posts_per_page'         => -1,
				'orderby'                => 'ID',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			)
		);

		$collection_ids = array_fill_keys( array_map( static fn( $attachment ) => (int) $attachment->ID, $attachments ), array() );
		$collections = array();
		if ( $attachments && taxonomy_exists( 'etch_collection' ) ) {
			$relationships = wp_get_object_terms( array_keys( $collection_ids ), 'etch_collection', array( 'fields' => 'all_with_object_id' ) );
			if ( ! is_wp_error( $relationships ) ) {
				foreach ( $relationships as $relationship ) $collection_ids[ (int) $relationship->object_id ][] = (int) $relationship->term_id;
			}
		}
		if ( taxonomy_exists( 'etch_collection' ) ) {
			$terms = get_terms( array( 'taxonomy' => 'etch_collection', 'hide_empty' => false ) );
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) $collections[] = array( 'id' => (int) $term->term_id, 'parentId' => (int) $term->parent );
			}
		}
		$assets = array();
		foreach ( $attachments as $attachment ) {
			$attachment_id = (int) $attachment->ID;
			$file = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
			$metadata = wp_get_attachment_metadata( $attachment_id );
			$assets[] = array(
				'id' => $attachment_id,
				'fileName' => $file ? wp_basename( $file ) : (string) $attachment->post_title,
				'searchName' => ! empty( $metadata['file'] ) ? wp_basename( $metadata['file'] ) : (string) get_the_title( $attachment_id ),
				'mimeType' => (string) $attachment->post_mime_type,
				'fontUrl' => (string) wp_get_attachment_url( $attachment_id ),
				'altText' => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
				'collectionIds' => $collection_ids[ $attachment_id ],
			);
		}
		wp_send_json_success( array( 'assets' => $assets, 'collections' => $collections ) );
	}
}
