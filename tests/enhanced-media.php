<?php
/** Run with wp eval-file in a disposable WordPress installation. */
use UplinkPress\MediaBridgeForEtch\Plugin;
use UplinkPress\MediaBridgeForEtch\Image_Conversion;
use UplinkPress\MediaBridgeForEtch\Etch_Dynamic_Data;
use UplinkPress\MediaBridgeForEtch\Providers\Etch_Provider;
use UplinkPress\MediaBridgeForEtch\Native_Media_Library;

wp_set_current_user( 1 );
require_once ABSPATH . 'wp-admin/includes/image.php';
$settings = Plugin::settings();
$ids = array();
$token = '';
function enhanced_check( bool $ok, string $label ): void {
	if ( ! $ok ) throw new RuntimeException( $label );
	echo "PASS: $label\n";
}
try {
	enhanced_check( ! Plugin::defaults()['exif_map'] && ! Plugin::defaults()['exif_gps'], 'GPS and map are separate opt-ins' );
	$image = imagecreatetruecolor( 800, 600 );
	imagefill( $image, 0, 0, 0x145fa0 );
	ob_start(); imagejpeg( $image ); $bytes = ob_get_clean(); imagedestroy( $image );
	$upload = wp_upload_bits( 'enhanced-' . wp_generate_uuid4() . '.jpg', null, $bytes );
	$id = wp_insert_attachment( array( 'post_title' => 'Preserved title', 'post_excerpt' => 'Preserved caption', 'post_mime_type' => 'image/jpeg' ), $upload['file'] );
	$ids[] = $id;
	$metadata = wp_generate_attachment_metadata( $id, $upload['file'] );
	$metadata['image_meta']['camera'] = 'Preserved camera';
	wp_update_attachment_metadata( $id, $metadata );
	update_post_meta( $id, '_wp_attachment_image_alt', 'Preserved alt' );
	$url = wp_get_attachment_url( $id );
	$content = '<!-- wp:etch/element ' . wp_json_encode( array( 'attributes' => array( 'src' => $url ) ) ) . ' --><img src="' . $url . '"><!-- /wp:etch/element -->';
	$post_id = wp_insert_post( array( 'post_title' => 'Etch reference fixture', 'post_content' => wp_slash( $content ) ) );
	$ids[] = $post_id;
	$plan = Image_Conversion::begin( $id ); $token = $plan['token'];
	enhanced_check( count( $plan['files'] ) > 1, 'Conversion plans include generated image sizes' );
	$job = get_option( 'uplink_mbe_conversion_' . $token );
	$hashes = array();
	foreach ( $job['files'] as $key => $file ) {
		$hashes[ $file['path'] ] = hash_file( 'sha256', $file['path'] );
		$editor = wp_get_image_editor( $file['path'] );
		$output = $editor->save( get_temp_dir() . wp_generate_uuid4() . '.webp', 'image/webp' );
		if ( is_wp_error( $output ) ) throw new RuntimeException( $output->get_error_message() );
		$job['staged'][ $key ] = $output['path'];
	}
	$job['format'] = 'webp'; $job['quality'] = 80;
	update_option( 'uplink_mbe_conversion_' . $token, $job, false );
	Plugin::update_settings( array_merge( $settings, array( 'image_conversion' => 0 ) ) );
	try { Image_Conversion::commit( $token ); throw new LogicException( 'Disabled commit unexpectedly succeeded' ); }
	catch ( RuntimeException $error ) { enhanced_check( ! Image_Conversion::active( $id ) && wp_get_attachment_url( $id ) === $url, 'Compression setting blocks pending conversions without changing the attachment' ); }
	Plugin::update_settings( $settings );
	$result = Image_Conversion::commit( $token );
	enhanced_check( $result['sizes'] === count( $plan['files'] ), 'Every unique image size is converted' );
	$new_url = wp_get_attachment_url( $id );
	enhanced_check( str_contains( get_post( $post_id )->post_content, $new_url ) && ! str_contains( get_post( $post_id )->post_content, $url ), 'Etch block attributes and rendered image references both update' );
	$new_meta = wp_get_attachment_metadata( $id );
	foreach ( $new_meta['sizes'] as $key => $size ) {
		$path = dirname( get_attached_file( $id ) ) . '/' . $size['file'];
		$info = wp_getimagesize( $path );
		enhanced_check( $info['mime'] === 'image/webp' && $info[0] === $metadata['sizes'][ $key ]['width'] && $info[1] === $metadata['sizes'][ $key ]['height'], 'Generated ' . $key . ' has the correct format and dimensions' );
	}
	foreach ( $hashes as $path => $hash ) enhanced_check( hash_file( 'sha256', $path ) === $hash, 'Original source bytes remain unchanged' );
	enhanced_check( Etch_Dynamic_Data::attachment_exif( $id )['camera_model'] === 'Preserved camera', 'Etch image metadata survives conversion' );
	enhanced_check( get_post( $id )->post_title === 'Preserved title' && get_post_meta( $id, '_wp_attachment_image_alt', true ) === 'Preserved alt', 'Title and alt text survive conversion' );
	$library = new Native_Media_Library( new Etch_Provider(), false );
	$method = new ReflectionMethod( Native_Media_Library::class, 'attachment_data' );
	enhanced_check( $method->invoke( $library, $id )['optimization']['optimized'], 'Converted images appear as optimized in the Enhanced Manager and filters' );
	Plugin::update_settings( array_merge( $settings, array( 'image_conversion' => 0 ) ) );
	Image_Conversion::restore( $id );
	enhanced_check( wp_get_attachment_url( $id ) === $url && get_post( $post_id )->post_content === $content, 'Restore stays available after compression is disabled and restores Etch references' );
	echo "Enhanced media integration tests passed.\n";
} finally {
	Plugin::update_settings( $settings );
	if ( $token ) Image_Conversion::expire( $token );
	foreach ( array_reverse( $ids ) as $object_id ) {
		if ( 'attachment' === get_post_type( $object_id ) ) wp_delete_attachment( $object_id, true );
		else wp_delete_post( $object_id, true );
	}
}
