<?php
/** Run only in a disposable WordPress installation. */
use UplinkPress\MediaBridgeForEtch\File_Replacement;
use UplinkPress\MediaBridgeForEtch\Plugin;
if ( ! defined( 'UPLINK_MBE_VERSION' ) ) require_once dirname( __DIR__ ) . '/media-bridge-for-etch.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
wp_set_current_user( 1 );
register_taxonomy( 'etch_collection', 'attachment', array( 'hierarchical' => true ) );
function replacement_check( $test, $label ) { if ( ! $test ) throw new RuntimeException( $label ); echo "PASS: $label\n"; }
$dir = wp_upload_dir()['path'] . '/mbe-replacement-' . wp_generate_uuid4();
wp_mkdir_p( $dir );
$GLOBALS['mbe_replacement_dir'] = $dir;
function replacement_fixture( $name, $width, $height, $color ) {
	$dir = $GLOBALS['mbe_replacement_dir'];
	$im = imagecreatetruecolor( $width, $height ); imagefill( $im, 0, 0, $color );
	$path = $dir . '/' . $name; imagepng( $im, $path ); imagedestroy( $im );
	return array( 'file' => $path, 'type' => 'image/png', 'url' => '' );
}
$id = 0; $term = 0;
try {
	replacement_check( 0 === Plugin::defaults()['file_replacement'], 'Replacement defaults to off' );
	$old = replacement_fixture( 'old.png', 900, 600, 0x00ff00 );
	$id = wp_insert_attachment( array( 'post_title' => 'Keep title', 'post_mime_type' => 'image/png', 'post_excerpt' => 'Keep caption', 'post_content' => 'Keep description' ), $old['file'] );
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $old['file'] ) );
	update_post_meta( $id, '_wp_attachment_image_alt', 'Keep alt' );
	$term = wp_insert_term( 'Replacement test', 'etch_collection' )['term_id'];
	wp_set_object_terms( $id, array( $term ), 'etch_collection' );
	update_term_meta( $term, '_uplink_mbe_media_order', array( $id ) );
	$old_url = wp_get_attachment_url( $id ); $old_hash = md5_file( $old['file'] );
	$old_meta = wp_get_attachment_metadata( $id );
	$thumb = dirname( $old['file'] ) . '/' . $old_meta['sizes']['thumbnail']['file']; $thumb_hash = md5_file( $thumb );
	$new = replacement_fixture( 'new.png', 1200, 700, 0xff0000 );
	$result = File_Replacement::replace( $id, $new, 'keep' );
	replacement_check( true === $result, 'Same-URL replacement succeeds: ' . ( is_wp_error( $result ) ? $result->get_error_message() : '' ) );
	replacement_check( $old_url === wp_get_attachment_url( $id ) && $old_hash !== md5_file( $old['file'] ), 'URL preserved and bytes replaced' );
	replacement_check( $thumb_hash !== md5_file( $thumb ), 'Existing thumbnail URL refreshed' );
	replacement_check( 1200 === wp_get_attachment_metadata( $id )['width'], 'Dimensions regenerated' );
	replacement_check( 'Keep title' === get_the_title( $id ) && 'Keep alt' === get_post_meta( $id, '_wp_attachment_image_alt', true ), 'Title and alt preserved' );
	replacement_check( array( $term ) === wp_get_object_terms( $id, 'etch_collection', array( 'fields' => 'ids' ) ) && array( $id ) === get_term_meta( $term, '_uplink_mbe_media_order', true ), 'Collections and custom order preserved' );
	$before = md5_file( $old['file'] );
	$im = imagecreatetruecolor( 100, 100 ); imagejpeg( $im, $dir . '/bad.jpg' ); imagedestroy( $im );
	$bad = array( 'file' => $dir . '/bad.jpg', 'type' => 'image/jpeg' );
	replacement_check( is_wp_error( File_Replacement::replace( $id, $bad, 'keep' ) ) && md5_file( $old['file'] ) === $before, 'Wrong type rejected without modifying original' );
	// A server-side optimizer may change the format while metadata is generated.
	$webp = $dir . '/optimized-original.webp';
	$im = imagecreatetruecolor( 800, 600 ); imagewebp( $im, $webp ); imagedestroy( $im );
	replacement_check( true === File_Replacement::replace( $id, array( 'file' => $webp, 'type' => 'image/webp' ), 'new' ), 'WebP replacement establishes the new format' );
	$webp_url = wp_get_attachment_url( $id );
	$convert = static function ( $metadata, $attachment_id ) {
		$file = get_attached_file( $attachment_id );
		if ( 'server-convert.png' !== basename( $file ) ) return $metadata;
		$editor = wp_get_image_editor( $file );
		$output = $editor->save( dirname( $file ) . '/server-converted.webp', 'image/webp' );
		update_attached_file( $attachment_id, $output['path'] );
		return array( 'file' => _wp_relative_upload_path( $output['path'] ), 'width' => $output['width'], 'height' => $output['height'], 'sizes' => array() );
	};
	add_filter( 'wp_generate_attachment_metadata', $convert, 99, 2 );
	$result = File_Replacement::replace( $id, replacement_fixture( 'server-convert.png', 500, 400, 0xff00ff ), 'keep' );
	remove_filter( 'wp_generate_attachment_metadata', $convert, 99 );
	replacement_check( true === $result && $webp_url === wp_get_attachment_url( $id ) && 'image/webp' === get_post_mime_type( $id ), 'Keep-URL validation uses the final optimized format: ' . ( is_wp_error( $result ) ? $result->get_error_message() : '' ) );
	$metadata = wp_get_attachment_metadata( $id ); $metadata['previous_optimizer'] = array( 'stale' => true );
	update_post_meta( $id, '_wp_attachment_metadata', $metadata );
	$preserve = static function ( $metadata, $attachment_id ) {
		$existing = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );
		if ( isset( $existing['previous_optimizer'] ) ) $metadata['previous_optimizer'] = $existing['previous_optimizer'];
		return $metadata;
	};
	add_filter( 'wp_update_attachment_metadata', $preserve, 10, 2 );
	$result = File_Replacement::replace( $id, replacement_fixture( 'fresh.png', 600, 500, 0 ), 'new' );
	remove_filter( 'wp_update_attachment_metadata', $preserve, 10 );
	replacement_check( true === $result && ! isset( wp_get_attachment_metadata( $id )['previous_optimizer'] ), 'Replacement does not inherit stale optimization metadata' );
	$pdf = $dir . '/new.txt'; file_put_contents( $pdf, 'Replacement text document' );
	$result = File_Replacement::replace( $id, array( 'file' => $pdf, 'type' => 'text/plain' ), 'new' );
	replacement_check( true === $result && 'text/plain' === get_post_mime_type( $id ), 'New-name mode permits a different type' );
	replacement_check( $old_url !== wp_get_attachment_url( $id ) && is_file( $old['file'] ), 'New URL used and old file retained' );
	replacement_check( 'Keep caption' === get_post( $id )->post_excerpt && 'Keep description' === get_post( $id )->post_content, 'Caption and description preserved' );
	$fields = ( new File_Replacement() )->attachment_field( array(), get_post( $id ) );
	replacement_check( empty( $fields ), 'Replacement control hidden while disabled' );
} finally {
	if ( $id ) wp_delete_attachment( $id, true );
	if ( $term ) wp_delete_term( $term, 'etch_collection' );
	foreach ( glob( $dir . '/*' ) as $file ) unlink( $file );
	rmdir( $dir );
}
