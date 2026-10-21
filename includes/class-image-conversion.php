<?php
namespace UplinkPress\MediaBridgeForEtch;
if ( ! defined( 'ABSPATH' ) ) exit;

/** Local, reversible conversion. Source files are retained; no Cimo license required. */
final class Image_Conversion {
	private const BACKUP = '_uplink_mbe_conversion_backup';
	private const FORMATS = array( 'webp' => 'image/webp', 'avif' => 'image/avif', 'jxl' => 'image/jxl', 'png' => 'image/png', 'jpg' => 'image/jpeg' );
	public function __construct() {
		add_action( 'wp_ajax_uplink_mbe_image_conversion', array( $this, 'ajax' ) );
		add_action( 'delete_attachment', array( self::class, 'delete_files' ) );
		add_action( 'uplink_mbe_conversion_expire', array( self::class, 'expire' ) );
		add_filter( 'wp_get_attachment_image', array( self::class, 'picture' ), 30 );
		add_filter( 'the_content', array( self::class, 'picture' ), 30 );
		add_filter( 'render_block', array( self::class, 'picture' ), 30 );
	}
	/** Formats enabled by the administrator, in the order used by preview cards. */
	public static function enabled_formats(): array {
		$settings = Plugin::settings();
		return array_values( array_filter( array( 'webp', 'avif', 'jxl', 'jpg' ), static fn( string $format ): bool => ! empty( $settings[ 'compression_' . $format ] ) ) );
	}
	public static function assets(): void {
		if ( ! current_user_can( 'upload_files' ) || ( ! self::role_allowed( 'compress' ) && ! self::role_allowed( 'restore' ) ) ) return;
		wp_enqueue_script_module( 'uplink-mbe-optimization-components', UPLINK_MBE_URL . 'assets/optimization-components.js', array(), UPLINK_MBE_ASSET_VERSION );
		wp_enqueue_style( 'uplink-mbe-optimization-theme', UPLINK_MBE_URL . 'assets/vendor/webawesome/theme.css', array(), UPLINK_MBE_ASSET_VERSION );
		wp_enqueue_style( 'uplink-mbe-image-conversion', UPLINK_MBE_URL . 'assets/image-conversion.css', array( 'uplink-mbe-native-media' ), UPLINK_MBE_ASSET_VERSION );
		wp_enqueue_script( 'uplink-mbe-image-conversion', UPLINK_MBE_URL . 'assets/image-conversion.js', array( 'uplink-mbe-native-media' ), UPLINK_MBE_ASSET_VERSION, true );
		wp_localize_script( 'uplink-mbe-image-conversion', 'uplinkMbeConversion', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'uplink_mbe_image_conversion' ), 'enabled' => ! empty( Plugin::settings()['image_conversion'] ), 'formats' => self::enabled_formats(), 'settingsUrl' => admin_url( 'upload.php?page=media-bridge-for-etch&tab=settings#uplink-mbe-compression-settings' ), 'worker' => UPLINK_MBE_URL . 'assets/image-encode-worker.js?v=' . UPLINK_MBE_ASSET_VERSION,
			'strings' => array(
				'sizesTitle' => __( 'Image sizes', 'media-bridge-for-etch' ),
				'allSizes' => __( 'All sizes', 'media-bridge-for-etch' ), 'mainOnly' => __( 'Main image only', 'media-bridge-for-etch' ), 'chooseSizes' => __( 'Selected sizes', 'media-bridge-for-etch' ),
				'excludedSize' => __( 'Excluded in Settings. Check to include for this image.', 'media-bridge-for-etch' ),
				'exclusionsHint' => __( 'All sizes honors Settings exclusions. Choose Selected sizes to override them for this image.', 'media-bridge-for-etch' ),
				'sizeHint' => __( 'Includes existing WordPress and custom sizes. Sizes that share a file are processed together once. Unselected files keep their current format.', 'media-bridge-for-etch' ),
				'scopeLabel' => __( 'Optimize', 'media-bridge-for-etch' ), 'tooLarge' => __( 'A file exceeds the 40-megapixel limit. Choose the main image or select smaller sizes.', 'media-bridge-for-etch' ),
				'sizeInventory' => __( 'Available image files', 'media-bridge-for-etch' ), 'originalUpload' => __( 'Original upload', 'media-bridge-for-etch' ), 'selectionEmpty' => __( 'Select at least one image size before applying compression.', 'media-bridge-for-etch' ),
				'selectedFiles' => __( 'files selected', 'media-bridge-for-etch' ), 'totalFiles' => __( 'files available', 'media-bridge-for-etch' ),
				'previewOnly' => __( 'Previews show the main image. Only your selected sizes will be saved.', 'media-bridge-for-etch' ),
				'title' => __( 'Optimize image', 'media-bridge-for-etch' ),
				'help' => __( 'Compare your enabled formats at suggested quality settings, then fine-tune the result you prefer. Your files change only when you choose a format. Originals are kept for restoration.', 'media-bridge-for-etch' ),
				'mainPreview' => __( 'Main image preview', 'media-bridge-for-etch' ), 'previewBadge' => __( 'Preview', 'media-bridge-for-etch' ),
				'qualityHelp' => __( 'Drag an image divider to compare. Adjust quality below the image.', 'media-bridge-for-etch' ),
				'compare' => __( 'Generate format previews', 'media-bridge-for-etch' ),
				'applyCompression' => __( 'Apply compression', 'media-bridge-for-etch' ),
				'compress' => __( 'Compress current format', 'media-bridge-for-etch' ),
				'restore' => __( 'Restore originals', 'media-bridge-for-etch' ),
				'restoring' => __( 'Restoring files and image references…', 'media-bridge-for-etch' ),
				'restored' => __( 'Original image and references restored.', 'media-bridge-for-etch' ),
				'processing' => __( 'Processing image', 'media-bridge-for-etch' ),
				'keepOpen' => __( 'Keep this window open until processing finishes.', 'media-bridge-for-etch' ),
				'preparing' => __( 'Checking original files and generated sizes…', 'media-bridge-for-etch' ),
				'ready' => __( 'Previews are ready. Choose image sizes and apply your preferred format. Savings for the selected files will be saved in this tab.', 'media-bridge-for-etch' ),
				'restoreFirst' => __( 'Restore the original before making another conversion.', 'media-bridge-for-etch' ),
				'compareFirst' => __( 'Compare the image before converting it.', 'media-bridge-for-etch' ),
				'failed' => __( 'Image conversion failed.', 'media-bridge-for-etch' ),
				'encoderFailed' => __( 'The local image encoder could not load. Check whether your browser or site policy blocks workers or WebAssembly.', 'media-bridge-for-etch' ),
				'fetchFailed' => __( 'An original image size could not be downloaded.', 'media-bridge-for-etch' ),
				'before' => __( 'Before', 'media-bridge-for-etch' ), 'after' => __( 'After', 'media-bridge-for-etch' ), 'size' => __( 'Image size', 'media-bridge-for-etch' ), 'savings' => __( 'Saved', 'media-bridge-for-etch' ), 'quality' => __( 'Quality', 'media-bridge-for-etch' ), 'date' => __( 'Completed', 'media-bridge-for-etch' ), 'automatic' => __( 'Automatic', 'media-bridge-for-etch' ),
				'files' => __( 'files', 'media-bridge-for-etch' ),
				'preview' => __( 'comparison preview', 'media-bridge-for-etch' ),
				'preparingPreview' => __( 'Preparing preview…', 'media-bridge-for-etch' ),
				'retryPreview' => __( 'Retry preview', 'media-bridge-for-etch' ),
				'releaseQuality' => __( 'Release to update the preview.', 'media-bridge-for-etch' ),
				'smallerFile' => __( 'Smaller file', 'media-bridge-for-etch' ),
				'moreDetail' => __( 'More detail', 'media-bridge-for-etch' ),
				'jxlInfo' => __( 'About JPEG XL', 'media-bridge-for-etch' ),
				'closeInfo' => __( 'Close information', 'media-bridge-for-etch' ),
				'compressionSettings' => __( 'Compression settings', 'media-bridge-for-etch' ),
				'noFormats' => __( 'No output formats are enabled. Select at least one format in Compression settings.', 'media-bridge-for-etch' ),
				'currentOptimized' => __( 'This image is already optimized in the enabled format. Enable another output format to compare.', 'media-bridge-for-etch' ),
				'jxlSummary' => __( 'Original-format fallback included', 'media-bridge-for-etch' ),
				/* translators: %s: Image format. */
				'useFormat' => __( 'Optimize to %s', 'media-bridge-for-etch' ),
				/* translators: %s: Image format. */
				'inspectFormat' => __( 'Inspect %s comparison', 'media-bridge-for-etch' ),
				/* translators: %s: Percentage reduction in file size. */
				'smallerResult' => __( '%s%% smaller', 'media-bridge-for-etch' ),
				/* translators: %s: Percentage increase in file size. */
				'largerResult' => __( '%s%% larger', 'media-bridge-for-etch' ),
				'original' => __( 'Original', 'media-bridge-for-etch' ),
				'compressed' => __( 'Compressed', 'media-bridge-for-etch' ),
				'compareTitle' => __( 'Original and compressed', 'media-bridge-for-etch' ),
				'compareHelp' => __( 'Drag the divider or use the arrow keys to compare. Press Escape to close.', 'media-bridge-for-etch' ),
				'openCompare' => __( 'Inspect', 'media-bridge-for-etch' ),
				'close' => __( 'Close comparison', 'media-bridge-for-etch' ),
				'previewNeeded' => __( 'Preview this format to compare image quality and file size.', 'media-bridge-for-etch' ),
				/* translators: %s: Image format. */
				'compareImages' => __( '%s original and compressed comparison', 'media-bridge-for-etch' ),
				/* translators: %s: Image format. */
				'previewFormat' => __( 'Preview %s', 'media-bridge-for-etch' ),
				/* translators: %s: Image format. */
				'inspectShort' => __( 'Inspect %s', 'media-bridge-for-etch' ),
				'openPreview' => __( 'Open full-size preview', 'media-bridge-for-etch' ),
				'jxlHelp' => __( 'JPEG XL is served through picture sources with the original image as fallback. Existing picture elements, CSS backgrounds and external links keep their original format.', 'media-bridge-for-etch' ),
				'useJxl' => __( 'Use JPEG XL with fallback', 'media-bridge-for-etch' ),
				'view' => __( 'View saved result', 'media-bridge-for-etch' ),
				'updating' => __( 'Saving the restoration record and updating image references…', 'media-bridge-for-etch' ),
				/* translators: %s: Image format. */
				'convert' => __( 'Convert to %s', 'media-bridge-for-etch' ),
				/* translators: %s: Saved format. */
				'saved' => __( '%s optimization saved. Original files are retained. Restore before converting again or replacing this file.', 'media-bridge-for-etch' ),
				/* translators: %1$d: Current file. %2$d: Total files. */
				'previewProgress' => __( 'Preparing preview %1$d of %2$d…', 'media-bridge-for-etch' ),
				/* translators: %1$d: Current file. %2$d: Total files. */
				'progress' => __( 'Encoding %1$d of %2$d…', 'media-bridge-for-etch' ),
				/* translators: %1$d: Current file. %2$d: Total files. */
				'saving' => __( 'Saving size %1$d of %2$d…', 'media-bridge-for-etch' ),
				/* translators: %1$s: Encoded bytes. %2$s: Percentage saved. %3$d: File count. */
				'previewTotal' => __( 'Full image: %1$s · %2$s%% smaller', 'media-bridge-for-etch' ),
				/* translators: %1$d: File count. %2$d: Updated reference records. */
				'complete' => __( 'Saved %1$d files and updated %2$d reference records. Original backups are retained.', 'media-bridge-for-etch' ),
			),
		) );
	}
	/** Role grants never override WordPress upload or attachment-edit permissions. */
	public static function role_allowed( string $action ): bool {
		$key = 'restore' === $action ? 'restoration_roles' : 'compression_roles';
		return (bool) array_intersect( wp_get_current_user()->roles, (array) Plugin::settings()[ $key ] );
	}
	public static function allowed( int $id, string $action = 'compress' ): bool {
		return 'attachment' === get_post_type( $id ) && self::role_allowed( $action ) && current_user_can( 'upload_files' ) && current_user_can( 'edit_post', $id );
	}
	public static function active( int $id ): bool { return (bool) get_post_meta( $id, self::BACKUP, true ); }
	public static function summary( int $id ): ?array {
		$backup = get_post_meta( $id, self::BACKUP, true );
		if ( ! $backup ) return null;
		$total = 0; foreach ( $backup['converted'] as $file ) if ( is_file( $file ) ) $total += filesize( $file );
		return array( 'scope' => $backup['scope'] ?? 'all', 'format' => $backup['format'], 'before' => array_sum( array_column( $backup['sources'], 'bytes' ) ), 'after' => $total, 'sizes' => count( $backup['converted'] ), 'references' => $backup['changed'], 'quality' => $backup['quality'] ?? null, 'date' => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $backup['created'] ), 'rows' => $backup['rows'] ?? array() );
	}
	public static function delete_files( int $id ): void {
		$files = get_post_meta( $id, '_uplink_mbe_conversion_files', true );
		foreach ( is_array( $files ) ? $files : array() as $file ) {
			try { self::local( $file ); wp_delete_file( $file ); } catch ( \Throwable $error ) { /* Never delete paths outside local uploads. */ }
		}
		$index = get_option( 'uplink_mbe_jxl_sources', array() );
		if ( isset( $index[ $id ] ) ) { unset( $index[ $id ] ); update_option( 'uplink_mbe_jxl_sources', $index, false ); }
	}
	private static function fail( string $message ): void { throw new \RuntimeException( esc_html( $message ) ); }
	private static function local( string $path ): string {
		$base = realpath( wp_get_upload_dir()['basedir'] ); $real = realpath( $path );
		if ( ! $base || ! $real || ! str_starts_with( $real, $base . DIRECTORY_SEPARATOR ) || ! is_file( $real ) || ! is_readable( $real ) || is_link( $path ) ) self::fail( __( 'Every image size must be a readable local uploads file.', 'media-bridge-for-etch' ) );
		return $real;
	}
	private static function snapshot( int $id ): array {
		return array( 'file' => get_post_meta( $id, '_wp_attached_file', true ), 'metadata' => wp_get_attachment_metadata( $id ), 'mime' => get_post_mime_type( $id ), 'editor_backups' => get_post_meta( $id, '_wp_attachment_backup_sizes', true ) );
	}
	public static function begin( int $id ): array {
		if ( empty( Plugin::settings()['image_conversion'] ) ) self::fail( __( 'Image compression is disabled in settings.', 'media-bridge-for-etch' ) );
		if ( ! self::enabled_formats() ) self::fail( __( 'Select at least one output format in Compression settings.', 'media-bridge-for-etch' ) );
		if ( ! self::allowed( $id ) ) self::fail( __( 'You do not have permission to compress this image.', 'media-bridge-for-etch' ) );
		if ( self::active( $id ) ) return array( 'restore' => true, 'format' => get_post_meta( $id, self::BACKUP, true )['format'] );
		$old = self::snapshot( $id );
		if ( ! in_array( $old['mime'], array( 'image/png', 'image/jpeg', 'image/webp', 'image/avif' ), true ) || empty( $old['metadata']['width'] ) ) self::fail( __( 'Choose a local PNG, JPEG, WebP or AVIF image.', 'media-bridge-for-etch' ) );
		$path = self::local( get_attached_file( $id, true ) ); $meta = $old['metadata'];
		$source = array( 'full' => wp_basename( $path ) );
		foreach ( $meta['sizes'] ?? array() as $key => $size ) $source[ $key ] = $size['file'];
		if ( ! empty( $meta['original_image'] ) ) $source['original_image'] = $meta['original_image'];
		$files = array(); $aliases = array(); $seen = array();
		foreach ( $source as $key => $name ) {
			if ( ! is_string( $name ) || wp_basename( $name ) !== $name ) self::fail( __( 'An image size has an invalid filename.', 'media-bridge-for-etch' ) );
			$file = self::local( dirname( $path ) . '/' . $name );
			if ( isset( $seen[ $file ] ) ) { $aliases[ $key ] = $seen[ $file ]; continue; }
			$info = wp_getimagesize( $file );
			if ( ! $info ) self::fail( __( 'An image size cannot be decoded.', 'media-bridge-for-etch' ) );
			$seen[ $file ] = $key;
			$files[ $key ] = array( 'available' => $info[0] * $info[1] <= 40000000, 'path' => $file, 'name' => $name, 'url' => trailingslashit( dirname( wp_get_attachment_url( $id ) ) ) . rawurlencode( $name ), 'width' => $info[0], 'height' => $info[1], 'bytes' => filesize( $file ), 'hash' => hash_file( 'sha256', $file ) );
		}
		if ( count( $files ) > 100 ) self::fail( __( 'This attachment exceeds the 100-file conversion limit.', 'media-bridge-for-etch' ) );
		foreach ( self::excluded_keys( $files, $aliases ) as $key ) $files[ $key ]['excluded'] = true;
		$token = wp_generate_uuid4();
		$job = array( 'owner' => get_current_user_id(), 'id' => $id, 'old' => $old, 'files' => $files, 'aliases' => $aliases, 'staged' => array(), 'format' => '', 'quality' => null, 'expires' => time() + 7200 );
		add_option( 'uplink_mbe_conversion_' . $token, $job, '', false );
		wp_schedule_single_event( time() + 7200, 'uplink_mbe_conversion_expire', array( $token ) );
		foreach ( $files as &$file ) unset( $file['path'], $file['hash'] ); unset( $file );
		return array( 'token' => $token, 'files' => $files, 'aliases' => $aliases, 'restore' => false, 'mime' => $old['mime'], 'canCompress' => empty( $meta['cimo'] ) && empty( $meta['mbe_optimization'] ) && ! get_post_meta( $id, '_uplink_mbe_etch_optimization', true ) );
	}
	/** An excluded alias excludes its shared physical file as well. */
	private static function excluded_keys( array $files, array $aliases ): array {
		$excluded = array();
		foreach ( (array) Plugin::settings()['compression_excluded_sizes'] as $name ) {
			$key = $aliases[ $name ] ?? $name;
			if ( isset( $files[ $key ] ) ) $excluded[] = $key;
		}
		return array_values( array_unique( $excluded ) );
	}
	/** Select only files from the server-created manifest, before staging starts. */
	public static function select_sizes( string $token, string $scope, array $sizes = array() ): array {
		$job = self::job( $token );
		if ( ! in_array( $scope, array( 'all', 'main', 'selected' ), true ) ) self::fail( __( 'Choose a valid image-size option.', 'media-bridge-for-etch' ) );
		$all = $job['all_files'] ?? $job['files'];
		$keys = 'all' === $scope ? array_values( array_diff( array_keys( $all ), self::excluded_keys( $all, $job['aliases'] ) ) ) : ( 'main' === $scope ? array( 'full' ) : $sizes );
		if ( ! $keys ) self::fail( __( 'Select at least one image size.', 'media-bridge-for-etch' ) );
		$selected = array();
		foreach ( $keys as $key ) {
			if ( ! is_string( $key ) ) self::fail( __( 'Invalid image size.', 'media-bridge-for-etch' ) );
			$key = $job['aliases'][ $key ] ?? $key;
			if ( ! isset( $all[ $key ] ) ) self::fail( __( 'This image size does not exist for the attachment.', 'media-bridge-for-etch' ) );
			if ( isset( $all[ $key ]['available'] ) && ! $all[ $key ]['available'] ) self::fail( __( 'A selected file exceeds the 40-megapixel limit. Choose the main image or smaller sizes.', 'media-bridge-for-etch' ) );
			$selected[ $key ] = $all[ $key ];
		}
		if ( $job['staged'] && array_keys( $selected ) !== array_keys( $job['files'] ) ) self::fail( __( 'Size selection cannot change after encoding has started.', 'media-bridge-for-etch' ) );
		$job['all_files'] = $all; $job['files'] = $selected; $job['scope'] = $scope;
		update_option( 'uplink_mbe_conversion_' . $token, $job, false );
		return array( 'sizes' => array_keys( $selected ), 'count' => count( $selected ), 'scope' => $scope );
	}
	private static function job( string $token ): array {
		if ( ! preg_match( '/^[a-f0-9-]{36}$/D', $token ) ) self::fail( __( 'Invalid conversion session.', 'media-bridge-for-etch' ) );
		$job = get_option( 'uplink_mbe_conversion_' . $token );
		if ( ! is_array( $job ) || $job['expires'] < time() || $job['owner'] !== get_current_user_id() || ! self::allowed( $job['id'] ) ) self::fail( __( 'This conversion session expired or belongs to another user.', 'media-bridge-for-etch' ) );
		return $job;
	}
	public static function stage( string $token, string $key, string $format, array $upload, int $quality = 75 ): array {
		$job = self::job( $token );
		if ( ! isset( $job['scope'] ) ) self::fail( __( 'Choose image sizes before encoding.', 'media-bridge-for-etch' ) );
		if ( isset( $job['files'][ $key ]['available'] ) && ! $job['files'][ $key ]['available'] ) self::fail( __( 'This file exceeds the 40-megapixel conversion limit.', 'media-bridge-for-etch' ) );
		if ( empty( Plugin::settings()['image_conversion'] ) || ! in_array( $format, self::enabled_formats(), true ) ) self::fail( __( 'This compression format is disabled in settings.', 'media-bridge-for-etch' ) );
		if ( ! isset( self::FORMATS[ $format ], $job['files'][ $key ] ) || ( $job['format'] && $job['format'] !== $format ) ) self::fail( __( 'Invalid image size or conversion format.', 'media-bridge-for-etch' ) );
		if ( ! empty( $upload['error'] ) || empty( $upload['tmp_name'] ) || ! is_uploaded_file( $upload['tmp_name'] ) || filesize( $upload['tmp_name'] ) > wp_max_upload_size() ) self::fail( __( 'The converted file exceeds the upload limit or is not a valid upload.', 'media-bridge-for-etch' ) );
		if ( 'png' === $format ) $quality = 0;
		if ( $quality < 0 || $quality > 100 || ( 'png' !== $format && $quality < 1 ) || ( isset( $job['quality'] ) && $job['quality'] !== $quality ) ) self::fail( __( 'Invalid or inconsistent quality setting.', 'media-bridge-for-etch' ) );
		$expected = $job['files'][ $key ]; $path = $upload['tmp_name'];
		if ( 'jxl' === $format ) {
			$head = file_get_contents( $path, false, null, 0, 12 );
			if ( ! str_starts_with( $head, "\xff\x0a" ) && "\x00\x00\x00\x0cJXL \x0d\x0a\x87\x0a" !== $head ) self::fail( __( 'The uploaded file is not JPEG XL.', 'media-bridge-for-etch' ) );
		} else {
			$info = wp_getimagesize( $path );
			if ( ! $info || $info['mime'] !== self::FORMATS[ $format ] || $info[0] !== $expected['width'] || $info[1] !== $expected['height'] ) self::fail( __( 'Converted dimensions or format do not match the original image size.', 'media-bridge-for-etch' ) );
		}
		$temp = wp_tempnam( 'mbe-conversion' );
		// phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- Upload origin, actual MIME and dimensions were validated above; keep staging outside public uploads.
		if ( ! $temp || ! move_uploaded_file( $path, $temp ) ) self::fail( __( 'Could not stage the converted file.', 'media-bridge-for-etch' ) );
		if ( isset( $job['staged'][ $key ] ) ) wp_delete_file( $job['staged'][ $key ] );
		$job['staged'][ $key ] = $temp; $job['format'] = $format; $job['quality'] = $quality;
		update_option( 'uplink_mbe_conversion_' . $token, $job, false );
		return array( 'saved' => true );
	}
	private static function unchanged( array $job ): void {
		if ( self::active( $job['id'] ) || self::snapshot( $job['id'] ) !== $job['old'] ) self::fail( __( 'This image changed after comparison. Compare it again before converting.', 'media-bridge-for-etch' ) );
		foreach ( $job['all_files'] ?? $job['files'] as $file ) if ( hash_file( 'sha256', self::local( $file['path'] ) ) !== $file['hash'] ) self::fail( __( 'An original file changed after comparison. Compare it again.', 'media-bridge-for-etch' ) );
	}
	private static function save_attachment( int $id, array $data ): void {
		$result = wp_update_post( array( 'ID' => $id, 'post_mime_type' => $data['mime'] ), true );
		if ( is_wp_error( $result ) ) self::fail( $result->get_error_message() );
		update_post_meta( $id, '_wp_attached_file', $data['file'] );
		update_post_meta( $id, '_wp_attachment_metadata', wp_slash( $data['metadata'] ) );
		if ( $data['editor_backups'] ) update_post_meta( $id, '_wp_attachment_backup_sizes', wp_slash( $data['editor_backups'] ) );
		else delete_post_meta( $id, '_wp_attachment_backup_sizes' );
		clean_post_cache( $id );
		if ( self::snapshot( $id ) !== $data ) self::fail( __( 'Could not save image metadata.', 'media-bridge-for-etch' ) );
	}
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Explicit transaction boundaries; data values use prepared statements and caches are invalidated.
	public static function commit( string $token ): array {
		global $wpdb;
		$job = self::job( $token ); $id = $job['id'];
		if ( empty( Plugin::settings()['image_conversion'] ) ) self::fail( __( 'Image compression is disabled in settings.', 'media-bridge-for-etch' ) );
		self::unchanged( $job );
		foreach ( $job['files'] as $file ) if ( isset( $file['available'] ) && ! $file['available'] ) self::fail( __( 'A selected file exceeds the conversion limit.', 'media-bridge-for-etch' ) );
		if ( count( $job['staged'] ) !== count( $job['files'] ) ) self::fail( __( 'Every original and generated size must finish conversion before saving.', 'media-bridge-for-etch' ) );
		$format = $job['format'];
		if ( ! in_array( $format, self::enabled_formats(), true ) ) self::fail( __( 'This compression format is disabled in settings.', 'media-bridge-for-etch' ) );
		$mime = self::FORMATS[ $format ];
		if ( $mime === $job['old']['mime'] && ( ! empty( $job['old']['metadata']['cimo'] ) || ! empty( $job['old']['metadata']['mbe_optimization'] ) || get_post_meta( $id, '_uplink_mbe_etch_optimization', true ) ) ) self::fail( __( 'This image already has optimization data. Choose a different format.', 'media-bridge-for-etch' ) );
		$journal = array(); $written = array(); $urls = array(); $destinations = array(); $started = false;
		$old_jxl_index = get_option( 'uplink_mbe_jxl_sources', array() );
		try {
			foreach ( $job['files'] as $key => $file ) {
				if ( ! is_file( $job['staged'][ $key ] ) ) self::fail( __( 'A staged file is missing. Compare the image again.', 'media-bridge-for-etch' ) );
				$dir = dirname( $file['path'] );
				$name = wp_unique_filename( $dir, pathinfo( $file['name'], PATHINFO_FILENAME ) . '-mbe-' . substr( $token, 0, 8 ) . '.' . $format );
				$target = $dir . '/' . $name;
				if ( is_link( $target ) || ! copy( $job['staged'][ $key ], $target ) ) self::fail( __( 'Could not save a converted image size.', 'media-bridge-for-etch' ) );
				$written[] = $target; $destinations[ $key ] = array( 'path' => $target, 'file' => $name, 'bytes' => filesize( $target ) );
				$urls[ $file['url'] ] = trailingslashit( dirname( $file['url'] ) ) . rawurlencode( $name );
			}
			$new = $job['old'];
			if ( 'jxl' !== $format ) {
				if ( isset( $destinations['full'] ) ) {
					$new['editor_backups'] = '';
					$new['file'] = _wp_relative_upload_path( $destinations['full']['path'] ); $new['mime'] = $mime;
					$new['metadata']['file'] = $new['file']; $new['metadata']['filesize'] = $destinations['full']['bytes'];
				}
				unset( $new['metadata']['cimo'] );
				$new['metadata']['mbe_optimization'] = array( 'format' => $format, 'sizes' => count( $written ) );
				foreach ( $new['metadata']['sizes'] ?? array() as $key => $size ) { $target = $destinations[ $job['aliases'][ $key ] ?? $key ] ?? null; if ( ! $target ) continue; $size['file'] = $target['file']; $size['mime-type'] = $mime; $size['filesize'] = $target['bytes']; $new['metadata']['sizes'][ $key ] = $size; }
				if ( isset( $new['metadata']['original_image'], $destinations[ $job['aliases']['original_image'] ?? 'original_image' ] ) ) $new['metadata']['original_image'] = $destinations[ $job['aliases']['original_image'] ?? 'original_image' ]['file'];
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Current-site transaction groups reference and attachment changes.
			$wpdb->query( 'START TRANSACTION' ); $started = true;
			$changed = 0;
			if ( 'jxl' === $format ) {
				$index = get_option( 'uplink_mbe_jxl_sources', array() ); $index[ $id ] = $urls; update_option( 'uplink_mbe_jxl_sources', $index, false );
			} else { $changed = Image_References::update( $urls, $journal ); self::save_attachment( $id, $new ); }
			$rows = array(); foreach ( $job['files'] as $key => $source ) $rows[] = array( 'size' => $key, 'width' => $source['width'], 'height' => $source['height'], 'before' => $source['bytes'], 'after' => $destinations[ $key ]['bytes'] );
			$backup = array( 'scope' => $job['scope'] ?? 'all', 'etch_optimization' => get_post_meta( $id, '_uplink_mbe_etch_optimization', true ), 'quality' => $job['quality'], 'rows' => $rows, 'format' => $format, 'old' => $job['old'], 'new' => $new, 'sources' => $job['files'], 'urls' => $urls, 'converted' => $written, 'created' => time(), 'changed' => $changed );
			$retained = get_post_meta( $id, '_uplink_mbe_conversion_files', true );
				update_post_meta( $id, '_uplink_mbe_conversion_files', array_values( array_unique( array_merge( is_array( $retained ) ? $retained : array(), $written, array_column( $job['files'], 'path' ) ) ) ) );
			if ( ! add_post_meta( $id, self::BACKUP, wp_slash( $backup ), true ) ) self::fail( __( 'Could not save the restoration record.', 'media-bridge-for-etch' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Commit only after restoration data exists.
			$wpdb->query( 'COMMIT' );
			delete_post_meta( $id, '_uplink_mbe_etch_optimization' );
			self::expire( $token ); Etch_Dynamic_Data::invalidate_cache( $id ); self::refresh();
			return array( 'saved' => true, 'references' => $changed, 'sizes' => count( $written ), 'format' => $format, 'summary' => self::summary( $id ) );
		} catch ( \Throwable $error ) {
			if ( $started ) { $wpdb->query( 'ROLLBACK' ); Image_References::rollback( $journal ); self::save_attachment( $id, $job['old'] ); delete_post_meta( $id, self::BACKUP ); if ( 'jxl' === $format ) { update_option( 'uplink_mbe_jxl_sources', $old_jxl_index, false ); wp_cache_delete( 'uplink_mbe_jxl_sources', 'options' ); } }
			foreach ( $written as $file ) wp_delete_file( $file );
			throw $error;
		}
	}
	public static function restore( int $id ): array {
		global $wpdb;
		if ( ! self::allowed( $id, 'restore' ) ) self::fail( __( 'You cannot restore this image.', 'media-bridge-for-etch' ) );
		$backup = get_post_meta( $id, self::BACKUP, true );
		if ( ! $backup || self::snapshot( $id ) !== ( 'jxl' === $backup['format'] ? $backup['old'] : $backup['new'] ) ) self::fail( __( 'The attachment changed after conversion. Restore is unavailable until those changes are resolved.', 'media-bridge-for-etch' ) );
		foreach ( $backup['sources'] as $file ) if ( hash_file( 'sha256', self::local( $file['path'] ) ) !== $file['hash'] ) self::fail( __( 'An original backup is missing or changed. Nothing was restored.', 'media-bridge-for-etch' ) );
		$journal = array(); $wpdb->query( 'START TRANSACTION' );
		try {
			$changed = 0;
			if ( 'jxl' === $backup['format'] ) { $index = get_option( 'uplink_mbe_jxl_sources', array() ); unset( $index[ $id ] ); update_option( 'uplink_mbe_jxl_sources', $index, false ); }
			else { $changed = Image_References::update( array_flip( $backup['urls'] ), $journal ); self::save_attachment( $id, $backup['old'] ); }
			if ( ! empty( $backup['etch_optimization'] ) ) update_post_meta( $id, '_uplink_mbe_etch_optimization', $backup['etch_optimization'] );
			delete_post_meta( $id, self::BACKUP );
			if ( self::active( $id ) ) self::fail( __( 'Could not complete restoration.', 'media-bridge-for-etch' ) );
			$wpdb->query( 'COMMIT' ); Etch_Dynamic_Data::invalidate_cache( $id ); self::refresh();
			return array( 'restored' => true, 'references' => $changed );
		} catch ( \Throwable $error ) { $wpdb->query( 'ROLLBACK' ); Image_References::rollback( $journal ); self::save_attachment( $id, $backup['new'] ); update_post_meta( $id, self::BACKUP, wp_slash( $backup ) ); throw $error; }
	}
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	private static function refresh(): void {
		delete_transient( 'uplink_mbe_media_health_v3' );

	}
	public static function expire( string $token ): void {
		$job = get_option( 'uplink_mbe_conversion_' . $token );
		if ( $job ) { foreach ( $job['staged'] as $file ) if ( str_starts_with( $file, get_temp_dir() ) ) wp_delete_file( $file ); delete_option( 'uplink_mbe_conversion_' . $token ); wp_clear_scheduled_hook( 'uplink_mbe_conversion_expire', array( $token ) ); }
	}
	public function ajax(): void {
		check_ajax_referer( 'uplink_mbe_image_conversion', 'nonce' );
		$id = absint( $_POST['attachment_id'] ?? 0 );
		$mode = sanitize_key( wp_unslash( $_POST['mode'] ?? '' ) );
		if ( ! self::allowed( $id, 'restore' === $mode ? 'restore' : 'compress' ) ) wp_send_json_error( array( 'message' => __( 'You do not have permission for this image action.', 'media-bridge-for-etch' ) ), 403 );
		$token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
		$lock = 'uplink_mbe_replacing_' . $id;
		global $wpdb;
		$old_lock = get_option( $lock );
		if ( $old_lock && (int) $old_lock < time() - 300 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Compare-and-delete only an expired mutex.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $lock, $old_lock ) ); wp_cache_delete( $lock, 'options' );
		}
		$lock_owner = time() . ':' . wp_generate_uuid4();
		if ( ! add_option( $lock, $lock_owner, '', false ) ) wp_send_json_error( array( 'message' => __( 'This attachment is already being changed. Try again shortly.', 'media-bridge-for-etch' ) ), 409 );
		try {
			if ( in_array( $mode, array( 'select', 'stage', 'commit', 'cancel', 'validate' ), true ) && self::job( $token )['id'] !== $id ) self::fail( __( 'The conversion session does not match this attachment.', 'media-bridge-for-etch' ) );
			if ( 'begin' === $mode ) $result = self::begin( $id );
			elseif ( 'select' === $mode ) $result = self::select_sizes( $token, sanitize_key( wp_unslash( $_POST['scope'] ?? '' ) ), isset( $_POST['sizes'] ) && is_array( $_POST['sizes'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['sizes'] ) ) : array() );
			elseif ( 'stage' === $mode ) $result = self::stage( $token, sanitize_text_field( wp_unslash( $_POST['size'] ?? '' ) ), sanitize_key( wp_unslash( $_POST['format'] ?? '' ) ), $_FILES['file'] ?? array(), absint( $_POST['quality'] ?? 0 ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Upload provenance, size, actual MIME and dimensions are validated in stage().
			elseif ( 'validate' === $mode ) { self::unchanged( self::job( $token ) ); $result = array( 'valid' => true ); }
			elseif ( 'commit' === $mode ) $result = self::commit( $token );
			elseif ( 'restore' === $mode ) $result = self::restore( $id );
			elseif ( 'cancel' === $mode ) { self::expire( $token ); $result = array( 'cancelled' => true ); }
			else self::fail( __( 'Invalid conversion action.', 'media-bridge-for-etch' ) );
		} catch ( \Throwable $error ) { $result = new \WP_Error( 'conversion_failed', $error->getMessage() ); }
		finally {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Release only this request's mutex.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $lock, $lock_owner ) ); wp_cache_delete( $lock, 'options' );
		}
		if ( is_wp_error( $result ) ) wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		wp_send_json_success( $result );
	}
	public static function picture( string $html ): string {
		$index = get_option( 'uplink_mbe_jxl_sources', array() );
		if ( ! $index || ! str_contains( $html, '<img' ) ) return $html;
		$map = array(); foreach ( $index as $urls ) $map += $urls;
		$variants = Image_References::variants( $map );
		$pieces = preg_split( '/(<script\b[^>]*>.*?<\/script>|<style\b[^>]*>.*?<\/style>|<textarea\b[^>]*>.*?<\/textarea>|<!--.*?-->|<\/?picture\b[^>]*>|<img\b[^>]*>)/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE ); $depth = 0;
		foreach ( $pieces as &$part ) {
			if ( preg_match( '/^<picture\b/i', $part ) ) { ++$depth; continue; }
			if ( preg_match( '/^<\/picture\b/i', $part ) ) { $depth = max( 0, $depth - 1 ); continue; }
			if ( $depth || ! preg_match( '/^<img\b/i', $part ) ) continue;
			$tag = new \WP_HTML_Tag_Processor( $part ); if ( ! $tag->next_tag( 'IMG' ) ) continue;
			$src = html_entity_decode( (string) $tag->get_attribute( 'src' ), ENT_QUOTES );
			$new = Image_References::replace( $src, $variants ); if ( $new === $src ) continue;
			$set = (string) $tag->get_attribute( 'srcset' ); $sizes = (string) $tag->get_attribute( 'sizes' );
			$source_set = $set ? Image_References::replace( $set, $variants ) : $new;
			$part = '<picture class="uplink-mbe-picture" style="display:contents"><source type="image/jxl" srcset="' . esc_attr( $source_set ) . '"' . ( $sizes ? ' sizes="' . esc_attr( $sizes ) . '"' : '' ) . '>' . $part . '</picture>';
		} unset( $part );
		return implode( '', $pieces );
	}
}
