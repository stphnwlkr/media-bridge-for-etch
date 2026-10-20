<?php
/** Run only in a disposable WordPress installation. */
use UplinkPress\MediaBridgeForEtch\Plugin;
use UplinkPress\MediaBridgeForEtch\Admin;
use UplinkPress\MediaBridgeForEtch\Image_Conversion;
wp_set_current_user( 1 );
require_once ABSPATH . 'wp-admin/includes/image.php';
$original = get_option( 'uplink_mbe_settings' );
$id = 0; $token = '';
function compression_check( bool $ok, string $message ): void {
	if ( ! $ok ) throw new RuntimeException( $message );
	echo "PASS: $message\n";
}
function compression_reject( callable $work, string $message ): void {
	try { $work(); } catch ( RuntimeException $error ) { compression_check( true, $message ); return; }
	throw new RuntimeException( 'Unexpected success: ' . $message );
}
try {
	$d = Plugin::defaults();
	compression_check( 1 === $d['image_conversion'] && 1 === $d['compression_webp'] && 1 === $d['compression_avif'] && 0 === $d['compression_jxl'] && 0 === $d['compression_jpg'], 'Compression, WebP and AVIF default on; JPEG XL and JPG default off' );
	$settings = Plugin::settings();
	foreach ( array( 'compression_webp', 'compression_avif', 'compression_jxl', 'compression_jpg' ) as $key ) unset( $settings[ $key ] );
	update_option( 'uplink_mbe_settings', $settings );
	compression_check( Image_Conversion::enabled_formats() === array( 'webp', 'avif' ), 'Existing installations inherit the requested format defaults' );
	$admin = new Admin( Plugin::instance() );
	$saved = $admin->sanitize_settings( array( 'compression_webp' => 0, 'compression_avif' => 0, 'compression_jxl' => 1, 'compression_jpg' => 1 ) );
	Plugin::update_settings( $saved );
	compression_check( Image_Conversion::enabled_formats() === array( 'jxl', 'jpg' ), 'Explicit format selections persist through settings sanitization' );
	$partial = $admin->sanitize_settings( array( 'exif_gps' => 0 ) );
	compression_check( $partial['compression_jxl'] === 1 && $partial['compression_jpg'] === 1 && $partial['compression_webp'] === 0, 'Saving another settings tab preserves format selections' );
	$settings = array_merge( Plugin::settings(), array( 'image_conversion' => 1, 'compression_webp' => 1, 'compression_avif' => 1, 'compression_jxl' => 0, 'compression_jpg' => 0 ) );
	Plugin::update_settings( $settings );
	$image = imagecreatetruecolor( 64, 48 ); ob_start(); imagepng( $image ); $bytes = ob_get_clean(); imagedestroy( $image );
	$upload = wp_upload_bits( 'compression-settings-' . wp_generate_uuid4() . '.png', null, $bytes );
	$id = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => 'Compression settings fixture' ), $upload['file'] );
	$metadata = array( 'width' => 64, 'height' => 48, 'file' => _wp_relative_upload_path( $upload['file'] ), 'sizes' => array() );
	wp_update_attachment_metadata( $id, $metadata );
	$plan = Image_Conversion::begin( $id ); $token = $plan['token'];
	compression_reject( fn() => Image_Conversion::stage( $token, 'full', 'jxl', array() ), 'Disabled JPEG XL is rejected by the upload endpoint' );
	$editor = wp_get_image_editor( $upload['file'] ); $converted = $editor->save( get_temp_dir() . wp_generate_uuid4() . '.webp', 'image/webp' );
	if ( is_wp_error( $converted ) ) throw new RuntimeException( $converted->get_error_message() );
	$job = get_option( 'uplink_mbe_conversion_' . $token ); $job['format'] = 'webp'; $job['quality'] = 80; $job['staged'] = array( 'full' => $converted['path'] ); update_option( 'uplink_mbe_conversion_' . $token, $job, false );
	Plugin::update_settings( array_merge( $settings, array( 'compression_webp' => 0 ) ) );
	compression_reject( fn() => Image_Conversion::commit( $token ), 'Disabling a format blocks a pending conversion at commit' );
	compression_check( ! Image_Conversion::active( $id ) && get_attached_file( $id ) === $upload['file'], 'Blocked conversion leaves the original attachment unchanged' );
	Plugin::update_settings( $settings ); Image_Conversion::commit( $token );
	Plugin::update_settings( array_merge( $settings, array( 'image_conversion' => 0, 'compression_webp' => 0, 'compression_avif' => 0 ) ) );
	Image_Conversion::restore( $id );
	compression_check( get_attached_file( $id ) === $upload['file'] && ! Image_Conversion::active( $id ), 'Restore works after compression and its format are disabled' );
	Plugin::update_settings( array_merge( $settings, array( 'image_conversion' => 0 ) ) );
	compression_reject( fn() => Image_Conversion::begin( $id ), 'Master switch disables new compression sessions' );
	Plugin::update_settings( array_merge( $settings, array( 'compression_webp' => 0, 'compression_avif' => 0 ) ) );
	compression_reject( fn() => Image_Conversion::begin( $id ), 'An empty format selection cannot start a conversion' );
	echo "Compression settings integration tests passed.\n";
} finally {
	if ( $token ) Image_Conversion::expire( $token );
	if ( $id ) wp_delete_attachment( $id, true );
	if ( false === $original ) delete_option( 'uplink_mbe_settings' ); else update_option( 'uplink_mbe_settings', $original );
}
