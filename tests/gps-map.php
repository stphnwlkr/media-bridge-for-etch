<?php
/** GPS-map integration tests with generated coordinates at a public landmark. */
use UplinkPress\MediaBridgeForEtch\Exif_Map;
use UplinkPress\MediaBridgeForEtch\Etch_Dynamic_Data;
use UplinkPress\MediaBridgeForEtch\Native_Media_Library;
use UplinkPress\MediaBridgeForEtch\Providers\Etch_Provider;
use UplinkPress\MediaBridgeForEtch\Plugin;
wp_set_current_user( 1 );
function gps_check( $ok, $label ): void { if ( ! $ok ) throw new RuntimeException( $label ); echo "PASS: $label\n"; }
$saved = get_option( 'uplink_mbe_settings' );
$id = 0;
$file = '';
$keep = getenv( 'MBB_KEEP_MAP_FIXTURE' ) === '1';
try {
	gps_check( Exif_Map::coordinates( 0, 0 ) === array( 'latitude' => 0.0, 'longitude' => 0.0 ), 'Zero coordinates are valid' );
	foreach ( array( array( null, 1 ), array( 1, '' ), array( 91, 0 ), array( 0, -181 ), array( INF, 0 ), array( array(), 0 ) ) as $point ) gps_check( null === Exif_Map::coordinates( ...$point ), 'Reject missing or invalid coordinate pair' );
	$data = ''; $offset = 92;
	$entry = static function ( $tag, $type, $count, $value ) use ( &$data, $offset ) {
		if ( strlen( $value ) > 4 ) { $pointer = pack( 'V', $offset + strlen( $data ) ); $data .= $value; } else $pointer = str_pad( $value, 4, "\0" );
		return pack( 'vvV', $tag, $type, $count ) . $pointer;
	};
	$model = "GPS map fixture\0";
	$ifd = $entry( 0x0110, 2, strlen( $model ), $model ) . $entry( 0x8825, 4, 1, pack( 'V', 38 ) );
	$gps = $entry( 1, 2, 2, "N\0" ) . $entry( 2, 5, 3, pack( 'VVVVVV', 38, 1, 53, 1, 222, 10 ) ) . $entry( 3, 2, 2, "W\0" ) . $entry( 4, 5, 3, pack( 'VVVVVV', 77, 1, 2, 1, 708, 100 ) );
	$tiff = 'II' . pack( 'vV', 42, 8 ) . pack( 'v', 2 ) . $ifd . pack( 'V', 0 ) . pack( 'v', 4 ) . $gps . pack( 'V', 0 ) . $data;
	$payload = "Exif\0\0" . $tiff;
	$im = imagecreatetruecolor( 240, 160 ); imagefill( $im, 0, 0, imagecolorallocate( $im, 215, 230, 240 ) ); imagestring( $im, 5, 65, 70, 'GPS map test', imagecolorallocate( $im, 25, 65, 90 ) );
	ob_start(); imagejpeg( $im ); $jpeg = ob_get_clean(); imagedestroy( $im );
	$dir = wp_upload_dir()['path']; wp_mkdir_p( $dir );
	$file = $dir . '/mbb-gps-test-' . wp_generate_uuid4() . '.jpg';
	file_put_contents( $file, substr( $jpeg, 0, 2 ) . "\xff\xe1" . pack( 'n', strlen( $payload ) + 2 ) . $payload . substr( $jpeg, 2 ) );
	$id = wp_insert_attachment( array( 'post_title' => 'Media Bridge GPS map test', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit' ), $file );
	wp_update_attachment_metadata( $id, array( 'width' => 240, 'height' => 160, 'file' => _wp_relative_upload_path( $file ), 'sizes' => array() ) );
	Plugin::update_settings( array_merge( Plugin::settings(), array( 'exif_gps' => 1, 'exif_map' => 1 ) ) );
	$location = Exif_Map::location( $id );
	gps_check( abs( $location['latitude'] - 38.8895 ) < 0.000001 && abs( $location['longitude'] + 77.0353 ) < 0.000001, 'Read real encoded GPS with west longitude' );
	$library = new Native_Media_Library( new Etch_Provider(), false );
	$method = new ReflectionMethod( Native_Media_Library::class, 'attachment_data' );
	gps_check( $method->invoke( $library, $id )['location'] === $location, 'Inspector response provides validated coordinates' );
	Exif_Map::assets();
	gps_check( wp_script_is( 'uplink-mbe-exif-map', 'enqueued' ), 'Map loads bundled Leaflet when both opt-ins are enabled' );
	$leaflet = wp_scripts()->registered['uplink-mbe-leaflet'];
	gps_check( $leaflet->src === UPLINK_MBE_URL . 'assets/vendor/leaflet/leaflet.js', 'Leaflet JavaScript comes from the plugin, independent of Bricks assets' );
	gps_check( wp_styles()->registered['uplink-mbe-leaflet']->src === UPLINK_MBE_URL . 'assets/vendor/leaflet/leaflet.css', 'Leaflet CSS comes from the plugin' );
	wp_dequeue_script( 'uplink-mbe-exif-map' );
	Plugin::update_settings( array_merge( Plugin::settings(), array( 'exif_map' => 0 ) ) );
	gps_check( null === Exif_Map::location( $id ) && null === $method->invoke( $library, $id )['location'], 'Map opt-out omits map coordinates even when GPS fields are enabled' );
	gps_check( null !== Etch_Dynamic_Data::attachment_exif( $id )['gps']['latitude'], 'Map opt-out preserves enabled GPS metadata' );
	Exif_Map::assets();
	gps_check( ! wp_script_is( 'uplink-mbe-exif-map', 'enqueued' ), 'Map opt-out does not enqueue map assets' );
	Plugin::update_settings( array_merge( Plugin::settings(), array( 'exif_gps' => 0 ) ) );
	gps_check( null === Exif_Map::location( $id ) && null === $method->invoke( $library, $id )['location'], 'Disabling GPS omits map coordinates from inspector response' );
	Exif_Map::assets();
	gps_check( ! wp_script_is( 'uplink-mbe-exif-map', 'enqueued' ), 'Disabled GPS does not enqueue map assets' );
	gps_check( null === Etch_Dynamic_Data::attachment_exif( $id )['gps']['latitude'], 'Disabled GPS is also absent from EXIF reader' );
	Plugin::update_settings( array_merge( Plugin::settings(), array( 'exif_gps' => 1, 'exif_map' => 1 ) ) );
	$plain = wp_insert_attachment( array( 'post_title' => 'MBB no GPS fixture', 'post_mime_type' => 'image/jpeg' ) );
	try { gps_check( null === Exif_Map::location( $plain ), 'Image without GPS has no map location' ); } finally { wp_delete_attachment( $plain, true ); }
	if ( $keep ) echo 'MAP_FIXTURE_ID=' . $id . "\n";
} finally {
	update_option( 'uplink_mbe_settings', $saved );
	if ( ! $keep ) {
		if ( $id ) wp_delete_attachment( $id, true );
		if ( $file && is_file( $file ) ) unlink( $file );
	}
}
echo "GPS map tests passed.\n";
