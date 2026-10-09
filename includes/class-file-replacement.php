<?php
namespace UplinkPress\MediaBridgeForEtch;

/** Replace attachment bytes while preserving the attachment's identity and relationships. */
final class File_Replacement {
	public function __construct() {
		add_action( 'wp_ajax_uplink_mbe_replace_file', array( $this, 'ajax_replace' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'wp_enqueue_media', array( $this, 'assets' ) );
		add_filter( 'attachment_fields_to_edit', array( $this, 'attachment_field' ), 30, 2 );
	}

	public function attachment_field( array $fields, $post ): array {
		if ( ! empty( Plugin::settings()['file_replacement'] ) && current_user_can( 'upload_files' ) && current_user_can( 'edit_post', $post->ID ) ) {
			$fields['uplink_mbe_replace'] = array( 'label' => __( 'File', 'media-bridge-for-etch' ), 'input' => 'html', 'html' => sprintf( '<button type="button" class="button" data-mbe-replace-id="%d" data-mbe-replace-name="%s">%s</button>', $post->ID, esc_attr( wp_basename( (string) get_attached_file( $post->ID ) ) ), esc_html__( 'Replace file', 'media-bridge-for-etch' ) ) );
		}
		return $fields;
	}

	public function assets(): void {
		if ( empty( Plugin::settings()['file_replacement'] ) || ! current_user_can( 'upload_files' ) ) return;
		Upload_Workspace::assets();
		wp_enqueue_style( 'uplink-mbe-file-replacement', UPLINK_MBE_URL . 'assets/file-replacement.css', array(), UPLINK_MBE_ASSET_VERSION );
		wp_enqueue_script( 'uplink-mbe-file-replacement', UPLINK_MBE_URL . 'assets/file-replacement.js', array( 'uplink-mbe-upload-workspace' ), UPLINK_MBE_ASSET_VERSION, true );
		wp_localize_script( 'uplink-mbe-file-replacement', 'uplinkMbeReplacement', array(
			'appearance' => Plugin::settings()['appearance'],
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'uplink_mbe_replace_file' ),
			'strings' => array(
				'title' => __( 'Replace file', 'media-bridge-for-etch' ),
				'file' => __( 'Replacement file', 'media-bridge-for-etch' ),
				'keep' => __( 'Keep the existing filename and URL', 'media-bridge-for-etch' ),
				'savedAs' => __( 'Will be saved as:', 'media-bridge-for-etch' ),
				'keptNameHelp' => __( 'The selected file will replace the existing file. Its filename and URL will stay the same.', 'media-bridge-for-etch' ),
				'keepHelp' => __( 'Requires the same file type after optimization. The file changes everywhere this URL is used. Cached copies may take time to refresh.', 'media-bridge-for-etch' ),
				'new' => __( 'Use the replacement filename and type', 'media-bridge-for-etch' ),
				'newHelp' => __( 'Creates a new file URL. Links written directly into content are not rewritten. Existing files are retained so those links keep working.', 'media-bridge-for-etch' ),
				'preserve' => __( 'The attachment ID, title, alt text, captions, collections, and custom order stay the same.', 'media-bridge-for-etch' ),
				'cancel' => __( 'Cancel', 'media-bridge-for-etch' ),
				'saving' => __( 'Replacing…', 'media-bridge-for-etch' ),
				'saved' => __( 'File replaced.', 'media-bridge-for-etch' ),
				'error' => __( 'The file could not be replaced.', 'media-bridge-for-etch' ),
			),
		) );
	}

