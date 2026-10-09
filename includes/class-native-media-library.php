<?php
namespace UplinkPress\MediaBridgeForEtch;

use WP_Query;
use WP_Term;

final class Native_Media_Library {
	private const PAGE_SLUG = 'etch-collections';
	private const NONCE     = 'uplink_mbe_native_media';
	private const PAGE_SIZE = 40;
	private const MEDIA_ORDER_META = '_uplink_mbe_media_order';
	private const HEALTH_TRANSIENT = 'uplink_mbe_media_health_v3';
	private Provider_Interface $etch;
	private array $etch_optimization_before = array();

	public function __construct( Provider_Interface $etch, bool $register_admin_ui = true ) {
		$this->etch = $etch;
		add_filter( 'posts_orderby', array( $this, 'collection_media_orderby' ), 10, 2 );
		add_filter( 'uplink_mbe_execute_media_command', array( $this, 'execute_media_command' ), 10, 2 );
		add_filter( 'rest_pre_dispatch', array( $this, 'capture_etch_optimization' ), 10, 3 );
		add_filter( 'rest_post_dispatch', array( $this, 'record_etch_optimization' ), 10, 3 );

		if ( ! $register_admin_ui ) {
			return;
		}

		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'add_meta_boxes_attachment', array( $this, 'image_editor_collection_meta_box' ) );
		add_action( 'edit_form_top', array( $this, 'image_editor_context_fields' ) );
		add_filter( 'admin_body_class', array( $this, 'image_editor_body_class' ) );
		add_filter( 'redirect_post_location', array( $this, 'preserve_image_editor_context' ), 10, 2 );
		add_action( 'wp_enqueue_media', array( $this, 'media_modal_assets' ) );
		add_action( 'wp_enqueue_media', array( $this, 'native_collection_assets' ), 20 );
		add_filter( 'ajax_query_attachments_args', array( $this, 'filter_native_collection_query' ) );
		add_action( 'pre_get_posts', array( $this, 'filter_native_media_list_query' ) );
		add_filter( 'attachment_fields_to_edit', array( $this, 'native_collection_attachment_field' ), 20, 2 );
		add_filter( 'attachment_fields_to_save', array( $this, 'save_native_collection_attachment_field' ), 20, 2 );
		add_action( 'wp_ajax_uplink_mbe_native_state', array( $this, 'ajax_state' ) );
		add_action( 'wp_ajax_uplink_mbe_native_collections_state', array( $this, 'ajax_native_collections_state' ) );
		add_action( 'wp_ajax_uplink_mbe_save_collection', array( $this, 'ajax_save_collection' ) );
		add_action( 'wp_ajax_uplink_mbe_reorder_collections', array( $this, 'ajax_reorder_collections' ) );
		add_action( 'wp_ajax_uplink_mbe_reorder_media', array( $this, 'ajax_reorder_media' ) );
		add_action( 'wp_ajax_uplink_mbe_delete_collection', array( $this, 'ajax_delete_collection' ) );
		add_action( 'wp_ajax_uplink_mbe_bulk_collections', array( $this, 'ajax_bulk_collections' ) );
		add_action( 'wp_ajax_uplink_mbe_assign_media', array( $this, 'ajax_assign_media' ) );
		add_action( 'wp_ajax_uplink_mbe_get_attachment', array( $this, 'ajax_get_attachment' ) );
		add_action( 'wp_ajax_uplink_mbe_update_attachment', array( $this, 'ajax_update_attachment' ) );
		add_action( 'wp_ajax_uplink_mbe_media_health', array( $this, 'ajax_media_health' ) );
		add_action( 'wp_ajax_uplink_mbe_save_appearance', array( $this, 'ajax_save_appearance' ) );
		add_action( 'wp_ajax_uplink_mbe_delete_media', array( $this, 'ajax_delete_media' ) );
		add_action( 'add_attachment', array( $this, 'invalidate_media_health' ) );
		add_action( 'add_attachment', array( $this, 'assign_native_upload_collection' ), 20 );
		add_action( 'edit_attachment', array( $this, 'invalidate_media_health' ) );
		add_action( 'delete_attachment', array( $this, 'invalidate_media_health' ) );
	}

	public function media_modal_assets(): void {
		$settings = Plugin::settings();
		if ( ! current_user_can( 'upload_files' ) || ! $this->etch->is_available() || 'native' !== $settings['provider'] || empty( $settings['default_media_screen'] ) ) {
			return;
		}
		$manager_label = $this->manager_label();
		$upload_destinations = $this->upload_destinations();
		$taxonomy = get_taxonomy( $this->etch->taxonomy() );

		wp_enqueue_style( 'uplink-mbe-media-modal', UPLINK_MBE_URL . 'assets/media-modal.css', array( 'media-views' ), UPLINK_MBE_ASSET_VERSION );
		wp_add_inline_style( 'uplink-mbe-media-modal', $this->custom_color_css( true ) );
		wp_enqueue_script( 'uplink-mbe-media-modal', UPLINK_MBE_URL . 'assets/media-modal.js', array( 'media-views', 'wp-util' ), UPLINK_MBE_ASSET_VERSION, true );
		wp_localize_script(
			'uplink-mbe-media-modal',
			'uplinkMbeMediaModal',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( self::NONCE ),
				'maxUploadBytes' => wp_max_upload_size(),
				'maxUploadSize' => size_format( wp_max_upload_size() ),
				'uploadDestinations' => $upload_destinations,
				'maxCollectionDepth' => Plugin::etch_collection_depth(),
				'canManageCollections' => $taxonomy && current_user_can( $taxonomy->cap->manage_terms ),
				'cimoAvailable' => defined( 'CIMO_FILE' ),
				'altIconUrl' => UPLINK_MBE_URL . 'assets/universal-access-circle-stroke-sharp.svg',
				'exifIconUrl' => UPLINK_MBE_URL . 'assets/camera-lens-stroke-sharp.svg',
				'optimizationIconUrl' => UPLINK_MBE_URL . 'assets/circle-gauge-stroke-sharp.svg',
				'optimizationAvailable' => true,
				'appearance' => Plugin::settings()['appearance'],
				'parentCountDisplay' => Plugin::settings()['parent_count_display'],
				'defaultMediaScreen' => ! empty( Plugin::settings()['default_media_screen'] ),
				'strings'    => array(
					'allMedia'       => __( 'All media', 'media-bridge-for-etch' ),
					'collapseCollection' => __( 'Collapse collection', 'media-bridge-for-etch' ),
					'collections'    => __( 'Collections', 'media-bridge-for-etch' ),
					'expandCollection' => __( 'Expand collection', 'media-bridge-for-etch' ),
					'altTextPresent' => __( 'Alt text provided', 'media-bridge-for-etch' ),
					'exifAvailable' => __( 'EXIF data available', 'media-bridge-for-etch' ),
					'attachmentDetails' => __( 'Attachment details', 'media-bridge-for-etch' ),
					'attachmentMetadata' => __( 'Attachment information', 'media-bridge-for-etch' ),
					'details'        => __( 'Main', 'media-bridge-for-etch' ),
					'fileInfo'       => __( 'Metadata', 'media-bridge-for-etch' ),
					'exif'           => __( 'EXIF', 'media-bridge-for-etch' ),
					'optimization'   => __( 'Optimization', 'media-bridge-for-etch' ),
					'fileName'       => __( 'File name', 'media-bridge-for-etch' ),
					'filePath'       => __( 'File path', 'media-bridge-for-etch' ),
					'fileType'       => __( 'File type', 'media-bridge-for-etch' ),
					'dimensions'     => __( 'Dimensions', 'media-bridge-for-etch' ),
					'fileSize'       => __( 'File size', 'media-bridge-for-etch' ),
					'uploaded'       => __( 'Uploaded', 'media-bridge-for-etch' ),
					'attachmentId'   => __( 'Attachment ID', 'media-bridge-for-etch' ),
					'close'          => __( 'Close', 'media-bridge-for-etch' ),
					'previousAttachment' => __( 'Previous attachment', 'media-bridge-for-etch' ),
					'nextAttachment' => __( 'Next attachment', 'media-bridge-for-etch' ),
					'title'          => __( 'Title', 'media-bridge-for-etch' ),
					'altText'        => __( 'Alternative text', 'media-bridge-for-etch' ),
					'decorative'     => __( 'Decorative', 'media-bridge-for-etch' ),
					'caption'        => __( 'Caption', 'media-bridge-for-etch' ),
					'description'    => __( 'Description', 'media-bridge-for-etch' ),
					'editImage'      => __( 'Edit image', 'media-bridge-for-etch' ),
					'saveChanges'    => __( 'Save changes', 'media-bridge-for-etch' ),
					'insertMedia'    => __( 'Insert', 'media-bridge-for-etch' ),
					'savingChanges'  => __( 'Saving changes…', 'media-bridge-for-etch' ),
					'inserting'      => __( 'Inserting…', 'media-bridge-for-etch' ),
					'replaceFeaturedImage' => __( 'Replace featured image', 'media-bridge-for-etch' ),
					'saved'          => __( 'Attachment saved.', 'media-bridge-for-etch' ),
					'empty'          => __( 'No media found in this collection.', 'media-bridge-for-etch' ),
					/* translators: %s is the administrator-defined media manager label. */
					'error'          => sprintf( __( 'The %s library could not be loaded.', 'media-bridge-for-etch' ), $manager_label ),
					/* translators: %s is the administrator-defined media manager label. */
					'heading'        => sprintf( __( 'Browse %s', 'media-bridge-for-etch' ), $manager_label ),
					'instructions'   => __( 'Choose a collection, then select one or more media items for the current field.', 'media-bridge-for-etch' ),
					'loading'        => __( 'Loading media…', 'media-bridge-for-etch' ),
					'allDates'       => __( 'All dates', 'media-bridge-for-etch' ),
					'all'            => __( 'All', 'media-bridge-for-etch' ),
					'allExtensions'  => __( 'All extensions', 'media-bridge-for-etch' ),
					'allTypes'       => __( 'All media items', 'media-bridge-for-etch' ),
					'audio'          => __( 'Audio', 'media-bridge-for-etch' ),
					'clearFilters'   => __( 'Clear all', 'media-bridge-for-etch' ),
					'documents'      => __( 'Documents', 'media-bridge-for-etch' ),
					'filterByDate'   => __( 'Filter by upload date', 'media-bridge-for-etch' ),
					'filterByExtension' => __( 'Filter by file extension', 'media-bridge-for-etch' ),
					'filterByType'   => __( 'Filter by type', 'media-bridge-for-etch' ),
					'filter'         => __( 'Filter', 'media-bridge-for-etch' ),
					'mediaType'      => __( 'Media type', 'media-bridge-for-etch' ),
					'mimeSubtype'    => __( 'MIME subtype', 'media-bridge-for-etch' ),
					'uploadedBy'     => __( 'Uploaded by', 'media-bridge-for-etch' ),
					'attachmentStatus' => __( 'Attachment status', 'media-bridge-for-etch' ),
					'attached'       => __( 'Attached', 'media-bridge-for-etch' ),
					'unattached'     => __( 'Unattached', 'media-bridge-for-etch' ),
					'uploadedFrom'   => __( 'Uploaded from', 'media-bridge-for-etch' ),
					'uploadedTo'     => __( 'Uploaded to', 'media-bridge-for-etch' ),
					'widthPixels'    => __( 'Width (px)', 'media-bridge-for-etch' ),
					'heightPixels'   => __( 'Height (px)', 'media-bridge-for-etch' ),
					'fileSizeMb'     => __( 'File size (MB)', 'media-bridge-for-etch' ),
					'minimum'        => __( 'Minimum', 'media-bridge-for-etch' ),
					'maximum'        => __( 'Maximum', 'media-bridge-for-etch' ),
					'missingAltText' => __( 'Missing alt text', 'media-bridge-for-etch' ),
					'optimizationStatus' => __( 'Optimization', 'media-bridge-for-etch' ),
					'cimoOptimized'  => __( 'Optimized', 'media-bridge-for-etch' ),
					'cimoNotOptimized' => __( 'Not optimized', 'media-bridge-for-etch' ),
					'cimoOptimizedLabel' => __( 'Optimized by Cimo', 'media-bridge-for-etch' ),
					'optimizedLabel' => __( 'Optimized media', 'media-bridge-for-etch' ),
					'images'         => __( 'Images', 'media-bridge-for-etch' ),
					'search'         => __( 'Search media', 'media-bridge-for-etch' ),
					'searchPlaceholder' => __( 'Search', 'media-bridge-for-etch' ),
					'searchWildcardHelp' => __( 'Use * as a filename wildcard, for example *-1.png.', 'media-bridge-for-etch' ),
					'viewMode'       => __( 'View mode', 'media-bridge-for-etch' ),
					'listView'       => __( 'List view', 'media-bridge-for-etch' ),
					'gridView'       => __( 'Grid view', 'media-bridge-for-etch' ),
					'masonryView'    => __( 'Masonry view', 'media-bridge-for-etch' ),
					/* translators: %s is the administrator-defined media manager label. */
					'searchLabel'    => sprintf( __( 'Search %s', 'media-bridge-for-etch' ), $manager_label ),
					/* translators: %s is the media item title. */
					'selectMedia'    => __( 'Select %s', 'media-bridge-for-etch' ),
					'tab'            => $manager_label,
					'uncategorized'  => __( 'Uncategorized', 'media-bridge-for-etch' ),
					'video'          => __( 'Video', 'media-bridge-for-etch' ),
					'uploadHeading' => __( 'Upload', 'media-bridge-for-etch' ),
					'uploadInstructions' => defined( 'CIMO_FILE' ) ? __( 'Choose a collection, then add files. Optimization stays on unless you keep originals.', 'media-bridge-for-etch' ) : __( 'Choose a collection, then add files.', 'media-bridge-for-etch' ),
					'destination' => __( 'Destination', 'media-bridge-for-etch' ),
					'dropFiles' => __( 'Drop files here', 'media-bridge-for-etch' ),
					'acceptedFiles' => __( 'Files accepted by WordPress', 'media-bridge-for-etch' ),
					'perFile' => __( 'per file', 'media-bridge-for-etch' ),
					'chooseFiles' => __( 'Choose files', 'media-bridge-for-etch' ),
					'clearFiles' => __( 'Clear', 'media-bridge-for-etch' ),
					'keepOriginals' => __( 'Keep originals', 'media-bridge-for-etch' ),
					'keepOriginalsHelp' => __( 'Upload the original file without Cimo pre-upload optimization. This can preserve embedded EXIF data.', 'media-bridge-for-etch' ),
					'cancelUpload' => __( 'Cancel', 'media-bridge-for-etch' ),
					'newCollection' => __( 'New collection', 'media-bridge-for-etch' ),
					'collectionName' => __( 'Collection name', 'media-bridge-for-etch' ),
					'parentCollection' => __( 'Parent collection', 'media-bridge-for-etch' ),
					'topLevel' => __( 'Top level', 'media-bridge-for-etch' ),
					'createCollection' => __( 'Create collection', 'media-bridge-for-etch' ),
					'collectionCreated' => __( 'Collection created and selected.', 'media-bridge-for-etch' ),
					/* translators: %s is the file name. */
					'removeFile' => __( 'Remove %s', 'media-bridge-for-etch' ),
					'fileReady' => __( 'Ready', 'media-bridge-for-etch' ),
					'fileWillOptimize' => __( 'Will optimize', 'media-bridge-for-etch' ),
					'fileKeptOriginal' => __( 'Original will be kept', 'media-bridge-for-etch' ),
					'fileOverLimit' => __( 'Over the upload limit', 'media-bridge-for-etch' ),
					/* translators: 1: number of files, 2: combined file size. */
					'filesReady' => __( '%1$d files · %2$s ready', 'media-bridge-for-etch' ),
					/* translators: %d is the number of files over the upload limit. */
					'filesOverLimit' => __( '%d over limit', 'media-bridge-for-etch' ),
					/* translators: %d is the number of files ready to upload. */
					'uploadFiles' => __( 'Upload %d', 'media-bridge-for-etch' ),
					/* translators: %d is the number of failed files ready to retry. */
					'retryFiles' => __( 'Retry %d', 'media-bridge-for-etch' ),
					'uploading' => __( 'Uploading…', 'media-bridge-for-etch' ),
					/* translators: 1: number of finished uploads, 2: total number of uploads. */
					'uploadingFiles' => __( '%1$d of %2$d finished', 'media-bridge-for-etch' ),
					/* translators: 1: number of successful uploads, 2: number of failed uploads. */
					'uploadResult' => __( '%1$d uploaded · %2$d failed', 'media-bridge-for-etch' ),
					/* translators: %d is the number of successful uploads. */
					'uploadComplete' => __( '%d uploaded', 'media-bridge-for-etch' ),
					'uploadFailed' => __( 'WordPress could not upload this file.', 'media-bridge-for-etch' ),
				),
			)
		);
	}

	/**
	 * Add Etch Collections to WordPress's media screens without replacing them.
	 */
	public function native_collection_assets(): void {
		if ( ! $this->native_collections_are_enabled() || ! current_user_can( 'upload_files' ) ) {
			return;
		}

		// The full manager already owns its collection navigation and uploader.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This value only identifies the current admin screen.
		if ( isset( $_GET['page'] ) && self::PAGE_SLUG === sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			return;
		}

		$taxonomy = get_taxonomy( $this->etch->taxonomy() );
		$native_collections_asset_version = UPLINK_MBE_ASSET_VERSION . '.30';
		wp_enqueue_style( 'uplink-mbe-native-collections', UPLINK_MBE_URL . 'assets/native-collections.css', array( 'media-views' ), $native_collections_asset_version );
		wp_enqueue_script( 'uplink-mbe-native-collections', UPLINK_MBE_URL . 'assets/native-collections.js', array( 'media-views', 'wp-util', 'jquery-ui-draggable', 'jquery-ui-droppable' ), $native_collections_asset_version, true );
		wp_localize_script(
			'uplink-mbe-native-collections',
			'uplinkMbeNativeCollections',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( self::NONCE ),
				'collections' => $this->native_collection_data(),
				'counts'      => $this->native_collection_counts(),
				'parentCountDisplay' => Plugin::settings()['parent_count_display'],
				'maxCollectionDepth' => Plugin::etch_collection_depth(),
				'canAssign'   => $taxonomy && current_user_can( $taxonomy->cap->assign_terms ),
				'canManage'   => $taxonomy && current_user_can( $taxonomy->cap->manage_terms ),
				'strings'     => array(
					'collections'   => __( 'Etch Collections', 'media-bridge-for-etch' ),
					'allMedia'      => __( 'All media', 'media-bridge-for-etch' ),
					'uncategorized' => __( 'Uncategorized', 'media-bridge-for-etch' ),
					'collapse'      => __( 'Collapse Etch Collections', 'media-bridge-for-etch' ),
					'expand'        => __( 'Expand Etch Collections', 'media-bridge-for-etch' ),
					'destination'   => __( 'Etch Collection', 'media-bridge-for-etch' ),
					'uploadHelp'    => __( 'Assign newly uploaded files to a collection.', 'media-bridge-for-etch' ),
					'newCollection' => __( 'New collection', 'media-bridge-for-etch' ),
					'addChild'      => __( 'Add child collection', 'media-bridge-for-etch' ),
					'editCollection' => __( 'Edit collection', 'media-bridge-for-etch' ),
					'deleteCollection' => __( 'Delete collection', 'media-bridge-for-etch' ),
					'collectionName' => __( 'Collection name', 'media-bridge-for-etch' ),
					'parentCollection' => __( 'Parent collection', 'media-bridge-for-etch' ),
					'topLevel'      => __( 'Top level', 'media-bridge-for-etch' ),
					'createCollection' => __( 'Create collection', 'media-bridge-for-etch' ),
					'cancel'        => __( 'Cancel', 'media-bridge-for-etch' ),
					'collectionCreated' => __( 'Collection created.', 'media-bridge-for-etch' ),
					'collectionUpdated' => __( 'Collection updated.', 'media-bridge-for-etch' ),
					'collectionDeleted' => __( 'Collection deleted.', 'media-bridge-for-etch' ),
					/* translators: %s is the collection name. */
					'confirmDelete' => __( 'Delete “%s”? Media files will remain in the library.', 'media-bridge-for-etch' ),
					/* translators: %s is the collection name. */
					'confirmDeleteWithChildren' => __( 'Delete “%s”? Its child collections will be moved to the top level. Media files will remain in the library.', 'media-bridge-for-etch' ),
					'assignHelp'    => __( 'Drag media onto a collection to assign it.', 'media-bridge-for-etch' ),
					'dragAssignOne' => __( 'Assign 1 item', 'media-bridge-for-etch' ),
					/* translators: %d is the number of selected media items. */
					'dragAssignMany' => __( 'Assign %d items', 'media-bridge-for-etch' ),
					'dragAssignHelp' => __( 'Drop onto a collection', 'media-bridge-for-etch' ),
					'reorderInstructions' => __( 'Drag to reorder, or press Alt plus the Up or Down arrow key.', 'media-bridge-for-etch' ),
					'orderSaved'     => __( 'Collection order saved.', 'media-bridge-for-etch' ),
					/* translators: %d is the number of media items assigned. */
					'assigned'      => __( '%d media items assigned.', 'media-bridge-for-etch' ),
					/* translators: %d is the number of media items moved to Uncategorized. */
					'cleared'       => __( '%d media items moved to Uncategorized.', 'media-bridge-for-etch' ),
					'error'         => __( 'The collection could not be updated.', 'media-bridge-for-etch' ),
				),
			)
		);
	}

	/**
	 * Apply an Etch Collection selected in a native WordPress media browser.
	 *
	 * @param array<string, mixed> $query WordPress attachment query arguments.
	 * @return array<string, mixed>
	 */
	public function filter_native_collection_query( array $query ): array {
		if ( ! $this->native_collections_are_enabled() ) {
			return $query;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WordPress verifies the media query request; this only adds a read-only taxonomy filter.
		$request_query = isset( $_REQUEST['query'] ) && is_array( $_REQUEST['query'] ) ? wp_unslash( $_REQUEST['query'] ) : array();
		$collection_id = absint( $request_query['uplink_mbe_collection'] ?? 0 );
		$uncategorized = ! empty( $request_query['uplink_mbe_uncategorized'] );
		if ( ! $collection_id && ! $uncategorized ) {
			return $query;
		}

		$clause = array(
			'taxonomy' => $this->etch->taxonomy(),
			'operator' => 'NOT EXISTS',
		);
		if ( $collection_id && $this->etch->get_term( $collection_id ) ) {
			$clause = array(
				'taxonomy'         => $this->etch->taxonomy(),
				'field'            => 'term_id',
				'terms'            => array( $collection_id ),
				'include_children' => true,
			);
		} elseif ( ! $uncategorized ) {
			return $query;
		}

		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- The selected collection defines the requested media view.
		$tax_query   = isset( $query['tax_query'] ) && is_array( $query['tax_query'] ) ? $query['tax_query'] : array();
		$tax_query[] = $clause;
		if ( 1 < count( $tax_query ) && ! isset( $tax_query['relation'] ) ) {
			$tax_query['relation'] = 'AND';
		}
		$query['tax_query'] = $tax_query;

		return $query;
	}

	/**
	 * Apply the selected Etch Collection to the native list-mode Media Library.
	 *
	 * @param \WP_Query $query Current admin query.
	 */
	public function filter_native_media_list_query( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() || ! $this->native_collections_are_enabled() || 'attachment' !== $query->get( 'post_type' ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- These values only filter the read-only Media Library query.
		$collection_id = isset( $_GET['uplink_mbe_collection'] ) ? absint( $_GET['uplink_mbe_collection'] ) : 0;
		$uncategorized = ! empty( $_GET['uplink_mbe_uncategorized'] );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( ! $collection_id && ! $uncategorized ) {
			return;
		}

		$clause = array(
			'taxonomy' => $this->etch->taxonomy(),
			'operator' => 'NOT EXISTS',
		);
		if ( $collection_id && $this->etch->get_term( $collection_id ) ) {
			$clause = array(
				'taxonomy'         => $this->etch->taxonomy(),
				'field'            => 'term_id',
				'terms'            => array( $collection_id ),
				'include_children' => true,
			);
		} elseif ( ! $uncategorized ) {
			return;
		}

		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- The selected collection defines the requested media view.
		$tax_query   = $query->get( 'tax_query' );
		$tax_query   = is_array( $tax_query ) ? $tax_query : array();
		$tax_query[] = $clause;
		if ( 1 < count( $tax_query ) && ! isset( $tax_query['relation'] ) ) {
			$tax_query['relation'] = 'AND';
		}
		$query->set( 'tax_query', $tax_query );
	}

	/**
	 * Return current collection names, hierarchy, and counts to the native sidebar.
	 */
	public function ajax_native_collections_state(): void {
		$this->verify_request( false, false );
		wp_send_json_success(
			array(
				'collections' => $this->native_collection_data(),
				'counts'      => $this->native_collection_counts(),
			)
		);
	}

	/**
	 * Add collection assignments to the native attachment details form.
	 *
	 * @param array<string, mixed> $fields     Attachment fields.
	 * @param \WP_Post             $attachment Attachment post.
	 * @return array<string, mixed>
	 */
	public function native_collection_attachment_field( array $fields, \WP_Post $attachment ): array {
		if ( ! $this->native_collections_are_enabled() || ! current_user_can( 'edit_post', $attachment->ID ) ) {
			return $fields;
		}
		$taxonomy = get_taxonomy( $this->etch->taxonomy() );
		if ( ! $taxonomy || ! current_user_can( $taxonomy->cap->assign_terms ) ) {
			return $fields;
		}

		$assigned = wp_get_object_terms( $attachment->ID, $this->etch->taxonomy(), array( 'fields' => 'ids' ) );
		$assigned = is_wp_error( $assigned ) ? array() : array_map( 'intval', $assigned );
		$collections = $this->native_collection_data();
		$children_by_parent = array();
		foreach ( $collections as $collection ) {
			$children_by_parent[ (int) $collection['parent'] ][] = $collection;
		}

		$html     = '<input type="hidden" name="attachments[' . (int) $attachment->ID . '][uplink_mbe_collections_present]" value="1">';
		$html    .= '<div class="uplink-mbe-native-assignment-list">';
		$rendered = array();
		$render_group = function ( array $collection, bool $is_child = false ) use ( &$render_group, &$rendered, $children_by_parent, $assigned, $attachment ): string {
			$collection_id = (int) $collection['id'];
			if ( isset( $rendered[ $collection_id ] ) ) {
				return '';
			}
			$rendered[ $collection_id ] = true;
			$children = $children_by_parent[ $collection_id ] ?? array();
			$classes  = 'uplink-mbe-native-assignment-option';
			$classes .= $is_child ? ' is-child' : '';
			$classes .= $children ? ' has-children' : '';

			$group  = '<div class="uplink-mbe-native-assignment-group' . ( $children ? ' has-children' : '' ) . '">';
			$group .= '<label class="' . esc_attr( $classes ) . '">';
			$group .= '<input type="checkbox" name="attachments[' . (int) $attachment->ID . '][uplink_mbe_collections][]" value="' . $collection_id . '" ' . checked( in_array( $collection_id, $assigned, true ), true, false ) . '>';
			$group .= '<span>' . esc_html( $collection['name'] ) . '</span></label>';
			if ( $children ) {
				/* translators: %s is a parent collection name. */
				$group .= '<div class="uplink-mbe-native-assignment-children" role="group" aria-label="' . esc_attr( sprintf( __( 'Subcollections: %s', 'media-bridge-for-etch' ), $collection['name'] ) ) . '">';
				foreach ( $children as $child ) {
					$group .= $render_group( $child, true );
				}
				$group .= '</div>';
			}
			return $group . '</div>';
		};
		foreach ( $children_by_parent[0] ?? array() as $collection ) {
			$html .= $render_group( $collection );
		}
		foreach ( $collections as $collection ) {
			if ( ! isset( $rendered[ (int) $collection['id'] ] ) ) {
				$html .= $render_group( $collection, (bool) $collection['parent'] );
			}
		}
		$html .= '</div>';

		$fields['uplink_mbe_collections'] = array(
			'label' => __( 'Etch Collections', 'media-bridge-for-etch' ),
			'input' => 'html',
			'html'  => $html,
			'helps' => __( 'Leave every collection unchecked to keep this item uncategorized.', 'media-bridge-for-etch' ),
		);

		return $fields;
	}

	/**
	 * Save collection assignments from the native attachment details form.
	 *
	 * @param array<string, mixed> $post       Attachment data being saved.
	 * @param array<string, mixed> $attachment Submitted attachment fields.
	 * @return array<string, mixed>
	 */
	public function save_native_collection_attachment_field( array $post, array $attachment ): array {
		if ( ! $this->native_collections_are_enabled() || empty( $attachment['uplink_mbe_collections_present'] ) ) {
			return $post;
		}
		$attachment_id = absint( $post['ID'] ?? 0 );
		$taxonomy      = get_taxonomy( $this->etch->taxonomy() );
		if ( ! $attachment_id || ! $taxonomy || ! current_user_can( 'edit_post', $attachment_id ) || ! current_user_can( $taxonomy->cap->assign_terms ) ) {
			return $post;
		}

		$submitted = isset( $attachment['uplink_mbe_collections'] ) && is_array( $attachment['uplink_mbe_collections'] ) ? $attachment['uplink_mbe_collections'] : array();
		$term_ids  = array_values( array_unique( array_filter( array_map( 'absint', $submitted ) ) ) );
		$term_ids  = array_values( array_filter( $term_ids, fn( int $term_id ): bool => (bool) $this->etch->get_term( $term_id ) ) );
		wp_set_object_terms( $attachment_id, $term_ids, $this->etch->taxonomy(), false );

		return $post;
	}

	/**
	 * Assign a native WordPress upload to its selected Etch Collection.
	 */
	public function assign_native_upload_collection( int $attachment_id ): void {
		if ( ! $this->native_collections_are_enabled() ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WordPress verifies the upload request before creating the attachment.
		$collection_id = absint( $_REQUEST['uplink_mbe_collection'] ?? 0 );
		$taxonomy      = get_taxonomy( $this->etch->taxonomy() );
		if ( ! $collection_id || ! $taxonomy || ! current_user_can( $taxonomy->cap->assign_terms ) || ! $this->etch->get_term( $collection_id ) ) {
			return;
		}
		wp_set_object_terms( $attachment_id, array( $collection_id ), $this->etch->taxonomy(), false );
	}

	private function native_collections_are_enabled(): bool {
		$settings = Plugin::settings();
		return 'native' === $settings['provider'] && empty( $settings['default_media_screen'] ) && ! empty( $settings['native_media_collections'] ) && $this->etch->is_available();
	}

	/**
	 * @return array<int, array{id:int,name:string,parent:int,depth:int,count:int,totalCount:int}>
	 */
	private function native_collection_data(): array {
		$terms = $this->etch->get_terms();
		if ( is_wp_error( $terms ) ) {
			return array();
		}
		$terms       = $this->etch->order_terms_hierarchically( $terms );
		$count_mode  = Plugin::settings()['parent_count_display'];
		$needs_total = in_array( $count_mode, array( 'cumulative', 'direct_total' ), true );
		$parents     = array();
		foreach ( $terms as $term ) {
			$parents[ (int) $term->term_id ] = (int) $term->parent;
		}
		$parent_ids = array_fill_keys( array_filter( array_values( $parents ) ), true );

		$data = array();
		foreach ( $terms as $term ) {
			$term_id      = (int) $term->term_id;
			$direct_count = (int) $term->count;
			$total_count  = $direct_count;
			if ( $needs_total && isset( $parent_ids[ $term_id ] ) ) {
				$descendants = get_term_children( $term_id, $this->etch->taxonomy() );
				if ( ! is_wp_error( $descendants ) && $descendants ) {
					$object_ids = get_objects_in_term( array_merge( array( $term_id ), array_map( 'intval', $descendants ) ), $this->etch->taxonomy() );
					if ( ! is_wp_error( $object_ids ) ) {
						$total_count = count( array_unique( array_map( 'intval', $object_ids ) ) );
					}
				}
			}

			$depth  = 0;
			$parent = (int) $term->parent;
			$seen   = array();
			while ( $parent && isset( $parents[ $parent ] ) && ! isset( $seen[ $parent ] ) ) {
				$seen[ $parent ] = true;
				++$depth;
				$parent = $parents[ $parent ];
			}
			$data[] = array(
				'id'         => $term_id,
				'name'       => $term->name,
				'parent'     => (int) $term->parent,
				'depth'      => $depth,
				'count'      => $direct_count,
				'totalCount' => $total_count,
			);
		}
		return $data;
	}

	/**
	 * @return array{all:int,uncategorized:int}
	 */
	private function native_collection_counts(): array {
		$count_posts = wp_count_posts( 'attachment' );
		$query       = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- A NOT EXISTS query is required for the native uncategorized filter count.
				'tax_query'      => array(
					array(
						'taxonomy' => $this->etch->taxonomy(),
						'operator' => 'NOT EXISTS',
					),
				),
			)
		);
		return array(
			'all'           => isset( $count_posts->inherit ) ? (int) $count_posts->inherit : 0,
			'uncategorized' => (int) $query->found_posts,
		);
	}

	private function upload_destinations(): array {
		$terms = $this->etch->get_terms();
		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$terms = $this->etch->order_terms_hierarchically( $terms );
		$parents = array();
		foreach ( $terms as $term ) {
			$parents[ (int) $term->term_id ] = (int) $term->parent;
		}

		$destinations = array();
		foreach ( $terms as $term ) {
			$depth  = 0;
			$parent = (int) $term->parent;
			$seen   = array();
			while ( $parent && isset( $parents[ $parent ] ) && ! isset( $seen[ $parent ] ) ) {
				$seen[ $parent ] = true;
				++$depth;
				$parent = $parents[ $parent ];
			}
			$destinations[] = array(
				'id'    => (int) $term->term_id,
				'name'  => $term->name,
				'depth' => $depth,
			);
		}

		return $destinations;
	}

	public function menu(): void {
		$settings = Plugin::settings();
		if ( 'native' !== $settings['provider'] || empty( $settings['default_media_screen'] ) ) {
			return;
		}

		$manager_label = $this->manager_label();
		add_media_page(
			$manager_label,
			$manager_label,
			'upload_files',
			self::PAGE_SLUG,
			array( $this, 'render' ),
			0
		);
		remove_submenu_page( 'upload.php', 'upload.php' );
	}

	public function assets( string $hook ): void {
		$image_editor_id = $this->requested_image_editor_id();
		if ( $image_editor_id ) {
			$manager_is_default = ! empty( Plugin::settings()['default_media_screen'] );
			$fallback_return_url = \uplink_mbe_get_library_url( $manager_is_default ? $image_editor_id : 0 );
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This URL only controls navigation after editing.
			$requested_return_url = isset( $_GET['uplink_mbe_return'] ) ? esc_url_raw( wp_unslash( $_GET['uplink_mbe_return'] ) ) : '';
			$return_url           = wp_validate_redirect( $requested_return_url, $fallback_return_url );

			wp_enqueue_style( 'uplink-mbe-image-editor', UPLINK_MBE_URL . 'assets/image-editor.css', array(), UPLINK_MBE_ASSET_VERSION . '.1' );
			wp_add_inline_style( 'uplink-mbe-image-editor', $this->custom_color_css() );
			wp_enqueue_script( 'uplink-mbe-image-editor', UPLINK_MBE_URL . 'assets/image-editor.js', array( 'jquery', 'image-edit' ), UPLINK_MBE_ASSET_VERSION, true );
			wp_localize_script(
				'uplink-mbe-image-editor',
				'uplinkMbeImageEditor',
				array(
					'attachmentId' => $image_editor_id,
					'returnUrl'     => $return_url,
					'returnLabel'   => $manager_is_default ? __( 'Back to media manager', 'media-bridge-for-etch' ) : __( 'Back to media', 'media-bridge-for-etch' ),
					'uploadUrl'     => \uplink_mbe_get_library_url( 0, 'upload' ),
				)
			);
			return;
		}

		if ( in_array( $hook, array( 'upload.php', 'media-new.php', 'post.php', 'post-new.php' ), true ) ) {
			$this->native_collection_assets();
		}

		if ( 'media_page_' . self::PAGE_SLUG !== $hook || ! current_user_can( 'upload_files' ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- These query values only choose the initial non-mutating library view.
		$initial_attachment_id = isset( $_GET['uplink_mbe_attachment'] ) ? absint( $_GET['uplink_mbe_attachment'] ) : 0;
		$initial_action        = isset( $_GET['uplink_mbe_action'] ) ? sanitize_key( wp_unslash( $_GET['uplink_mbe_action'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( $initial_attachment_id && ( 'attachment' !== get_post_type( $initial_attachment_id ) || ! current_user_can( 'edit_post', $initial_attachment_id ) ) ) {
			$initial_attachment_id = 0;
		}
		$initial_action = in_array( $initial_action, array( 'upload', 'new_collection', 'manage_collections' ), true ) ? $initial_action : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This query value only selects an initial read-only collection view.
		$initial_collection_id = isset( $_GET['uplink_mbe_collection'] ) ? absint( $_GET['uplink_mbe_collection'] ) : 0;
		if ( $initial_collection_id && ! $this->etch->get_term( $initial_collection_id ) ) {
			$initial_collection_id = 0;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'uplink-mbe-native-media', UPLINK_MBE_URL . 'assets/native-media.css', array(), UPLINK_MBE_ASSET_VERSION . '.1' );
		wp_add_inline_style( 'uplink-mbe-native-media', $this->custom_color_css() );
		wp_enqueue_script( 'uplink-mbe-native-media', UPLINK_MBE_URL . 'assets/native-media.js', array( 'media-editor' ), UPLINK_MBE_ASSET_VERSION, true );
		$this->enqueue_attachment_compatibility_assets();

		$taxonomy        = get_taxonomy( $this->etch->taxonomy() );
		$settings        = Plugin::settings();
		$gallery_enabled = ! empty( $settings['collection_gallery'] );
		$palette         = array( 'light' => array(), 'dark' => array() );
		foreach ( Plugin::appearance_defaults() as $mode => $colors ) {
			foreach ( $colors as $name => $fallback ) {
				$palette[ $mode ][ $name ] = $settings[ $mode . '_' . $name ] ?? $fallback;
			}
		}
		wp_localize_script(
			'uplink-mbe-native-media',
			'uplinkMbeNativeMedia',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( self::NONCE ),
				'altIconUrl'   => UPLINK_MBE_URL . 'assets/universal-access-circle-stroke-sharp.svg',
				'exifIconUrl'  => UPLINK_MBE_URL . 'assets/camera-lens-stroke-sharp.svg',
				'optimizationIconUrl' => UPLINK_MBE_URL . 'assets/circle-gauge-stroke-sharp.svg',
				'optimizationAvailable' => true,
				'pageSize'     => self::PAGE_SIZE,
				'pagination'   => ! empty( Plugin::settings()['native_pagination'] ),
				'parentCountDisplay' => Plugin::settings()['parent_count_display'],
				'maxCollectionDepth' => Plugin::etch_collection_depth(),
				'galleryEnabled' => $gallery_enabled,
				'canCustomizeAppearance' => current_user_can( 'manage_options' ),
				'appearance'    => $settings['appearance'],
				'palette'       => $palette,
				'paletteDefaults' => Plugin::appearance_defaults(),
				'initialAttachmentId' => $initial_attachment_id,
				'initialAction' => $initial_action,
				'initialCollectionId' => $initial_collection_id,
				'openGalleryGenerator' => $gallery_enabled && isset( $_GET['mbe_gallery_generator'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['mbe_gallery_generator'] ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This only opens a non-mutating interface panel.
				'canManage'    => $taxonomy && current_user_can( $taxonomy->cap->manage_terms ),
				'deletionSync' => ! empty( Plugin::settings()['sync_deletions'] ) && 'native' !== Plugin::settings()['provider'],
				'strings'      => array(
					'add'                  => __( 'Add to collection', 'media-bridge-for-etch' ),
					'allMedia'             => __( 'All media', 'media-bridge-for-etch' ),
					'attachmentDetails'    => __( 'Attachment details', 'media-bridge-for-etch' ),
					'altSaved'             => __( 'Attachment saved.', 'media-bridge-for-etch' ),
					'altTextPresent'       => __( 'Alt text provided', 'media-bridge-for-etch' ),
					'cimoOptimized'        => __( 'Optimized by Cimo', 'media-bridge-for-etch' ),
					'exifAvailable'        => __( 'EXIF data available', 'media-bridge-for-etch' ),
					'cimoOptimizedLabel'   => __( 'Optimized by Cimo', 'media-bridge-for-etch' ),
					'optimizedLabel'       => __( 'Optimized media', 'media-bridge-for-etch' ),
					'attachedToContent'    => __( 'Attached to content', 'media-bridge-for-etch' ),
					'attachmentSaved'      => __( 'Attachment changes saved.', 'media-bridge-for-etch' ),
					'clear'                => __( 'Move to Uncategorized', 'media-bridge-for-etch' ),
					'chooseCollection'     => __( 'Choose a collection…', 'media-bridge-for-etch' ),
					'collections'          => __( 'Collections', 'media-bridge-for-etch' ),
					'collectionName'       => __( 'Collection name', 'media-bridge-for-etch' ),
					'copied'               => __( 'File path copied.', 'media-bridge-for-etch' ),
					'confirmDelete'        => __( 'Delete this collection? Media files will remain in the library.', 'media-bridge-for-etch' ),
					'confirmDeleteSynced'  => __( 'Delete this collection? Media files will remain, but the mapped collection may also be deleted by the active bridge.', 'media-bridge-for-etch' ),
					'confirmBulkCollections' => __( 'Delete the selected collections? Their media files will remain in the library.', 'media-bridge-for-etch' ),
					/* translators: 1: parent collection name, 2: number of child collections. */
					'childDeletePrompt'    => __( '“%1$s” contains %2$d child collections. Choose what should happen to those children. Media files will remain in the WordPress library.', 'media-bridge-for-etch' ),
					/* translators: %d is the number of unselected child collections. */
					'childDeletePromptBulk' => __( 'The selected parent collections contain %d child collections that are not selected. Choose what should happen to those children. Media files will remain in the WordPress library.', 'media-bridge-for-etch' ),
					'confirmDeleteMedia'   => __( 'Permanently delete this media item and its files? This cannot be undone.', 'media-bridge-for-etch' ),
					/* translators: %d is the number of selected media items. */
					'confirmDeleteMediaBulk' => __( 'Permanently delete %d selected media items and their files? This cannot be undone.', 'media-bridge-for-etch' ),
					'confirmDeleteUsed'    => __( 'This media item is referenced by site content. Permanently deleting it may break images in pages, posts, templates, or other content. Choose OK only if you understand the impact and still want to delete it.', 'media-bridge-for-etch' ),
					/* translators: 1: number of selected media items that appear to be in use, 2: total number of selected media items. */
					'confirmDeleteUsedBulk' => __( '%1$d of %2$d selected media items are referenced by site content. Permanently deleting the selection may break images in pages, posts, templates, or other content. Choose OK only if you understand the impact and still want to delete the selected items.', 'media-bridge-for-etch' ),
					'createCollection'     => __( 'Create collection', 'media-bridge-for-etch' ),
					'createdCollections'   => __( 'Collections added.', 'media-bridge-for-etch' ),
					'deleteCollection'     => __( 'Delete collection', 'media-bridge-for-etch' ),
					'collectionDeleted'    => __( 'Collection deleted. Media files were not removed.', 'media-bridge-for-etch' ),
					'deleteMedia'          => __( 'Delete permanently', 'media-bridge-for-etch' ),
					'editImage'            => __( 'Edit image', 'media-bridge-for-etch' ),
					'deletedMedia'         => __( 'Media deleted permanently.', 'media-bridge-for-etch' ),
					'deletedMediaPartial'  => __( 'Some selected media could not be deleted.', 'media-bridge-for-etch' ),
					'deletedCollections'   => __( 'Collections deleted. Media files were not removed.', 'media-bridge-for-etch' ),
					'editCollection'       => __( 'Edit collection', 'media-bridge-for-etch' ),
					'expandCollection'     => __( 'Expand collection', 'media-bridge-for-etch' ),
					'collapseCollection'   => __( 'Collapse collection', 'media-bridge-for-etch' ),
					'empty'                => __( 'No media found in this view.', 'media-bridge-for-etch' ),
					'error'                => __( 'Something went wrong. Please try again.', 'media-bridge-for-etch' ),
					'loading'              => __( 'Loading media…', 'media-bridge-for-etch' ),
					'loadingMore'          => __( 'Loading more media…', 'media-bridge-for-etch' ),
					/* translators: %d is the number of matching media items. */
					'resultCount'          => __( '%d media item found.', 'media-bridge-for-etch' ),
					/* translators: %d is the number of matching media items. */
					'resultsCount'         => __( '%d media items found.', 'media-bridge-for-etch' ),
					/* translators: 1: number of matching media items, 2: search text. */
					'searchResult'         => __( '%1$d media item found for “%2$s”.', 'media-bridge-for-etch' ),
					/* translators: 1: number of matching media items, 2: search text. */
					'searchResults'        => __( '%1$d media items found for “%2$s”.', 'media-bridge-for-etch' ),
					'newCollection'        => __( 'New collection', 'media-bridge-for-etch' ),
					'newSubcollection'     => __( 'New sub-collection', 'media-bridge-for-etch' ),
					'subcollections'       => __( 'Subcollections', 'media-bridge-for-etch' ),
					'nextAttachment'       => __( 'Next attachment', 'media-bridge-for-etch' ),
					'noParent'             => __( 'Top level', 'media-bridge-for-etch' ),
					'parentCollection'     => __( 'Parent collection', 'media-bridge-for-etch' ),
					'previousAttachment'   => __( 'Previous attachment', 'media-bridge-for-etch' ),
					'fileType'             => __( 'File type', 'media-bridge-for-etch' ),
					'optimizationStatus'   => __( 'Optimization', 'media-bridge-for-etch' ),
					'cimoOptimized'        => __( 'Optimized', 'media-bridge-for-etch' ),
					'cimoNotOptimized'     => __( 'Not optimized', 'media-bridge-for-etch' ),
					'finishUpload'         => __( 'Add media', 'media-bridge-for-etch' ),
					'remove'               => __( 'Remove from collection', 'media-bridge-for-etch' ),
					'reorderInstructions'  => __( 'Drag to reorder, or press Alt plus the Up or Down arrow key.', 'media-bridge-for-etch' ),
					'moveItemHere' => __( 'Move item here', 'media-bridge-for-etch' ),
					/* translators: %d is the number of selected media items. */
					'moveItemsHere' => __( 'Move %d items here', 'media-bridge-for-etch' ),
					/* translators: %d is the number of selected media items. */
					'movingItems' => __( 'Moving %d items', 'media-bridge-for-etch' ),
					'reorderMedia'         => __( 'Reorder', 'media-bridge-for-etch' ),
					'doneReordering'       => __( 'Done reordering', 'media-bridge-for-etch' ),
					'mediaOrderSaved'      => __( 'Media order saved.', 'media-bridge-for-etch' ),
					'mediaOrderHelp'       => __( 'Drag items before or after another item to reorder. Or focus an image and press Alt + an arrow key. Changes save automatically for this collection only.', 'media-bridge-for-etch' ),
					'orderSaved'           => __( 'Collection order saved.', 'media-bridge-for-etch' ),
					'saveCollection'       => __( 'Collection saved.', 'media-bridge-for-etch' ),
					'saveAltText'          => __( 'Save changes', 'media-bridge-for-etch' ),
					'selectMedia'          => __( 'Select', 'media-bridge-for-etch' ),
					'selected'             => __( 'selected', 'media-bridge-for-etch' ),
					'selectHelp'           => __( 'Select this item. Shift-click to select a range.', 'media-bridge-for-etch' ),
					'uncategorized'        => __( 'Uncategorized', 'media-bridge-for-etch' ),
					'upload'               => __( 'Select or Upload Media', 'media-bridge-for-etch' ),
					'gridView'             => __( 'Grid view', 'media-bridge-for-etch' ),
					'listView'             => __( 'List view', 'media-bridge-for-etch' ),
					'masonryView'          => __( 'Masonry view', 'media-bridge-for-etch' ),
					'allIssues'            => __( 'All issues', 'media-bridge-for-etch' ),
					'allIssuesDescription' => __( 'Media with one or more health issues.', 'media-bridge-for-etch' ),
					'brokenFile'           => __( 'Broken file', 'media-bridge-for-etch' ),
					'brokenFileDescription' => __( 'The attachment file is missing, unreadable, or empty.', 'media-bridge-for-etch' ),
					'missingAlt'           => __( 'Missing alt text', 'media-bridge-for-etch' ),
					'missingAltDescription' => __( 'Images without alt text that are not marked as decorative.', 'media-bridge-for-etch' ),
					'missingSizes'         => __( 'Missing image sizes', 'media-bridge-for-etch' ),
					'missingSizesDescription' => __( 'Images missing one or more sizes registered with WordPress.', 'media-bridge-for-etch' ),
					'oversized'            => __( 'Oversized', 'media-bridge-for-etch' ),
					'oversizedDescription' => __( 'Size limits: images 1 MB, fonts 0.5 MB, documents 5 MB, audio 10 MB, and video 50 MB.', 'media-bridge-for-etch' ),
					'suspectedDuplicates'       => __( 'Suspected duplicates', 'media-bridge-for-etch' ),
					'suspectedDuplicatesDescription' => __( 'Files whose contents match another attachment. Different titles or filenames may be intentional, so review the matching attachments before deleting anything.', 'media-bridge-for-etch' ),
					'obsoleteFormat'       => __( 'Obsolete format', 'media-bridge-for-etch' ),
					'obsoleteFormatDescription' => __( 'BMP or TIFF media that should be converted to a modern web format.', 'media-bridge-for-etch' ),
					'decorative'           => __( 'Decorative', 'media-bridge-for-etch' ),
					'decorativeDescription' => __( 'Images intentionally marked as decorative.', 'media-bridge-for-etch' ),
					'healthy'              => __( 'Healthy', 'media-bridge-for-etch' ),
					'healthyDescription'   => __( 'Media with none of the file, metadata, size, format, or duplicate issues checked here.', 'media-bridge-for-etch' ),
					'scannedNow'           => __( 'Scanned just now', 'media-bridge-for-etch' ),
					'galleryChoose'         => __( 'Choose a collection to preview its gallery.', 'media-bridge-for-etch' ),
					'shortcodeCopied'       => __( 'Shortcode copied.', 'media-bridge-for-etch' ),
					'appearanceSaved'       => __( 'Appearance saved.', 'media-bridge-for-etch' ),
					'appearanceAdjusted'    => __( 'Appearance saved. Some colors were restored to accessible values.', 'media-bridge-for-etch' ),
					'appearanceExported'    => __( 'Appearance exported.', 'media-bridge-for-etch' ),
					'appearanceImported'    => __( 'Appearance imported for preview. Save to apply it.', 'media-bridge-for-etch' ),
					'appearanceImportError' => __( 'That file is not a valid Media Bridge appearance export.', 'media-bridge-for-etch' ),
					/* translators: %s: Contrast ratio, for example 4.50:1. */
					'contrastPasses'        => __( 'Passes WCAG AA contrast at %s.', 'media-bridge-for-etch' ),
					/* translators: %s: Contrast ratio, for example 3.25:1. */
					'contrastFails'         => __( 'Fails WCAG AA contrast at %s.', 'media-bridge-for-etch' ),
				),
			)
		);
	}

	/**
	 * Let attachment-field providers load controls needed by their modal fields.
	 */
	private function enqueue_attachment_compatibility_assets(): void {
		/**
		 * Fires while the Media Bridge manager enqueues its attachment detail UI.
		 * Providers that use attachment_fields_to_edit may enqueue field assets here.
		 */
		do_action( 'uplink_mbe_enqueue_attachment_compatibility_assets' );

		// Meta Box exposes attachment fields through WordPress's compatibility API,
		// but normally enqueues their controls only on the native attachment screen.
		if ( ! function_exists( 'rwmb_get_registry' ) ) {
			return;
		}
		$registry = rwmb_get_registry( 'meta_box' );
		if ( ! is_object( $registry ) || ! method_exists( $registry, 'all' ) ) {
			return;
		}
		foreach ( $registry->all() as $meta_box ) {
			$config = is_object( $meta_box ) && isset( $meta_box->meta_box ) && is_array( $meta_box->meta_box ) ? $meta_box->meta_box : array();
			$post_types = isset( $config['post_types'] ) && is_array( $config['post_types'] ) ? $config['post_types'] : array();
			if ( in_array( 'attachment', $post_types, true ) && ! empty( $config['media_modal'] ) && method_exists( $meta_box, 'enqueue' ) ) {
				$meta_box->enqueue();
			}
		}
	}

	public function render(): void {
		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}
		$taxonomy     = get_taxonomy( $this->etch->taxonomy() );
		$can_manage   = $taxonomy && current_user_can( $taxonomy->cap->manage_terms );
		$appearance      = Plugin::settings()['appearance'];
		$gallery_enabled = ! empty( Plugin::settings()['collection_gallery'] );
		$manager_label = $this->manager_label();
		$palette_fields  = $this->palette_fields();
		$contrast_fields = $this->palette_contrast_fields();
		$palette_settings = Plugin::settings();
		/* translators: %s is the administrator-defined media manager label. */
		$about_label = sprintf( __( 'About %s', 'media-bridge-for-etch' ), $manager_label );
		/* translators: %s is the administrator-defined media manager label. */
		$info_description = sprintf( __( 'Organize WordPress media directly with %s. An item can belong to more than one collection.', 'media-bridge-for-etch' ), $manager_label );
		?>
		<div class="wrap uplink-mbe-native-wrap uplink-mbe-theme-<?php echo esc_attr( $appearance ); ?>">
			<?php do_action( 'uplink_mbe_loop_dialog' ); ?>
			<div class="uplink-mbe-native-heading">
				<div class="uplink-mbe-native-title-group">
					<div class="uplink-mbe-native-title-row">
					<h1><?php echo esc_html( $manager_label ); ?></h1>
					<button type="button" class="uplink-mbe-info-toggle" id="uplink-mbe-info-toggle" aria-expanded="false" aria-controls="uplink-mbe-info-popup" aria-label="<?php echo esc_attr( $about_label ); ?>">
							<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
						</button>
					</div>
					<div class="uplink-mbe-info-popup" id="uplink-mbe-info-popup" role="region" aria-labelledby="uplink-mbe-info-title" hidden>
					<h2 id="uplink-mbe-info-title"><?php echo esc_html( $about_label ); ?></h2>
						<p class="uplink-mbe-version"><?php
							/* translators: %s is the installed plugin version. */
							printf( esc_html__( 'Uplink Media Bridge for Etch version %s', 'media-bridge-for-etch' ), esc_html( UPLINK_MBE_VERSION ) );
						?></p>
						<p><?php echo esc_html( $info_description ); ?></p>
						<p><?php esc_html_e( 'Open an image to review its details. Use the footer checkbox, Ctrl/Command-click to select individual images, or Shift-click to select a range for bulk actions.', 'media-bridge-for-etch' ); ?></p>
						<div class="uplink-mbe-alt-legend"><img src="<?php echo esc_url( UPLINK_MBE_URL . 'assets/universal-access-circle-stroke-sharp.svg' ); ?>" alt="" aria-hidden="true"><span><?php esc_html_e( 'Accessibility icon: alt text is present', 'media-bridge-for-etch' ); ?></span></div>
					</div>
				</div>
				<div class="uplink-mbe-native-heading-actions">
					<?php if ( current_user_can( 'manage_options' ) && current_user_can( 'edit_posts' ) ) : ?>
					<button type="button" class="uplink-mbe-icon-button" id="uplink-mbe-open-loop-generator" aria-haspopup="dialog" aria-controls="uplink-mbe-loop-dialog" aria-label="<?php esc_attr_e( 'Generate Etch loop', 'media-bridge-for-etch' ); ?>" title="<?php esc_attr_e( 'Generate Etch loop', 'media-bridge-for-etch' ); ?>"><svg aria-hidden="true" style="stroke:none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" color="currentColor" fill="none">
    <defs></defs>
    <path fill="currentColor" d="M11.951,13.977 C11.619,14.378 11.211,14.805 10.752,15.138 C10.301,15.465 9.731,15.75 9.092,15.75 C6.913,15.75 5.631,13.962 5.631,12 C5.631,10.038 6.913,8.25 9.092,8.25 C9.731,8.25 10.301,8.535 10.752,8.862 C11.211,9.195 11.619,9.622 11.951,10.023 C12.117,10.224 12.268,10.424 12.402,10.611 C12.536,10.424 12.687,10.224 12.853,10.023 C13.185,9.622 13.592,9.195 14.051,8.862 C14.503,8.535 15.073,8.25 15.712,8.25 C17.89,8.25 19.173,10.038 19.173,12 C19.173,13.962 17.89,15.75 15.712,15.75 C15.073,15.75 14.503,15.465 14.051,15.138 C13.592,14.805 13.185,14.378 12.853,13.977 C12.687,13.776 12.536,13.576 12.402,13.389 C12.268,13.576 12.117,13.776 11.951,13.977 Z M23.185,21.75 L18.42,21.75 L18.42,20.25 L21.68,20.25 L21.68,3.75 L18.42,3.75 L18.42,2.25 L23.185,2.25 Z M6.384,2.25 L6.384,3.75 L3.124,3.75 L3.124,20.25 L6.384,20.25 L6.384,21.75 L1.619,21.75 L1.619,2.25 Z M7.136,12 C7.136,13.351 7.946,14.25 9.092,14.25 C9.283,14.25 9.543,14.16 9.868,13.925 C10.185,13.695 10.501,13.372 10.79,13.023 C11.075,12.678 11.315,12.329 11.485,12.065 L11.526,12 L11.485,11.935 C11.315,11.671 11.075,11.322 10.79,10.977 C10.501,10.628 10.185,10.305 9.868,10.075 C9.543,9.84 9.283,9.75 9.092,9.75 C7.946,9.75 7.136,10.649 7.136,12 Z M13.319,12.065 C13.489,12.329 13.729,12.678 14.014,13.023 C14.302,13.372 14.618,13.695 14.936,13.925 C15.261,14.16 15.52,14.25 15.712,14.25 C16.857,14.25 17.668,13.351 17.668,12 C17.668,10.649 16.857,9.75 15.712,9.75 C15.52,9.75 15.261,9.84 14.936,10.075 C14.618,10.305 14.302,10.628 14.014,10.977 C13.729,11.322 13.489,11.671 13.319,11.935 L13.278,12 Z"></path>
</svg></button>
					<?php endif; ?>
					<?php if ( current_user_can( 'upload_files' ) ) : ?>
						<button type="button" class="uplink-mbe-icon-button is-primary" id="uplink-mbe-upload-media" aria-label="<?php esc_attr_e( 'Upload media', 'media-bridge-for-etch' ); ?>" title="<?php esc_attr_e( 'Upload media', 'media-bridge-for-etch' ); ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4"></path><path d="m7 9 5-5 5 5"></path><path d="M5 14v5h14v-5"></path></svg></button>
					<?php endif; ?>
					<?php if ( current_user_can( 'manage_options' ) ) : ?>
						<button type="button" class="uplink-mbe-icon-button" id="uplink-mbe-appearance-toggle" aria-expanded="false" aria-controls="uplink-mbe-appearance-panel" aria-label="<?php esc_attr_e( 'Customize appearance', 'media-bridge-for-etch' ); ?>" title="<?php esc_attr_e( 'Customize appearance', 'media-bridge-for-etch' ); ?>"><span class="uplink-mbe-palette-icon" aria-hidden="true"></span></button>
						<a class="uplink-mbe-icon-button" href="<?php echo esc_url( admin_url( 'upload.php?page=media-bridge-for-etch' ) ); ?>" aria-label="<?php esc_attr_e( 'Media Bridge settings', 'media-bridge-for-etch' ); ?>" title="<?php esc_attr_e( 'Media Bridge settings', 'media-bridge-for-etch' ); ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3A1.7 1.7 0 0 0 10 3V2.8h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z"></path></svg></a>
					<?php endif; ?>
				</div>
				<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<div class="uplink-mbe-appearance-panel" id="uplink-mbe-appearance-panel" role="dialog" aria-modal="false" aria-labelledby="uplink-mbe-appearance-title" hidden>
					<div class="uplink-mbe-appearance-header"><div><h2 id="uplink-mbe-appearance-title"><?php esc_html_e( 'Customize appearance', 'media-bridge-for-etch' ); ?></h2><p><?php esc_html_e( 'Changes preview in the manager as you edit.', 'media-bridge-for-etch' ); ?></p></div><button type="button" class="uplink-mbe-popover-close" data-appearance-close aria-label="<?php esc_attr_e( 'Close appearance settings', 'media-bridge-for-etch' ); ?>">&times;</button></div>
					<form id="uplink-mbe-appearance-form">
						<div class="uplink-mbe-appearance-scroll">
						<label class="uplink-mbe-appearance-select" for="uplink-mbe-appearance"><span><?php esc_html_e( 'Default appearance', 'media-bridge-for-etch' ); ?></span><select id="uplink-mbe-appearance" name="appearance"><option value="auto" <?php selected( $appearance, 'auto' ); ?>><?php esc_html_e( 'Auto, match device', 'media-bridge-for-etch' ); ?></option><option value="light" <?php selected( $appearance, 'light' ); ?>><?php esc_html_e( 'Light', 'media-bridge-for-etch' ); ?></option><option value="dark" <?php selected( $appearance, 'dark' ); ?>><?php esc_html_e( 'Dark', 'media-bridge-for-etch' ); ?></option></select></label>
						<div class="uplink-mbe-palette-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Palette preview', 'media-bridge-for-etch' ); ?>"><button type="button" class="uplink-mbe-palette-tab" role="tab" id="uplink-mbe-palette-light-tab" data-palette-tab="light" aria-selected="true" aria-controls="uplink-mbe-palette-light"><?php esc_html_e( 'Light palette', 'media-bridge-for-etch' ); ?></button><button type="button" class="uplink-mbe-palette-tab" role="tab" id="uplink-mbe-palette-dark-tab" data-palette-tab="dark" aria-selected="false" aria-controls="uplink-mbe-palette-dark" tabindex="-1"><?php esc_html_e( 'Dark palette', 'media-bridge-for-etch' ); ?></button></div>
						<?php foreach ( array( 'light', 'dark' ) as $mode ) : ?>
						<div class="uplink-mbe-appearance-palette" id="uplink-mbe-palette-<?php echo esc_attr( $mode ); ?>" role="tabpanel" aria-labelledby="uplink-mbe-palette-<?php echo esc_attr( $mode ); ?>-tab" data-palette-panel="<?php echo esc_attr( $mode ); ?>" <?php echo 'dark' === $mode ? 'hidden' : ''; ?>>
							<?php foreach ( $palette_fields as $group_label => $fields ) : ?>
							<fieldset class="uplink-mbe-palette-group"><legend><?php echo esc_html( $group_label ); ?></legend>
								<?php foreach ( $fields as $name => $label ) : $key = $mode . '_' . $name; ?>
								<label class="uplink-mbe-color-control" for="uplink-mbe-color-<?php echo esc_attr( str_replace( '_', '-', $key ) ); ?>"><span><?php echo esc_html( $label ); ?></span><input type="color" id="uplink-mbe-color-<?php echo esc_attr( str_replace( '_', '-', $key ) ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $palette_settings[ $key ] ); ?>" data-palette-color="<?php echo esc_attr( $name ); ?>" data-palette-mode="<?php echo esc_attr( $mode ); ?>"><span class="uplink-mbe-color-value"><output><?php echo esc_html( strtoupper( $palette_settings[ $key ] ) ); ?></output><?php if ( isset( $contrast_fields[ $name ] ) ) : ?><span class="uplink-mbe-contrast-indicator" data-contrast-for="<?php echo esc_attr( $name ); ?>"><span class="uplink-mbe-contrast-dot" aria-hidden="true"></span><span class="uplink-mbe-contrast-ratio"></span></span><?php endif; ?></span></label>
								<?php endforeach; ?>
							</fieldset>
							<?php endforeach; ?>
						</div>
						<?php endforeach; ?>
						<p class="uplink-mbe-appearance-help"><?php esc_html_e( 'Saving restores any foreground colors that fall below WCAG 2.2 AA contrast.', 'media-bridge-for-etch' ); ?></p>
						</div>
						<div class="uplink-mbe-appearance-actions"><button type="button" class="button" id="uplink-mbe-reset-palette"><?php esc_html_e( 'Reset palette', 'media-bridge-for-etch' ); ?></button><div class="uplink-mbe-appearance-transfer"><button type="button" class="button" id="uplink-mbe-export-appearance"><?php esc_html_e( 'Export', 'media-bridge-for-etch' ); ?></button><button type="button" class="button" id="uplink-mbe-import-appearance"><?php esc_html_e( 'Import', 'media-bridge-for-etch' ); ?></button><input type="file" id="uplink-mbe-import-appearance-file" class="screen-reader-text" accept=".json,application/json" aria-label="<?php esc_attr_e( 'Choose an appearance JSON file', 'media-bridge-for-etch' ); ?>"></div><span class="spinner" id="uplink-mbe-appearance-spinner"></span><div class="uplink-mbe-appearance-commit"><button type="button" class="button" data-appearance-close><?php esc_html_e( 'Cancel', 'media-bridge-for-etch' ); ?></button><button type="submit" class="button button-primary"><?php esc_html_e( 'Save appearance', 'media-bridge-for-etch' ); ?></button></div></div><p id="uplink-mbe-appearance-status" class="screen-reader-text" role="status" aria-live="polite"></p>
					</form>
				</div>
				<?php endif; ?>
			</div>

			<?php if ( ! $this->etch->is_available() ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'Etch Collections are not available. Activate Etch before using this screen.', 'media-bridge-for-etch' ); ?></p></div>
			<?php else : ?>
				<div id="uplink-mbe-native-notice" class="notice inline" role="status" aria-live="polite" hidden><p></p></div>
				<div id="uplink-mbe-native-toast" class="uplink-mbe-native-toast" role="status" aria-live="polite" aria-atomic="true" hidden></div>
				<div class="uplink-mbe-native-shell" id="uplink-mbe-native-library">
					<aside class="uplink-mbe-native-sidebar" aria-label="<?php esc_attr_e( 'Media collections', 'media-bridge-for-etch' ); ?>">
						<div class="uplink-mbe-library-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Media workspace', 'media-bridge-for-etch' ); ?>"><button type="button" id="uplink-mbe-library-tab" role="tab" aria-selected="true"><?php esc_html_e( 'Library', 'media-bridge-for-etch' ); ?></button><button type="button" id="uplink-mbe-health-tab" role="tab" aria-selected="false"><?php esc_html_e( 'Health', 'media-bridge-for-etch' ); ?> <span id="uplink-mbe-health-tab-count">0</span></button></div>
						<div id="uplink-mbe-library-navigation">
							<div class="uplink-mbe-collection-heading">
								<h2><?php esc_html_e( 'Collections', 'media-bridge-for-etch' ); ?></h2>
								<div class="uplink-mbe-collection-heading-actions">
									<?php if ( $gallery_enabled ) : ?>
										<button type="button" class="uplink-mbe-icon-button" id="uplink-mbe-create-gallery" aria-label="<?php esc_attr_e( 'Create gallery', 'media-bridge-for-etch' ); ?>" title="<?php esc_attr_e( 'Create gallery', 'media-bridge-for-etch' ); ?>" disabled><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"></rect><path d="m7 15 3-3 3 3 2-2 3 3"></path><circle cx="8.5" cy="8.5" r="1.5"></circle><path d="M18 2v4M16 4h4"></path></svg></button>
									<?php endif; ?>
									<?php if ( $can_manage ) : ?>
										<button type="button" class="uplink-mbe-icon-button" id="uplink-mbe-new-collection" aria-label="<?php esc_attr_e( 'New collection', 'media-bridge-for-etch' ); ?>" title="<?php esc_attr_e( 'New collection', 'media-bridge-for-etch' ); ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7h7l2 2h9v10H3z"></path><path d="M12 12v5M9.5 14.5h5"></path></svg></button>
										<button type="button" class="uplink-mbe-icon-button" id="uplink-mbe-manage-collections" aria-label="<?php esc_attr_e( 'Manage collections', 'media-bridge-for-etch' ); ?>" title="<?php esc_attr_e( 'Manage collections', 'media-bridge-for-etch' ); ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h10M18 7h2M4 17h2M10 17h10"></path><circle cx="16" cy="7" r="2"></circle><circle cx="8" cy="17" r="2"></circle></svg></button>
									<?php endif; ?>
								</div>
							</div>
							<nav id="uplink-mbe-collection-tree"></nav>
						</div>
						<nav id="uplink-mbe-health-navigation" class="uplink-mbe-health-navigation" aria-label="<?php esc_attr_e( 'Media health filters', 'media-bridge-for-etch' ); ?>" hidden></nav>
						<p id="uplink-mbe-health-description" class="uplink-mbe-health-description" role="status" aria-live="polite" hidden></p>
					</aside>

					<main class="uplink-mbe-native-content">
						<div class="uplink-mbe-health-summary" id="uplink-mbe-health-summary" hidden><strong><?php esc_html_e( 'Media health', 'media-bridge-for-etch' ); ?></strong><div class="uplink-mbe-health-progress"><span id="uplink-mbe-health-progress-bar"></span></div><span id="uplink-mbe-health-status"></span><button type="button" class="uplink-mbe-toolbar-button" id="uplink-mbe-health-rescan" aria-label="<?php esc_attr_e( 'Rescan media health', 'media-bridge-for-etch' ); ?>" title="<?php esc_attr_e( 'Rescan media health', 'media-bridge-for-etch' ); ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11a8 8 0 1 0-2.3 5.7"></path><path d="M20 4v7h-7"></path></svg></button></div>
						<section class="uplink-mbe-library-drawer uplink-mbe-library-toolbar" id="uplink-mbe-library-drawer">
							<div class="uplink-mbe-library-drawer-bar">
								<form id="uplink-mbe-media-search" role="search">
									<label class="screen-reader-text" for="uplink-mbe-search-input"><?php esc_html_e( 'Search media', 'media-bridge-for-etch' ); ?></label>
									<input type="search" id="uplink-mbe-search-input" placeholder="<?php esc_attr_e( 'Search', 'media-bridge-for-etch' ); ?>" title="<?php esc_attr_e( 'Use * as a filename wildcard, for example *-1.png.', 'media-bridge-for-etch' ); ?>">
									<button type="submit" class="uplink-mbe-toolbar-button" aria-label="<?php esc_attr_e( 'Search media', 'media-bridge-for-etch' ); ?>" title="<?php esc_attr_e( 'Search media', 'media-bridge-for-etch' ); ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg></button>
								</form>
								<button type="button" class="uplink-mbe-toolbar-button uplink-mbe-library-drawer-toggle" id="uplink-mbe-library-drawer-toggle" aria-expanded="false" aria-controls="uplink-mbe-library-drawer-panel" aria-label="<?php esc_attr_e( 'Open filters', 'media-bridge-for-etch' ); ?>" title="<?php esc_attr_e( 'Filter media', 'media-bridge-for-etch' ); ?>">
									<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16l-6 7v6l-4 2v-8z"></path></svg>
									<span class="uplink-mbe-library-drawer-summary" id="uplink-mbe-library-drawer-summary" aria-live="polite"></span>
								</button>
								<button type="button" class="uplink-mbe-toolbar-button" id="uplink-mbe-reorder-media" aria-pressed="false" aria-label="<?php esc_attr_e( 'Reorder', 'media-bridge-for-etch' ); ?>" title="<?php esc_attr_e( 'Reorder', 'media-bridge-for-etch' ); ?>" hidden><svg aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" color="currentColor" fill="none"><path d="M22.75 20.75H13.25V13.25H22.75V20.75ZM10.75 7.75H7C4.65279 7.75 2.75 9.65279 2.75 12C2.75 14.3472 4.65279 16.25 7 16.25H8.18945L6.4375 14.498L7.49805 13.4375L11.0586 16.998L7.49805 20.5586L6.4375 19.498L8.18555 17.75H7C3.82436 17.75 1.25 15.1756 1.25 12C1.25 8.82436 3.82436 6.25 7 6.25H10.75V7.75ZM14.75 19.25H21.25V14.75H14.75V19.25ZM22.75 10.75H13.25V3.25H22.75V10.75ZM14.75 9.25H21.25V4.75H14.75V9.25Z" fill="currentColor"></path></svg></button>
								<div class="uplink-mbe-view-switch" role="group" aria-label="<?php esc_attr_e( 'View mode', 'media-bridge-for-etch' ); ?>">
									<button type="button" class="uplink-mbe-view-button" id="uplink-mbe-list-view" aria-label="<?php esc_attr_e( 'List view', 'media-bridge-for-etch' ); ?>" title="<?php esc_attr_e( 'List view', 'media-bridge-for-etch' ); ?>" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 6h12M8 12h12M8 18h12"></path><circle cx="4" cy="6" r="1"></circle><circle cx="4" cy="12" r="1"></circle><circle cx="4" cy="18" r="1"></circle></svg></button>
									<button type="button" class="uplink-mbe-view-button" id="uplink-mbe-grid-view" aria-label="<?php esc_attr_e( 'Grid view', 'media-bridge-for-etch' ); ?>" title="<?php esc_attr_e( 'Grid view', 'media-bridge-for-etch' ); ?>" aria-pressed="true"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="3" width="7" height="7" rx="1"></rect><rect x="3" y="14" width="7" height="7" rx="1"></rect><rect x="14" y="14" width="7" height="7" rx="1"></rect></svg></button>
									<button type="button" class="uplink-mbe-view-button" id="uplink-mbe-masonry-view" aria-label="<?php esc_attr_e( 'Masonry view', 'media-bridge-for-etch' ); ?>" title="<?php esc_attr_e( 'Masonry view', 'media-bridge-for-etch' ); ?>" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="8" height="5" rx="1"></rect><rect x="14" y="3" width="7" height="9" rx="1"></rect><rect x="3" y="11" width="8" height="10" rx="1"></rect><rect x="14" y="15" width="7" height="6" rx="1"></rect></svg></button>
								</div>
								<button type="button" class="uplink-mbe-toolbar-button" id="uplink-mbe-display-options-toggle" aria-expanded="false" aria-controls="uplink-mbe-display-options" aria-label="<?php esc_attr_e( 'Open display options', 'media-bridge-for-etch' ); ?>" title="<?php esc_attr_e( 'Display options', 'media-bridge-for-etch' ); ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="5" r="1"></circle><circle cx="12" cy="12" r="1"></circle><circle cx="12" cy="19" r="1"></circle></svg></button>
							</div>
							<div class="uplink-mbe-library-drawer-panel uplink-mbe-toolbar-popover" id="uplink-mbe-library-drawer-panel" role="region" aria-label="<?php esc_attr_e( 'Media filters', 'media-bridge-for-etch' ); ?>" hidden>
								<div class="uplink-mbe-popover-heading"><h3><?php esc_html_e( 'Filter', 'media-bridge-for-etch' ); ?></h3><div class="uplink-mbe-popover-heading-actions"><button type="button" class="button" id="uplink-mbe-clear-filters" disabled><?php esc_html_e( 'Clear all', 'media-bridge-for-etch' ); ?></button><button type="button" class="uplink-mbe-popover-close" data-filter-close aria-label="<?php esc_attr_e( 'Close filters', 'media-bridge-for-etch' ); ?>">&times;</button></div></div>
								<div class="uplink-mbe-library-drawer-primary">
									<div class="uplink-mbe-library-controls" aria-label="<?php esc_attr_e( 'Media display options', 'media-bridge-for-etch' ); ?>">
									<label for="uplink-mbe-type-filter"><?php esc_html_e( 'Media type', 'media-bridge-for-etch' ); ?>
									<select id="uplink-mbe-type-filter">
										<option value=""><?php esc_html_e( 'All', 'media-bridge-for-etch' ); ?></option>
										<option value="image"><?php esc_html_e( 'Images', 'media-bridge-for-etch' ); ?></option>
										<option value="audio"><?php esc_html_e( 'Audio', 'media-bridge-for-etch' ); ?></option>
										<option value="video"><?php esc_html_e( 'Video', 'media-bridge-for-etch' ); ?></option>
										<option value="application"><?php esc_html_e( 'Documents', 'media-bridge-for-etch' ); ?></option>
									</select></label>
									<label for="uplink-mbe-mime-filter"><?php esc_html_e( 'MIME subtype', 'media-bridge-for-etch' ); ?><select id="uplink-mbe-mime-filter" data-all-mime-types="<?php esc_attr_e( 'All', 'media-bridge-for-etch' ); ?>"><option value=""><?php esc_html_e( 'All', 'media-bridge-for-etch' ); ?></option></select></label>
									<label for="uplink-mbe-uploader-filter"><?php esc_html_e( 'Uploaded by', 'media-bridge-for-etch' ); ?><select id="uplink-mbe-uploader-filter" data-all-uploaders="<?php esc_attr_e( 'All', 'media-bridge-for-etch' ); ?>"><option value=""><?php esc_html_e( 'All', 'media-bridge-for-etch' ); ?></option></select></label>
									<label for="uplink-mbe-attachment-status-filter"><?php esc_html_e( 'Attachment status', 'media-bridge-for-etch' ); ?><select id="uplink-mbe-attachment-status-filter"><option value=""><?php esc_html_e( 'All', 'media-bridge-for-etch' ); ?></option><option value="attached"><?php esc_html_e( 'Attached', 'media-bridge-for-etch' ); ?></option><option value="unattached"><?php esc_html_e( 'Unattached', 'media-bridge-for-etch' ); ?></option></select></label>
					<label for="uplink-mbe-optimization-status-filter"><?php esc_html_e( 'Optimization', 'media-bridge-for-etch' ); ?><select id="uplink-mbe-optimization-status-filter"><option value=""><?php esc_html_e( 'All', 'media-bridge-for-etch' ); ?></option><option value="optimized"><?php esc_html_e( 'Optimized', 'media-bridge-for-etch' ); ?></option><option value="not_optimized"><?php esc_html_e( 'Not optimized', 'media-bridge-for-etch' ); ?></option></select></label>
									<label for="uplink-mbe-uploaded-from-filter"><?php esc_html_e( 'Uploaded from', 'media-bridge-for-etch' ); ?><input type="date" id="uplink-mbe-uploaded-from-filter"></label>
									<label for="uplink-mbe-uploaded-to-filter"><?php esc_html_e( 'Uploaded to', 'media-bridge-for-etch' ); ?><input type="date" id="uplink-mbe-uploaded-to-filter"></label>
									<fieldset class="uplink-mbe-filter-range"><legend><?php esc_html_e( 'Width (px)', 'media-bridge-for-etch' ); ?></legend><div><input type="number" min="0" id="uplink-mbe-width-min-filter" placeholder="<?php esc_attr_e( 'Minimum', 'media-bridge-for-etch' ); ?>" aria-label="<?php esc_attr_e( 'Minimum width', 'media-bridge-for-etch' ); ?>"><input type="number" min="0" id="uplink-mbe-width-max-filter" placeholder="<?php esc_attr_e( 'Maximum', 'media-bridge-for-etch' ); ?>" aria-label="<?php esc_attr_e( 'Maximum width', 'media-bridge-for-etch' ); ?>"></div></fieldset>
									<fieldset class="uplink-mbe-filter-range"><legend><?php esc_html_e( 'Height (px)', 'media-bridge-for-etch' ); ?></legend><div><input type="number" min="0" id="uplink-mbe-height-min-filter" placeholder="<?php esc_attr_e( 'Minimum', 'media-bridge-for-etch' ); ?>" aria-label="<?php esc_attr_e( 'Minimum height', 'media-bridge-for-etch' ); ?>"><input type="number" min="0" id="uplink-mbe-height-max-filter" placeholder="<?php esc_attr_e( 'Maximum', 'media-bridge-for-etch' ); ?>" aria-label="<?php esc_attr_e( 'Maximum height', 'media-bridge-for-etch' ); ?>"></div></fieldset>
									<fieldset class="uplink-mbe-filter-range"><legend><?php esc_html_e( 'File size (MB)', 'media-bridge-for-etch' ); ?></legend><div><input type="number" min="0" step="0.1" id="uplink-mbe-file-size-min-filter" placeholder="<?php esc_attr_e( 'Minimum', 'media-bridge-for-etch' ); ?>" aria-label="<?php esc_attr_e( 'Minimum file size in megabytes', 'media-bridge-for-etch' ); ?>"><input type="number" min="0" step="0.1" id="uplink-mbe-file-size-max-filter" placeholder="<?php esc_attr_e( 'Maximum', 'media-bridge-for-etch' ); ?>" aria-label="<?php esc_attr_e( 'Maximum file size in megabytes', 'media-bridge-for-etch' ); ?>"></div></fieldset>
									<label class="uplink-mbe-filter-toggle" for="uplink-mbe-missing-alt-filter"><span><?php esc_html_e( 'Missing alt text', 'media-bridge-for-etch' ); ?></span><input type="checkbox" id="uplink-mbe-missing-alt-filter"></label>
									</div>
								</div>
							</div>
							<div class="uplink-mbe-toolbar-popover uplink-mbe-display-options" id="uplink-mbe-display-options" role="region" aria-label="<?php esc_attr_e( 'Display options', 'media-bridge-for-etch' ); ?>" hidden>
								<div class="uplink-mbe-popover-heading"><h3><?php esc_html_e( 'Display', 'media-bridge-for-etch' ); ?></h3><button type="button" class="uplink-mbe-popover-close" data-display-close aria-label="<?php esc_attr_e( 'Close display options', 'media-bridge-for-etch' ); ?>">&times;</button></div>
								<div class="uplink-mbe-grid-size-control"><label for="uplink-mbe-grid-size"><?php esc_html_e( 'Thumbnail size', 'media-bridge-for-etch' ); ?></label><input type="range" id="uplink-mbe-grid-size" min="180" max="400" step="10" value="240" aria-describedby="uplink-mbe-grid-size-value"><output id="uplink-mbe-grid-size-value" for="uplink-mbe-grid-size">240px</output></div>
								<div class="uplink-mbe-grid-ratio-control"><span><?php esc_html_e( 'Aspect ratio', 'media-bridge-for-etch' ); ?></span><div class="uplink-mbe-grid-ratio-options" role="group" aria-label="<?php esc_attr_e( 'Aspect ratio', 'media-bridge-for-etch' ); ?>"><button type="button" data-grid-ratio="16/9" aria-pressed="false"><?php esc_html_e( '16:9', 'media-bridge-for-etch' ); ?></button><button type="button" data-grid-ratio="4/3" aria-pressed="true"><?php esc_html_e( '4:3', 'media-bridge-for-etch' ); ?></button><button type="button" data-grid-ratio="1/1" aria-pressed="false"><?php esc_html_e( '1:1', 'media-bridge-for-etch' ); ?></button></div></div>
								<label class="uplink-mbe-display-toggle"><span><?php esc_html_e( 'File name', 'media-bridge-for-etch' ); ?></span><input type="checkbox" data-display-field="filename"></label>
								<label class="uplink-mbe-display-toggle"><span><?php esc_html_e( 'Author', 'media-bridge-for-etch' ); ?></span><input type="checkbox" data-display-field="author" checked></label>
								<label class="uplink-mbe-display-toggle"><span><?php esc_html_e( 'Date', 'media-bridge-for-etch' ); ?></span><input type="checkbox" data-display-field="date" checked></label>
								<label class="uplink-mbe-display-toggle"><span><?php esc_html_e( 'MIME type', 'media-bridge-for-etch' ); ?></span><input type="checkbox" data-display-field="mime" checked></label>
								<label class="uplink-mbe-display-toggle"><span><?php esc_html_e( 'File size', 'media-bridge-for-etch' ); ?></span><input type="checkbox" data-display-field="fileSize" checked></label>
								<label class="uplink-mbe-display-toggle"><span><?php esc_html_e( 'Dimensions', 'media-bridge-for-etch' ); ?></span><input type="checkbox" data-display-field="dimensions"></label>
								<label class="uplink-mbe-display-toggle"><span><?php esc_html_e( 'Show EXIF in inspector', 'media-bridge-for-etch' ); ?></span><input type="checkbox" data-display-field="exif"></label>
							</div>
							<div class="uplink-mbe-selection-tools" id="uplink-mbe-selection-tools" hidden>
									<span id="uplink-mbe-selection-count" aria-live="polite"></span>
									<span class="uplink-mbe-selection-help" id="uplink-mbe-selection-help"><?php esc_html_e( 'Choose a collection to enable its Add and Remove actions. Shift-click checkboxes to select a range.', 'media-bridge-for-etch' ); ?></span>
									<label class="screen-reader-text" for="uplink-mbe-bulk-collection"><?php esc_html_e( 'Choose a collection', 'media-bridge-for-etch' ); ?></label>
									<select id="uplink-mbe-bulk-collection" aria-describedby="uplink-mbe-selection-help"></select>
									<button type="button" class="button" id="uplink-mbe-bulk-add" disabled><?php esc_html_e( 'Add to collection', 'media-bridge-for-etch' ); ?></button>
									<button type="button" class="button" id="uplink-mbe-bulk-remove" disabled><?php esc_html_e( 'Remove from collection', 'media-bridge-for-etch' ); ?></button>
									<button type="button" class="button" id="uplink-mbe-bulk-clear"><?php esc_html_e( 'Uncategorize', 'media-bridge-for-etch' ); ?></button>
									<button type="button" class="button" id="uplink-mbe-bulk-deselect"><?php esc_html_e( 'Clear selection', 'media-bridge-for-etch' ); ?></button>
									<button type="button" class="button button-link-delete" id="uplink-mbe-bulk-delete"><?php esc_html_e( 'Delete permanently', 'media-bridge-for-etch' ); ?></button>
							</div>
						</section>
						<p id="uplink-mbe-search-status" class="screen-reader-text" role="status" aria-live="polite" aria-atomic="true"></p>
						<div class="uplink-mbe-media-scroll">
							<div id="uplink-mbe-media-grid" class="uplink-mbe-media-grid" aria-busy="false"></div>
							<div id="uplink-mbe-media-pagination" class="uplink-mbe-pagination"></div>
						</div>
					</main>
				</div>

				<div class="uplink-mbe-modal" id="uplink-mbe-collection-modal" hidden>
					<div class="uplink-mbe-modal-backdrop" data-modal-close></div>
					<div class="uplink-mbe-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="uplink-mbe-modal-title">
						<button type="button" class="uplink-mbe-modal-close" data-modal-close aria-label="<?php esc_attr_e( 'Close', 'media-bridge-for-etch' ); ?>">&times;</button>
						<h2 id="uplink-mbe-modal-title"><?php esc_html_e( 'New collection', 'media-bridge-for-etch' ); ?></h2>
						<form id="uplink-mbe-collection-form">
							<input type="hidden" id="uplink-mbe-collection-id" value="0">
							<label for="uplink-mbe-collection-name"><?php esc_html_e( 'Collection name', 'media-bridge-for-etch' ); ?></label>
							<input type="text" id="uplink-mbe-collection-name" required maxlength="200">
							<label for="uplink-mbe-collection-parent"><?php esc_html_e( 'Parent collection', 'media-bridge-for-etch' ); ?></label>
							<select id="uplink-mbe-collection-parent"></select>
							<div class="uplink-mbe-modal-actions">
								<button type="button" class="button" data-modal-close><?php esc_html_e( 'Cancel', 'media-bridge-for-etch' ); ?></button>
								<button type="submit" class="button button-primary"><?php esc_html_e( 'Save collection', 'media-bridge-for-etch' ); ?></button>
							</div>
						</form>
					</div>
				</div>

				<div class="uplink-mbe-attachment-inspector" id="uplink-mbe-attachment-modal" hidden>
					<div class="uplink-mbe-attachment-dialog" role="dialog" aria-modal="true" aria-labelledby="uplink-mbe-attachment-dialog-title">
						<header class="uplink-mbe-attachment-header">
							<h2 class="uplink-mbe-attachment-eyebrow" id="uplink-mbe-attachment-dialog-title"><?php esc_html_e( 'Attachment details', 'media-bridge-for-etch' ); ?></h2>
							<div class="uplink-mbe-attachment-header-actions">
								<button type="button" class="uplink-mbe-attachment-header-nav" id="uplink-mbe-attachment-previous" aria-label="<?php esc_attr_e( 'Previous attachment', 'media-bridge-for-etch' ); ?>"><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span></button>
								<button type="button" class="uplink-mbe-attachment-header-nav" id="uplink-mbe-attachment-next" aria-label="<?php esc_attr_e( 'Next attachment', 'media-bridge-for-etch' ); ?>"><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></button>
								<button type="button" class="uplink-mbe-attachment-header-nav" data-attachment-close aria-label="<?php esc_attr_e( 'Close', 'media-bridge-for-etch' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
							</div>
						</header>
						<div class="uplink-mbe-attachment-preview-wrap">
							<div class="uplink-mbe-attachment-preview" id="uplink-mbe-attachment-preview"></div>
						</div>
						<div class="uplink-mbe-attachment-info">
							<h3 id="uplink-mbe-attachment-title"></h3>
							<p class="uplink-mbe-attachment-position" id="uplink-mbe-attachment-position" aria-live="polite"></p>
							<div class="uplink-mbe-attachment-notice" id="uplink-mbe-attachment-notice" role="status" aria-live="polite" aria-atomic="true" hidden><p></p></div>
							<div class="uplink-mbe-attachment-metadata">
								<div class="uplink-mbe-attachment-metadata-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Attachment metadata', 'media-bridge-for-etch' ); ?>">
									<button type="button" id="uplink-mbe-attachment-main-tab" role="tab" aria-selected="true" aria-controls="uplink-mbe-attachment-main-panel"><?php esc_html_e( 'Main', 'media-bridge-for-etch' ); ?></button>
									<button type="button" id="uplink-mbe-attachment-file-tab" role="tab" aria-selected="false" aria-controls="uplink-mbe-attachment-file-panel" tabindex="-1"><?php esc_html_e( 'Metadata', 'media-bridge-for-etch' ); ?></button>
									<button type="button" id="uplink-mbe-attachment-exif-tab" role="tab" aria-selected="false" aria-controls="uplink-mbe-attachment-exif" tabindex="-1" hidden><?php esc_html_e( 'EXIF', 'media-bridge-for-etch' ); ?></button>
									<button type="button" id="uplink-mbe-attachment-optimization-tab" role="tab" aria-selected="false" aria-controls="uplink-mbe-attachment-optimization" tabindex="-1" hidden><?php esc_html_e( 'Optimization', 'media-bridge-for-etch' ); ?></button>
								</div>
								<div id="uplink-mbe-attachment-main-panel" class="uplink-mbe-attachment-metadata-panel uplink-mbe-attachment-section uplink-mbe-attachment-fields" role="tabpanel" aria-labelledby="uplink-mbe-attachment-main-tab">
									<label for="uplink-mbe-attachment-title-input"><?php esc_html_e( 'Title', 'media-bridge-for-etch' ); ?></label>
									<input type="text" id="uplink-mbe-attachment-title-input">
									<label for="uplink-mbe-attachment-alt"><?php esc_html_e( 'Alt text', 'media-bridge-for-etch' ); ?></label>
									<textarea id="uplink-mbe-attachment-alt" rows="3"></textarea>
									<label class="uplink-mbe-decorative-toggle"><input type="checkbox" id="uplink-mbe-attachment-decorative"> <?php esc_html_e( 'Decorative image', 'media-bridge-for-etch' ); ?></label>
									<label for="uplink-mbe-attachment-caption"><?php esc_html_e( 'Caption', 'media-bridge-for-etch' ); ?></label>
									<textarea id="uplink-mbe-attachment-caption" rows="3"></textarea>
									<label for="uplink-mbe-attachment-description"><?php esc_html_e( 'Description', 'media-bridge-for-etch' ); ?></label>
									<textarea id="uplink-mbe-attachment-description" rows="4"></textarea>
									<div class="uplink-mbe-attachment-compatibility" id="uplink-mbe-attachment-compatibility" hidden>
										<form id="uplink-mbe-attachment-compatibility-form"></form>
									</div>
									<fieldset class="uplink-mbe-attachment-collection-assignment">
										<legend><?php esc_html_e( 'Collections', 'media-bridge-for-etch' ); ?></legend>
										<div id="uplink-mbe-attachment-collection-options"></div>
										<p><?php esc_html_e( 'Leave every collection unchecked to place this item in Uncategorized.', 'media-bridge-for-etch' ); ?></p>
									</fieldset>
								</div>
								<div id="uplink-mbe-attachment-file-panel" class="uplink-mbe-attachment-metadata-panel uplink-mbe-attachment-section uplink-mbe-attachment-fields" role="tabpanel" aria-labelledby="uplink-mbe-attachment-file-tab" hidden>
									<dl class="uplink-mbe-attachment-data-list">
										<div><dt><?php esc_html_e( 'File name', 'media-bridge-for-etch' ); ?></dt><dd id="uplink-mbe-attachment-filename"></dd></div>
										<div><dt><?php esc_html_e( 'File path', 'media-bridge-for-etch' ); ?></dt><dd class="uplink-mbe-attachment-path-row"><code id="uplink-mbe-attachment-path"></code><button type="button" class="button-link dashicons dashicons-admin-page" id="uplink-mbe-attachment-copy" aria-label="<?php esc_attr_e( 'Copy file path', 'media-bridge-for-etch' ); ?>"></button></dd></div>
										<div><dt><?php esc_html_e( 'File type', 'media-bridge-for-etch' ); ?></dt><dd id="uplink-mbe-attachment-type"></dd></div>
										<div><dt><?php esc_html_e( 'Dimensions', 'media-bridge-for-etch' ); ?></dt><dd id="uplink-mbe-attachment-dimensions"></dd></div>
										<div><dt><?php esc_html_e( 'File size', 'media-bridge-for-etch' ); ?></dt><dd id="uplink-mbe-attachment-size"></dd></div>
										<div><dt><?php esc_html_e( 'Uploaded by', 'media-bridge-for-etch' ); ?></dt><dd id="uplink-mbe-attachment-author"></dd></div>
										<div><dt><?php esc_html_e( 'Uploaded', 'media-bridge-for-etch' ); ?></dt><dd id="uplink-mbe-attachment-date"></dd></div>
										<div><dt><?php esc_html_e( 'Attachment ID', 'media-bridge-for-etch' ); ?></dt><dd id="uplink-mbe-attachment-id"></dd></div>
									</dl>
								</div>
								<div class="uplink-mbe-attachment-metadata-panel uplink-mbe-attachment-exif" id="uplink-mbe-attachment-exif" role="tabpanel" aria-labelledby="uplink-mbe-attachment-exif-tab" hidden><dl></dl></div>
								<div class="uplink-mbe-attachment-metadata-panel uplink-mbe-optimization-details" id="uplink-mbe-attachment-optimization" role="tabpanel" aria-labelledby="uplink-mbe-attachment-optimization-tab" hidden><div class="uplink-mbe-optimization-groups" id="uplink-mbe-attachment-optimization-list"></div></div>
							</div>
							<div class="uplink-mbe-attachment-actions">
								<a href="#" class="button" id="uplink-mbe-attachment-edit" hidden><span class="dashicons dashicons-wordpress-alt" aria-hidden="true"></span><span><?php esc_html_e( 'Edit image', 'media-bridge-for-etch' ); ?></span></a>
								<button type="button" class="button button-primary" id="uplink-mbe-attachment-save-alt"><?php esc_html_e( 'Save changes', 'media-bridge-for-etch' ); ?></button>
								<button type="button" class="button button-link-delete" id="uplink-mbe-attachment-delete"><?php esc_html_e( 'Delete permanently', 'media-bridge-for-etch' ); ?></button>
							</div>
						</div>
					</div>
				</div>

				<div class="uplink-mbe-modal" id="uplink-mbe-collection-manager-modal" hidden>
					<div class="uplink-mbe-modal-backdrop" data-collection-manager-close></div>
					<div class="uplink-mbe-modal-dialog uplink-mbe-collection-manager-dialog" role="dialog" aria-modal="true" aria-labelledby="uplink-mbe-collection-manager-title">
						<button type="button" class="uplink-mbe-modal-close" data-collection-manager-close aria-label="<?php esc_attr_e( 'Close', 'media-bridge-for-etch' ); ?>">&times;</button>
						<h2 id="uplink-mbe-collection-manager-title"><?php esc_html_e( 'Manage collections', 'media-bridge-for-etch' ); ?></h2>
						<div class="uplink-mbe-manager-notice" id="uplink-mbe-manager-notice" role="status" aria-live="polite" hidden></div>
						<section class="uplink-mbe-collection-manager-section">
							<h3><?php esc_html_e( 'Add multiple collections', 'media-bridge-for-etch' ); ?></h3>
							<p><?php esc_html_e( 'Enter collection names separated by commas or new lines.', 'media-bridge-for-etch' ); ?></p>
							<label for="uplink-mbe-bulk-collection-parent"><?php esc_html_e( 'Parent collection', 'media-bridge-for-etch' ); ?></label>
							<select id="uplink-mbe-bulk-collection-parent"></select>
							<label for="uplink-mbe-bulk-collection-names"><?php esc_html_e( 'Collection names', 'media-bridge-for-etch' ); ?></label>
							<textarea id="uplink-mbe-bulk-collection-names" rows="4" placeholder="<?php esc_attr_e( 'Branding, Client photos, Social media', 'media-bridge-for-etch' ); ?>"></textarea>
							<button type="button" class="button button-primary" id="uplink-mbe-bulk-create-collections"><?php esc_html_e( 'Add collections', 'media-bridge-for-etch' ); ?></button>
						</section>
						<section class="uplink-mbe-collection-manager-section">
							<div class="uplink-mbe-collection-manager-heading"><h3><?php esc_html_e( 'Delete collections', 'media-bridge-for-etch' ); ?></h3><label><input type="checkbox" id="uplink-mbe-select-all-collections"> <?php esc_html_e( 'Select all', 'media-bridge-for-etch' ); ?></label></div>
							<p><?php esc_html_e( 'Deleting collections removes only their categorization. Media files remain in the WordPress library.', 'media-bridge-for-etch' ); ?></p>
							<div id="uplink-mbe-collection-manager-list" class="uplink-mbe-collection-manager-list"></div>
							<button type="button" class="button button-link-delete" id="uplink-mbe-bulk-delete-collections" disabled><?php esc_html_e( 'Delete selected collections', 'media-bridge-for-etch' ); ?></button>
						</section>
					</div>
				</div>

				<div class="uplink-mbe-modal" id="uplink-mbe-child-delete-modal" hidden>
					<div class="uplink-mbe-modal-backdrop" data-child-delete-action="cancel"></div>
					<div class="uplink-mbe-modal-dialog uplink-mbe-child-delete-dialog" role="dialog" aria-modal="true" aria-labelledby="uplink-mbe-child-delete-title" aria-describedby="uplink-mbe-child-delete-description">
						<button type="button" class="uplink-mbe-modal-close" data-child-delete-action="cancel" aria-label="<?php esc_attr_e( 'Close', 'media-bridge-for-etch' ); ?>">&times;</button>
						<h2 id="uplink-mbe-child-delete-title"><?php esc_html_e( 'Delete parent collection', 'media-bridge-for-etch' ); ?></h2>
						<p id="uplink-mbe-child-delete-description"></p>
						<div class="uplink-mbe-child-delete-actions">
							<button type="button" class="button" data-child-delete-action="cancel"><?php esc_html_e( 'Cancel', 'media-bridge-for-etch' ); ?></button>
							<button type="button" class="button" id="uplink-mbe-promote-children" data-child-delete-action="promote"><?php esc_html_e( 'Promote children and delete parent', 'media-bridge-for-etch' ); ?></button>
							<button type="button" class="button button-link-delete" id="uplink-mbe-delete-parent-children" data-child-delete-action="delete"><?php esc_html_e( 'Delete parent and child collections', 'media-bridge-for-etch' ); ?></button>
						</div>
					</div>
				</div>

				<?php if ( $gallery_enabled ) : ?>
				<div class="uplink-mbe-modal" id="uplink-mbe-gallery-generator-modal" hidden>
					<div class="uplink-mbe-modal-backdrop" data-gallery-generator-close></div>
					<div class="uplink-mbe-modal-dialog uplink-mbe-gallery-generator-dialog" role="dialog" aria-modal="true" aria-labelledby="uplink-mbe-gallery-generator-title">
						<button type="button" class="uplink-mbe-modal-close" data-gallery-generator-close aria-label="<?php esc_attr_e( 'Close', 'media-bridge-for-etch' ); ?>">&times;</button>
						<h2 id="uplink-mbe-gallery-generator-title"><?php esc_html_e( 'Create collection gallery', 'media-bridge-for-etch' ); ?></h2>
						<p><?php esc_html_e( 'Choose a collection and configure the gallery. The preview updates as you make changes.', 'media-bridge-for-etch' ); ?></p>
						<div class="uplink-mbe-gallery-generator-layout">
							<div class="uplink-mbe-gallery-generator-controls">
								<label for="uplink-mbe-gallery-collection"><?php esc_html_e( 'Collection', 'media-bridge-for-etch' ); ?></label>
								<select id="uplink-mbe-gallery-collection"><option value=""><?php esc_html_e( 'Choose a collection…', 'media-bridge-for-etch' ); ?></option></select>
								<details open><summary><?php esc_html_e( 'Gallery layout', 'media-bridge-for-etch' ); ?></summary><div class="uplink-mbe-gallery-generator-fields">
									<label><?php esc_html_e( 'Layout', 'media-bridge-for-etch' ); ?><select id="uplink-mbe-gallery-layout"><option value="grid">Grid</option><option value="tiled">Tiled mosaic</option><option value="circles">Circles</option><option value="square">Square</option><option value="columns">Columns</option></select></label>
									<label><?php esc_html_e( 'Columns', 'media-bridge-for-etch' ); ?><input id="uplink-mbe-gallery-columns" type="number" min="1" max="8" value="3"></label>
									<label><?php esc_html_e( 'Spacing', 'media-bridge-for-etch' ); ?><input id="uplink-mbe-gallery-gap" type="number" min="0" max="80" value="8"></label>
									<label><?php esc_html_e( 'Maximum images', 'media-bridge-for-etch' ); ?><input id="uplink-mbe-gallery-limit" type="number" min="1" max="100" value="24"></label>
									<label><?php esc_html_e( 'Aspect ratio', 'media-bridge-for-etch' ); ?><select id="uplink-mbe-gallery-ratio"><option value="1/1">Square</option><option value="4/3">4:3</option><option value="3/2">3:2</option><option value="16/9">16:9</option><option value="3/4">Portrait</option><option value="auto">Original</option></select></label>
									<label><?php esc_html_e( 'Image size', 'media-bridge-for-etch' ); ?><select id="uplink-mbe-gallery-size"><option value="thumbnail">Thumbnail</option><option value="medium">Medium</option><option value="large" selected>Large</option><option value="full">Full</option></select></label>
									<label class="uplink-mbe-generator-check"><input id="uplink-mbe-gallery-crop" type="checkbox" checked> <?php esc_html_e( 'Crop thumbnails', 'media-bridge-for-etch' ); ?></label>
									<label class="uplink-mbe-generator-check"><input id="uplink-mbe-gallery-children" type="checkbox"> <?php esc_html_e( 'Include child collections', 'media-bridge-for-etch' ); ?></label>
									<label class="uplink-mbe-generator-check"><input id="uplink-mbe-gallery-random" type="checkbox"> <?php esc_html_e( 'Random order', 'media-bridge-for-etch' ); ?></label>
								</div></details>
								<details><summary><?php esc_html_e( 'Thumbnail text', 'media-bridge-for-etch' ); ?></summary><div class="uplink-mbe-gallery-generator-fields">
									<label class="uplink-mbe-generator-check"><input id="uplink-mbe-gallery-title" type="checkbox"> <?php esc_html_e( 'Show title', 'media-bridge-for-etch' ); ?></label>
									<label class="uplink-mbe-generator-check"><input id="uplink-mbe-gallery-caption" type="checkbox"> <?php esc_html_e( 'Show caption', 'media-bridge-for-etch' ); ?></label>
									<label><?php esc_html_e( 'Text position', 'media-bridge-for-etch' ); ?><select id="uplink-mbe-gallery-text-position"><option value="bottom">Bottom</option><option value="top">Top</option><option value="center">Center</option><option value="below">Below image</option></select></label>
									<label><?php esc_html_e( 'Text alignment', 'media-bridge-for-etch' ); ?><select id="uplink-mbe-gallery-text-align"><option value="center">Center</option><option value="left">Left</option><option value="right">Right</option></select></label>
									<label><?php esc_html_e( 'Title size', 'media-bridge-for-etch' ); ?><input id="uplink-mbe-gallery-title-size" type="number" min="10" max="64" value="16"></label>
									<label><?php esc_html_e( 'Caption size', 'media-bridge-for-etch' ); ?><input id="uplink-mbe-gallery-caption-size" type="number" min="10" max="48" value="14"></label>
									<label><?php esc_html_e( 'Text background opacity', 'media-bridge-for-etch' ); ?><input id="uplink-mbe-gallery-background-opacity" type="number" min="0" max="100" value="72"></label>
									<label><?php esc_html_e( 'Text background', 'media-bridge-for-etch' ); ?><input id="uplink-mbe-gallery-text-background" type="color" value="#000000"></label>
									<label><?php esc_html_e( 'Title color', 'media-bridge-for-etch' ); ?><input id="uplink-mbe-gallery-title-color" type="color" value="#ffffff"></label>
									<label><?php esc_html_e( 'Caption color', 'media-bridge-for-etch' ); ?><input id="uplink-mbe-gallery-caption-color" type="color" value="#ffffff"></label>
								</div></details>
								<details><summary><?php esc_html_e( 'Lightbox', 'media-bridge-for-etch' ); ?></summary><div class="uplink-mbe-gallery-generator-fields">
									<label><?php esc_html_e( 'Image behavior', 'media-bridge-for-etch' ); ?><select id="uplink-mbe-gallery-lightbox"><option value="custom">Custom lightbox</option><option value="native">WordPress lightbox</option><option value="none">No lightbox</option></select></label>
									<label><?php esc_html_e( 'Thumbnail strip', 'media-bridge-for-etch' ); ?><select id="uplink-mbe-gallery-strip"><option value="horizontal">Horizontal</option><option value="vertical">Vertical</option></select></label>
									<label><?php esc_html_e( 'Information position', 'media-bridge-for-etch' ); ?><select id="uplink-mbe-gallery-info"><option value="bottom">Below image</option><option value="top">Above image</option></select></label>
									<label><?php esc_html_e( 'Lightbox image size', 'media-bridge-for-etch' ); ?><select id="uplink-mbe-gallery-lightbox-size"><option value="large">Large</option><option value="full" selected>Full</option></select></label>
									<label><?php esc_html_e( 'Font family', 'media-bridge-for-etch' ); ?><select id="uplink-mbe-gallery-lightbox-font"><option value="inherit">Theme font</option><option value="system">System</option><option value="serif">Serif</option><option value="mono">Monospace</option></select></label>
									<label><?php esc_html_e( 'Title size', 'media-bridge-for-etch' ); ?><input id="uplink-mbe-gallery-lightbox-title-size" type="number" min="10" max="72" value="20"></label>
									<label><?php esc_html_e( 'Caption size', 'media-bridge-for-etch' ); ?><input id="uplink-mbe-gallery-lightbox-caption-size" type="number" min="10" max="48" value="15"></label>
									<label><?php esc_html_e( 'Title weight', 'media-bridge-for-etch' ); ?><select id="uplink-mbe-gallery-lightbox-title-weight"><option>300</option><option>400</option><option>500</option><option>600</option><option selected>700</option><option>800</option><option>900</option></select></label>
									<label><?php esc_html_e( 'Caption weight', 'media-bridge-for-etch' ); ?><select id="uplink-mbe-gallery-lightbox-caption-weight"><option>300</option><option selected>400</option><option>500</option><option>600</option><option>700</option><option>800</option><option>900</option></select></label>
									<label><?php esc_html_e( 'Background', 'media-bridge-for-etch' ); ?><input id="uplink-mbe-gallery-lightbox-background" type="color" value="#111315"></label>
									<label><?php esc_html_e( 'Information panel', 'media-bridge-for-etch' ); ?><input id="uplink-mbe-gallery-lightbox-panel" type="color" value="#1d2125"></label>
									<label><?php esc_html_e( 'Title color', 'media-bridge-for-etch' ); ?><input id="uplink-mbe-gallery-lightbox-title-color" type="color" value="#ffffff"></label>
									<label><?php esc_html_e( 'Caption color', 'media-bridge-for-etch' ); ?><input id="uplink-mbe-gallery-lightbox-caption-color" type="color" value="#d9dde1"></label>
									<label class="uplink-mbe-generator-check"><input id="uplink-mbe-gallery-lightbox-title" type="checkbox" checked> <?php esc_html_e( 'Show title', 'media-bridge-for-etch' ); ?></label>
									<label class="uplink-mbe-generator-check"><input id="uplink-mbe-gallery-lightbox-caption" type="checkbox" checked> <?php esc_html_e( 'Show caption', 'media-bridge-for-etch' ); ?></label>
									<label class="uplink-mbe-generator-check"><input id="uplink-mbe-gallery-lightbox-thumbnails" type="checkbox" checked> <?php esc_html_e( 'Show thumbnail strip', 'media-bridge-for-etch' ); ?></label>
									<label class="uplink-mbe-generator-check"><input id="uplink-mbe-gallery-fullscreen" type="checkbox" checked> <?php esc_html_e( 'Fullscreen control', 'media-bridge-for-etch' ); ?></label>
									<label class="uplink-mbe-generator-check"><input id="uplink-mbe-gallery-zoom" type="checkbox" checked> <?php esc_html_e( 'Zoom controls', 'media-bridge-for-etch' ); ?></label>
								</div></details>
							</div>
							<div class="uplink-mbe-gallery-generator-result">
								<h3><?php esc_html_e( 'Preview', 'media-bridge-for-etch' ); ?></h3>
								<div id="uplink-mbe-gallery-generator-preview" class="uplink-mbe-gallery-generator-preview"></div>
								<p class="description"><?php esc_html_e( 'This preview uses the media currently loaded for the collection. Your site styles may affect the final appearance.', 'media-bridge-for-etch' ); ?></p>
								<label for="uplink-mbe-gallery-shortcode"><?php esc_html_e( 'Shortcode', 'media-bridge-for-etch' ); ?></label>
								<textarea id="uplink-mbe-gallery-shortcode" rows="5" readonly></textarea>
								<div class="uplink-mbe-gallery-generator-actions"><button type="button" class="button button-primary" id="uplink-mbe-gallery-copy"><?php esc_html_e( 'Copy shortcode', 'media-bridge-for-etch' ); ?></button><span id="uplink-mbe-gallery-copy-status" role="status" aria-live="polite"></span></div>
							</div>
						</div>
					</div>
				</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Return the administrator-defined client-facing media manager label.
	 */
	private function manager_label(): string {
		$label = trim( (string) ( Plugin::settings()['manager_label'] ?? '' ) );
		return '' !== $label ? $label : __( 'Etch Collections', 'media-bridge-for-etch' );
	}

	/**
	 * Group the manager color controls by their job in the interface.
	 *
	 * @return array<string, array<string, string>>
	 */
	private function palette_fields(): array {
		return array(
			__( 'Base interface', 'media-bridge-for-etch' ) => array(
				'background'    => __( 'Page background', 'media-bridge-for-etch' ),
				'text'          => __( 'Primary text', 'media-bridge-for-etch' ),
				'surface'       => __( 'Panel background', 'media-bridge-for-etch' ),
				'muted'         => __( 'Secondary text', 'media-bridge-for-etch' ),
				'card'          => __( 'Media card background', 'media-bridge-for-etch' ),
				'surface_muted' => __( 'Secondary surface', 'media-bridge-for-etch' ),
			),
			__( 'Previews and badges', 'media-bridge-for-etch' ) => array(
				'preview'      => __( 'Preview background', 'media-bridge-for-etch' ),
				'preview_text' => __( 'Preview text and icons', 'media-bridge-for-etch' ),
				'badge'        => __( 'Badge background', 'media-bridge-for-etch' ),
				'badge_text'   => __( 'Badge text and icons', 'media-bridge-for-etch' ),
			),
			__( 'Controls and borders', 'media-bridge-for-etch' ) => array(
				'control'        => __( 'Control background', 'media-bridge-for-etch' ),
				'control_border' => __( 'Control border', 'media-bridge-for-etch' ),
				'border'         => __( 'Borders', 'media-bridge-for-etch' ),
				'soft_border'    => __( 'Soft borders', 'media-bridge-for-etch' ),
			),
			__( 'Accents', 'media-bridge-for-etch' ) => array(
				'accent'         => __( 'Accent background', 'media-bridge-for-etch' ),
				'accent_icon'    => __( 'Text and icons on accent', 'media-bridge-for-etch' ),
				'accent_soft'    => __( 'Soft accent background', 'media-bridge-for-etch' ),
				'accent_text'    => __( 'Text on soft accent', 'media-bridge-for-etch' ),
				'accent_hover'   => __( 'Accent hover background', 'media-bridge-for-etch' ),
			),
			__( 'Status colors', 'media-bridge-for-etch' ) => array(
				'alt'          => __( 'Alt text background', 'media-bridge-for-etch' ),
				'alt_text'     => __( 'Alt text foreground', 'media-bridge-for-etch' ),
				'guide'        => __( 'Guide background', 'media-bridge-for-etch' ),
				'guide_text'   => __( 'Guide text', 'media-bridge-for-etch' ),
				'drop_bg'      => __( 'Drop target background', 'media-bridge-for-etch' ),
				'drop_text'    => __( 'Drop target text', 'media-bridge-for-etch' ),
				'danger_solid' => __( 'Destructive background', 'media-bridge-for-etch' ),
				'danger_icon'  => __( 'Text and icons on destructive background', 'media-bridge-for-etch' ),
				'guide_border' => __( 'Guide border', 'media-bridge-for-etch' ),
				'drop_border'  => __( 'Drop target border', 'media-bridge-for-etch' ),
				'danger'       => __( 'Destructive text', 'media-bridge-for-etch' ),
			),
			__( 'Scrollbars', 'media-bridge-for-etch' ) => array(
				'scrollbar_track' => __( 'Track', 'media-bridge-for-etch' ),
				'scrollbar_thumb' => __( 'Thumb', 'media-bridge-for-etch' ),
				'scrollbar_hover' => __( 'Thumb hover', 'media-bridge-for-etch' ),
			),
		);
	}

	/**
	 * Return palette fields whose contrast can be measured against a configured background.
	 *
	 * @return array<string, bool>
	 */
	private function palette_contrast_fields(): array {
		return array_fill_keys(
			array( 'text', 'muted', 'preview_text', 'badge_text', 'accent', 'accent_text', 'accent_icon', 'alt_text', 'guide_text', 'drop_text', 'danger', 'danger_icon' ),
			true
		);
	}


	public function ajax_state(): void {
		$this->verify_request( false, false );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce and capability checks are centralized in verify_request().
		$filter     = sanitize_key( wp_unslash( $_POST['filter'] ?? 'all' ) );
		$collection = absint( $_POST['collection'] ?? 0 );
		$search     = sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) );
		$type       = sanitize_key( wp_unslash( $_POST['type'] ?? '' ) );
		$extension  = sanitize_key( wp_unslash( $_POST['extension'] ?? '' ) );
		$date       = sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) );
		$mime_subtype = sanitize_mime_type( wp_unslash( $_POST['mime_subtype'] ?? '' ) );
		$uploaded_by = absint( $_POST['uploaded_by'] ?? 0 );
		$attachment_status = sanitize_key( wp_unslash( $_POST['attachment_status'] ?? '' ) );
		$uploaded_from = $this->valid_filter_date( sanitize_text_field( wp_unslash( $_POST['uploaded_from'] ?? '' ) ) );
		$uploaded_to = $this->valid_filter_date( sanitize_text_field( wp_unslash( $_POST['uploaded_to'] ?? '' ) ) );
		$width_min = $this->numeric_filter_value( sanitize_text_field( wp_unslash( $_POST['width_min'] ?? '' ) ) );
		$width_max = $this->numeric_filter_value( sanitize_text_field( wp_unslash( $_POST['width_max'] ?? '' ) ) );
		$height_min = $this->numeric_filter_value( sanitize_text_field( wp_unslash( $_POST['height_min'] ?? '' ) ) );
		$height_max = $this->numeric_filter_value( sanitize_text_field( wp_unslash( $_POST['height_max'] ?? '' ) ) );
		$file_size_min = $this->numeric_filter_value( sanitize_text_field( wp_unslash( $_POST['file_size_min'] ?? '' ) ) );
		$file_size_max = $this->numeric_filter_value( sanitize_text_field( wp_unslash( $_POST['file_size_max'] ?? '' ) ) );
		$missing_alt = rest_sanitize_boolean( wp_unslash( $_POST['missing_alt'] ?? false ) );
		$optimization_status = sanitize_key( wp_unslash( $_POST['optimization_status'] ?? '' ) );
		$optimization_status = in_array( $optimization_status, array( 'optimized', 'not_optimized' ), true ) ? $optimization_status : '';
		$health_filter = sanitize_key( wp_unslash( $_POST['health_filter'] ?? '' ) );
		$page       = max( 1, absint( $_POST['page'] ?? 1 ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$taxonomy   = $this->etch->taxonomy();
		$filename_pattern = '';
		if ( str_contains( $search, '*' ) ) {
			$filename_pattern = '(^|/)' . str_replace( '\\*', '.*', preg_quote( $search, '/' ) ) . '$';
		}
		$query_search = '' === $filename_pattern ? $search : '';

		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => self::PAGE_SIZE,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
			's'              => $query_search,
		);
		$restricted_ids = null;
		if ( in_array( $health_filter, array( 'all_issues', 'broken', 'missing_alt', 'missing_sizes', 'oversized', 'suspected_duplicates', 'obsolete_format', 'decorative', 'healthy' ), true ) ) {
			$restricted_ids = array_map( 'absint', $this->media_health_scan()['ids'][ $health_filter ] ?? array() );
		}

		$advanced_ids = $this->advanced_filter_ids(
			array(
				'width_min'     => $width_min,
				'width_max'     => $width_max,
				'height_min'    => $height_min,
				'height_max'    => $height_max,
				'file_size_min' => $file_size_min,
				'file_size_max' => $file_size_max,
				'missing_alt'   => $missing_alt,
				'optimization_status' => $optimization_status,
			)
		);
		if ( null !== $advanced_ids ) {
			$restricted_ids = null === $restricted_ids ? $advanced_ids : array_values( array_intersect( $restricted_ids, $advanced_ids ) );
		}
		if ( null !== $restricted_ids ) {
			$args['post__in'] = $restricted_ids ? $restricted_ids : array( 0 );
		}

		if ( $mime_subtype && preg_match( '#^[a-z0-9.+-]+/[a-z0-9.+-]+$#', $mime_subtype ) ) {
			$args['post_mime_type'] = $mime_subtype;
		} elseif ( in_array( $type, array( 'image', 'audio', 'video', 'application' ), true ) ) {
			$args['post_mime_type'] = $type;
		}
		if ( $uploaded_by ) {
			$args['author'] = $uploaded_by;
		}
		if ( 'attached' === $attachment_status ) {
			$args['post_parent__not_in'] = array( 0 );
		} elseif ( 'unattached' === $attachment_status ) {
			$args['post_parent'] = 0;
		}
		$attachment_meta_query = array();
		if ( '' !== $filename_pattern ) {
			$attachment_meta_query[] = array(
				'key'     => '_wp_attached_file',
				'value'   => $filename_pattern,
				'compare' => 'REGEXP',
			);
		}
		if ( preg_match( '/^[a-z0-9]{1,10}$/', $extension ) ) {
			$attachment_meta_query[] = array(
				'key'     => '_wp_attached_file',
				'value'   => '\\.' . preg_quote( $extension, '/' ) . '$',
				'compare' => 'REGEXP',
			);
		}
		if ( $attachment_meta_query ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Filename wildcard and extension filters must query the attachment path.
			$args['meta_query'] = $attachment_meta_query;
		}
		if ( preg_match( '/^(\d{4})-(\d{2})$/', $date, $date_parts ) ) {
			$args['year']     = absint( $date_parts[1] );
			$args['monthnum'] = absint( $date_parts[2] );
		}
		if ( $uploaded_from || $uploaded_to ) {
			$date_query = array( 'inclusive' => true );
			if ( $uploaded_from ) {
				$date_query['after'] = $uploaded_from;
			}
			if ( $uploaded_to ) {
				$date_query['before'] = $uploaded_to;
			}
			$args['date_query'] = array( $date_query );
		}

		if ( 'collection' === $filter && $collection ) {
			$args['orderby'] = 'collection_order';
			$args['order'] = 'ASC';
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- The selected taxonomy term defines this media-library view.
			$args['tax_query'] = array(
				array(
					'taxonomy'         => $taxonomy,
					'field'            => 'term_id',
					'terms'            => array( $collection ),
					'include_children' => true,
				),
			);
		} elseif ( 'uncategorized' === $filter ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- A NOT EXISTS query is required to identify uncategorized attachments.
			$args['tax_query'] = array(
				array(
					'taxonomy' => $taxonomy,
					'operator' => 'NOT EXISTS',
				),
			);
		}

		if ( '' !== $query_search ) {
			// WordPress treats a leading hyphen as a search exclusion by default. Media searches commonly
			// contain filename suffixes such as "-1.png", so treat hyphens literally for this query.
			add_filter( 'wp_query_search_exclusion_prefix', '__return_empty_string', PHP_INT_MAX );
			// WordPress keeps filename searching opt-in for attachment queries and consumes this filter on the next query.
			add_filter( 'wp_allow_query_attachment_by_filename', '__return_true' );
		}
		$query = new WP_Query( $args );
		if ( '' !== $query_search ) {
			remove_filter( 'wp_query_search_exclusion_prefix', '__return_empty_string', PHP_INT_MAX );
		}
		$media = array();
		foreach ( $query->posts as $attachment ) {
			$media[] = $this->attachment_data( (int) $attachment->ID );
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) ) {
			wp_send_json_error( array( 'message' => $terms->get_error_message() ), 500 );
		}
		$terms = $this->etch->order_terms_hierarchically( $terms );
		$parents = array();
		foreach ( $terms as $term ) {
			$parents[ (int) $term->term_id ] = (int) $term->parent;
		}

		$count_mode  = Plugin::settings()['parent_count_display'];
		$needs_total = in_array( $count_mode, array( 'cumulative', 'direct_total' ), true );
		$collections = array();
		foreach ( $terms as $term ) {
			$term_id     = (int) $term->term_id;
			$direct_count = (int) $term->count;
			$total_count  = $direct_count;
			if ( $needs_total ) {
				$descendants = get_term_children( $term_id, $taxonomy );
				if ( ! is_wp_error( $descendants ) && $descendants ) {
					$object_ids = get_objects_in_term( array_merge( array( $term_id ), array_map( 'intval', $descendants ) ), $taxonomy );
					if ( ! is_wp_error( $object_ids ) ) {
						$total_count = count( array_unique( array_map( 'intval', $object_ids ) ) );
					}
				}
			}
			$depth  = 0;
			$parent = (int) $term->parent;
			$seen   = array();
			while ( $parent && isset( $parents[ $parent ] ) && ! isset( $seen[ $parent ] ) ) {
				$seen[ $parent ] = true;
				++$depth;
				$parent = $parents[ $parent ];
			}
			$collections[] = array(
				'id'         => $term_id,
				'name'       => $term->name,
				'parent'     => (int) $term->parent,
				'depth'      => $depth,
				'count'      => $direct_count,
				'totalCount' => $total_count,
			);
		}

		$count_posts = wp_count_posts( 'attachment' );
		$all_count   = isset( $count_posts->inherit ) ? (int) $count_posts->inherit : 0;
		$uncategorized_query = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- A NOT EXISTS query is required for the uncategorized count.
				'tax_query'      => array(
					array(
						'taxonomy' => $taxonomy,
						'operator' => 'NOT EXISTS',
					),
				),
			)
		);

		wp_send_json_success(
			array(
				'collections' => $collections,
				'media'       => $media,
				'counts'      => array(
					'all'           => $all_count,
					'uncategorized' => (int) $uncategorized_query->found_posts,
				),
				'months'      => $this->attachment_months(),
				'extensions'  => $this->attachment_extensions(),
				'mimeTypes'   => $this->attachment_mime_types(),
				'uploaders'   => $this->attachment_uploaders(),
				'pagination'  => array(
					'page'  => $page,
					'pages' => max( 1, (int) $query->max_num_pages ),
					'total' => (int) $query->found_posts,
				),
			)
		);
	}

	public function ajax_save_collection(): void {
		$this->verify_request( true );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce and capability checks are centralized in verify_request().
		$term_id = absint( $_POST['term_id'] ?? 0 );
		$name    = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$parent  = absint( $_POST['parent'] ?? 0 );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( '' === $name ) {
			wp_send_json_error( array( 'message' => __( 'Enter a collection name.', 'media-bridge-for-etch' ) ), 400 );
		}

		$parent_error = $this->validate_parent( $parent, $term_id );
		if ( $parent_error ) {
			wp_send_json_error( array( 'message' => $parent_error ), 400 );
		}
		$positions               = $this->snapshot_positions();
		$target_was_alphabetical = $this->siblings_are_alphabetical( $parent );

		$position_needed = true;
		if ( $term_id ) {
			$term = $this->etch->get_term( $term_id );
			if ( ! $term ) {
				wp_send_json_error( array( 'message' => __( 'The collection no longer exists.', 'media-bridge-for-etch' ) ), 404 );
			}

			$position_needed = (int) $term->parent !== $parent || null === $this->etch->get_position( $term_id );
			$result          = $this->etch->update_term( $term_id, array( 'name' => $name, 'parent' => $parent ) );
		} else {
			$result = $this->etch->create_term( array( 'name' => $name, 'parent' => $parent ) );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}
		$saved_id = (int) $result['term_id'];
		$this->restore_positions( $positions, $position_needed ? array( $saved_id ) : array() );
		if ( $target_was_alphabetical ) {
			$this->alphabetize_positions( $parent );
		} elseif ( $position_needed ) {
			$this->etch->append_position( $saved_id, $parent );
		}

		wp_send_json_success( array( 'term_id' => $saved_id ) );
	}

	public function ajax_reorder_collections(): void {
		$this->verify_request( true );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce and capability checks are centralized in verify_request().
		$parent = absint( $_POST['parent'] ?? 0 );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON is decoded and every submitted ID is normalized with absint().
		$ordered_ids = json_decode( wp_unslash( $_POST['ordered_ids'] ?? '[]' ), true );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$ordered_ids = is_array( $ordered_ids ) ? array_values( array_unique( array_filter( array_map( 'absint', $ordered_ids ) ) ) ) : array();
		$current_ids = get_terms(
			array(
				'taxonomy'   => $this->etch->taxonomy(),
				'hide_empty' => false,
				'parent'     => $parent,
				'fields'     => 'ids',
			)
		);
		if ( is_wp_error( $current_ids ) ) {
			wp_send_json_error( array( 'message' => $current_ids->get_error_message() ), 500 );
		}

		$current_ids = array_values( array_map( 'intval', $current_ids ) );
		$submitted   = $ordered_ids;
		$expected    = $current_ids;
		sort( $submitted, SORT_NUMERIC );
		sort( $expected, SORT_NUMERIC );
		if ( ! $ordered_ids || $submitted !== $expected ) {
			wp_send_json_error( array( 'message' => __( 'The collection list changed. Reload and try reordering again.', 'media-bridge-for-etch' ) ), 409 );
		}

		foreach ( $ordered_ids as $position => $term_id ) {
			$this->etch->set_position( $term_id, $position );
		}

		wp_send_json_success( array( 'ordered_ids' => $ordered_ids ) );
	}

	/** Apply collection-specific order only when explicitly requested by a media query. */
	public function collection_media_orderby( string $orderby, WP_Query $query ): string {
		if ( 'collection_order' !== $query->get( 'orderby' ) || 'attachment' !== $query->get( 'post_type' ) ) {
			return $orderby;
		}
		$clauses = $query->get( 'tax_query' );
		$clauses = is_array( $clauses ) ? $clauses : array();
		if ( ! $clauses && $query->get( $this->etch->taxonomy() ) ) {
			$clauses = array( array( 'taxonomy' => $this->etch->taxonomy(), 'field' => 'slug', 'terms' => array( $query->get( $this->etch->taxonomy() ) ) ) );
		}
		$collection = $this->order_collection_from_clauses( $clauses );
		if ( ! $collection ) {
			return $orderby;
		}
		global $wpdb;
		$ids = get_term_meta( $collection, self::MEDIA_ORDER_META, true );
		$ids = is_array( $ids ) ? array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ) : array();
		$direction = 'DESC' === strtoupper( (string) $query->get( 'order' ) ) ? 'DESC' : 'ASC';
		if ( ! $ids ) {
			return "{$wpdb->posts}.ID {$direction}";
		}
		// Integer-only IDs; missing or newly assigned items follow the curated list.
		$cases = array();
		foreach ( $ids as $position => $id ) {
			$cases[] = "WHEN {$id} THEN {$position}";
		}
		return "CASE {$wpdb->posts}.ID " . implode( ' ', $cases ) . ' ELSE ' . count( $ids ) . " END {$direction}, {$wpdb->posts}.ID {$direction}";
	}

	/** Require one unambiguous collection clause; never guess an order for several collections. */
	private function order_collection_from_clauses( array $clauses ): int {
		if ( isset( $clauses['relation'] ) && 'OR' === strtoupper( $clauses['relation'] ) ) {
			return 0;
		}
		$collections = array();
		foreach ( $clauses as $clause ) {
			if ( ! is_array( $clause ) ) {
				continue;
			}
			if ( ! isset( $clause['taxonomy'] ) ) {
				// Nested expressions can represent several collection scopes. Leave them alone.
				return 0;
			}
			if ( $this->etch->taxonomy() !== $clause['taxonomy'] ) {
				continue;
			}
			$terms = (array) ( $clause['terms'] ?? array() );
			if ( 1 !== count( $terms ) || 'IN' !== strtoupper( $clause['operator'] ?? 'IN' ) ) {
				return 0;
			}
			$field = $clause['field'] ?? 'term_id';
			$field = 'term_id' === $field ? 'id' : $field;
			if ( ! in_array( $field, array( 'id', 'slug', 'name', 'term_taxonomy_id' ), true ) ) {
				return 0;
			}
			$term = get_term_by( $field, reset( $terms ), $this->etch->taxonomy() );
			if ( ! $term ) {
				return 0;
			}
			$collections[] = (int) $term->term_id;
		}
		return 1 === count( $collections ) ? $collections[0] : 0;
	}

	/** Move items relative to an anchor in the complete collection, including unloaded items. */
	public function ajax_reorder_media(): void {
		$this->verify_request();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified above.
		$collection = absint( $_POST['collection'] ?? 0 );
		$target = absint( $_POST['target'] ?? 0 );
		$placement = sanitize_key( wp_unslash( $_POST['placement'] ?? '' ) );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validate the decoded IDs below.
		$raw_ids = wp_unslash( $_POST['media_ids'] ?? '[]' );
		$ids = is_string( $raw_ids ) ? json_decode( $raw_ids, true ) : null;
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( ! $collection || ! $this->etch->get_term( $collection ) || ! is_array( $ids ) || ! $ids || ! in_array( $placement, array( 'before', 'after' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Choose a collection and valid media to reorder.', 'media-bridge-for-etch' ) ), 400 );
		}
		foreach ( $ids as $id ) {
			if ( ! is_int( $id ) || $id <= 0 ) {
				wp_send_json_error( array( 'message' => __( 'Invalid media IDs.', 'media-bridge-for-etch' ) ), 400 );
			}
		}
		$ids = array_values( array_unique( $ids ) );
		$query = new WP_Query(
			array(
				'post_type' => 'attachment',
				'post_status' => 'inherit',
				'posts_per_page' => -1,
				'no_found_rows' => true,
				'orderby' => 'collection_order',
				'order' => 'ASC',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Match the enhanced collection view, including its descendants.
				'tax_query' => array( array(
					'taxonomy' => $this->etch->taxonomy(),
					'field' => 'term_id',
					'terms' => array( $collection ),
					'include_children' => true,
				) ),
			)
		);
		$current = array_map( 'intval', wp_list_pluck( $query->posts, 'ID' ) );
		if ( array_diff( $ids, $current ) || ! in_array( $target, $current, true ) || in_array( $target, $ids, true ) ) {
			wp_send_json_error( array( 'message' => __( 'The collection changed. Reload and try reordering again.', 'media-bridge-for-etch' ) ), 409 );
		}
		// Preserve the current relative order of a multi-selection, not its click order.
		$moved = array_values( array_intersect( $current, $ids ) );
		$ordered = array_values( array_diff( $current, $ids ) );
		$index = array_search( $target, $ordered, true ) + ( 'after' === $placement ? 1 : 0 );
		array_splice( $ordered, $index, 0, $moved );
		// Validate permissions before persisting the collection-specific order.
		foreach ( $query->posts as $attachment ) {
			if ( ! current_user_can( 'edit_post', $attachment->ID ) ) {
				wp_send_json_error( array( 'message' => __( 'You must be allowed to edit every item in this collection to reorder it.', 'media-bridge-for-etch' ) ), 403 );
			}
		}
		$result = update_term_meta( $collection, self::MEDIA_ORDER_META, $ordered );
		if ( is_wp_error( $result ) || ( false === $result && get_term_meta( $collection, self::MEDIA_ORDER_META, true ) !== $ordered ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not save media order. Please try again.', 'media-bridge-for-etch' ) ), 500 );
		}
		wp_send_json_success( array( 'ordered_ids' => $ordered ) );
	}

	public function ajax_delete_collection(): void {
		$this->verify_request( true );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce and capability checks are centralized in verify_request().
		$term_id      = absint( $_POST['term_id'] ?? 0 );
		$child_action = sanitize_key( wp_unslash( $_POST['child_action'] ?? '' ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( ! $term_id || ! $this->etch->get_term( $term_id ) ) {
			wp_send_json_error( array( 'message' => __( 'The collection no longer exists.', 'media-bridge-for-etch' ) ), 404 );
		}
		$positions             = $this->snapshot_positions();
		$root_was_alphabetical = $this->siblings_are_alphabetical( 0 );

		$children = get_terms(
			array(
				'taxonomy'   => $this->etch->taxonomy(),
				'hide_empty' => false,
				'parent'     => $term_id,
				'fields'     => 'ids',
			)
		);
		$children = is_wp_error( $children ) ? array() : array_map( 'intval', $children );
		if ( $children && ! in_array( $child_action, array( 'promote', 'delete' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Choose whether to promote or delete the child collections.', 'media-bridge-for-etch' ) ), 409 );
		}

		if ( 'promote' === $child_action ) {
			foreach ( $children as $child_id ) {
				$updated = $this->etch->update_term( $child_id, array( 'parent' => 0 ) );
				if ( is_wp_error( $updated ) ) {
					wp_send_json_error( array( 'message' => $updated->get_error_message() ), 400 );
				}
			}
		} elseif ( 'delete' === $child_action ) {
			foreach ( $children as $child_id ) {
				$deleted_child = $this->etch->delete_term( $child_id );
				if ( is_wp_error( $deleted_child ) || ! $deleted_child ) {
					$message = is_wp_error( $deleted_child ) ? $deleted_child->get_error_message() : __( 'A child collection could not be deleted.', 'media-bridge-for-etch' );
					wp_send_json_error( array( 'message' => $message ), 400 );
				}
			}
		}

		$result = $this->etch->delete_term( $term_id );
		if ( is_wp_error( $result ) || ! $result ) {
			$message = is_wp_error( $result ) ? $result->get_error_message() : __( 'The collection could not be deleted.', 'media-bridge-for-etch' );
			wp_send_json_error( array( 'message' => $message ), 400 );
		}
		$promoted = 'promote' === $child_action ? $children : array();
		$this->restore_positions( $positions, $promoted );
		if ( $promoted && $root_was_alphabetical ) {
			$this->alphabetize_positions( 0 );
		} else {
			foreach ( $promoted as $child_id ) {
				$this->etch->append_position( $child_id, 0 );
			}
		}
		wp_send_json_success();
	}

	public function ajax_bulk_collections(): void {
		$this->verify_request( true );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce and capability checks are centralized in verify_request().
		$mode   = sanitize_key( wp_unslash( $_POST['mode'] ?? '' ) );
		$parent = absint( $_POST['parent'] ?? 0 );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( 'create' === $mode ) {
			$positions               = $this->snapshot_positions();
			$target_was_alphabetical = $this->siblings_are_alphabetical( $parent );
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce and capability checks are centralized in verify_request().
			$raw_names = sanitize_textarea_field( wp_unslash( $_POST['names'] ?? '' ) );
			$names     = preg_split( '/[,\r\n]+/', $raw_names );
			$names     = is_array( $names ) ? array_slice( array_values( array_unique( array_filter( array_map( 'trim', $names ) ) ) ), 0, 50 ) : array();
			if ( ! $names ) {
				wp_send_json_error( array( 'message' => __( 'Enter at least one collection name.', 'media-bridge-for-etch' ) ), 400 );
			}
			$parent_error = $this->validate_parent( $parent );
			if ( $parent_error ) {
				wp_send_json_error( array( 'message' => $parent_error ), 400 );
			}

			$created = 0;
			$created_ids = array();
			$skipped = 0;
			$errors  = array();
			foreach ( $names as $name ) {
				$result = $this->etch->create_term( array( 'name' => sanitize_text_field( $name ), 'parent' => $parent ) );
				if ( is_wp_error( $result ) ) {
					if ( 'term_exists' === $result->get_error_code() ) {
						++$skipped;
					} else {
						$errors[] = $name;
					}
				} else {
					$created_ids[] = (int) $result['term_id'];
					++$created;
				}
			}
			$this->restore_positions( $positions );
			if ( $created_ids && $target_was_alphabetical ) {
				$this->alphabetize_positions( $parent );
			} else {
				foreach ( $created_ids as $created_id ) {
					$this->etch->append_position( $created_id, $parent );
				}
			}
			wp_send_json_success( array( 'created' => $created, 'skipped' => $skipped, 'errors' => $errors ) );
		}

		if ( 'delete' === $mode ) {
			$positions             = $this->snapshot_positions();
			$root_was_alphabetical = $this->siblings_are_alphabetical( 0 );
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON is decoded and every submitted ID is normalized with absint().
			$term_ids = json_decode( wp_unslash( $_POST['term_ids'] ?? '[]' ), true );
			$term_ids = is_array( $term_ids ) ? array_values( array_unique( array_filter( array_map( 'absint', $term_ids ) ) ) ) : array();
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce and capability checks are centralized in verify_request().
			$child_action = sanitize_key( wp_unslash( $_POST['child_action'] ?? '' ) );
			if ( ! $term_ids ) {
				wp_send_json_error( array( 'message' => __( 'Choose at least one collection.', 'media-bridge-for-etch' ) ), 400 );
			}
			$all_terms = $this->etch->get_terms();
			$all_terms = is_wp_error( $all_terms ) ? array() : $all_terms;
			$unselected_children = array_values(
				array_filter(
					$all_terms,
					static fn( WP_Term $term ): bool => in_array( (int) $term->parent, $term_ids, true ) && ! in_array( (int) $term->term_id, $term_ids, true )
				)
			);
			if ( $unselected_children && ! in_array( $child_action, array( 'promote', 'delete' ), true ) ) {
				wp_send_json_error( array( 'message' => __( 'Choose whether to promote or delete the unselected child collections.', 'media-bridge-for-etch' ) ), 409 );
			}
			if ( 'delete' === $child_action ) {
				$term_ids = array_values( array_unique( array_merge( $term_ids, array_map( static fn( WP_Term $term ): int => (int) $term->term_id, $unselected_children ) ) ) );
			} elseif ( 'promote' === $child_action ) {
				foreach ( $unselected_children as $child ) {
					$updated = $this->etch->update_term( (int) $child->term_id, array( 'parent' => 0 ) );
					if ( is_wp_error( $updated ) ) {
						wp_send_json_error( array( 'message' => $updated->get_error_message() ), 400 );
					}
				}
			}
			$promoted_ids = 'promote' === $child_action ? array_map( static fn( WP_Term $term ): int => (int) $term->term_id, $unselected_children ) : array();
			$terms = array_values( array_filter( array_map( fn( int $term_id ): ?WP_Term => $this->etch->get_term( $term_id ), $term_ids ) ) );
			usort( $terms, static fn( WP_Term $first, WP_Term $second ): int => (int) ( bool ) $second->parent <=> (int) ( bool ) $first->parent );
			$deleted = 0;
			$errors  = array();
			foreach ( $terms as $term ) {
				$result = $this->etch->delete_term( (int) $term->term_id );
				if ( is_wp_error( $result ) || ! $result ) {
					$errors[] = (int) $term->term_id;
				} else {
					++$deleted;
				}
			}
			$this->restore_positions( $positions, $promoted_ids );
			if ( $promoted_ids && $root_was_alphabetical ) {
				$this->alphabetize_positions( 0 );
			} else {
				foreach ( $promoted_ids as $promoted_id ) {
					$this->etch->append_position( $promoted_id, 0 );
				}
			}
			wp_send_json_success( array( 'deleted' => $deleted, 'errors' => $errors ) );
		}

		wp_send_json_error( array( 'message' => __( 'Choose a valid collection action.', 'media-bridge-for-etch' ) ), 400 );
	}

	public function ajax_assign_media(): void {
		$this->verify_request();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce and capability checks are centralized in verify_request().
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON is decoded and every submitted ID is normalized with absint().
		$media_ids  = json_decode( wp_unslash( $_POST['media_ids'] ?? '[]' ), true );
		$media_ids  = is_array( $media_ids ) ? array_values( array_unique( array_filter( array_map( 'absint', $media_ids ) ) ) ) : array();
		$collection = absint( $_POST['collection'] ?? 0 );
		$mode       = sanitize_key( wp_unslash( $_POST['mode'] ?? 'add' ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( ! $media_ids || ! in_array( $mode, array( 'add', 'remove', 'clear' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Choose at least one media item.', 'media-bridge-for-etch' ) ), 400 );
		}
		if ( 'clear' !== $mode && ( ! $collection || ! $this->etch->get_term( $collection ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Choose a valid collection.', 'media-bridge-for-etch' ) ), 400 );
		}

		$errors = $this->assign_media_items( $media_ids, $collection, $mode );

		if ( $errors ) {
			wp_send_json_error(
				array(
					'message' => __( 'Some media items could not be updated.', 'media-bridge-for-etch' ),
					'ids'     => $errors,
				),
				400
			);
		}

		wp_send_json_success( array( 'updated' => count( $media_ids ) ) );
	}

	/**
	 * Return one attachment for a direct library link without applying library filters.
	 */
	public function ajax_get_attachment(): void {
		$this->verify_request( false, false );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The nonce check is centralized in verify_request().
		$attachment_id = absint( $_POST['attachment_id'] ?? 0 );
		if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) || ! current_user_can( 'edit_post', $attachment_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to edit this attachment, or it no longer exists.', 'media-bridge-for-etch' ) ), 403 );
		}

		wp_send_json_success( $this->attachment_data( $attachment_id, true ) );
	}

	/**
	 * Execute a Media Bridge command from a trusted integration such as Uplink Ops Center.
	 *
	 * Usage: apply_filters( 'uplink_mbe_execute_media_command', null, $command ).
	 * Supported operations are update, add, remove, and clear.
	 *
	 * @param mixed $result  A result supplied by an earlier handler.
	 * @param mixed $command Command payload.
	 * @return mixed
	 */
	public function execute_media_command( $result, $command ) {
		if ( null !== $result || ! is_array( $command ) ) {
			return $result;
		}

		$interactive_allowed = current_user_can( 'upload_files' );
		$allowed             = $interactive_allowed;
		/**
		 * Allow a trusted server-side integration to execute a Media Bridge command
		 * when there is no interactive WordPress user.
		 *
		 * @param bool  $allowed Whether the command may run.
		 * @param array $command Normalized command payload.
		 */
		$allowed = (bool) apply_filters( 'uplink_mbe_media_command_allowed', $allowed, $command );
		if ( ! $allowed ) {
			return new \WP_Error( 'uplink_mbe_forbidden', __( 'You are not allowed to manage media.', 'media-bridge-for-etch' ) );
		}
		$trusted_command = ! $interactive_allowed;

		$operation = sanitize_key( $command['operation'] ?? '' );
		if ( 'update' === $operation ) {
			$attachment_id = absint( $command['attachment_id'] ?? 0 );
			$updated       = $this->update_attachment_fields( $attachment_id, $command['fields'] ?? array(), $trusted_command );
			if ( is_wp_error( $updated ) ) {
				return $updated;
			}
			return array( 'operation' => 'update', 'attachment' => $this->attachment_data( $attachment_id ) );
		}

		if ( in_array( $operation, array( 'add', 'remove', 'clear' ), true ) ) {
			$media_ids  = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $command['attachment_ids'] ?? array() ) ) ) ) );
			$collection = absint( $command['collection_id'] ?? 0 );
			if ( ! $media_ids || ( 'clear' !== $operation && ( ! $collection || ! $this->etch->get_term( $collection ) ) ) ) {
				return new \WP_Error( 'uplink_mbe_invalid_command', __( 'The media command is missing valid attachment or collection IDs.', 'media-bridge-for-etch' ) );
			}
			$errors = $this->assign_media_items( $media_ids, $collection, $operation, $trusted_command );
			if ( $errors ) {
				return new \WP_Error( 'uplink_mbe_partial_failure', __( 'Some media items could not be updated.', 'media-bridge-for-etch' ), array( 'ids' => $errors ) );
			}
			return array( 'operation' => $operation, 'updated' => count( $media_ids ), 'attachment_ids' => $media_ids, 'collection_id' => $collection );
		}

		return new \WP_Error( 'uplink_mbe_unknown_command', __( 'The requested Media Bridge operation is not supported.', 'media-bridge-for-etch' ) );
	}

	public function ajax_update_attachment(): void {
		$this->verify_request();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce and capability checks are centralized in verify_request().
		$attachment_id = absint( $_POST['attachment_id'] ?? 0 );
		$fields = array(
			'title'       => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
			'alt'         => sanitize_text_field( wp_unslash( $_POST['alt_text'] ?? '' ) ),
			'caption'     => sanitize_textarea_field( wp_unslash( $_POST['caption'] ?? '' ) ),
			'description' => wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) ),
			'decorative'  => isset( $_POST['decorative'] ) && '1' === sanitize_key( wp_unslash( $_POST['decorative'] ) ),
		);
		$has_collection_update = isset( $_POST['collections'] );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON is decoded and each submitted term ID is normalized with absint().
		$collection_ids = $has_collection_update ? json_decode( wp_unslash( $_POST['collections'] ), true ) : array();
		$collection_ids = is_array( $collection_ids ) ? array_values( array_unique( array_filter( array_map( 'absint', $collection_ids ) ) ) ) : null;
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( 'attachment' !== get_post_type( $attachment_id ) || ! current_user_can( 'edit_post', $attachment_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to edit this attachment.', 'media-bridge-for-etch' ) ), 403 );
		}
		if ( null === $collection_ids ) {
			wp_send_json_error( array( 'message' => __( 'The collection selection was not valid.', 'media-bridge-for-etch' ) ), 400 );
		}
		foreach ( $collection_ids as $collection_id ) {
			if ( ! $this->etch->get_term( $collection_id ) ) {
				wp_send_json_error( array( 'message' => __( 'One of the selected collections no longer exists.', 'media-bridge-for-etch' ) ), 409 );
			}
		}

		$updated = $this->update_attachment_fields( $attachment_id, $fields );
		if ( is_wp_error( $updated ) ) {
			wp_send_json_error( array( 'message' => $updated->get_error_message() ), 400 );
		}
		if ( $has_collection_update ) {
			$assigned = wp_set_object_terms( $attachment_id, $collection_ids, $this->etch->taxonomy(), false );
			if ( is_wp_error( $assigned ) ) {
				wp_send_json_error( array( 'message' => $assigned->get_error_message() ), 400 );
			}
		}
		wp_send_json_success( $this->attachment_data( $attachment_id ) );
	}

	public function ajax_media_health(): void {
		$this->verify_request( false, false );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce and capability checks are centralized in verify_request().
		$force = isset( $_POST['force'] ) && '1' === sanitize_key( wp_unslash( $_POST['force'] ) );
		wp_send_json_success( $this->media_health_scan( $force ) );
	}

	public function invalidate_media_health(): void {
		delete_transient( self::HEALTH_TRANSIENT );
	}

	private function media_health_scan( bool $force = false ): array {
		if ( ! $force ) {
			$cached = get_transient( self::HEALTH_TRANSIENT );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$ids = array_fill_keys( array( 'all_issues', 'broken', 'missing_alt', 'missing_sizes', 'oversized', 'suspected_duplicates', 'obsolete_format', 'decorative', 'healthy' ), array() );
		$attachment_ids = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);
		$registered_sizes      = wp_get_registered_image_subsizes();
		$max_bytes             = array( 'image' => MB_IN_BYTES, 'font' => (int) ( MB_IN_BYTES / 2 ), 'application' => 5 * MB_IN_BYTES, 'audio' => 10 * MB_IN_BYTES, 'video' => 50 * MB_IN_BYTES );
		$issues_by_attachment = array();
		$duplicate_candidates = array();

		foreach ( $attachment_ids as $attachment_id ) {
			$attachment_id = (int) $attachment_id;
			$mime          = strtolower( (string) get_post_mime_type( $attachment_id ) );
			$file          = get_attached_file( $attachment_id );
			$extension     = strtolower( pathinfo( (string) $file, PATHINFO_EXTENSION ) );
			$is_image      = str_starts_with( $mime, 'image/' ) && 'svg' !== $extension;
			$decorative    = $is_image && '1' === (string) get_post_meta( $attachment_id, '_uplink_mbe_decorative', true );
			$issues        = array();
			$file_size     = $file && is_file( $file ) && is_readable( $file ) ? (int) @filesize( $file ) : 0;
			if ( ! $file || ! is_file( $file ) || ! is_readable( $file ) || 0 === $file_size ) {
				$issues[] = 'broken';
			} else {
				$duplicate_candidates[ $file_size ][] = array(
					'id'   => $attachment_id,
					'file' => $file,
				);
			}
			if ( $is_image && '' === trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) && ! $decorative ) {
				$issues[] = 'missing_alt';
			}
			if ( in_array( $extension, array( 'bmp', 'tif', 'tiff' ), true ) ) {
				$issues[] = 'obsolete_format';
			}
			$bucket = str_starts_with( $mime, 'image/' ) ? 'image' : ( str_starts_with( $mime, 'audio/' ) ? 'audio' : ( str_starts_with( $mime, 'video/' ) ? 'video' : ( str_starts_with( $mime, 'font/' ) ? 'font' : 'application' ) ) );
			if ( $file_size > $max_bytes[ $bucket ] ) {
				$issues[] = 'oversized';
			}
			if ( $is_image && $file && is_file( $file ) ) {
				$metadata = wp_get_attachment_metadata( $attachment_id );
				$width    = is_array( $metadata ) ? absint( $metadata['width'] ?? 0 ) : 0;
				$height   = is_array( $metadata ) ? absint( $metadata['height'] ?? 0 ) : 0;
				$generated = is_array( $metadata ) && isset( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ? $metadata['sizes'] : array();
				$missing = array();
				foreach ( $registered_sizes as $name => $size ) {
					$target_width  = absint( $size['width'] ?? 0 );
					$target_height = absint( $size['height'] ?? 0 );
					$should_generate = image_resize_dimensions( $width, $height, $target_width, $target_height, ! empty( $size['crop'] ) );
					if ( $should_generate ) {
						if ( ! isset( $generated[ $name ] ) ) {
							$missing[] = $name;
						}
					}
				}
				if ( $missing ) {
					$issues[] = 'missing_sizes';
				}
			}
			$issues_by_attachment[ $attachment_id ] = array_values( array_unique( $issues ) );
			if ( $decorative ) {
				$ids['decorative'][] = $attachment_id;
			}
		}

		$file_hashes = array();
		foreach ( $duplicate_candidates as $candidates ) {
			if ( count( $candidates ) < 2 ) {
				continue;
			}
			$hash_groups = array();
			foreach ( $candidates as $candidate ) {
				$file = $candidate['file'];
				if ( ! array_key_exists( $file, $file_hashes ) ) {
					$file_hashes[ $file ] = @hash_file( 'sha256', $file ) ?: '';
				}
				$hash = $file_hashes[ $file ];
				if ( '' !== $hash ) {
					$hash_groups[ $hash ][] = (int) $candidate['id'];
				}
			}
			foreach ( $hash_groups as $duplicate_ids ) {
				if ( count( $duplicate_ids ) < 2 ) {
					continue;
				}
				foreach ( $duplicate_ids as $attachment_id ) {
					$issues_by_attachment[ $attachment_id ][] = 'suspected_duplicates';
				}
			}
		}

		foreach ( $attachment_ids as $attachment_id ) {
			$attachment_id = (int) $attachment_id;
			$issues = array_values( array_unique( $issues_by_attachment[ $attachment_id ] ?? array() ) );
			foreach ( $issues as $issue ) {
				$ids[ $issue ][] = $attachment_id;
			}
			if ( $issues ) {
				$ids['all_issues'][] = $attachment_id;
			} else {
				$ids['healthy'][] = $attachment_id;
			}
		}

		$counts = array_map( 'count', $ids );
		$result = array( 'counts' => $counts, 'ids' => $ids, 'total' => count( $attachment_ids ), 'checkedAt' => current_time( 'timestamp' ) );
		set_transient( self::HEALTH_TRANSIENT, $result, 5 * MINUTE_IN_SECONDS );
		return $result;
	}

	public function ajax_delete_media(): void {
		$this->verify_request( false, false );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce and capability checks are centralized in verify_request().
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON is decoded and every submitted ID is normalized with absint().
		$media_ids = json_decode( wp_unslash( $_POST['media_ids'] ?? '[]' ), true );
		$media_ids = is_array( $media_ids ) ? array_values( array_unique( array_filter( array_map( 'absint', $media_ids ) ) ) ) : array();
		$acknowledge_used = isset( $_POST['acknowledge_used'] ) && '1' === sanitize_key( wp_unslash( $_POST['acknowledge_used'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( ! $media_ids ) {
			wp_send_json_error( array( 'message' => __( 'Choose at least one media item.', 'media-bridge-for-etch' ) ), 400 );
		}

		if ( ! $acknowledge_used ) {
			$used_ids = array_values(
				array_filter(
					$media_ids,
					fn( int $media_id ): bool => 'attachment' === get_post_type( $media_id ) && current_user_can( 'delete_post', $media_id ) && $this->attachment_is_used( $media_id )
				)
			);
			if ( $used_ids ) {
				wp_send_json_error(
					array(
						'message'                 => __( 'One or more selected media items appear to be in use.', 'media-bridge-for-etch' ),
						'requiresAcknowledgement' => true,
						'usedCount'               => count( $used_ids ),
					),
					409
				);
			}
		}

		$deleted = array();
		$failed  = array();
		foreach ( $media_ids as $media_id ) {
			if ( 'attachment' !== get_post_type( $media_id ) || ! current_user_can( 'delete_post', $media_id ) || ! wp_delete_attachment( $media_id, true ) ) {
				$failed[] = $media_id;
				continue;
			}
			$deleted[] = $media_id;
		}

		if ( ! $deleted ) {
			wp_send_json_error( array( 'message' => __( 'The selected media could not be deleted.', 'media-bridge-for-etch' ) ), 403 );
		}

		wp_send_json_success(
			array(
				'deleted' => $deleted,
				'failed'  => $failed,
			)
		);
	}

	private function attachment_data( int $attachment_id, bool $include_compat = false ): array {
		$url       = wp_get_attachment_url( $attachment_id );
		$thumbnail = wp_get_attachment_image_url( $attachment_id, 'medium' );
		$is_image  = wp_attachment_is_image( $attachment_id );
		if ( ! $thumbnail && ! $is_image ) {
			$poster_id = (int) get_post_thumbnail_id( $attachment_id );
			$thumbnail = $poster_id ? wp_get_attachment_image_url( $poster_id, 'medium' ) : false;
		}
		$has_preview = (bool) $thumbnail;
		$terms     = wp_get_object_terms( $attachment_id, $this->etch->taxonomy(), array( 'fields' => 'ids' ) );
		$file      = get_attached_file( $attachment_id );
		$filename  = $file ? wp_basename( $file ) : rawurldecode( wp_basename( (string) $url ) );
		$metadata  = wp_get_attachment_metadata( $attachment_id );
		$url_path  = wp_parse_url( (string) $url, PHP_URL_PATH );
		$extension = pathinfo( $filename, PATHINFO_EXTENSION );
		$width     = is_array( $metadata ) ? absint( $metadata['width'] ?? 0 ) : 0;
		$height    = is_array( $metadata ) ? absint( $metadata['height'] ?? 0 ) : 0;
		$attachment = get_post( $attachment_id );
		$edit_url   = get_edit_post_link( $attachment_id, 'raw' );
		$image_edit_url = '';
		if ( $is_image && $edit_url ) {
			$manager_return_url = admin_url( 'upload.php?page=' . self::PAGE_SLUG . '&uplink_mbe_attachment=' . $attachment_id );
			$image_edit_url = add_query_arg( 'uplink_mbe_image_editor', '1', $edit_url ) . '&uplink_mbe_return=' . rawurlencode( $manager_return_url );
		}

		$data = array(
			'id'          => $attachment_id,
			'title'       => (string) get_post_field( 'post_title', $attachment_id, 'raw' ) ?: $filename,
			'filename'    => $filename,
			'alt'         => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'caption'     => $attachment instanceof \WP_Post ? (string) $attachment->post_excerpt : '',
			'description' => $attachment instanceof \WP_Post ? (string) $attachment->post_content : '',
			'decorative'  => '1' === (string) get_post_meta( $attachment_id, '_uplink_mbe_decorative', true ),
			'exif'        => $this->attachment_exif( $attachment_id ),
			'optimization' => $this->attachment_optimization( $attachment_id, is_array( $metadata ) ? $metadata : array() ),
			'author'      => $attachment instanceof \WP_Post ? (string) get_the_author_meta( 'display_name', (int) $attachment->post_author ) : '',
			'mime'        => (string) get_post_mime_type( $attachment_id ),
			'filePath'    => is_string( $url_path ) ? $url_path : '',
			'fileType'    => $extension ? strtoupper( $extension ) : __( 'File', 'media-bridge-for-etch' ),
			'fileSize'    => $file && is_file( $file ) ? size_format( (int) filesize( $file ), 1 ) : '—',
			'dimensions'  => $width && $height ? $width . '×' . $height : '—',
			'date'        => get_the_date( '', $attachment_id ),
			'attached'    => $attachment instanceof \WP_Post && 0 < (int) $attachment->post_parent,
			'url'         => (string) $url,
			'thumbnail'   => (string) ( $thumbnail ?: wp_mime_type_icon( $attachment_id ) ),
			'hasPreview'  => $has_preview,
			'isImage'     => $is_image,
			'collections' => is_wp_error( $terms ) ? array() : array_map( 'intval', $terms ),
			'editUrl'     => $edit_url,
			'imageEditUrl' => $image_edit_url,
			'canEdit'     => current_user_can( 'edit_post', $attachment_id ),
			'canDelete'   => current_user_can( 'delete_post', $attachment_id ),
		);

		if ( $include_compat ) {
			$data['compat'] = $this->attachment_compatibility_data( $attachment_id );
		}

		return $data;
	}

	/**
	 * Return attachment fields registered through WordPress's compatibility API.
	 *
	 * Third-party plugins use attachment_fields_to_edit and
	 * attachment_fields_to_save to extend native attachment details. Reusing the
	 * generated form here keeps those plugins' field markup and save callbacks
	 * intact. Etch Collections is omitted because the manager renders its own
	 * hierarchical assignment control.
	 *
	 * @return array{html:string,nonce:string}
	 */
	private function attachment_compatibility_data( int $attachment_id ): array {
		if ( ! current_user_can( 'edit_post', $attachment_id ) ) {
			return array( 'html' => '', 'nonce' => '' );
		}
		if ( ! function_exists( 'get_compat_media_markup' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}

		$exclude_etch = function ( array $fields ): array {
			unset( $fields[ $this->etch->taxonomy() ], $fields['uplink_mbe_collections'] );
			return $fields;
		};
		add_filter( 'attachment_fields_to_edit', $exclude_etch, PHP_INT_MAX );
		$compat = get_compat_media_markup( $attachment_id, array( 'in_modal' => true ) );
		remove_filter( 'attachment_fields_to_edit', $exclude_etch, PHP_INT_MAX );

		return array(
			'html'  => isset( $compat['item'] ) ? (string) $compat['item'] : '',
			'nonce' => wp_create_nonce( 'update-post_' . $attachment_id ),
		);
	}

	public function image_editor_body_class( string $classes ): string {
		if ( ! $this->requested_image_editor_id() ) {
			return $classes;
		}

		$appearance = Plugin::settings()['appearance'];
		if ( ! in_array( $appearance, array( 'auto', 'light', 'dark' ), true ) ) {
			$appearance = 'auto';
		}
		return $classes . ' uplink-mbe-image-editor-screen uplink-mbe-theme-' . $appearance;
	}

	public function image_editor_collection_meta_box( \WP_Post $attachment ): void {
		$taxonomy = get_taxonomy( $this->etch->taxonomy() );
		if ( ! $taxonomy || ! wp_attachment_is_image( $attachment->ID ) || ! current_user_can( $taxonomy->cap->assign_terms ) ) {
			return;
		}
		$taxonomy->labels->all_items      = __( 'Collections', 'media-bridge-for-etch' );
		$taxonomy->labels->search_items   = __( 'Search Collections', 'media-bridge-for-etch' );
		$taxonomy->labels->parent_item    = __( 'Parent Collection', 'media-bridge-for-etch' );
		$taxonomy->labels->parent_item_colon = __( 'Parent Collection:', 'media-bridge-for-etch' );
		$taxonomy->labels->edit_item      = __( 'Edit Collection', 'media-bridge-for-etch' );
		$taxonomy->labels->update_item    = __( 'Update Collection', 'media-bridge-for-etch' );
		$taxonomy->labels->add_new_item   = __( 'Add New Collection', 'media-bridge-for-etch' );
		$taxonomy->labels->new_item_name  = __( 'New Collection Name', 'media-bridge-for-etch' );

		add_meta_box(
			'uplink-mbe-etch-collections',
			__( 'Etch Collections', 'media-bridge-for-etch' ),
			array( $this, 'render_image_editor_collection_meta_box' ),
			'attachment',
			'side',
			'default'
		);
	}

	public function render_image_editor_collection_meta_box( \WP_Post $attachment ): void {
		post_categories_meta_box(
			$attachment,
			array(
				'args' => array(
					'taxonomy' => $this->etch->taxonomy(),
				),
			)
		);
	}

	public function image_editor_context_fields( \WP_Post $attachment ): void {
		if ( $attachment->ID !== $this->requested_image_editor_id() ) {
			return;
		}

		$fallback_return_url = admin_url( 'upload.php?page=' . self::PAGE_SLUG . '&uplink_mbe_attachment=' . $attachment->ID );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This value only preserves navigation after the standard attachment update.
		$requested_return_url = isset( $_GET['uplink_mbe_return'] ) ? esc_url_raw( wp_unslash( $_GET['uplink_mbe_return'] ) ) : '';
		$return_url = wp_validate_redirect( $requested_return_url, $fallback_return_url );
		?>
		<input type="hidden" name="uplink_mbe_image_editor" value="1">
		<input type="hidden" name="uplink_mbe_return" value="<?php echo esc_attr( $return_url ); ?>">
		<?php
	}

	public function preserve_image_editor_context( string $location, int $attachment_id ): string {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WordPress verifies the attachment update before this redirect filter runs; these fields only preserve navigation state.
		if ( empty( $_POST['uplink_mbe_image_editor'] ) || empty( $_POST['uplink_mbe_return'] ) || 'attachment' !== get_post_type( $attachment_id ) || ! current_user_can( 'edit_post', $attachment_id ) ) {
			return $location;
		}
		$requested_return_url = esc_url_raw( wp_unslash( $_POST['uplink_mbe_return'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$fallback_return_url = admin_url( 'upload.php?page=' . self::PAGE_SLUG . '&uplink_mbe_attachment=' . $attachment_id );
		$return_url = wp_validate_redirect( $requested_return_url, $fallback_return_url );

		return add_query_arg( 'uplink_mbe_image_editor', '1', $location ) . '&uplink_mbe_return=' . rawurlencode( $return_url );
	}

	private function requested_image_editor_id(): int {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- These values only request an editing screen for an attachment the user can already edit.
		if ( empty( $_GET['uplink_mbe_image_editor'] ) || empty( $_GET['post'] ) ) {
			return 0;
		}
		$attachment_id = absint( $_GET['post'] );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) || ! wp_attachment_is_image( $attachment_id ) || ! current_user_can( 'edit_post', $attachment_id ) ) {
			return 0;
		}
		return $attachment_id;
	}

	private function attachment_exif( int $attachment_id ): array {
		$image_meta = Etch_Dynamic_Data::attachment_exif( $attachment_id );
		$exif       = array();
		$map        = array(
			'camera_model' => __( 'Camera', 'media-bridge-for-etch' ),
			'lens'      => __( 'Lens', 'media-bridge-for-etch' ),
			'aperture_display' => __( 'Aperture', 'media-bridge-for-etch' ),
			'focal_display' => __( 'Focal length', 'media-bridge-for-etch' ),
			'iso'       => __( 'ISO', 'media-bridge-for-etch' ),
			'exposure_display' => __( 'Shutter speed', 'media-bridge-for-etch' ),
			'date_taken_iso' => __( 'Captured', 'media-bridge-for-etch' ),
			'credit'    => __( 'Credit', 'media-bridge-for-etch' ),
			'copyright' => __( 'Copyright', 'media-bridge-for-etch' ),
		);
		foreach ( $map as $key => $label ) {
			$value = $image_meta[ $key ] ?? '';
			if ( '' === $value || null === $value || 0 === $value || '0' === $value ) {
				continue;
			}
			if ( 'date_taken_iso' === $key ) {
				$timestamp = strtotime( (string) $value );
				$value = $timestamp ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) : $value;
			}
			$exif[] = array( 'label' => $label, 'value' => sanitize_text_field( (string) $value ) );
		}
		$gps = is_array( $image_meta['gps'] ?? null ) ? $image_meta['gps'] : array();
		$gps_map = array(
			'latitude'    => __( 'Latitude', 'media-bridge-for-etch' ),
			'longitude'   => __( 'Longitude', 'media-bridge-for-etch' ),
			'altitude_ft' => __( 'Altitude', 'media-bridge-for-etch' ),
		);
		foreach ( $gps_map as $key => $label ) {
			$value = $gps[ $key ] ?? null;
			if ( null === $value || '' === $value ) {
				continue;
			}
			if ( in_array( $key, array( 'latitude', 'longitude' ), true ) && is_numeric( $value ) ) {
				$value = rtrim( rtrim( number_format( (float) $value, 6, '.', '' ), '0' ), '.' );
			}
			$exif[] = array( 'label' => $label, 'value' => sanitize_text_field( (string) $value ) );
		}
		return $exif;
	}

	public function capture_etch_optimization( $result, $server, $request ) {
		if ( ! $request instanceof \WP_REST_Request || 'POST' !== $request->get_method() || ! preg_match( '#^/etch-api/replace/(\d+)$#', $request->get_route(), $matches ) ) {
			return $result;
		}
		$attachment_id = absint( $matches[1] );
		$file          = get_attached_file( $attachment_id );
		if ( $attachment_id && $file && is_readable( $file ) ) {
			$this->etch_optimization_before[ $attachment_id ] = (int) filesize( $file );
		}
		return $result;
	}

	public function record_etch_optimization( $response, $server, $request ) {
		if ( ! $request instanceof \WP_REST_Request || 'POST' !== $request->get_method() || ! preg_match( '#^/etch-api/replace/(\d+)$#', $request->get_route(), $matches ) ) {
			return $response;
		}
		$attachment_id = absint( $matches[1] );
		$original_size = absint( $this->etch_optimization_before[ $attachment_id ] ?? 0 );
		unset( $this->etch_optimization_before[ $attachment_id ] );
		$status = $response instanceof \WP_HTTP_Response ? $response->get_status() : 500;
		$file   = get_attached_file( $attachment_id );
		if ( $original_size && $status < 400 && $file && is_readable( $file ) ) {
			update_post_meta(
				$attachment_id,
				'_uplink_mbe_etch_optimization',
				array(
					'original_size'  => $original_size,
					'optimized_size' => (int) filesize( $file ),
					'format'         => (string) get_post_mime_type( $attachment_id ),
					'date'           => time(),
				)
			);
		}
		return $response;
	}

	private function attachment_optimization( int $attachment_id, array $metadata ): array {
		$cimo = $this->attachment_cimo( $attachment_id, $metadata );
		$etch = get_post_meta( $attachment_id, '_uplink_mbe_etch_optimization', true );
		$etch = is_array( $etch ) && ! empty( $etch['optimized_size'] ) ? $etch : array();
		$current_file = get_attached_file( $attachment_id );
		$current_size = $current_file && is_readable( $current_file ) ? (int) filesize( $current_file ) : 0;
		$current_mime = (string) get_post_mime_type( $attachment_id );
		$etch_current = $etch && ! (
			( ! empty( $etch['optimized_size'] ) && $current_size && absint( $etch['optimized_size'] ) !== $current_size )
			|| ( ! empty( $etch['format'] ) && $current_mime && (string) $etch['format'] !== $current_mime )
		);
		if ( ! empty( $cimo['optimized'] ) && ! empty( $cimo['_current'] ) ) {
			$cimo['details'][0]['section'] = __( 'Current file', 'media-bridge-for-etch' );
			if ( $etch ) {
				$etch_original  = absint( $etch['original_size'] ?? 0 );
				$etch_optimized = absint( $etch['optimized_size'] ?? 0 );
				$etch_savings   = $etch_original > 0 ? max( 0, ( ( $etch_original - $etch_optimized ) / $etch_original ) * 100 ) : 0;
				$cimo['details'][] = array( 'section' => __( 'Earlier Etch optimization', 'media-bridge-for-etch' ), 'label' => __( 'Optimization source', 'media-bridge-for-etch' ), 'value' => __( 'Etch', 'media-bridge-for-etch' ) );
				$cimo['details'][] = array( 'label' => __( 'Full-size before', 'media-bridge-for-etch' ), 'value' => size_format( $etch_original, 1 ) );
				$cimo['details'][] = array( 'label' => __( 'Full-size after', 'media-bridge-for-etch' ), 'value' => size_format( $etch_optimized, 1 ) );
				$cimo['details'][] = array( 'label' => __( 'Full-size saved', 'media-bridge-for-etch' ), 'value' => number_format_i18n( $etch_savings, 1 ) . '%' );
			}
			unset( $cimo['_current'], $cimo['_full_original_size'], $cimo['_full_optimized_size'], $cimo['_full_savings'], $cimo['_status'], $cimo['_generated_processed'], $cimo['_total_original_size'], $cimo['_total_optimized_size'] );
			return $cimo;
		}
		if ( ! $etch_current ) {
			return array( 'optimized' => false, 'summary' => '', 'details' => array() );
		}
		$original_size  = absint( $etch['original_size'] ?? 0 );
		$optimized_size = absint( $etch['optimized_size'] ?? 0 );
		$savings        = $original_size > 0 ? max( 0, ( ( $original_size - $optimized_size ) / $original_size ) * 100 ) : 0;
		$details        = array(
			array( 'section' => __( 'Current file', 'media-bridge-for-etch' ), 'label' => __( 'Optimization source', 'media-bridge-for-etch' ), 'value' => __( 'Etch', 'media-bridge-for-etch' ) ),
			array( 'label' => __( 'Original size', 'media-bridge-for-etch' ), 'value' => size_format( $original_size, 1 ) ),
			array( 'label' => __( 'Optimized size', 'media-bridge-for-etch' ), 'value' => size_format( $optimized_size, 1 ) ),
			array( 'label' => __( 'Space saved', 'media-bridge-for-etch' ), 'value' => number_format_i18n( $savings, 1 ) . '%' ),
		);
		if ( ! empty( $etch['format'] ) ) {
			$details[] = array( 'label' => __( 'Optimized format', 'media-bridge-for-etch' ), 'value' => sanitize_text_field( (string) $etch['format'] ) );
		}
		if ( ! empty( $cimo['optimized'] ) ) {
			$details[] = array( 'section' => __( 'Earlier Cimo optimization', 'media-bridge-for-etch' ), 'label' => __( 'Optimization source', 'media-bridge-for-etch' ), 'value' => __( 'Cimo', 'media-bridge-for-etch' ) );
			if ( ! empty( $cimo['_status'] ) ) {
				$details[] = array( 'label' => __( 'Status', 'media-bridge-for-etch' ), 'value' => $cimo['_status'] );
			}
			if ( ! empty( $cimo['_full_original_size'] ) ) {
				$details[] = array( 'label' => __( 'Full-size before', 'media-bridge-for-etch' ), 'value' => size_format( $cimo['_full_original_size'], 1 ) );
			}
			if ( ! empty( $cimo['_full_optimized_size'] ) ) {
				$details[] = array( 'label' => __( 'Full-size after', 'media-bridge-for-etch' ), 'value' => size_format( $cimo['_full_optimized_size'], 1 ) );
				$details[] = array( 'label' => __( 'Full-size saved', 'media-bridge-for-etch' ), 'value' => number_format_i18n( $cimo['_full_savings'], 1 ) . '%' );
			}
			if ( ! empty( $cimo['_generated_processed'] ) ) {
				/* translators: %s: Number of generated WordPress image sizes optimized by Cimo. */
				$details[] = array( 'label' => __( 'WordPress image sizes', 'media-bridge-for-etch' ), 'value' => sprintf( _n( '%s size optimized by Cimo', '%s sizes optimized by Cimo', $cimo['_generated_processed'], 'media-bridge-for-etch' ), number_format_i18n( $cimo['_generated_processed'] ) ) );
			}
			if ( ! empty( $cimo['_total_original_size'] ) && ! empty( $cimo['_total_optimized_size'] ) ) {
				$details[] = array( 'label' => __( 'Total before', 'media-bridge-for-etch' ), 'value' => size_format( $cimo['_total_original_size'], 1 ) );
				$details[] = array( 'label' => __( 'Total after', 'media-bridge-for-etch' ), 'value' => size_format( $cimo['_total_optimized_size'], 1 ) );
			}
		}
		return array(
			'optimized' => true,
			/* translators: %s: Percentage of file size saved. */
			'summary'   => sprintf( ! empty( $cimo['optimized'] ) ? __( 'Optimized in Etch after Cimo · %s saved', 'media-bridge-for-etch' ) : __( 'Optimized in Etch · %s saved', 'media-bridge-for-etch' ), number_format_i18n( $savings, 1 ) . '%' ),
			'details'   => $details,
		);
	}

	/**
	 * Normalize Cimo's attachment metadata without depending on its internal PHP classes.
	 */
	private function attachment_cimo( int $attachment_id, array $metadata ): array {
		$cimo = isset( $metadata['cimo'] ) && is_array( $metadata['cimo'] ) ? $metadata['cimo'] : array();
		if ( ! $cimo ) {
			return array( 'optimized' => false, 'summary' => '', 'details' => array() );
		}

		$original_size       = 0;
		$optimized_size      = 0;
		$full_original_size  = 0;
		$full_optimized_size = 0;
		$processed           = 0;
		$conversion_time     = 0;
		$converted_format    = '';
		$generated_processed = 0;
		$bulk                = isset( $cimo['bulk_optimization'] ) && is_array( $cimo['bulk_optimization'] ) ? $cimo['bulk_optimization'] : array();
		foreach ( $bulk as $size_name => $size_data ) {
			if ( ! is_array( $size_data ) || 'skip' === ( $size_data['status'] ?? '' ) ) {
				continue;
			}
			$entry_original  = absint( $size_data['originalFilesize'] ?? 0 );
			$entry_optimized = absint( $size_data['convertedFilesize'] ?? 0 );
			if ( ! $entry_original || ! $entry_optimized ) {
				continue;
			}
			$original_size  += $entry_original;
			$optimized_size += $entry_optimized;
			if ( 'full' === $size_name ) {
				$full_original_size  = $entry_original;
				$full_optimized_size = $entry_optimized;
			} else {
				++$generated_processed;
			}
			$conversion_time += (float) ( $size_data['conversionTime'] ?? 0 );
			if ( ! $converted_format && ! empty( $size_data['convertedFormat'] ) ) {
				$converted_format = sanitize_text_field( (string) $size_data['convertedFormat'] );
			}
			++$processed;
		}
		if ( ! $processed ) {
			$original_size       = absint( $cimo['originalFilesize'] ?? 0 );
			$optimized_size      = absint( $cimo['convertedFilesize'] ?? 0 );
			$full_original_size  = $original_size;
			$full_optimized_size = $optimized_size;
			$conversion_time     = (float) ( $cimo['conversionTime'] ?? 0 );
			$converted_format    = sanitize_text_field( (string) ( $cimo['convertedFormat'] ?? '' ) );
			$processed           = $original_size && $optimized_size ? 1 : 0;
		}

		$optimized = $processed > 0 || ! empty( $cimo['optimized_during_upload'] );
		if ( ! $optimized ) {
			return array( 'optimized' => false, 'summary' => '', 'details' => array() );
		}

		/*
		 * Cimo metadata can survive a later attachment replacement. Only treat its
		 * result as current when the recorded full-size output still matches the
		 * attachment on disk. This also allows a subsequent Cimo run to become the
		 * authoritative record again without relying on a timestamp Cimo does not
		 * currently store.
		 */
		$current_file = get_attached_file( $attachment_id );
		$current_size = $current_file && is_readable( $current_file ) ? (int) filesize( $current_file ) : 0;
		$current_mime = (string) get_post_mime_type( $attachment_id );
		$is_current = ! (
			( $full_optimized_size && $current_size && $full_optimized_size !== $current_size )
			|| ( $converted_format && $current_mime && $converted_format !== $current_mime )
		);

		$savings = $original_size > 0
			? max( 0, ( ( $original_size - $optimized_size ) / $original_size ) * 100 )
			: 0;
		$full_savings = $full_original_size > 0
			? max( 0, ( ( $full_original_size - $full_optimized_size ) / $full_original_size ) * 100 )
			: 0;
		$status  = $bulk
			? __( 'Bulk optimized', 'media-bridge-for-etch' )
			: __( 'Optimized on upload', 'media-bridge-for-etch' );
		$details = array(
			array( 'label' => __( 'Optimization source', 'media-bridge-for-etch' ), 'value' => __( 'Cimo', 'media-bridge-for-etch' ) ),
			array( 'label' => __( 'Status', 'media-bridge-for-etch' ), 'value' => $status ),
		);
		if ( $full_original_size ) {
			$details[] = array( 'label' => __( 'Full-size before', 'media-bridge-for-etch' ), 'value' => size_format( $full_original_size, 1 ) );
		}
		if ( $full_optimized_size ) {
			$details[] = array( 'label' => __( 'Full-size after', 'media-bridge-for-etch' ), 'value' => size_format( $full_optimized_size, 1 ) );
			$details[] = array( 'label' => __( 'Full-size saved', 'media-bridge-for-etch' ), 'value' => number_format_i18n( $full_savings, 1 ) . '%' );
		}
		if ( $processed > 1 ) {
			$details[] = array( 'label' => __( 'Total before', 'media-bridge-for-etch' ), 'value' => size_format( $original_size, 1 ) );
			$details[] = array( 'label' => __( 'Total after', 'media-bridge-for-etch' ), 'value' => size_format( $optimized_size, 1 ) );
			$details[] = array( 'label' => __( 'Total saved', 'media-bridge-for-etch' ), 'value' => number_format_i18n( $savings, 1 ) . '%' );
		}
		if ( $converted_format ) {
			$details[] = array( 'label' => __( 'Optimized format', 'media-bridge-for-etch' ), 'value' => $converted_format );
		}
		if ( $processed > 1 ) {
			/* translators: %s: Number of generated WordPress image sizes optimized by Cimo. */
			$details[] = array( 'label' => __( 'WordPress image sizes', 'media-bridge-for-etch' ), 'value' => sprintf( _n( '%s size optimized', '%s sizes optimized', $generated_processed, 'media-bridge-for-etch' ), number_format_i18n( $generated_processed ) ) );
		}
		if ( ! empty( $cimo['smartOptimized'] ) ) {
			$details[] = array( 'label' => __( 'Smart optimization', 'media-bridge-for-etch' ), 'value' => __( 'Enabled', 'media-bridge-for-etch' ) );
		}
		if ( $conversion_time > 0 ) {
			$formatted_time = $conversion_time < 1000
				? number_format_i18n( $conversion_time, 0 ) . ' ms'
				: number_format_i18n( $conversion_time / 1000, 1 ) . ' s';
			$details[] = array( 'label' => __( 'Processing time', 'media-bridge-for-etch' ), 'value' => $formatted_time );
		}

		$summary = __( 'Optimized by Cimo', 'media-bridge-for-etch' );
		if ( $optimized_size && $original_size ) {
			if ( $processed > 1 ) {
				/* translators: %s: Percentage of total file size saved across all generated image files. */
				$summary = sprintf( __( 'Optimized by Cimo · %s total saved', 'media-bridge-for-etch' ), number_format_i18n( $savings, 1 ) . '%' );
			} else {
				/* translators: %s: Percentage of file size saved. */
				$summary = sprintf( __( 'Optimized by Cimo · %s saved', 'media-bridge-for-etch' ), number_format_i18n( $savings, 1 ) . '%' );
			}
		}

		return array(
			'optimized'            => true,
			'summary'              => $summary,
			'details'              => $details,
			'_current'             => $is_current,
			'_full_original_size'  => $full_original_size,
			'_full_optimized_size' => $full_optimized_size,
			'_full_savings'        => $full_savings,
			'_status'              => $status,
			'_generated_processed' => $generated_processed,
			'_total_original_size' => $original_size,
			'_total_optimized_size' => $optimized_size,
		);
	}

	private function assign_media_items( array $media_ids, int $collection, string $mode, bool $trusted_command = false ): array {
		$errors = array();
		foreach ( $media_ids as $media_id ) {
			if ( 'attachment' !== get_post_type( $media_id ) || ( ! $trusted_command && ! current_user_can( 'edit_post', $media_id ) ) ) {
				$errors[] = $media_id;
				continue;
			}
			if ( 'add' === $mode ) {
				$result = wp_set_object_terms( $media_id, array( $collection ), $this->etch->taxonomy(), true );
			} elseif ( 'remove' === $mode ) {
				$result = wp_remove_object_terms( $media_id, array( $collection ), $this->etch->taxonomy() );
			} else {
				$result = wp_set_object_terms( $media_id, array(), $this->etch->taxonomy(), false );
			}
			if ( is_wp_error( $result ) ) {
				$errors[] = $media_id;
			}
		}
		if ( ! $errors ) {
			do_action( 'uplink_mbe_media_collections_changed', $media_ids, $collection, $mode );
		}
		return $errors;
	}

	private function update_attachment_fields( int $attachment_id, $fields, bool $trusted_command = false ) {
		if ( 'attachment' !== get_post_type( $attachment_id ) || ( ! $trusted_command && ! current_user_can( 'edit_post', $attachment_id ) ) ) {
			return new \WP_Error( 'uplink_mbe_cannot_edit', __( 'You are not allowed to edit this attachment.', 'media-bridge-for-etch' ) );
		}
		$fields      = is_array( $fields ) ? $fields : array();
		$decorative  = array_key_exists( 'decorative', $fields )
			? ! empty( $fields['decorative'] )
			: '1' === (string) get_post_meta( $attachment_id, '_uplink_mbe_decorative', true );
		$post_update = array( 'ID' => $attachment_id );
		if ( array_key_exists( 'title', $fields ) ) {
			$post_update['post_title'] = sanitize_text_field( $fields['title'] );
		}
		if ( array_key_exists( 'caption', $fields ) ) {
			$post_update['post_excerpt'] = sanitize_textarea_field( $fields['caption'] );
		}
		if ( array_key_exists( 'description', $fields ) ) {
			$post_update['post_content'] = wp_kses_post( $fields['description'] );
		}
		if ( 1 < count( $post_update ) ) {
			$updated = wp_update_post( wp_slash( $post_update ), true );
			if ( is_wp_error( $updated ) ) {
				return $updated;
			}
		}
		if ( array_key_exists( 'alt', $fields ) || array_key_exists( 'decorative', $fields ) ) {
			$current_alt = (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
			$alt         = $decorative ? '' : sanitize_text_field( $fields['alt'] ?? $current_alt );
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
			if ( $decorative ) {
				update_post_meta( $attachment_id, '_uplink_mbe_decorative', '1' );
			} else {
				delete_post_meta( $attachment_id, '_uplink_mbe_decorative' );
			}
		}
		do_action( 'uplink_mbe_attachment_updated', $attachment_id, $fields, $this->attachment_data( $attachment_id ) );
		$this->invalidate_media_health();
		return true;
	}

	private function valid_filter_date( string $value ): string {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts ) ) {
			return '';
		}
		return checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) ? $value : '';
	}

	private function numeric_filter_value( mixed $value ): ?float {
		$value = is_scalar( $value ) ? (string) $value : '';
		return '' !== $value && is_numeric( $value ) ? max( 0, (float) $value ) : null;
	}

	/**
	 * Return attachment IDs matching filters that WordPress cannot query reliably.
	 * Null means no advanced filter is active; an empty array means no matches.
	 */
	private function advanced_filter_ids( array $criteria ): ?array {
		$has_dimensions = null !== $criteria['width_min'] || null !== $criteria['width_max'] || null !== $criteria['height_min'] || null !== $criteria['height_max'];
		$has_file_size  = null !== $criteria['file_size_min'] || null !== $criteria['file_size_max'];
		if ( ! $has_dimensions && ! $has_file_size && ! $criteria['missing_alt'] && empty( $criteria['optimization_status'] ) ) {
			return null;
		}

		$ids = get_posts(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			)
		);
		$matches = array();
		foreach ( $ids as $attachment_id ) {
			$attachment_id = (int) $attachment_id;
			$metadata      = wp_get_attachment_metadata( $attachment_id );
			$width         = is_array( $metadata ) ? (float) ( $metadata['width'] ?? 0 ) : 0;
			$height        = is_array( $metadata ) ? (float) ( $metadata['height'] ?? 0 ) : 0;
			$file_size     = is_array( $metadata ) ? (float) ( $metadata['filesize'] ?? 0 ) : 0;
			if ( $has_file_size && ! $file_size ) {
				$file = get_attached_file( $attachment_id );
				$file_size = $file && is_readable( $file ) ? (float) filesize( $file ) : 0;
			}
			$file_size_mb = $file_size / MB_IN_BYTES;

			if ( null !== $criteria['width_min'] && $width < $criteria['width_min'] ) {
				continue;
			}
			if ( null !== $criteria['width_max'] && $width > $criteria['width_max'] ) {
				continue;
			}
			if ( null !== $criteria['height_min'] && $height < $criteria['height_min'] ) {
				continue;
			}
			if ( null !== $criteria['height_max'] && $height > $criteria['height_max'] ) {
				continue;
			}
			if ( null !== $criteria['file_size_min'] && $file_size_mb < $criteria['file_size_min'] ) {
				continue;
			}
			if ( null !== $criteria['file_size_max'] && $file_size_mb > $criteria['file_size_max'] ) {
				continue;
			}
			if ( $criteria['missing_alt'] && ( ! wp_attachment_is_image( $attachment_id ) || '' !== trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) || '1' === (string) get_post_meta( $attachment_id, '_uplink_mbe_decorative', true ) ) ) {
				continue;
			}
			if ( ! empty( $criteria['optimization_status'] ) ) {
				$is_optimized = ! empty( $this->attachment_optimization( $attachment_id, is_array( $metadata ) ? $metadata : array() )['optimized'] );
				if ( ( 'optimized' === $criteria['optimization_status'] && ! $is_optimized ) || ( 'not_optimized' === $criteria['optimization_status'] && $is_optimized ) ) {
					continue;
				}
			}
			$matches[] = $attachment_id;
		}
		return $matches;
	}

	private function attachment_mime_types(): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- A small distinct list supplies the MIME subtype filter.
		$mime_types = $wpdb->get_col( "SELECT DISTINCT post_mime_type FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit' AND post_mime_type != '' ORDER BY post_mime_type ASC" );
		$mime_options = array_map(
			static function ( string $mime_type ): array {
				$parts = explode( '/', $mime_type, 2 );
				$label = strtoupper( str_replace( array( 'X-', '+XML' ), array( '', ' + XML' ), $parts[1] ?? $mime_type ) );
				return array( 'value' => $mime_type, 'label' => $label, 'type' => $parts[0] ?? '' );
			},
			array_values( array_filter( array_map( 'strval', is_array( $mime_types ) ? $mime_types : array() ) ) )
		);
		usort( $mime_options, static fn( array $a, array $b ): int => strcasecmp( $a['label'], $b['label'] ) );
		return $mime_options;
	}

	private function attachment_uploaders(): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- A small distinct author list supplies the uploader filter.
		$author_ids = $wpdb->get_col( "SELECT DISTINCT post_author FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit' AND post_author > 0 ORDER BY post_author ASC" );
		$uploaders  = array();
		foreach ( is_array( $author_ids ) ? $author_ids : array() as $author_id ) {
			$user = get_userdata( (int) $author_id );
			if ( $user ) {
				$uploaders[] = array( 'value' => (int) $author_id, 'label' => $user->display_name );
			}
		}
		usort( $uploaders, static fn( array $a, array $b ): int => strcasecmp( $a['label'], $b['label'] ) );
		return $uploaders;
	}

	private function attachment_months(): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- A small distinct list mirrors the core Media Library date filter.
		$rows = $wpdb->get_results(
			"SELECT DISTINCT YEAR(post_date) AS year, MONTH(post_date) AS month FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit' ORDER BY post_date DESC",
			ARRAY_A
		);
		return array_map(
			static fn( array $row ): array => array(
				'value' => sprintf( '%04d-%02d', absint( $row['year'] ), absint( $row['month'] ) ),
				'label' => wp_date( 'F Y', gmmktime( 12, 0, 0, absint( $row['month'] ), 15, absint( $row['year'] ) ), wp_timezone() ),
			),
			is_array( $rows ) ? $rows : array()
		);
	}

	private function attachment_extensions(): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- A compact filename list provides the available extension filter values.
		$files = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT pm.meta_value FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND p.post_type = %s AND p.post_status = %s",
				'_wp_attached_file',
				'attachment',
				'inherit'
			)
		);
		$extensions = array();
		foreach ( is_array( $files ) ? $files : array() as $file ) {
			$extension = strtolower( (string) pathinfo( (string) $file, PATHINFO_EXTENSION ) );
			if ( preg_match( '/^[a-z0-9]{1,10}$/', $extension ) ) {
				$extensions[ $extension ] = strtoupper( $extension );
			}
		}
		natcasesort( $extensions );
		return array_map(
			static fn( string $value, string $label ): array => array( 'value' => $value, 'label' => $label ),
			array_keys( $extensions ),
			array_values( $extensions )
		);
	}

	private function attachment_is_used( int $attachment_id ): bool {
		global $wpdb;
		$url      = (string) wp_get_attachment_url( $attachment_id );
		$patterns = array(
			'%' . $wpdb->esc_like( 'wp-image-' . $attachment_id ) . '%',
			'%' . $wpdb->esc_like( '"mediaId":"' . $attachment_id . '"' ) . '%',
			'%' . $wpdb->esc_like( '"mediaId":' . $attachment_id ) . '%',
		);
		if ( $url ) {
			$patterns[] = '%' . $wpdb->esc_like( $url ) . '%';
		}

		$where = implode( ' OR ', array_fill( 0, count( $patterns ), 'post_content LIKE %s' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and placeholder fragments are controlled locally; values are prepared below.
		$content_match = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID != %d AND post_status NOT IN ('trash', 'auto-draft') AND ({$where}) LIMIT 1", array_merge( array( $attachment_id ), $patterns ) ) );
		if ( $content_match ) {
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Featured-image usage is stored in core post meta.
		$featured_match = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %d LIMIT 1",
				$attachment_id
			)
		);
		return (bool) $featured_match;
	}

	private function validate_parent( int $parent, int $term_id = 0 ): string {
		if ( $term_id && $parent === $term_id ) {
			return __( 'A collection cannot be its own parent.', 'media-bridge-for-etch' );
		}

		$parent_depth = 0;
		if ( $parent ) {
			$parent_term = $this->etch->get_term( $parent );
			if ( ! $parent_term ) {
				return __( 'The selected parent collection no longer exists.', 'media-bridge-for-etch' );
			}

			$ancestors = get_ancestors( $parent, $this->etch->taxonomy(), 'taxonomy' );
			if ( $term_id && in_array( $term_id, array_map( 'intval', $ancestors ), true ) ) {
				return __( 'A collection cannot be moved inside one of its sub-collections.', 'media-bridge-for-etch' );
			}
			$parent_depth = count( $ancestors ) + 1;
		}

		$subtree_height = $term_id ? $this->collection_subtree_height( $term_id ) : 1;
		$max_depth      = Plugin::etch_collection_depth();
		if ( $parent_depth + $subtree_height > $max_depth ) {
			/* translators: %d is the configured maximum number of collection levels. */
			return sprintf( __( 'Etch Collections are limited to %d levels by the Media Bridge setting.', 'media-bridge-for-etch' ), $max_depth );
		}

		return '';
	}

	private function collection_subtree_height( int $term_id ): int {
		$terms = $this->etch->get_terms();
		if ( is_wp_error( $terms ) ) {
			return 1;
		}

		$children = array();
		foreach ( $terms as $term ) {
			$children[ (int) $term->parent ][] = (int) $term->term_id;
		}

		$height = function ( int $current, array $visited = array() ) use ( &$height, $children ): int {
			if ( isset( $visited[ $current ] ) ) {
				return 1;
			}
			$visited[ $current ] = true;
			$max_child_height = 0;
			foreach ( $children[ $current ] ?? array() as $child_id ) {
				$max_child_height = max( $max_child_height, $height( $child_id, $visited ) );
			}
			return 1 + $max_child_height;
		};

		return $height( $term_id );
	}

	/**
	 * Capture explicit provider positions before a collection mutation.
	 *
	 * @return array<int, int>
	 */
	private function snapshot_positions(): array {
		$terms = $this->etch->get_terms();
		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$positions = array();
		foreach ( $terms as $term ) {
			$position = $this->etch->get_position( (int) $term->term_id );
			if ( null !== $position ) {
				$positions[ (int) $term->term_id ] = $position;
			}
		}
		return $positions;
	}

	/**
	 * Restore surviving terms without assigning old positions to terms that
	 * intentionally moved into a different sibling group.
	 *
	 * @param array<int, int> $positions Saved term positions.
	 * @param int[]           $exclude   Moved terms whose positions will be appended.
	 */
	private function restore_positions( array $positions, array $exclude = array() ): void {
		$exclude = array_fill_keys( array_map( 'intval', $exclude ), true );
		foreach ( $positions as $term_id => $position ) {
			if ( isset( $exclude[ $term_id ] ) || ! $this->etch->get_term( $term_id ) ) {
				continue;
			}
			$this->etch->set_position( (int) $term_id, (int) $position );
		}
	}

	private function siblings_are_alphabetical( int $parent ): bool {
		$terms = get_terms(
			array(
				'taxonomy'   => $this->etch->taxonomy(),
				'hide_empty' => false,
				'parent'     => $parent,
			)
		);
		if ( is_wp_error( $terms ) || count( $terms ) < 2 ) {
			return true;
		}

		$by_position = $terms;
		usort(
			$by_position,
			function ( WP_Term $first, WP_Term $second ): int {
				$position = ( $this->etch->get_position( (int) $first->term_id ) ?? 0 ) <=> ( $this->etch->get_position( (int) $second->term_id ) ?? 0 );
				if ( 0 !== $position ) {
					return $position;
				}
				$name = strcasecmp( $first->name, $second->name );
				return 0 !== $name ? $name : (int) $first->term_id <=> (int) $second->term_id;
			}
		);
		$alphabetical = $terms;
		usort(
			$alphabetical,
			static function ( WP_Term $first, WP_Term $second ): int {
				$name = strcasecmp( $first->name, $second->name );
				return 0 !== $name ? $name : (int) $first->term_id <=> (int) $second->term_id;
			}
		);

		return array_map( static fn( WP_Term $term ): int => (int) $term->term_id, $by_position ) === array_map( static fn( WP_Term $term ): int => (int) $term->term_id, $alphabetical );
	}

	private function alphabetize_positions( int $parent ): void {
		$terms = get_terms(
			array(
				'taxonomy'   => $this->etch->taxonomy(),
				'hide_empty' => false,
				'parent'     => $parent,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
		if ( is_wp_error( $terms ) ) {
			return;
		}

		foreach ( $terms as $position => $term ) {
			$this->etch->set_position( (int) $term->term_id, $position );
		}
	}

	private function verify_request( bool $manage = false, bool $assign = true ): void {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to manage media.', 'media-bridge-for-etch' ) ), 403 );
		}

		$taxonomy = get_taxonomy( $this->etch->taxonomy() );
		if ( ! $taxonomy ) {
			wp_send_json_error( array( 'message' => __( 'Etch Collections are not available.', 'media-bridge-for-etch' ) ), 409 );
		}
		if ( $manage && ! current_user_can( $taxonomy->cap->manage_terms ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to manage collections.', 'media-bridge-for-etch' ) ), 403 );
		}
		if ( ! $manage && $assign && ! current_user_can( $taxonomy->cap->assign_terms ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to assign collections.', 'media-bridge-for-etch' ) ), 403 );
		}
	}

	public function ajax_save_appearance(): void {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to change the media appearance.', 'media-bridge-for-etch' ) ), 403 );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON values are decoded and sanitized as hex colors below.
		$submitted = json_decode( wp_unslash( $_POST['settings'] ?? '{}' ), true );
		if ( ! is_array( $submitted ) ) {
			wp_send_json_error( array( 'message' => __( 'The appearance settings were not valid.', 'media-bridge-for-etch' ) ), 400 );
		}
		$current   = Plugin::settings();
		$sanitized = Plugin::sanitize_appearance( $submitted, $current );
		$adjusted  = false;
		foreach ( $sanitized as $key => $value ) {
			if ( isset( $submitted[ $key ] ) && strtolower( (string) $submitted[ $key ] ) !== strtolower( (string) $value ) ) {
				$adjusted = true;
				break;
			}
		}
		Plugin::update_settings( array_merge( $current, $sanitized ) );

		$palette = array( 'light' => array(), 'dark' => array() );
		foreach ( Plugin::appearance_defaults() as $mode => $colors ) {
			foreach ( $colors as $name => $fallback ) {
				$palette[ $mode ][ $name ] = $sanitized[ $mode . '_' . $name ] ?? $fallback;
			}
		}
		wp_send_json_success(
			array(
				'appearance' => $sanitized['appearance'],
				'palette'    => $palette,
				'adjusted'   => $adjusted,
			)
		);
	}

	private function custom_color_css( bool $modal = false ): string {
		$settings = Plugin::settings();
		$native_map = array(
			'background' => 'background', 'surface' => 'surface', 'card' => 'card', 'surface_muted' => 'surface-muted', 'text' => 'text',
			'muted' => 'muted', 'border' => 'border', 'soft_border' => 'soft-border', 'control' => 'control',
			'control_border' => 'control-border', 'preview' => 'preview', 'preview_text' => 'preview-text', 'badge' => 'badge', 'badge_text' => 'badge-text', 'accent' => 'accent',
			'accent_text' => 'accent-text', 'accent_icon' => 'accent-icon', 'accent_soft' => 'accent-soft', 'accent_hover' => 'accent-hover', 'alt' => 'alt',
			'alt_text' => 'alt-text', 'guide' => 'guide', 'guide_border' => 'guide-border', 'guide_text' => 'guide-text',
			'drop_bg' => 'drop-bg', 'drop_border' => 'drop-border', 'drop_text' => 'drop-text', 'danger' => 'danger',
			'danger_solid' => 'danger-solid', 'danger_icon' => 'danger-icon', 'scrollbar_track' => 'scrollbar-track', 'scrollbar_thumb' => 'scrollbar-thumb',
			'scrollbar_hover' => 'scrollbar-hover',
		);
		$modal_map = array(
			'background' => 'bg', 'surface' => 'surface', 'card' => 'card', 'surface_muted' => 'footer', 'text' => 'text', 'muted' => 'muted',
			'border' => 'border', 'soft_border' => 'soft-border', 'control' => 'control', 'control_border' => 'hover-border',
			'preview' => 'preview', 'preview_text' => 'preview-text', 'badge' => 'badge', 'badge_text' => 'badge-text', 'accent' => 'accent', 'accent_text' => 'accent-text', 'accent_icon' => 'accent-icon',
			'accent_soft' => 'accent-soft', 'accent_hover' => 'accent-hover', 'alt' => 'alt', 'alt_text' => 'alt-text',
			'guide' => 'guide', 'guide_border' => 'guide-border', 'guide_text' => 'guide-text', 'drop_bg' => 'drop-bg',
			'drop_border' => 'drop-border', 'drop_text' => 'drop-text', 'danger' => 'danger', 'danger_solid' => 'danger-solid', 'danger_icon' => 'danger-icon',
			'scrollbar_track' => 'scrollbar-track', 'scrollbar_thumb' => 'scrollbar-thumb', 'scrollbar_hover' => 'scrollbar-hover',
		);
		$declarations = static function ( string $mode, string $prefix, array $map ) use ( $settings ): string {
			$css = '';
			foreach ( $map as $setting => $variable ) {
				$css .= '--' . $prefix . $variable . ':' . esc_attr( $settings[ $mode . '_' . $setting ] ) . ';';
			}
			return $css;
		};

		if ( $modal ) {
			$selector = ':is(.uplink-mbe-modal-browser,.media-modal.uplink-mbe-frame)';
			$light = $declarations( 'light', 'mbe-modal-', $modal_map );
			$dark  = $declarations( 'dark', 'mbe-modal-', $modal_map );
			return $selector . '{' . $light . '}' . $selector . '.uplink-mbe-theme-dark{' . $dark . '}@media(prefers-color-scheme:dark){' . $selector . '.uplink-mbe-theme-auto{' . $dark . '}}';
		}

		$light = $declarations( 'light', 'mbe-', $native_map );
		$dark  = $declarations( 'dark', 'mbe-', $native_map );
		$selector = ':is(.uplink-mbe-native-wrap,body.uplink-mbe-image-editor-screen)';
		return $selector . '{' . $light . '}' . $selector . '.uplink-mbe-theme-dark{' . $dark . '}@media(prefers-color-scheme:dark){' . $selector . '.uplink-mbe-theme-auto{' . $dark . '}}';
	}
}
