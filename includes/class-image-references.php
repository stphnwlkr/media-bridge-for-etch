<?php
namespace UplinkPress\MediaBridgeForEtch;
if ( ! defined( 'ABSPATH' ) ) exit;

/** Exact local image URL replacement, including JSON and safe serialized arrays. */
final class Image_References {
	public static function variants( array $urls ): array {
		$out = array();
		foreach ( $urls as $old => $new ) {
			foreach ( array( $old => $new, set_url_scheme( $old, 'http' ) => set_url_scheme( $new, 'http' ), set_url_scheme( $old, 'https' ) => set_url_scheme( $new, 'https' ), preg_replace( '#^https?:#', '', $old ) => preg_replace( '#^https?:#', '', $new ), wp_parse_url( $old, PHP_URL_PATH ) => wp_parse_url( $new, PHP_URL_PATH ) ) as $a => $b ) {
				if ( ! $a || ! $b || $a === $b ) continue;
				$out[ $a ] = $b;
				$out[ str_replace( '/', '\\/', $a ) ] = str_replace( '/', '\\/', $b );
			}
		}
		uksort( $out, static fn( $a, $b ) => strlen( $b ) <=> strlen( $a ) );
		return $out;
	}
	private static function objects( $value ): bool {
		if ( is_object( $value ) ) return true;
		if ( is_array( $value ) ) foreach ( $value as $item ) if ( self::objects( $item ) ) return true;
		return false;
	}
	public static function replace( string $raw, array $map ): string {
		if ( is_serialized( $raw ) ) {
			// Never instantiate classes from database values while rewriting references.
			$value = @unserialize( $raw, array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- Class creation is explicitly disabled.
			if ( self::objects( $value ) ) return $raw;
			$walk = static function ( $item ) use ( &$walk, $map ) {
				if ( is_string( $item ) ) return self::replace( $item, $map );
				if ( is_array( $item ) ) foreach ( $item as $key => $child ) $item[ $key ] = $walk( $child );
				return $item;
			};
			$new = $walk( $value );
			return $new === $value ? $raw : serialize( $new ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Preserve the existing database serialization format.
		}
		if ( ! $map ) return $raw;
		$pattern = '~(?<![a-zA-Z0-9:])(?:' . implode( '|', array_map( static fn( $url ) => preg_quote( $url, '~' ), array_keys( $map ) ) ) . ')(?![a-zA-Z0-9._%+\x7e-])~';
		return preg_replace_callback( $pattern, static fn( $m ) => $map[ $m[0] ], $raw ) ?? $raw;
	}
	/** Must run inside the conversion transaction. Journal supports nontransactional rollback. */
	public static function update( array $urls, array &$journal ): int {
		global $wpdb;
		$map = self::variants( $urls ); $changed = 0;
		$tables = array(
			array( $wpdb->posts, 'ID', array( 'post_content', 'post_excerpt' ), '' ),
			array( $wpdb->postmeta, 'meta_id', array( 'meta_value' ), 'post' ),
			array( $wpdb->termmeta, 'meta_id', array( 'meta_value' ), 'term' ),
			array( $wpdb->options, 'option_id', array( 'option_value' ), 'option' ),
		);
		foreach ( $tables as list( $table, $pk, $columns, $kind ) ) {
			$cursor = 0;
			$like = array(); $arguments = array();
			foreach ( array_unique( array_map( static fn( $url ) => wp_basename( wp_parse_url( $url, PHP_URL_PATH ) ), array_keys( $urls ) ) ) as $name ) {
				foreach ( $columns as $column ) { $like[] = "%i LIKE %s"; $arguments[] = $column; $arguments[] = '%' . $wpdb->esc_like( $name ) . '%'; }
			}
			if ( ! $like ) continue;
			$where = implode( ' OR ', $like );
			do {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The predicate contains only fixed %i/%s fragments; every identifier and value is passed to prepare().
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE %i > %d AND ({$where}) ORDER BY %i LIMIT 200", array_merge( array( $table, $pk, $cursor ), $arguments, array( $pk ) ) ), ARRAY_A );
				if ( $wpdb->last_error ) throw new \RuntimeException( esc_html__( 'Could not read existing image references.', 'media-bridge-for-etch' ) );
				foreach ( $rows as $row ) {
					$cursor = (int) $row[ $pk ];
					if ( 'post' === $kind && ( in_array( $row['meta_key'], array( '_wp_attachment_metadata', '_wp_attached_file', '_wp_attachment_backup_sizes' ), true ) || str_starts_with( $row['meta_key'], '_uplink_mbe_' ) ) ) continue;
					if ( 'option' === $kind && ( str_starts_with( $row['option_name'], '_' ) || str_starts_with( $row['option_name'], 'uplink_mbe_' ) || 'cron' === $row['option_name'] ) ) continue;
					foreach ( $columns as $column ) {
						$before = (string) $row[ $column ]; $after = self::replace( $before, $map );
						if ( $after === $before ) continue;
						$post_id = '' === $kind ? $cursor : ( 'post' === $kind ? (int) $row['post_id'] : 0 );
						if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) throw new \RuntimeException( esc_html__( 'You cannot update a post that references this image.', 'media-bridge-for-etch' ) );
						// Compare-and-set avoids overwriting edits made during conversion.
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixed identifiers, values escaped by wpdb; exact-value guard protects concurrent edits.
						$n = $wpdb->query( $wpdb->prepare( 'UPDATE %i SET %i = %s WHERE %i = %d AND BINARY %i = %s', $table, $column, $after, $pk, $cursor, $column, $before ) );
						if ( 1 !== $n ) throw new \RuntimeException( esc_html__( 'An image reference changed during conversion. Please retry.', 'media-bridge-for-etch' ) );
						$journal[] = array( $table, $pk, $cursor, $column, $before, $after ); ++$changed;
						if ( $post_id ) { clean_post_cache( $post_id ); wp_cache_delete( $post_id, 'post_meta' ); }
						if ( 'term' === $kind ) wp_cache_delete( (int) $row['term_id'], 'term_meta' );
						if ( 'option' === $kind ) wp_cache_delete( $row['option_name'], 'options' );
					}
				}
			} while ( count( $rows ) === 200 );
		}
		wp_cache_delete( 'alloptions', 'options' );
		return $changed;
	}
	public static function rollback( array $journal ): void {
		global $wpdb;
		foreach ( array_reverse( $journal ) as list( $table, $pk, $id, $column, $before, $after ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Private in-memory journal from the fixed table list, not user input.
			$wpdb->query( $wpdb->prepare( 'UPDATE %i SET %i = %s WHERE %i = %d AND BINARY %i = %s', $table, $column, $before, $pk, $id, $column, $after ) );
		}
		wp_cache_delete( 'alloptions', 'options' );
	}
}
