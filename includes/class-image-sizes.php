<?php
namespace UplinkPress\MediaBridgeForEtch;

if ( ! defined( 'ABSPATH' ) ) exit;

/** Repair registered image sizes without replacing the original attachment. */
final class Image_Sizes {
	private const ACTION = 'uplink_mbe_image_sizes';
	private const PENDING = '_uplink_mbe_avif_upload_pending';
	public function __construct() {
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'ajax' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ), 25 );
		add_action( 'wp_enqueue_media', array( self::class, 'enqueue_assets' ), 40 );
		add_action( 'add_attachment', array( $this, 'mark_upload' ) );
		add_filter( 'plupload_init', array( $this, 'upload_settings' ) );
		add_filter( 'plupload_default_settings', array( $this, 'upload_settings' ) );
		add_filter( 'wp_prevent_unsupported_mime_type_uploads', array( $this, 'allow_avif_upload' ), 10, 2 );
	}
	/** Relax only AVIF's editor-availability guard; WordPress still validates upload permissions and real MIME. */
	public function upload_settings( array $settings ): array {
		if ( current_user_can( 'upload_files' ) && ! wp_image_editor_supports( array( 'mime_type' => 'image/avif' ) ) ) $settings['avif_upload_error'] = false;
		return $settings;
	}
	public function allow_avif_upload( bool $prevent, ?string $mime ): bool {
		return 'image/avif' === $mime && current_user_can( 'upload_files' ) ? false : $prevent;
	}
	/** Mark only new AVIF uploads that this host cannot resize. */
	public function mark_upload( int $id ): void {
		if ( 'image/avif' === get_post_mime_type( $id ) && ! wp_image_editor_supports( array( 'mime_type' => 'image/avif' ) ) ) update_post_meta( $id, self::PENDING, 1 );
	}
	public function assets( string $hook ): void {
		if ( ! in_array( $hook, array( 'upload.php', 'media-new.php', 'post.php', 'post-new.php', 'media_page_etch-collections' ), true ) ) return;
		self::enqueue_assets();
	}
	public static function enqueue_assets(): void {
		if ( ! current_user_can( 'upload_files' ) ) return;
		global $pagenow;
		$dependencies = array( 'wp-plupload', 'wp-api-fetch' );
		if ( 'media-new.php' === $pagenow ) $dependencies[] = 'plupload-handlers';
		wp_enqueue_script( self::ACTION, UPLINK_MBE_URL . 'assets/image-sizes.js', $dependencies, UPLINK_MBE_ASSET_VERSION, true );
		wp_localize_script( self::ACTION, 'uplinkMbeImageSizes', array(
			'hostNeedsFallback' => ! wp_image_editor_supports( array( 'mime_type' => 'image/avif' ) ),
			'manualAllowed' => Image_Conversion::role_allowed( 'compress' ),
			'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( self::ACTION ),
			'worker' => UPLINK_MBE_URL . 'assets/image-size-worker.js?v=' . UPLINK_MBE_ASSET_VERSION,
			'strings' => array(
				'autoWorking' => __( 'Generating AVIF image sizes. Keep this page open.', 'media-bridge-for-etch' ),
				'autoDone' => __( 'AVIF image sizes are ready.', 'media-bridge-for-etch' ),
				'autoFailed' => __( 'The image was uploaded, but some AVIF sizes could not be generated. Retry without uploading the file again.', 'media-bridge-for-etch' ),
				'retry' => __( 'Retry image sizes', 'media-bridge-for-etch' ),
				'repair' => __( 'Repair missing image sizes', 'media-bridge-for-etch' ),
				'about' => __( 'How repair works', 'media-bridge-for-etch' ),
				'help' => __( 'Uses WordPress first, then this browser for AVIF if needed. Keep this page open. Originals and their EXIF remain unchanged; browser-generated copies do not include EXIF.', 'media-bridge-for-etch' ),
				'working' => __( 'Repairing image', 'media-bridge-for-etch' ),
				'done' => __( 'Image size repair finished.', 'media-bridge-for-etch' ),
				'failed' => __( 'Some images could not be repaired. Completed sizes are retained; retry to continue.', 'media-bridge-for-etch' ),
				'unsupported' => __( 'This browser cannot process AVIF locally. Try a current browser with AVIF decoding, workers and WebAssembly enabled.', 'media-bridge-for-etch' ),
				'fetchFailed' => __( 'The original image could not be downloaded.', 'media-bridge-for-etch' ),
				'error' => __( 'Image size repair failed.', 'media-bridge-for-etch' ),
			),
		) );
	}
	private static function fail( string $message ): void { throw new \RuntimeException( esc_html( $message ) ); }
	private static function source( int $id ): array {
		if ( Image_Conversion::active( $id ) ) self::fail( __( 'Restore the original before repairing sizes for this converted image.', 'media-bridge-for-etch' ) );
		$uploads = wp_get_upload_dir();
		$base = realpath( $uploads['basedir'] );
		$file = realpath( (string) get_attached_file( $id ) );
		if ( ! $base || ! $file || ! str_starts_with( $file, $base . DIRECTORY_SEPARATOR ) || ! is_file( $file ) || ! is_readable( $file ) ) self::fail( __( 'A readable original in the local uploads directory is required.', 'media-bridge-for-etch' ) );
		$mime = wp_get_image_mime( $file );
		if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/webp', 'image/avif' ), true ) ) self::fail( __( 'This image format cannot be repaired here.', 'media-bridge-for-etch' ) );
		$info = wp_getimagesize( $file );
		$meta = wp_get_attachment_metadata( $id );
		if ( ! $info || ! is_array( $meta ) || (int) ( $meta['width'] ?? 0 ) !== $info[0] || (int) ( $meta['height'] ?? 0 ) !== $info[1] ) self::fail( __( 'The original dimensions do not match the attachment metadata.', 'media-bridge-for-etch' ) );
		if ( $info[0] * $info[1] > 40000000 || filesize( $file ) > 32 * MB_IN_BYTES ) self::fail( __( 'Repair is limited to originals under 40 megapixels and 32 MB.', 'media-bridge-for-etch' ) );
		if ( 'image/avif' === $mime && str_contains( (string) file_get_contents( $file, false, null, 0, 64 ), 'avis' ) ) self::fail( __( 'Animated AVIF images are left unchanged.', 'media-bridge-for-etch' ) );
		return array( 'file' => $file, 'mime' => $mime, 'width' => $info[0], 'height' => $info[1], 'meta' => $meta );
	}
	private static function missing( array $source ): array {
		$result = array();
		foreach ( wp_get_registered_image_subsizes() as $name => $size ) {
			if ( isset( $source['meta']['sizes'][ $name ] ) ) continue;
			$d = image_resize_dimensions( $source['width'], $source['height'], $size['width'], $size['height'], $size['crop'] );
			if ( $d ) $result[ $name ] = array( 'name' => $name, 'width' => $d[4], 'height' => $d[5], 'crop' => array( $d[2], $d[3], $d[6], $d[7] ) );
		}
		return $result;
	}
	private static function signature( int $id, array $source, int $time ): string {
		return wp_hash( $id . '|' . get_current_user_id() . '|' . $time . '|' . $source['file'] . '|' . hash_file( 'sha256', $source['file'] ) . '|' . wp_json_encode( wp_get_registered_image_subsizes() ), 'nonce' );
	}
	private static function plan( int $id ): array {
		$source = self::source( $id );
		if ( self::missing( $source ) && wp_image_editor_supports( array( 'mime_type' => $source['mime'] ) ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$result = wp_update_image_subsizes( $id );
			if ( is_wp_error( $result ) ) self::fail( $result->get_error_message() );
			$source = self::source( $id );
		}
		$sizes = array_values( self::missing( $source ) );
		if ( ! $sizes ) delete_post_meta( $id, self::PENDING );
		if ( $sizes && 'image/avif' !== $source['mime'] ) self::fail( __( 'WordPress could not generate these sizes. The browser fallback currently supports AVIF originals.', 'media-bridge-for-etch' ) );
		$time = time();
		return array( 'sizes' => $sizes, 'url' => wp_get_attachment_url( $id ), 'width' => $source['width'], 'height' => $source['height'], 'token' => $time . ':' . self::signature( $id, $source, $time ) );
	}
	private static function save( int $id ): array {
		check_ajax_referer( self::ACTION, 'nonce' );
		$source = self::source( $id );
		$token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
		$parts = explode( ':', $token, 2 );
		$time = (int) $parts[0];
		if ( count( $parts ) !== 2 || $time > time() || $time < time() - 900 || ! hash_equals( self::signature( $id, $source, $time ), $parts[1] ) ) self::fail( __( 'The repair session expired or the original changed. Start the repair again.', 'media-bridge-for-etch' ) );
		$name = sanitize_text_field( wp_unslash( $_POST['size'] ?? '' ) );
		$missing = self::missing( $source );
		// Retrying a completed size is harmless; never overwrite an existing derivative.
		if ( isset( $source['meta']['sizes'][ $name ] ) ) return array( 'saved' => true );
		if ( 'image/avif' !== $source['mime'] || ! isset( $missing[ $name ] ) ) self::fail( __( 'This is not a missing registered AVIF size.', 'media-bridge-for-etch' ) );
		$upload = $_FILES['file'] ?? array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Provenance, length, MIME and dimensions checked below.
		$temp = $upload['tmp_name'] ?? '';
		if ( ! is_string( $temp ) || ! is_uploaded_file( $temp ) || UPLOAD_ERR_OK !== ( $upload['error'] ?? -1 ) || ! filesize( $temp ) || filesize( $temp ) > min( wp_max_upload_size(), 32 * MB_IN_BYTES ) ) self::fail( __( 'The generated file upload is not valid.', 'media-bridge-for-etch' ) );
		$info = wp_getimagesize( $temp );
		$expected = $missing[ $name ];
		if ( 'image/avif' !== wp_get_image_mime( $temp ) || ! $info || $info[0] !== $expected['width'] || $info[1] !== $expected['height'] ) self::fail( __( 'The generated file has the wrong format or dimensions.', 'media-bridge-for-etch' ) );
		if ( filesize( $temp ) > filesize( $source['file'] ) ) self::fail( __( 'The generated copy is larger than the original.', 'media-bridge-for-etch' ) );
		$filename = wp_unique_filename( dirname( $source['file'] ), pathinfo( $source['file'], PATHINFO_FILENAME ) . '-' . $info[0] . 'x' . $info[1] . '.avif' );
		$destination = dirname( $source['file'] ) . DIRECTORY_SEPARATOR . $filename;
		// Exclusive creation also protects against a file appearing after wp_unique_filename().
		// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Local validated uploads need exclusive creation and streaming; WP_Filesystem cannot provide exclusive mode.
		$handle = @fopen( $destination, 'x+b' );
		if ( ! $handle ) self::fail( __( 'The image size could not be saved.', 'media-bridge-for-etch' ) );
		$input = fopen( $temp, 'rb' );
		$copied = $input ? stream_copy_to_stream( $input, $handle ) : false;
		if ( $input ) fclose( $input );
		fclose( $handle );
		// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		if ( $copied !== filesize( $temp ) ) { wp_delete_file( $destination ); self::fail( __( 'The generated file was not completely saved.', 'media-bridge-for-etch' ) ); }
		$stat = stat( dirname( $destination ) );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Match the local uploads directory's file permissions, as WordPress upload handling does.
		chmod( $destination, $stat['mode'] & 0000666 );
		$meta = $source['meta'];
		$meta['sizes'][ $name ] = array( 'file' => $filename, 'width' => $info[0], 'height' => $info[1], 'mime-type' => 'image/avif', 'filesize' => $copied );
		if ( ! wp_update_attachment_metadata( $id, $meta ) ) { wp_delete_file( $destination ); self::fail( __( 'The image size metadata could not be saved.', 'media-bridge-for-etch' ) ); }
		$source['meta'] = $meta;
		if ( ! self::missing( $source ) ) delete_post_meta( $id, self::PENDING );
		return array( 'saved' => true );
	}
	public function ajax(): void {
		check_ajax_referer( self::ACTION, 'nonce' );
		$id = absint( $_POST['attachment_id'] ?? 0 );
		$mode = sanitize_key( wp_unslash( $_POST['mode'] ?? '' ) );
		$automatic = in_array( $mode, array( 'auto_plan', 'auto_save' ), true );
		// Upload derivatives use normal WordPress upload/edit permissions. Manual repair keeps the configured compression role restriction.
		$allowed = $automatic ? ( 'attachment' === get_post_type( $id ) && current_user_can( 'upload_files' ) && current_user_can( 'edit_post', $id ) ) : Image_Conversion::allowed( $id );
		if ( ! $allowed ) wp_send_json_error( array( 'message' => __( 'You do not have permission to repair this image.', 'media-bridge-for-etch' ) ), 403 );
		if ( $automatic && ! get_post_meta( $id, self::PENDING, true ) ) wp_send_json_success( array( 'sizes' => array(), 'skipped' => true ) );
		$lock = 'uplink_mbe_replacing_' . $id;
		$owner = time() . ':' . wp_generate_uuid4();
		global $wpdb;
		$old = get_option( $lock );
		if ( $old && (int) $old < time() - 300 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Compare-and-delete an expired attachment mutex.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $lock, $old ) );
			wp_cache_delete( $lock, 'options' );
		}
		if ( ! add_option( $lock, $owner, '', false ) ) wp_send_json_error( array( 'message' => __( 'This attachment is already being changed. Try again shortly.', 'media-bridge-for-etch' ) ), 409 );
		try {
			if ( in_array( $mode, array( 'plan', 'auto_plan' ), true ) ) $result = self::plan( $id );
			elseif ( in_array( $mode, array( 'save', 'auto_save' ), true ) ) $result = self::save( $id );
			else self::fail( __( 'Invalid image size action.', 'media-bridge-for-etch' ) );
		} catch ( \Throwable $error ) { $result = new \WP_Error( 'image_sizes_failed', $error->getMessage() ); }
		finally {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Release only this request's attachment mutex.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $lock, $owner ) );
			wp_cache_delete( $lock, 'options' );
			delete_transient( 'uplink_mbe_media_health_v3' );
		}
		if ( is_wp_error( $result ) ) wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		wp_send_json_success( $result );
	}
}
