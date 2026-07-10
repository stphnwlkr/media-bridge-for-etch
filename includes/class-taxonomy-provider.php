<?php
namespace MediaBridgeForEtch;

use WP_Error;
use WP_Term;

abstract class Taxonomy_Provider implements Provider_Interface {
	public function is_available(): bool {
		return taxonomy_exists( $this->taxonomy() );
	}

	public function get_term( int $term_id ): ?WP_Term {
		$term = get_term( $term_id, $this->taxonomy() );
		return $term instanceof WP_Term ? $term : null;
	}

	public function get_terms(): array|WP_Error {
		return get_terms(
			array(
				'taxonomy'   => $this->taxonomy(),
				'hide_empty' => false,
			)
		);
	}

	public function get_object_terms( int $attachment_id ): array|WP_Error {
		return wp_get_object_terms(
			$attachment_id,
			$this->taxonomy(),
			array( 'fields' => 'ids' )
		);
	}

	public function create_term( array $args ): array|WP_Error {
		$args = $this->sanitize_term_args( $args );
		$name = $args['name'];
		unset( $args['name'] );

		if ( '' === $name ) {
			return new WP_Error( 'mbe_empty_term_name', __( 'A synchronized folder must have a name.', 'media-bridge-for-etch' ) );
		}

		return wp_insert_term( $name, $this->taxonomy(), $args );
	}

	public function update_term( int $term_id, array $args ): array|WP_Error {
		if ( ! $this->get_term( $term_id ) ) {
			return new WP_Error( 'mbe_invalid_term', __( 'The synchronized folder no longer exists.', 'media-bridge-for-etch' ) );
		}

		return wp_update_term( $term_id, $this->taxonomy(), $this->sanitize_term_args( $args, false ) );
	}

	public function delete_term( int $term_id ): bool|int|WP_Error {
		return wp_delete_term( $term_id, $this->taxonomy() );
	}

	public function set_object_terms( int $attachment_id, array $term_ids ): array|WP_Error {
		$term_ids = array_values( array_unique( array_map( 'intval', $term_ids ) ) );
		return wp_set_object_terms( $attachment_id, $term_ids, $this->taxonomy(), false );
	}

	public function get_position( int $term_id ): int {
		$key = $this->order_meta_key();
		return $key ? (int) get_term_meta( $term_id, $key, true ) : 0;
	}

	public function set_position( int $term_id, int $position ): void {
		$key = $this->order_meta_key();
		if ( $key && $this->get_term( $term_id ) ) {
			update_term_meta( $term_id, $key, max( 0, $position ) );
		}
	}

	private function sanitize_term_args( array $args, bool $include_name = true ): array {
		$clean = array();

		if ( $include_name ) {
			$clean['name'] = sanitize_text_field( (string) ( $args['name'] ?? '' ) );
		}
		if ( array_key_exists( 'slug', $args ) ) {
			$clean['slug'] = sanitize_title( (string) $args['slug'] );
		}
		if ( array_key_exists( 'description', $args ) ) {
			$clean['description'] = sanitize_textarea_field( (string) $args['description'] );
		}
		if ( array_key_exists( 'parent', $args ) ) {
			$clean['parent'] = absint( $args['parent'] );
		}

		return $clean;
	}
}
