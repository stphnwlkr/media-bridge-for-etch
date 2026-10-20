<?php
/** Run only in a disposable WordPress installation. */
use UplinkPress\MediaBridgeForEtch\Plugin;
use UplinkPress\MediaBridgeForEtch\Admin;
use UplinkPress\MediaBridgeForEtch\Image_Conversion;
$original = get_option( 'uplink_mbe_settings' );
$users = array(); $id = 0; $token = '';
function permission_check( bool $ok, string $message ): void {
	if ( ! $ok ) throw new RuntimeException( $message );
	echo "PASS: $message\n";
}
function permission_reject( callable $work, string $message ): void {
	try { $work(); } catch ( RuntimeException $error ) { permission_check( true, $message ); return; }
	throw new RuntimeException( 'Unexpected success: ' . $message );
}
try {
	wp_set_current_user( 1 );
	$settings = Plugin::settings(); unset( $settings['compression_roles'], $settings['restoration_roles'] );
	update_option( 'uplink_mbe_settings', $settings );
	permission_check( Plugin::settings()['compression_roles'] === array( 'administrator' ) && Plugin::settings()['restoration_roles'] === array( 'administrator' ), 'Existing installs inherit administrator-only defaults for both actions' );
	$admin = new Admin( Plugin::instance() );
	$settings = $admin->sanitize_settings( array( 'compression_roles' => array( 'author', 'unknown', '', array() ), 'restoration_roles' => array( 'editor' ) ) );
	permission_check( $settings['compression_roles'] === array( 'author' ) && $settings['restoration_roles'] === array( 'editor' ), 'Independent role lists accept only existing role names' );
	Plugin::update_settings( $settings );
	$partial = $admin->sanitize_settings( array( 'exif_gps' => 0 ) );
	permission_check( $partial['compression_roles'] === array( 'author' ) && $partial['restoration_roles'] === array( 'editor' ), 'Other settings tabs preserve both role grants' );
	$empty = $admin->sanitize_settings( array( 'compression_roles' => array( '' ), 'restoration_roles' => array( '' ) ) );
	permission_check( ! $empty['compression_roles'] && ! $empty['restoration_roles'], 'Unchecking every role saves an empty selection' );
	foreach ( array( 'author', 'editor', 'subscriber' ) as $role ) {
		$user_id = wp_insert_user( array( 'user_login' => 'permissions-' . wp_generate_uuid4(), 'user_pass' => wp_generate_password(), 'role' => $role ) );
		if ( is_wp_error( $user_id ) ) throw new RuntimeException( $user_id->get_error_message() );
		$users[ $role ] = $user_id;
	}
	$image = imagecreatetruecolor( 32, 24 ); ob_start(); imagepng( $image ); $bytes = ob_get_clean(); imagedestroy( $image );
	$upload = wp_upload_bits( 'permissions-' . wp_generate_uuid4() . '.png', null, $bytes );
	$id = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => 'Permission fixture', 'post_author' => $users['author'] ), $upload['file'] );
	wp_update_attachment_metadata( $id, array( 'width' => 32, 'height' => 24, 'file' => _wp_relative_upload_path( $upload['file'] ), 'sizes' => array() ) );
	wp_set_current_user( $users['author'] );
	permission_check( Image_Conversion::allowed( $id ) && ! Image_Conversion::allowed( $id, 'restore' ) && ! current_user_can( 'manage_options' ), 'Author can compress an owned image without admin access but cannot restore' );
	$plan = Image_Conversion::begin( $id ); $token = $plan['token'];
	permission_reject( fn() => Image_Conversion::restore( $id ), 'Compression grant does not authorize the restoration endpoint' );
	Plugin::update_settings( array_merge( $settings, array( 'compression_roles' => array() ) ) );
	permission_reject( fn() => Image_Conversion::commit( $token ), 'Revoking compression permission blocks an existing session' );
	Plugin::update_settings( $settings );
	wp_update_post( array( 'ID' => $id, 'post_author' => 1 ) );
	permission_check( ! Image_Conversion::allowed( $id ), 'Role grant cannot bypass editing another user image' );
	wp_update_post( array( 'ID' => $id, 'post_author' => $users['author'] ) );
	$deny_upload = static function ( $caps ) { $caps['upload_files'] = false; return $caps; };
	add_filter( 'user_has_cap', $deny_upload );
	permission_check( ! Image_Conversion::allowed( $id ), 'Role grant cannot bypass upload permission' );
	remove_filter( 'user_has_cap', $deny_upload );
	// Complete a real conversion, then restore it with a separately authorized user.
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$editor = wp_get_image_editor( $upload['file'] );
	$converted = $editor->save( get_temp_dir() . wp_generate_uuid4() . '.webp', 'image/webp' );
	if ( is_wp_error( $converted ) ) throw new RuntimeException( $converted->get_error_message() );
	$job = get_option( 'uplink_mbe_conversion_' . $token ); $job['format'] = 'webp'; $job['quality'] = 80; $job['staged'] = array( 'full' => $converted['path'] ); update_option( 'uplink_mbe_conversion_' . $token, $job, false );
	Image_Conversion::commit( $token );
	permission_check( Image_Conversion::active( $id ), 'Non-admin compression grant can save a conversion' );
	wp_set_current_user( $users['editor'] );
	permission_check( ! Image_Conversion::allowed( $id ) && Image_Conversion::allowed( $id, 'restore' ), 'Restoration-only editor has independent access' );
	permission_reject( fn() => Image_Conversion::begin( $id ), 'Restoration grant does not authorize compression endpoint' );
	Plugin::update_settings( array_merge( $settings, array( 'image_conversion' => 0 ) ) );
	Image_Conversion::restore( $id );
	permission_check( ! Image_Conversion::active( $id ) && get_attached_file( $id ) === $upload['file'], 'Restoration-only editor restores originals while compression is disabled' );
	Plugin::update_settings( array_merge( $settings, array( 'compression_roles' => array( 'subscriber' ), 'restoration_roles' => array( 'subscriber' ) ) ) );
	wp_set_current_user( $users['subscriber'] );
	permission_check( ! Image_Conversion::allowed( $id ) && ! Image_Conversion::allowed( $id, 'restore' ), 'Selecting a role does not give it missing WordPress media permissions' );
	echo "Compression permissions integration tests passed.\n";
} finally {
	wp_set_current_user( 1 );
	if ( $token ) Image_Conversion::expire( $token );
	if ( $id ) wp_delete_attachment( $id, true );
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $users as $user_id ) wp_delete_user( $user_id );
	if ( false === $original ) delete_option( 'uplink_mbe_settings' ); else update_option( 'uplink_mbe_settings', $original );
}
