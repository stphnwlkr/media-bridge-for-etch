<?php
namespace UplinkPress\MediaBridgeForEtch;

final class Upload_Workspace {
	public static function assets(): void {
		static $enqueued = false;
		if ( $enqueued ) return;
		$enqueued = true;
		// Cimo exposes location filters for custom upload interfaces.
		wp_enqueue_script( 'wp-hooks' );
		wp_add_inline_script( 'wp-hooks', "['cimo.selectFiles.allowedLocations', 'cimo.dropZone.allowedLocations'].forEach(function (hook) { wp.hooks.addFilter(hook, 'uplink-media-bridge/upload-workspace', function (locations) { return locations.concat(['.uplink-mbe-upload-workspace']); }); });" );
		wp_enqueue_style( 'uplink-mbe-upload-workspace', UPLINK_MBE_URL . 'assets/media-modal.css', array(), UPLINK_MBE_ASSET_VERSION );
		wp_enqueue_script( 'uplink-mbe-upload-workspace', UPLINK_MBE_URL . 'assets/upload-workspace.js', array( 'jquery' ), UPLINK_MBE_ASSET_VERSION, true );
		wp_localize_script( 'uplink-mbe-upload-workspace', 'uplinkMbeUploadConfig', array(
			'cimoAvailable' => defined( 'CIMO_FILE' ),
			'maxUploadBytes' => wp_max_upload_size(),
			'maxUploadSize' => size_format( wp_max_upload_size() ),
			'strings' => array(
				'acceptedFiles' => __( 'Files accepted by WordPress', 'media-bridge-for-etch' ),
				'cancelUpload' => __( 'Cancel', 'media-bridge-for-etch' ),
				'chooseFiles' => __( 'Choose files', 'media-bridge-for-etch' ),
				'clearFiles' => __( 'Clear', 'media-bridge-for-etch' ),
				'collectionCreated' => __( 'Collection created and selected.', 'media-bridge-for-etch' ),
				'collectionName' => __( 'Collection name', 'media-bridge-for-etch' ),
				'createCollection' => __( 'Create collection', 'media-bridge-for-etch' ),
				'destination' => __( 'Destination', 'media-bridge-for-etch' ),
				'dropFiles' => __( 'Drop files here', 'media-bridge-for-etch' ),
				'fileKeptOriginal' => __( 'Original will be kept', 'media-bridge-for-etch' ),
				'fileOverLimit' => __( 'Over the upload limit', 'media-bridge-for-etch' ),
				'fileReady' => __( 'Ready', 'media-bridge-for-etch' ),
				'fileWillOptimize' => __( 'Will optimize', 'media-bridge-for-etch' ),
				'filesOverLimit' => __( '%d over limit', 'media-bridge-for-etch' ),
				'filesReady' => __( '%1$d files · %2$s ready', 'media-bridge-for-etch' ),
				'keepOriginals' => __( 'Keep originals', 'media-bridge-for-etch' ),
				'keepOriginalsHelp' => __( 'Upload the original file without Cimo pre-upload optimization. This can preserve embedded EXIF data.', 'media-bridge-for-etch' ),
				'newCollection' => __( 'New collection', 'media-bridge-for-etch' ),
				'parentCollection' => __( 'Parent collection', 'media-bridge-for-etch' ),
				'perFile' => __( 'per file', 'media-bridge-for-etch' ),
				'removeFile' => __( 'Remove %s', 'media-bridge-for-etch' ),
				'renameUploadHelp' => __( 'You can rename optimized files too. The extension matches the file format.', 'media-bridge-for-etch' ),
				'retryFiles' => __( 'Retry %d', 'media-bridge-for-etch' ),
				'topLevel' => __( 'Top level', 'media-bridge-for-etch' ),
				'uncategorized'  => __( 'Uncategorized', 'media-bridge-for-etch' ),
				'uploadComplete' => __( '%d uploaded', 'media-bridge-for-etch' ),
				'uploadFailed' => __( 'WordPress could not upload this file.', 'media-bridge-for-etch' ),
				'uploadFileName' => __( 'File name', 'media-bridge-for-etch' ),
				'uploadFiles' => __( 'Upload %d', 'media-bridge-for-etch' ),
				'uploadHeading' => __( 'Upload', 'media-bridge-for-etch' ),
				'uploadInstructions' => defined( 'CIMO_FILE' ) ? __( 'Choose a collection, then add files. Optimization stays on unless you keep originals.', 'media-bridge-for-etch' ) : __( 'Choose a collection, then add files.', 'media-bridge-for-etch' ),
				'uploadResult' => __( '%1$d uploaded · %2$d failed', 'media-bridge-for-etch' ),
				'uploading' => __( 'Uploading…', 'media-bridge-for-etch' ),
				'uploadingFiles' => __( '%1$d of %2$d finished', 'media-bridge-for-etch' ),
				'validFileName' => __( 'Enter a file name without slashes.', 'media-bridge-for-etch' ),
				'replacementHeading' => __( 'Choose a replacement', 'media-bridge-for-etch' ),
				'replacementInstructions' => __( 'Choose one file. Review its final format and filename after optimization, then replace the attachment.', 'media-bridge-for-etch' ),
				'chooseOneFile' => __( 'Choose only one replacement file.', 'media-bridge-for-etch' ),
				'chooseFile' => __( 'Choose file', 'media-bridge-for-etch' ),
				'dropFile' => __( 'Drop one file here', 'media-bridge-for-etch' ),
				'fileSelected' => __( '1 file · %s ready', 'media-bridge-for-etch' ),
				'replaceFile' => __( 'Replace file', 'media-bridge-for-etch' ),
			),
		) );
	}
}
