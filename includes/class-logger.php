<?php
namespace MediaBridgeForEtch;

final class Logger {
	private const OPTION = 'mbe_sync_log';
	private const LIMIT  = 200;

	public static function add( string $type, string $message, array $context = array() ): void {
		$entries   = get_option( self::OPTION, array() );
		$entries   = is_array( $entries ) ? $entries : array();
		$entries[] = array(
			'time'    => current_time( 'mysql', true ),
			'type'    => sanitize_key( $type ),
			'message' => sanitize_text_field( $message ),
			'context' => self::sanitize_context( $context ),
		);
		if ( count( $entries ) > self::LIMIT ) {
			$entries = array_slice( $entries, - self::LIMIT );
		}
		update_option( self::OPTION, $entries, false );
	}

	public static function all(): array {
		$entries = get_option( self::OPTION, array() );
		return is_array( $entries ) ? array_reverse( $entries ) : array();
	}

	public static function clear(): void {
		delete_option( self::OPTION );
	}

	private static function sanitize_context( array $context ): array {
		$clean = array();
		foreach ( $context as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( is_scalar( $value ) || null === $value ) {
				$clean[ $key ] = sanitize_text_field( (string) $value );
			}
		}
		return $clean;
	}
}
