<?php
namespace UplinkPress\MediaBridgeForEtch;

final class Etch_Dynamic_Data {
	private const GLOBAL_CACHE_PREFIX = 'uplink_mbe_etch_exif_v2_';
	private static array $cache = array();

	public function __construct() {
		add_filter( 'etch/dynamic_data/post', array( $this, 'add_image_exif' ), 20, 2 );
		add_filter( 'etch/dynamic_data/option', array( $this, 'add_global_image_exif' ), 20 );
		add_action( 'add_attachment', array( $this, 'invalidate_attachment' ) );
		add_action( 'edit_attachment', array( $this, 'invalidate_attachment' ) );
		add_action( 'delete_attachment', array( $this, 'invalidate_attachment' ) );
		add_action( 'added_post_meta', array( $this, 'invalidate_attachment_meta' ), 10, 4 );
		add_action( 'updated_post_meta', array( $this, 'invalidate_attachment_meta' ), 10, 4 );
		add_action( 'deleted_post_meta', array( $this, 'invalidate_attachment_meta' ), 10, 4 );
	}

	public function add_image_exif( array $data, int $post_id ): array {
		if ( 'attachment' === get_post_type( $post_id ) && wp_attachment_is_image( $post_id ) ) {
			$data['image'] = is_array( $data['image'] ?? null ) ? $data['image'] : array();
			$data['image']['id'] = $post_id;
			$data['image']['url'] = (string) wp_get_attachment_url( $post_id );
			$data['image']['exif'] = self::attachment_exif( $post_id );
		}

		return $this->enrich_images( $data );
	}

	/**
	 * Expose image metadata without requiring a post or loop context.
	 *
	 * @param array<string, mixed> $data Etch options dynamic data.
	 * @return array<string, mixed>
	 */
	public function add_global_image_exif( array $data ): array {
		$cache_key = self::global_cache_key();
		$images    = get_transient( $cache_key );
		if ( ! is_array( $images ) ) {
			$images = array();
			$ids = get_posts(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					'post_mime_type' => 'image',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'no_found_rows'  => true,
				)
			);
			foreach ( $ids as $attachment_id ) {
				$attachment_id = absint( $attachment_id );
				if ( ! $attachment_id || ! wp_attachment_is_image( $attachment_id ) ) {
					continue;
				}
				$images[ $attachment_id ] = array(
					'id'      => $attachment_id,
					'url'     => (string) wp_get_attachment_url( $attachment_id ),
					'title'   => (string) get_post_field( 'post_title', $attachment_id, 'raw' ),
					'alt'     => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
					'caption' => get_the_excerpt( $attachment_id ),
					'exif'    => self::attachment_exif( $attachment_id ),
				);
			}
			set_transient( $cache_key, $images, 12 * HOUR_IN_SECONDS );
		}

