<?php
namespace UplinkPress\MediaBridgeForEtch;

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
			return new WP_Error( 'uplink_mbe_empty_term_name', __( 'A synchronized folder must have a name.', 'media-bridge-for-etch' ) );
		}

		return wp_insert_term( $name, $this->taxonomy(), $args );
	}

	public function update_term( int $term_id, array $args ): array|WP_Error {
		if ( ! $this->get_term( $term_id ) ) {
			return new WP_Error( 'uplink_mbe_invalid_term', __( 'The synchronized folder no longer exists.', 'media-bridge-for-etch' ) );
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

	public function get_position( int $term_id ): ?int {
		$key = $this->order_meta_key();
		if ( ! $key || ! metadata_exists( 'term', $term_id, $key ) ) {
			return null;
		}

		return (int) get_term_meta( $term_id, $key, true );
	}

	public function set_position( int $term_id, int $position ): void {
		$key = $this->order_meta_key();
		if ( $key && $this->get_term( $term_id ) ) {
			update_term_meta( $term_id, $key, max( 0, $position ) );
		}
	}

	/**
	 * Keep parents followed by their descendants while preserving each sibling
	 * group's provider-defined position.
	 *
	 * @param WP_Term[] $terms Taxonomy terms.
	 * @return WP_Term[]
	 */
	public function order_terms_hierarchically( array $terms ): array {
		$children  = array();
		$positions = array();
		foreach ( $terms as $term ) {
			$term_id               = (int) $term->term_id;
			$children[ (int) $term->parent ][] = $term;
			$positions[ $term_id ]  = $this->get_position( $term_id ) ?? 0;
		}

		foreach ( $children as &$siblings ) {
			usort(
				$siblings,
				static function ( WP_Term $first, WP_Term $second ) use ( $positions ): int {
					$first_id  = (int) $first->term_id;
					$second_id = (int) $second->term_id;
					$position  = $positions[ $first_id ] <=> $positions[ $second_id ];
					if ( 0 !== $position ) {
						return $position;
					}

					$name = strcasecmp( $first->name, $second->name );
					return 0 !== $name ? $name : $first_id <=> $second_id;
				}
			);
		}
		unset( $siblings );

		$ordered = array();
		$seen    = array();
		$append  = static function ( int $parent ) use ( &$append, &$children, &$ordered, &$seen ): void {
			foreach ( $children[ $parent ] ?? array() as $term ) {
				$term_id = (int) $term->term_id;
				if ( isset( $seen[ $term_id ] ) ) {
					continue;
				}
				$seen[ $term_id ] = true;
				$ordered[]        = $term;
				$append( $term_id );
			}
		};
		$append( 0 );

		// Preserve any orphaned terms instead of silently omitting them.
		foreach ( $terms as $term ) {
			if ( ! isset( $seen[ (int) $term->term_id ] ) ) {
				$ordered[] = $term;
			}
		}

		return $ordered;
	}

	/**
	 * Place a newly created or moved term after its current siblings.
	 */
	public function append_position( int $term_id, int $parent ): void {
		$sibling_ids = get_terms(
			array(
				'taxonomy'   => $this->taxonomy(),
				'hide_empty' => false,
				'parent'     => $parent,
				'fields'     => 'ids',
			)
		);
		if ( is_wp_error( $sibling_ids ) ) {
			return;
		}

		$position = -1;
		foreach ( $sibling_ids as $sibling_id ) {
			$sibling_id = (int) $sibling_id;
			if ( $term_id === $sibling_id ) {
				continue;
			}
			$position = max( $position, $this->get_position( $sibling_id ) ?? 0 );
		}

		$this->set_position( $term_id, $position + 1 );
	}

	private function sanitize_term_args( array $args, bool $include_name = true ): array {
		$clean = array();

		if ( $include_name || array_key_exists( 'name', $args ) ) {
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
