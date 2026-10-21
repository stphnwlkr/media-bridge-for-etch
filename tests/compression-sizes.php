<?php
/** Run on a disposable WordPress installation. */
use UplinkPress\MediaBridgeForEtch\Plugin;
use UplinkPress\MediaBridgeForEtch\Image_Conversion;
wp_set_current_user( 1 );
require_once ABSPATH . 'wp-admin/includes/image.php';
$original = get_option( 'uplink_mbe_settings' ); $id = 0; $tokens = array();
function sizes_check( bool $ok, string $label ): void { if ( ! $ok ) throw new RuntimeException( $label ); echo "PASS: $label\n"; }
function sizes_reject( callable $fn, string $label ): void { try { $fn(); } catch ( RuntimeException $e ) { sizes_check( true, $label ); return; } throw new RuntimeException( 'Unexpected success: ' . $label ); }
try {
	Plugin::update_settings( array_merge( Plugin::settings(), array( 'image_conversion' => 1, 'compression_excluded_sizes' => array(), 'compression_webp' => 1, 'compression_roles' => array( 'administrator' ), 'restoration_roles' => array( 'administrator' ) ) ) );
	add_image_size( 'mbe_custom_card', 333, 222, true ); add_image_size( 'mbe_custom_hero', 640, 320, true );
	$image = imagecreatetruecolor( 1920, 1280 ); $bg = imagecolorallocate( $image, 80, 140, 190 ); imagefill( $image, 0, 0, $bg ); ob_start(); imagejpeg( $image, null, 95 ); $bytes = ob_get_clean(); imagedestroy( $image );
	$up = wp_upload_bits( 'size-test-' . wp_generate_uuid4() . '.jpg', null, $bytes );
	$id = wp_insert_attachment( array( 'post_title' => 'Size selection fixture', 'post_mime_type' => 'image/jpeg', 'post_author' => 1 ), $up['file'] );
	$meta = wp_generate_attachment_metadata( $id, $up['file'] );
	$meta['sizes']['mbe_custom_alias'] = $meta['sizes']['mbe_custom_card'];
	$raw = dirname( $up['file'] ) . '/raw-' . wp_basename( $up['file'] ); copy( $up['file'], $raw ); $meta['original_image'] = wp_basename( $raw );
	wp_update_attachment_metadata( $id, $meta );
	remove_image_size( 'mbe_custom_hero' );
	$hashes = array(); foreach ( array_merge( array( $up['file'], $raw ), array_map( static fn( $size ) => dirname( $up['file'] ) . '/' . $size['file'], $meta['sizes'] ) ) as $path ) $hashes[ $path ] = hash_file( 'sha256', $path );
	foreach ( array( 'all', 'main', 'selected' ) as $scope ) {
		$plan = Image_Conversion::begin( $id ); $token = $plan['token']; $tokens[] = $token;
		sizes_check( isset( $plan['files']['mbe_custom_card'], $plan['files']['mbe_custom_hero'], $plan['files']['original_image'] ), "$scope: manifest includes custom, historical custom and original-upload files" );
		sizes_check( $plan['aliases']['mbe_custom_alias'] === 'mbe_custom_card' && ! isset( $plan['files']['mbe_custom_alias'] ), "$scope: shared custom-size file is deduplicated" );
		sizes_reject( fn() => Image_Conversion::select_sizes( $token, 'selected', array() ), 'Empty selection rejected' );
		sizes_reject( fn() => Image_Conversion::select_sizes( $token, 'selected', array( '../outside' ) ), 'Unrecognized size rejected' );
		$selection = Image_Conversion::select_sizes( $token, $scope, array( 'mbe_custom_alias', 'mbe_custom_hero' ) );
		$job = get_option( 'uplink_mbe_conversion_' . $token );
		$expected = 'all' === $scope ? count( $plan['files'] ) : ( 'main' === $scope ? 1 : 2 );
		sizes_check( count( $job['files'] ) === $expected, "$scope: exact selected physical-file count" );
		foreach ( $job['files'] as $key => $file ) {
			$editor = wp_get_image_editor( $file['path'] ); $output = $editor->save( get_temp_dir() . wp_generate_uuid4() . '.webp', 'image/webp' );
			if ( is_wp_error( $output ) ) throw new RuntimeException( $output->get_error_message() ); $job['staged'][ $key ] = $output['path'];
		}
		$job['format'] = 'webp'; $job['quality'] = 80; update_option( 'uplink_mbe_conversion_' . $token, $job, false );
		sizes_reject( fn() => Image_Conversion::select_sizes( $token, 'selected', array( 'thumbnail' ) ), 'Changing staged file set rejected' );
		$result = Image_Conversion::commit( $token ); $new = wp_get_attachment_metadata( $id );
		sizes_check( $result['sizes'] === $expected && $result['summary']['scope'] === $scope, "$scope: saved count and scope match selection" );
		foreach ( $meta['sizes'] as $key => $size ) {
			$canonical = $plan['aliases'][ $key ] ?? $key; $selected = in_array( $canonical, $selection['sizes'], true );
			sizes_check( $selected ? 'image/webp' === $new['sizes'][ $key ]['mime-type'] && str_ends_with( $new['sizes'][ $key ]['file'], '.webp' ) : $new['sizes'][ $key ] === $size, "$scope: $key is " . ( $selected ? 'converted' : 'unchanged' ) );
		}
		sizes_check( in_array( 'full', $selection['sizes'], true ) ? 'image/webp' === get_post_mime_type( $id ) : get_attached_file( $id ) === $up['file'] && 'image/jpeg' === get_post_mime_type( $id ), "$scope: main file identity follows selection" );
		if ( 'selected' === $scope ) sizes_check( str_contains( (string) wp_get_attachment_image_srcset( $id, 'full' ), $new['sizes']['mbe_custom_card']['file'] ), 'Responsive srcset includes the selected custom-size conversion with original main image' );
		$before = array_sum( array_column( $job['files'], 'bytes' ) ); sizes_check( $result['summary']['before'] === $before, "$scope: savings count selected sources only" );
		Image_Conversion::restore( $id ); sizes_check( wp_get_attachment_metadata( $id ) === $meta && get_attached_file( $id ) === $up['file'], "$scope: restoration exactly recovers metadata and main image" );
		foreach ( $hashes as $file => $hash ) sizes_check( hash_file( 'sha256', $file ) === $hash, "$scope: source bytes preserved for " . wp_basename( $file ) );
	}
	// Exclusions apply to physical files, including excluded aliases and historical custom sizes.
	$settings = Plugin::settings();
	Plugin::update_settings( array_merge( $settings, array( 'compression_excluded_sizes' => array( 'thumbnail', 'mbe_custom_alias', 'mbe_custom_hero' ) ) ) );
	$plan = Image_Conversion::begin( $id ); $tokens[] = $plan['token'];
	sizes_check( ! empty( $plan['files']['thumbnail']['excluded'] ) && ! empty( $plan['files']['mbe_custom_card']['excluded'] ) && ! empty( $plan['files']['mbe_custom_hero']['excluded'] ), 'Manifest marks thumbnail, alias-shared and historical custom exclusions' );
	$selection = Image_Conversion::select_sizes( $plan['token'], 'all', array( 'thumbnail' ) );
	sizes_check( ! array_intersect( array( 'thumbnail', 'mbe_custom_card', 'mbe_custom_hero' ), $selection['sizes'] ) && in_array( 'full', $selection['sizes'], true ), 'All sizes honors exclusions and ignores client attempts to include an excluded size' );
	foreach ( array( 'all', 'selected' ) as $scope ) {
		$plan = Image_Conversion::begin( $id ); $token = $plan['token']; $tokens[] = $token;
		$selection = Image_Conversion::select_sizes( $token, $scope, array( 'thumbnail', 'mbe_custom_alias' ) );
		$job = get_option( 'uplink_mbe_conversion_' . $token );
		foreach ( $job['files'] as $key => $file ) {
			$editor = wp_get_image_editor( $file['path'] ); $output = $editor->save( get_temp_dir() . wp_generate_uuid4() . '.webp', 'image/webp' );
			if ( is_wp_error( $output ) ) throw new RuntimeException( $output->get_error_message() ); $job['staged'][ $key ] = $output['path'];
		}
		$job['format'] = 'webp'; $job['quality'] = 80; update_option( 'uplink_mbe_conversion_' . $token, $job, false );
		$result = Image_Conversion::commit( $token ); $new = wp_get_attachment_metadata( $id );
		sizes_check( 'all' === $scope ? $new['sizes']['thumbnail'] === $meta['sizes']['thumbnail'] && $new['sizes']['mbe_custom_card'] === $meta['sizes']['mbe_custom_card'] : $new['sizes']['thumbnail']['mime-type'] === 'image/webp' && $new['sizes']['mbe_custom_alias']['mime-type'] === 'image/webp', "$scope: exclusions and per-image overrides persist in attachment metadata" );
		sizes_check( $result['sizes'] === count( $selection['sizes'] ), "$scope: savings count only the files included after exclusions or overrides" );
		Image_Conversion::restore( $id );
		sizes_check( wp_get_attachment_metadata( $id ) === $meta, "$scope: restoration recovers excluded and overridden size metadata" );
	}
	$admin = new \UplinkPress\MediaBridgeForEtch\Admin( Plugin::instance() );
	$sanitized = $admin->sanitize_settings( array( 'compression_excluded_sizes' => array( 'thumbnail', 'thumbnail', '', array(), 'mbe_custom_hero' ) ) );
	sizes_check( $sanitized['compression_excluded_sizes'] === array( 'thumbnail', 'mbe_custom_hero' ), 'Exclusion settings remove duplicates and malformed entries' );
	sizes_check( $admin->sanitize_settings( array( 'exif_gps' => 0 ) )['compression_excluded_sizes'] === Plugin::settings()['compression_excluded_sizes'], 'Saving other settings preserves exclusions' );
	sizes_check( $admin->sanitize_settings( array( 'compression_excluded_sizes' => array( '' ) ) )['compression_excluded_sizes'] === array(), 'Administrators can clear every exclusion' );
	Plugin::update_settings( $settings );
	// Synthetic JPEG SOF dimensions exercise a large original without allocating a 45MP bitmap.
	$jpeg = file_get_contents( $raw ); $sof = strpos( $jpeg, "\xff\xc0" );
	if ( false === $sof ) throw new RuntimeException( 'Fixture lacks JPEG SOF marker' );
	file_put_contents( $raw, substr_replace( $jpeg, pack( 'nn', 6000, 7500 ), $sof + 5, 4 ) );
	$plan = Image_Conversion::begin( $id ); $tokens[] = $plan['token'];
	sizes_check( false === $plan['files']['original_image']['available'], 'Oversized original is listed without blocking the scaled/main image' );
	sizes_reject( fn() => Image_Conversion::select_sizes( $plan['token'], 'all' ), 'All sizes rejects an oversized original explicitly' );
	$selection = Image_Conversion::select_sizes( $plan['token'], 'main' );
	sizes_check( $selection['sizes'] === array( 'full' ), 'Main-only selection works with an oversized unselected original' );
	file_put_contents( $raw, $jpeg );
	echo "All image-size scope tests passed.\n";
} finally {
	foreach ( $tokens as $token ) Image_Conversion::expire( $token );
	if ( $id ) wp_delete_attachment( $id, true );
	remove_image_size( 'mbe_custom_card' ); remove_image_size( 'mbe_custom_hero' );
	if ( false === $original ) delete_option( 'uplink_mbe_settings' ); else update_option( 'uplink_mbe_settings', $original );
}