		$namespace = is_array( $data['upm'] ?? null ) ? $data['upm'] : array();
		$namespace['img'] = $images;
		$data['upm'] = $namespace;
		return $data;
	}

	public function invalidate_attachment( int $attachment_id ): void {
		unset( self::$cache[ $attachment_id ] );
		self::delete_global_caches();
	}

	/**
	 * Invalidate EXIF caches when WordPress changes attachment metadata.
	 *
	 * WordPress passes one metadata ID to the added and updated hooks, but an
	 * array of metadata IDs to the deleted hook.
	 *
	 * @param int|int[] $meta_id Metadata ID or deleted metadata IDs.
	 */
	public function invalidate_attachment_meta( int|array $meta_id, int $object_id, string $meta_key, mixed $meta_value = null ): void {
		unset( $meta_id, $meta_value );
		if ( in_array( $meta_key, array( '_wp_attachment_metadata', '_wp_attached_file', '_wp_attachment_image_alt' ), true ) && 'attachment' === get_post_type( $object_id ) ) {
			$this->invalidate_attachment( $object_id );
		}
	}

	private static function global_cache_key(): string {
		return self::GLOBAL_CACHE_PREFIX . ( empty( Plugin::settings()['exif_gps'] ) ? 'without_gps' : 'with_gps' );
	}

	private static function delete_global_caches(): void {
		delete_transient( self::GLOBAL_CACHE_PREFIX . 'without_gps' );
		delete_transient( self::GLOBAL_CACHE_PREFIX . 'with_gps' );
	}

	private function enrich_images( array $value, int $depth = 0 ): array {
		if ( 8 < $depth ) {
			return $value;
		}
		if ( $this->looks_like_image( $value ) && empty( $value['exif'] ) ) {
			$attachment_id = absint( $value['id'] ?? $value['ID'] ?? 0 );
			if ( ! $attachment_id && ! empty( $value['url'] ) ) {
				$attachment_id = (int) attachment_url_to_postid( (string) $value['url'] );
			}
			if ( $attachment_id && wp_attachment_is_image( $attachment_id ) ) {
				$value['exif'] = self::attachment_exif( $attachment_id );
			}
		}
		foreach ( $value as $key => $item ) {
			if ( is_array( $item ) && 'exif' !== $key ) {
				$value[ $key ] = $this->enrich_images( $item, $depth + 1 );
			}
		}
		return $value;
	}

	private function looks_like_image( array $value ): bool {
		return ! empty( $value['url'] ) && ( array_key_exists( 'alt', $value ) || array_key_exists( 'sizes', $value ) || array_key_exists( 'srcset', $value ) );
	}

	public static function attachment_exif( int $attachment_id ): array {
		if ( isset( self::$cache[ $attachment_id ] ) ) {
			return self::$cache[ $attachment_id ];
		}
		$metadata   = wp_get_attachment_metadata( $attachment_id );
		$image_meta = is_array( $metadata ) && isset( $metadata['image_meta'] ) && is_array( $metadata['image_meta'] ) ? $metadata['image_meta'] : array();
		$file        = get_attached_file( $attachment_id );
		$original_file = function_exists( 'wp_get_original_image_path' ) ? wp_get_original_image_path( $attachment_id ) : '';
		$exif_file   = $original_file && is_file( $original_file ) && is_readable( $original_file ) ? $original_file : $file;
		$width       = is_array( $metadata ) ? absint( $metadata['width'] ?? 0 ) : 0;
		$height      = is_array( $metadata ) ? absint( $metadata['height'] ?? 0 ) : 0;
		$camera      = sanitize_text_field( (string) ( $image_meta['camera'] ?? '' ) );
		$captured    = ! empty( $image_meta['created_timestamp'] ) && is_numeric( $image_meta['created_timestamp'] ) ? (int) $image_meta['created_timestamp'] : 0;
		$raw_exif      = array();
		$exif_mime     = $exif_file && function_exists( 'wp_get_image_mime' ) ? wp_get_image_mime( $exif_file ) : false;
		$can_read_exif = in_array( $exif_mime, array( 'image/jpeg', 'image/tiff' ), true );
		if ( $can_read_exif && is_file( $exif_file ) && is_readable( $exif_file ) && function_exists( 'exif_read_data' ) ) {
			$read = @exif_read_data( $exif_file, 'ANY_TAG', true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- EXIF support varies by image format and host.
			$raw_exif = is_array( $read ) ? $read : array();
		}
		$ifd0 = is_array( $raw_exif['IFD0'] ?? null ) ? $raw_exif['IFD0'] : array();
		$exif = is_array( $raw_exif['EXIF'] ?? null ) ? $raw_exif['EXIF'] : array();
		$include_gps = ! empty( Plugin::settings()['exif_gps'] );
		$gps  = $include_gps && is_array( $raw_exif['GPS'] ?? null ) ? $raw_exif['GPS'] : array();
		if ( ! $camera ) {
			$camera = sanitize_text_field( (string) ( $ifd0['Model'] ?? '' ) );
		}
		if ( ! $captured && ! empty( $exif['DateTimeOriginal'] ) ) {
			$date = \DateTimeImmutable::createFromFormat( 'Y:m:d H:i:s', (string) $exif['DateTimeOriginal'], wp_timezone() );
			$captured = $date ? $date->getTimestamp() : 0;
		}
		$aperture = self::fraction_to_float( $image_meta['aperture'] ?? $exif['FNumber'] ?? null );
		$focal    = self::fraction_to_float( $image_meta['focal_length'] ?? $exif['FocalLength'] ?? null );
		$exposure = self::fraction_to_float( $image_meta['shutter_speed'] ?? $exif['ExposureTime'] ?? null );
		$latitude = self::gps_decimal( $gps['GPSLatitude'] ?? null, $gps['GPSLatitudeRef'] ?? '' );
		$longitude = self::gps_decimal( $gps['GPSLongitude'] ?? null, $gps['GPSLongitudeRef'] ?? '' );
		$altitude_m = self::fraction_to_float( $gps['GPSAltitude'] ?? null );
		$file_size = $file && is_file( $file ) ? (int) filesize( $file ) : 0;
		$result = array(
			'attachment_id'   => $attachment_id,
			'file_format'     => $file ? strtoupper( pathinfo( $file, PATHINFO_EXTENSION ) ) : null,
			'file_size'       => $file_size ? size_format( $file_size ) : null,
			'file_size_bytes' => $file_size ?: null,
			'width'           => $width ?: null,
			'height'          => $height ?: null,
			'dimensions'      => $width && $height ? $width . ' x ' . $height . ' px' : null,
			'camera_model'    => $camera ?: null,
			'lens'            => self::text_value( $exif['LensModel'] ?? $image_meta['lens'] ?? null ),
			'iso'             => self::number_value( $image_meta['iso'] ?? $exif['ISOSpeedRatings'] ?? null ),
			'exposure'        => $exposure,
			'exposure_display' => self::exposure_display( $exposure ),
			'date_taken'      => $captured ? wp_date( 'F j, Y', $captured ) : null,
			'date_taken_iso'  => $captured ? wp_date( DATE_ATOM, $captured ) : null,
			'aperture'        => $aperture,
			'aperture_display' => $aperture ? 'f/' . self::trim_decimal( $aperture ) : null,
			'focal_mm'        => $focal,
			'focal_display'   => $focal ? self::trim_decimal( $focal ) . ' mm' : null,
			'alt_text'        => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ?: null,
			'caption'         => get_the_excerpt( $attachment_id ) ?: null,
			'credit'          => self::text_value( $image_meta['credit'] ?? $ifd0['Artist'] ?? null ),
			'copyright'       => self::text_value( $image_meta['copyright'] ?? $ifd0['Copyright'] ?? null ),
			'gps'             => array(
				'latitude'    => $latitude,
				'longitude'   => $longitude,
				'altitude_ft' => null !== $altitude_m ? self::trim_decimal( $altitude_m * 3.28084 ) . ' ft' : null,
			),
		);
		self::$cache[ $attachment_id ] = $result;
		return $result;
	}

	private static function fraction_to_float( $value ): ?float {
		if ( is_array( $value ) ) {
			$value = reset( $value );
		}
		if ( null === $value || '' === $value ) {
			return null;
		}
		$parts = explode( '/', (string) $value, 2 );
		$number = (float) $parts[0];
		$divisor = isset( $parts[1] ) ? (float) $parts[1] : 1.0;
		return 0.0 === $divisor || ! is_finite( $number / $divisor ) ? null : $number / $divisor;
	}

	private static function text_value( $value ): ?string {
		if ( is_array( $value ) ) {
			$value = reset( $value );
		}
		$value = sanitize_text_field( (string) $value );
		return '' === $value ? null : $value;
	}

	private static function number_value( $value ): int|float|null {
		if ( is_array( $value ) ) {
			$value = reset( $value );
		}
		if ( ! is_numeric( $value ) ) {
			return null;
		}
		$number = (float) $value;
		return floor( $number ) === $number ? (int) $number : $number;
	}

	private static function gps_decimal( $coordinates, string $hemisphere ): ?float {
		if ( ! is_array( $coordinates ) || 2 > count( $coordinates ) ) {
			return null;
		}
		$degrees = self::fraction_to_float( $coordinates[0] ) ?? 0.0;
		$minutes = self::fraction_to_float( $coordinates[1] ) ?? 0.0;
		$seconds = self::fraction_to_float( $coordinates[2] ?? null ) ?? 0.0;
		$decimal = $degrees + ( $minutes / 60 ) + ( $seconds / 3600 );
		return in_array( strtoupper( $hemisphere ), array( 'S', 'W' ), true ) ? -$decimal : $decimal;
	}

	private static function exposure_display( ?float $seconds ): ?string {
		if ( null === $seconds || 0 >= $seconds ) {
			return null;
		}
		return 1 > $seconds ? '1/' . max( 1, (int) round( 1 / $seconds ) ) . ' s' : self::trim_decimal( $seconds ) . ' s';
	}

	private static function trim_decimal( float $number ): string {
		return rtrim( rtrim( number_format( $number, 2, '.', '' ), '0' ), '.' );
	}
}
