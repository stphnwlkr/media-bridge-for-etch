<?php
/** Isolated Editor-account checks. Creates temporary fixtures and restores settings. */
use UplinkPress\MediaBridgeForEtch\Plugin;
use UplinkPress\MediaBridgeForEtch\Admin;
use UplinkPress\MediaBridgeForEtch\Image_Conversion;
use UplinkPress\MediaBridgeForEtch\Native_Media_Library;
use UplinkPress\MediaBridgeForEtch\Providers\Etch_Provider;
class Editor_Test_Response extends RuntimeException {}
$original = get_option( 'uplink_mbe_settings' );
$original_user = get_current_user_id();
$ids = array(); $editor_id = 0;
$GLOBALS['editor_test_tokens'] = array(); $GLOBALS['editor_test_checks'] = array();
$admin_id = (int) get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0];
function editor_check( bool $ok, string $message ): void {
	if ( ! $ok ) throw new RuntimeException( $message );
	$GLOBALS['editor_test_checks'][] = 'PASS: ' . $message;
}
function editor_ajax( string $mode, int $id, array $extra = array() ): array {
	$old_post = $_POST; $old_request = $_REQUEST;
	$_POST = array_merge( array( 'mode' => $mode, 'attachment_id' => $id, 'nonce' => wp_create_nonce( 'uplink_mbe_image_conversion' ) ), $extra ); $_REQUEST = $_POST;
	$doing = static fn() => true;
	$die = static fn() => static function () { throw new Editor_Test_Response(); };
	$code = 200;
	$status = static function ( $header, $status_code ) use ( &$code ) { $code = $status_code; return $header; };
	add_filter( 'wp_doing_ajax', $doing ); add_filter( 'wp_die_ajax_handler', $die ); add_filter( 'status_header', $status, 10, 2 );
	ob_start();
	try { ( new Image_Conversion() )->ajax(); }
	catch ( Editor_Test_Response $response ) { /* Capture the actual AJAX JSON and HTTP status. */ }
	finally {
		$json = ob_get_clean();
		remove_filter( 'wp_doing_ajax', $doing ); remove_filter( 'wp_die_ajax_handler', $die ); remove_filter( 'status_header', $status, 10 );
		$_POST = $old_post; $_REQUEST = $old_request;
	}
	$result = json_decode( $json, true );
	if ( ! is_array( $result ) ) throw new RuntimeException( 'Invalid AJAX response: ' . $json );
	return array( $code, $result );
}
function editor_denied( string $mode, int $id, string $message, array $extra = array() ): void {
	list( $code, $result ) = editor_ajax( $mode, $id, $extra );
	editor_check( 403 === $code && false === $result['success'], $message );
}
function editor_grants( bool $compress, bool $restore ): void {
	$admin = new Admin( Plugin::instance() );
	Plugin::update_settings( $admin->sanitize_settings( array(
		'image_conversion' => 1, 'compression_webp' => 1,
		'compression_roles' => $compress ? array( 'administrator', 'editor' ) : array( 'administrator' ),
		'restoration_roles' => $restore ? array( 'administrator', 'editor' ) : array( 'administrator' ),
	) ) );
}
function editor_convert( int $id ): void {
	list( $code, $result ) = editor_ajax( 'begin', $id );
	editor_check( 200 === $code && $result['success'] && ! empty( $result['data']['token'] ), 'Authorized account starts compression through AJAX' );
	$token = $result['data']['token']; $GLOBALS['editor_test_tokens'][] = $token;
	$job = get_option( 'uplink_mbe_conversion_' . $token );
	foreach ( $job['files'] as $key => $file ) {
		$encoder = wp_get_image_editor( $file['path'] );
		$output = $encoder->save( get_temp_dir() . wp_generate_uuid4() . '.webp', 'image/webp' );
		if ( is_wp_error( $output ) ) throw new RuntimeException( $output->get_error_message() );
		$job['staged'][ $key ] = $output['path'];
	}
	// Supply real encoded files without pretending that CLI files are HTTP uploads.
	$job['format'] = 'webp'; $job['quality'] = 80;
	update_option( 'uplink_mbe_conversion_' . $token, $job, false );
	list( $code, $result ) = editor_ajax( 'commit', $id, array( 'token' => $token ) );
	editor_check( 200 === $code && $result['success'] && Image_Conversion::active( $id ), 'Authorized account commits real WebP files through AJAX' );
}
try {
	wp_set_current_user( $admin_id );
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$editor_id = wp_insert_user( array( 'user_login' => 'mbe-editor-test-' . wp_generate_uuid4(), 'user_pass' => wp_generate_password( 40 ), 'role' => 'editor' ) );
	if ( is_wp_error( $editor_id ) ) throw new RuntimeException( $editor_id->get_error_message() );
	foreach ( array( $editor_id, $admin_id ) as $owner ) {
		$image = imagecreatetruecolor( 64, 48 ); ob_start(); imagepng( $image ); $bytes = ob_get_clean(); imagedestroy( $image );
		$upload = wp_upload_bits( 'mbe-editor-test-' . wp_generate_uuid4() . '.png', null, $bytes );
		if ( $upload['error'] ) throw new RuntimeException( $upload['error'] );
		$id = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => 'Temporary Editor permission test', 'post_author' => $owner ), $upload['file'] );
		$ids[] = $id;
		wp_update_attachment_metadata( $id, array( 'width' => 64, 'height' => 48, 'file' => _wp_relative_upload_path( $upload['file'] ), 'sizes' => array() ) );
	}
	list( $owned, $other ) = $ids;
	$owned_source = get_attached_file( $owned ); $owned_hash = hash_file( 'sha256', $owned_source );
	$library = new Native_Media_Library( new Etch_Provider(), false );
	$data = new ReflectionMethod( Native_Media_Library::class, 'attachment_data' );
	wp_set_current_user( $editor_id );
	editor_check( wp_get_current_user()->roles === array( 'editor' ) && ! current_user_can( 'manage_options' ), 'Test account is a real Editor without administrator settings access' );
	editor_check( current_user_can( 'edit_post', $owned ) && current_user_can( 'edit_post', $other ), 'Standard Editor can edit owned and other users images' );
	editor_grants( false, false );
	$payload = $data->invoke( $library, $owned );
	editor_check( ! $payload['canConvert'] && ! $payload['canRestore'], 'Both permissions off removes both action grants from manager data' );
	editor_denied( 'begin', $owned, 'Both off: compression AJAX returns 403' );
	editor_denied( 'restore', $owned, 'Both off: restoration AJAX returns 403' );
	editor_grants( true, false );
	$payload = $data->invoke( $library, $owned );
	editor_check( $payload['canConvert'] && ! $payload['canRestore'], 'Compression only exposes compression without restoration' );
	editor_convert( $owned );
	$converted_path = get_attached_file( $owned );
	editor_denied( 'restore', $owned, 'Compression-only Editor cannot restore an actual converted image' );
	editor_check( Image_Conversion::active( $owned ) && get_attached_file( $owned ) === $converted_path, 'Denied restoration leaves converted files and backup intact' );
	editor_grants( false, true );
	$payload = $data->invoke( $library, $owned );
	editor_check( ! $payload['canConvert'] && $payload['canRestore'], 'Restoration only exposes restoration without compression' );
	editor_denied( 'begin', $owned, 'Restoration-only Editor cannot begin compression' );
	Plugin::update_settings( array_merge( Plugin::settings(), array( 'image_conversion' => 0 ) ) );
	list( $code, $result ) = editor_ajax( 'restore', $owned );
	editor_check( 200 === $code && $result['success'] && ! Image_Conversion::active( $owned ), 'Restoration-only Editor restores successfully even with compression switched off' );
	editor_check( get_attached_file( $owned ) === $owned_source && hash_file( 'sha256', $owned_source ) === $owned_hash, 'Restoration recovers exact original path and bytes' );
	editor_grants( true, true );
	wp_set_current_user( $admin_id ); editor_convert( $other );
	wp_set_current_user( $editor_id );
	// Restrict only the temporary account. The global Editor role is unchanged.
	wp_get_current_user()->add_cap( 'edit_others_posts', false );
	editor_check( current_user_can( 'edit_post', $owned ) && ! current_user_can( 'edit_post', $other ), 'Restricted Editor can edit own image but cannot edit the other users image' );
	$payload = $data->invoke( $library, $other );
	editor_check( ! $payload['canConvert'] && ! $payload['canRestore'], 'Both grants still hide actions on a non-editable image' );
	$other_path = get_attached_file( $other ); $other_meta = wp_get_attachment_metadata( $other );
	editor_denied( 'begin', $other, 'Non-editable image rejects compression despite role grant' );
	editor_denied( 'restore', $other, 'Non-editable converted image rejects restoration despite role grant' );
	editor_check( Image_Conversion::active( $other ) && get_attached_file( $other ) === $other_path && wp_get_attachment_metadata( $other ) === $other_meta, 'Rejected actions preserve non-editable image metadata and backup' );
	editor_convert( $owned );
	wp_get_current_user()->add_cap( 'upload_files', false );
	editor_denied( 'restore', $owned, 'Missing upload permission blocks restoration even when image is editable' );
	editor_denied( 'begin', $owned, 'Missing upload permission blocks compression even when image is editable' );
	wp_get_current_user()->remove_cap( 'upload_files' );
	list( $code, $result ) = editor_ajax( 'restore', $owned );
	editor_check( 200 === $code && $result['success'], 'Both enabled: restricted Editor can restore its editable image' );
	list( $code, $result ) = editor_ajax( 'begin', $owned );
	$token = $result['data']['token']; $GLOBALS['editor_test_tokens'][] = $token;
	editor_grants( false, true );
	editor_denied( 'stage', $owned, 'Revoked compression grant blocks staging a pending session', array( 'token' => $token ) );
	editor_denied( 'commit', $owned, 'Revoked compression grant blocks committing a pending session', array( 'token' => $token ) );
	editor_grants( true, true );
	wp_update_post( array( 'ID' => $owned, 'post_author' => $admin_id ) );
	editor_denied( 'commit', $owned, 'Losing image-edit permission blocks a previously authorized compression session', array( 'token' => $token ) );
	editor_check( ! Image_Conversion::active( $owned ) && get_attached_file( $owned ) === $owned_source && hash_file( 'sha256', $owned_source ) === $owned_hash, 'Rejected pending actions leave original bytes unchanged' );
} finally {
	wp_set_current_user( $admin_id );
	foreach ( $GLOBALS['editor_test_tokens'] as $token ) Image_Conversion::expire( $token );
	foreach ( $ids as $id ) wp_delete_attachment( $id, true );
	if ( $editor_id && ! is_wp_error( $editor_id ) ) { require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $editor_id ); }
	if ( false === $original ) delete_option( 'uplink_mbe_settings' ); else update_option( 'uplink_mbe_settings', $original );
	wp_set_current_user( $original_user );
}
echo implode( "\n", $GLOBALS['editor_test_checks'] ) . "\n";
echo 'Editor account checks passed: ' . count( $GLOBALS['editor_test_checks'] ) . ". Temporary account and fixtures removed; settings restored.\n";