	public function ajax_replace(): void {
		check_ajax_referer( 'uplink_mbe_replace_file', 'nonce' );
		if ( empty( Plugin::settings()['file_replacement'] ) ) wp_send_json_error( array( 'message' => __( 'Media Bridge file replacement is disabled in settings.', 'media-bridge-for-etch' ) ), 403 );
		$id = absint( $_POST['attachment_id'] ?? 0 );
		$mode = sanitize_key( wp_unslash( $_POST['mode'] ?? '' ) );
		if ( ! current_user_can( 'upload_files' ) || ! current_user_can( 'edit_post', $id ) || 'attachment' !== get_post_type( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot replace this attachment.', 'media-bridge-for-etch' ) ), 403 );
		}
		if ( ! in_array( $mode, array( 'keep', 'new' ), true ) || empty( $_FILES['file'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Choose a replacement file and how to name it.', 'media-bridge-for-etch' ) ), 400 );
		}
		if ( (int) ( $_FILES['file']['size'] ?? 0 ) > wp_max_upload_size() ) wp_send_json_error( array( 'message' => __( 'The replacement exceeds the upload size limit.', 'media-bridge-for-etch' ) ), 400 );
		// An atomic option prevents two replacements of the same attachment running together.
		$lock = 'uplink_mbe_replacing_' . $id;
		if ( ! add_option( $lock, time(), '', false ) ) {
			wp_send_json_error( array( 'message' => __( 'A replacement is already in progress for this attachment.', 'media-bridge-for-etch' ) ), 409 );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$result = null;
		try {
			// WordPress validates the HTTP upload, allowed MIME type, extension, and upload errors.
			$upload = wp_handle_upload( $_FILES['file'], array( 'test_form' => false ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$result = isset( $upload['error'] ) ? new \WP_Error( 'upload', $upload['error'] ) : self::replace( $id, $upload, $mode );
		} finally {
			delete_option( $lock );
		}
		if ( is_wp_error( $result ) ) wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		wp_send_json_success( array( 'id' => $id, 'url' => wp_get_attachment_url( $id ) ) );
	}

	/** $upload must be a successful wp_handle_upload result. */
	public static function replace( int $id, array $upload, string $mode ) {
		$staged = $upload['file'];
		$old_file = get_attached_file( $id, true );
		$old_meta = wp_get_attachment_metadata( $id );
		$old_meta = is_array( $old_meta ) ? $old_meta : array();
		$old_mime = get_post_mime_type( $id );
		$old_attached = get_post_meta( $id, '_wp_attached_file', true );
		$database_started = false;
		$backups = array();
		$generated = array( $staged );
		$committed = false;
		if ( $staged === $old_file ) return new \WP_Error( 'same_file', __( 'Choose a new upload to replace this file.', 'media-bridge-for-etch' ) );
		try {
			if ( ! in_array( $mode, array( 'keep', 'new' ), true ) ) throw new \RuntimeException( __( 'Invalid replacement option.', 'media-bridge-for-etch' ) );
			$base = realpath( wp_get_upload_dir()['basedir'] );
			if ( ! $base || ! realpath( $staged ) || ! str_starts_with( realpath( $staged ), $base . DIRECTORY_SEPARATOR ) ) throw new \RuntimeException( __( 'The replacement must be stored in the local uploads directory.', 'media-bridge-for-etch' ) );
			if ( 'keep' === $mode && ( ! $old_file || ! is_file( $old_file ) || ! str_starts_with( realpath( $old_file ), $base . DIRECTORY_SEPARATOR ) || ! is_writable( $old_file ) ) ) throw new \RuntimeException( __( 'The existing file must be writable in the local uploads directory to keep its URL.', 'media-bridge-for-etch' ) );
			// Generate against an isolated staging attachment so hooks cannot update the live record mid-replacement.
			$temp_id = wp_insert_attachment( array( 'post_mime_type' => $upload['type'], 'post_title' => 'Media Bridge replacement staging', 'post_status' => 'inherit' ), $staged, 0, true );
			if ( is_wp_error( $temp_id ) ) throw new \RuntimeException( $temp_id->get_error_message() );
			try {
				$metadata = wp_generate_attachment_metadata( $temp_id, $staged );
				$metadata = is_array( $metadata ) ? $metadata : array();
				wp_update_attachment_metadata( $temp_id, $metadata );
				$metadata = wp_get_attachment_metadata( $temp_id ) ?: array();
				$staged = get_attached_file( $temp_id, true );
				$generated[] = $staged;
				foreach ( $metadata['sizes'] ?? array() as $size ) $generated[] = dirname( $staged ) . '/' . $size['file'];
				if ( ! empty( $metadata['original_image'] ) ) $generated[] = dirname( $staged ) . '/' . $metadata['original_image'];
			} finally {
				// Keep generated files; delete only the temporary database record.
				delete_post_meta( $temp_id, '_wp_attached_file' );
				delete_post_meta( $temp_id, '_wp_attachment_metadata' );
				wp_delete_attachment( $temp_id, true );
			}
			if ( str_starts_with( $upload['type'], 'image/' ) && 'image/svg+xml' !== $upload['type'] && empty( $metadata['width'] ) ) throw new \RuntimeException( __( 'WordPress could not process the replacement image.', 'media-bridge-for-etch' ) );
			$final_type = wp_check_filetype_and_ext( $staged, wp_basename( $staged ) );
			if ( empty( $final_type['type'] ) ) throw new \RuntimeException( __( 'The processed replacement has an unsupported file type.', 'media-bridge-for-etch' ) );
			$upload['type'] = $final_type['type'];
			if ( 'keep' === $mode && ( $old_mime !== $upload['type'] || strtolower( pathinfo( $old_file, PATHINFO_EXTENSION ) ) !== strtolower( pathinfo( $staged, PATHINFO_EXTENSION ) ) ) ) throw new \RuntimeException( __( 'Keeping the URL requires the same file type and extension. Choose the other option to use a different type.', 'media-bridge-for-etch' ) );
			$target = $staged;
			if ( 'keep' === $mode ) {
				$target = $old_file;
				$writes = array( $old_file => $staged );
				$new_sizes = array();
				foreach ( $metadata['sizes'] ?? array() as $name => $size ) {
					$source = dirname( $staged ) . '/' . $size['file'];
					$filename = pathinfo( $old_file, PATHINFO_FILENAME ) . '-' . $size['width'] . 'x' . $size['height'] . '.' . pathinfo( $size['file'], PATHINFO_EXTENSION );
					$writes[ dirname( $old_file ) . '/' . $filename ] = $source;
					$size['file'] = $filename;
					$new_sizes[ $name ] = $size;
				}
				// Refresh previously generated URLs too, including sizes no longer registered.
				foreach ( $old_meta['sizes'] ?? array() as $size ) {
					$destination = dirname( $old_file ) . '/' . wp_basename( $size['file'] );
					if ( isset( $writes[ $destination ] ) || ! str_starts_with( $upload['type'], 'image/' ) ) continue;
					$editor = wp_get_image_editor( $staged );
					if ( is_wp_error( $editor ) ) throw new \RuntimeException( $editor->get_error_message() );
					$resized = $editor->resize( (int) $size['width'], (int) $size['height'], true );
					$dimensions = $editor->get_size();
					// WordPress does not upscale. Refresh old larger URLs with the smaller replacement.
					if ( is_wp_error( $resized ) && ! ( $dimensions['width'] <= (int) $size['width'] && $dimensions['height'] <= (int) $size['height'] ) ) throw new \RuntimeException( $resized->get_error_message() );
					$variant = $editor->save( dirname( $staged ) . '/mbe-' . wp_generate_uuid4() . '.' . pathinfo( $destination, PATHINFO_EXTENSION ) );
					if ( is_wp_error( $variant ) ) throw new \RuntimeException( $variant->get_error_message() );
					$generated[] = $variant['path'];
					$writes[ $destination ] = $variant['path'];
				}
				foreach ( $writes as $destination => $source ) {
					if ( is_link( $destination ) ) throw new \RuntimeException( __( 'Linked files cannot be replaced.', 'media-bridge-for-etch' ) );
					$backup = is_file( $destination ) ? wp_tempnam( 'mbe-replacement-backup' ) : null;
					if ( is_file( $destination ) && ( ! $backup || ! copy( $destination, $backup ) ) ) throw new \RuntimeException( __( 'Could not back up the existing file.', 'media-bridge-for-etch' ) );
					$backups[ $destination ] = $backup;
					if ( ! copy( $source, $destination ) ) throw new \RuntimeException( __( 'Could not write the replacement file.', 'media-bridge-for-etch' ) );
				}
				$metadata['file'] = _wp_relative_upload_path( $old_file );
				$metadata['sizes'] = $new_sizes;
				unset( $metadata['original_image'] );
			}
			$database_started = true;
			$result = wp_update_post( array( 'ID' => $id, 'post_mime_type' => $upload['type'] ), true );
			if ( is_wp_error( $result ) ) throw new \RuntimeException( $result->get_error_message() );
			update_attached_file( $id, $target );
			// Do not let metadata-preservation filters carry information from the old bytes forward.
			delete_post_meta( $id, '_wp_attachment_metadata' );
			wp_update_attachment_metadata( $id, $metadata );
			if ( get_attached_file( $id, true ) !== $target || wp_get_attachment_metadata( $id ) !== $metadata ) throw new \RuntimeException( __( 'Could not save replacement metadata.', 'media-bridge-for-etch' ) );
			delete_post_meta( $id, '_wp_attachment_backup_sizes' );
			delete_post_meta( $id, '_uplink_mbe_etch_optimization' );
			clean_post_cache( $id );
			$committed = true;
			return true;
		} catch ( \Throwable $error ) {
			if ( $database_started ) {
				wp_update_post( array( 'ID' => $id, 'post_mime_type' => $old_mime ) );
				update_post_meta( $id, '_wp_attached_file', $old_attached );
				update_post_meta( $id, '_wp_attachment_metadata', $old_meta );
			}
			foreach ( $backups as $destination => $backup ) {
				if ( $backup ) copy( $backup, $destination );
				else wp_delete_file( $destination );
			}
			return new \WP_Error( 'replacement_failed', $error->getMessage() );
		} finally {
			foreach ( $backups as $backup ) if ( $backup ) wp_delete_file( $backup );
			if ( ! $committed || 'keep' === $mode ) foreach ( array_unique( $generated ) as $file ) wp_delete_file( $file );
		}
	}
}
