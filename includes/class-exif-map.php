<?php
namespace UplinkPress\MediaBridgeForEtch;

if ( ! defined( 'ABSPATH' ) ) exit;

/** GPS map data is omitted entirely unless the administrator enables GPS metadata. */
final class Exif_Map {
	public static function coordinates( mixed $latitude, mixed $longitude ): ?array {
		if ( ! is_numeric( $latitude ) || ! is_numeric( $longitude ) ) return null;
		$latitude = (float) $latitude;
		$longitude = (float) $longitude;
		if ( ! is_finite( $latitude ) || ! is_finite( $longitude ) || abs( $latitude ) > 90 || abs( $longitude ) > 180 ) return null;
		return array( 'latitude' => $latitude, 'longitude' => $longitude );
	}
	public static function location( int $attachment_id ): ?array {
		if ( empty( Plugin::settings()['exif_gps'] ) || empty( Plugin::settings()['exif_map'] ) ) return null;
		$gps = Etch_Dynamic_Data::attachment_exif( $attachment_id )['gps'] ?? array();
		return self::coordinates( $gps['latitude'] ?? null, $gps['longitude'] ?? null );
	}
	public static function assets(): void {
		if ( empty( Plugin::settings()['exif_gps'] ) || empty( Plugin::settings()['exif_map'] ) || ! current_user_can( 'upload_files' ) ) return;
		$handle = 'uplink-mbe-leaflet';
		wp_enqueue_script( $handle, UPLINK_MBE_URL . 'assets/vendor/leaflet/leaflet.js', array(), '1.9.4', true );
		wp_enqueue_style( $handle, UPLINK_MBE_URL . 'assets/vendor/leaflet/leaflet.css', array(), '1.9.4' );
		wp_enqueue_style( 'uplink-mbe-exif-map', UPLINK_MBE_URL . 'assets/exif-map.css', array( 'uplink-mbe-leaflet' ), UPLINK_MBE_ASSET_VERSION );
		wp_enqueue_script( 'uplink-mbe-exif-map', UPLINK_MBE_URL . 'assets/exif-map.js', array( $handle ), UPLINK_MBE_ASSET_VERSION, true );
		wp_localize_script( 'uplink-mbe-exif-map', 'uplinkMbeExifMapStrings', array(
			'location' => __( 'Photo location', 'media-bridge-for-etch' ),
			'satellite' => __( 'Satellite', 'media-bridge-for-etch' ),
			'streets' => __( 'OpenStreetMap', 'media-bridge-for-etch' ),
			'openGoogle' => __( 'Open in Google Maps', 'media-bridge-for-etch' ),
			'openApple' => __( 'Open in Apple Maps', 'media-bridge-for-etch' ),
			'error' => __( 'Map imagery could not be loaded. Use the links below to open the location in Google Maps or Apple Maps.', 'media-bridge-for-etch' ),
		) );
	}
}
