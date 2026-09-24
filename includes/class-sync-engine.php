<?php
namespace UplinkPress\MediaBridgeForEtch;

use WP_Error;
use WP_Term;

final class Sync_Engine {
	private Provider_Interface $etch;
	private Provider_Interface $external;
	private array $pending_deletions = array();
	private static bool $syncing = false;

	public function __construct( Provider_Interface $etch, Provider_Interface $external ) {
		$this->etch     = $etch;
		$this->external = $external;
	}

	public function register_hooks(): void {
		add_action( 'created_term', array( $this, 'term_created' ), 20, 3 );
		add_action( 'edited_term', array( $this, 'term_edited' ), 20, 3 );
		add_action( 'pre_delete_term', array( $this, 'term_deleting' ), 20, 2 );
		add_action( 'delete_term', array( $this, 'term_deleted' ), 20, 5 );
		add_action( 'set_object_terms', array( $this, 'object_terms_set' ), 20, 6 );
		add_action( 'updated_term_meta', array( $this, 'term_meta_updated' ), 20, 4 );
	}

	public function term_created( int $term_id, int $tt_id, string $taxonomy ): void {
		$this->sync_term_event( $term_id, $taxonomy, 'created' );
	}

	public function term_edited( int $term_id, int $tt_id, string $taxonomy ): void {
		$this->sync_term_event( $term_id, $taxonomy, 'updated' );
	}

	public function term_deleting( int $term_id, string $taxonomy ): void {
		if ( self::$syncing || ! $this->is_bridge_taxonomy( $taxonomy ) ) {
			return;
		}

		$source = $this->provider_for_taxonomy( $taxonomy );
		$target = $this->other_provider( $source );
		$term   = $source->get_term( $term_id );
		if ( ! $term ) {
			return;
		}

		$this->pending_deletions[ $this->deletion_key( $term_id, $taxonomy ) ] = array(
			'source' => $source,
			'target' => $target,
			'mapped' => $this->mapped_term_id( $source, $term_id, $target ),
			'name'   => $term->name,
		);
	}

	public function term_deleted( int $term_id, int $tt_id, string $taxonomy, WP_Term $deleted_term, array $object_ids ): void {
		if ( self::$syncing || ! $this->is_bridge_taxonomy( $taxonomy ) ) {
			return;
		}

		$key     = $this->deletion_key( $term_id, $taxonomy );
		$pending = $this->pending_deletions[ $key ] ?? null;
		unset( $this->pending_deletions[ $key ] );
		if ( ! $pending ) {
			return;
		}

		if ( ! Plugin::settings()['sync_deletions'] ) {
			if ( $pending['mapped'] ) {
				delete_term_meta( $pending['mapped'], '_mbe_map_' . $pending['source']->id() );
			}
			Logger::add( 'notice', 'Folder deletion was not copied because deletion sync is disabled.', array( 'term' => $pending['name'] ) );
			return;
		}

		if ( ! $pending['mapped'] ) {
			return;
		}

		self::$syncing = true;
		try {
			$result = $pending['target']->delete_term( $pending['mapped'] );
			if ( is_wp_error( $result ) ) {
				Logger::add( 'error', 'Could not copy folder deletion.', array( 'error' => $result->get_error_message() ) );
			} else {
				Logger::add( 'delete', 'Folder deletion synchronized.', array( 'folder' => $pending['name'], 'source' => $pending['source']->label() ) );
			}
		} finally {
			self::$syncing = false;
		}
	}

	public function term_meta_updated( int $meta_id, int $term_id, string $meta_key, mixed $meta_value ): void {
		if ( self::$syncing ) {
			return;
		}
		$source = null;
		foreach ( array( $this->etch, $this->external ) as $provider ) {
			if ( $provider->order_meta_key() === $meta_key && $provider->get_term( $term_id ) ) {
				$source = $provider;
				break;
			}
		}
		if ( ! $source ) {
			return;
		}
		$target = $this->other_provider( $source );
		$mapped = $this->mapped_term_id( $source, $term_id, $target );
		if ( ! $mapped ) {
			return;
		}
		$mode = Plugin::settings()['conflict_mode'];
		if ( ( 'etch' === $mode && 'etch' !== $source->id() ) || ( 'external' === $mode && 'etch' === $source->id() ) ) {
			$authority    = $target;
			$authority_id = $mapped;
			$target       = $source;
			$mapped       = $term_id;
			$source       = $authority;
			$term_id      = $authority_id;
		}
		self::$syncing = true;
		try {
			$position = $source->get_position( $term_id );
			if ( null === $position ) {
				return;
			}
			$target->set_position( $mapped, $position );
			$this->touch_pair( $source, $term_id, $target, $mapped, $source->id() );
			Logger::add( 'order', 'Folder order synchronized.', array( 'source' => $source->label(), 'target' => $target->label() ) );
		} finally {
			self::$syncing = false;
		}
	}

	public function object_terms_set( int $object_id, array $terms, array $tt_ids, string $taxonomy, bool $append, array $old_tt_ids ): void {
		if ( self::$syncing || ! $this->is_bridge_taxonomy( $taxonomy ) || 'attachment' !== get_post_type( $object_id ) ) {
			return;
		}

		$source = $this->provider_for_taxonomy( $taxonomy );
		$target = $this->other_provider( $source );
		$mode   = Plugin::settings()['conflict_mode'];
		if ( 'etch' === $mode && 'etch' !== $source->id() ) {
			$source = $this->etch;
			$target = $this->external;
		} elseif ( 'external' === $mode && 'etch' === $source->id() ) {
			$source = $this->external;
			$target = $this->etch;
		}
		$this->sync_attachment( $object_id, $source, $target );
	}

	private function sync_term_event( int $term_id, string $taxonomy, string $event ): void {
		if ( self::$syncing || ! $this->is_bridge_taxonomy( $taxonomy ) ) {
			return;
		}

		$source = $this->provider_for_taxonomy( $taxonomy );
		$target = $this->other_provider( $source );
		$term   = $source->get_term( $term_id );
		if ( ! $term ) {
			return;
		}

		$mode = Plugin::settings()['conflict_mode'];
		if ( 'etch' === $mode && 'etch' !== $source->id() ) {
			$mapped = $this->mapped_term_id( $source, $term_id, $this->etch );
			if ( $mapped ) {
				$term    = $this->etch->get_term( $mapped );
				$term_id = $mapped;
				$source  = $this->etch;
				$target  = $this->external;
			}
		} elseif ( 'external' === $mode && 'etch' === $source->id() ) {
			$mapped = $this->mapped_term_id( $source, $term_id, $this->external );
			if ( $mapped ) {
				$term    = $this->external->get_term( $mapped );
				$term_id = $mapped;
				$source  = $this->external;
				$target  = $this->etch;
			}
		}
		if ( ! $term ) {
			return;
		}

		if ( $source->id() !== 'etch' && $this->term_depth( $term, $source ) > 2 ) {
			Logger::add( 'depth', 'A deeper client-side folder remains external-only.', array( 'folder' => $term->name, 'provider' => $source->label() ) );
			return;
		}

		$this->touch( $source, $term_id, $source->id() );
		self::$syncing = true;
		try {
			$target_id = $this->ensure_counterpart( $source, $term, $target );
			if ( ! $target_id ) {
				return;
			}

			$parent_id = 0;
			if ( $term->parent ) {
				$parent = $source->get_term( (int) $term->parent );
				if ( $parent ) {
					$parent_id = $this->ensure_counterpart( $source, $parent, $target );
				}
			}

			$result = $target->update_term(
				$target_id,
				array(
					'name'        => $term->name,
					'slug'        => $term->slug,
					'description' => $term->description,
					'parent'      => $parent_id,
				)
			);
			if ( is_wp_error( $result ) ) {
				Logger::add( 'error', 'Could not synchronize folder.', array( 'folder' => $term->name, 'error' => $result->get_error_message() ) );
				return;
			}

			$position = $source->get_position( $term_id );
			if ( null !== $position ) {
				$target->set_position( $target_id, $position );
			}
			$this->map_terms( $source, $term_id, $target, $target_id );
			$this->touch_pair( $source, $term_id, $target, $target_id, $source->id() );
			Logger::add( $event, "Folder {$event} synchronized.", array( 'folder' => $term->name, 'source' => $source->label(), 'target' => $target->label() ) );
		} finally {
			self::$syncing = false;
		}
	}

	public function reconcile(): array {
		$stats = array( 'mapped' => 0, 'created' => 0, 'errors' => 0, 'skipped' => 0, 'attachments' => 0 );
		if ( ! $this->etch->is_available() || ! $this->external->is_available() ) {
			$stats['errors']++;
			return $stats;
		}

		self::$syncing = true;
		try {
			$stats = $this->reconcile_direction( $this->etch, $this->external, $stats );
			$stats = $this->reconcile_direction( $this->external, $this->etch, $stats );
			$etch_objects = $this->attachment_ids_for_provider( $this->etch );
			$ext_objects  = $this->attachment_ids_for_provider( $this->external );

			if ( is_wp_error( $etch_objects ) || is_wp_error( $ext_objects ) ) {
				$stats['errors']++;
				$attachments = array();
			} else {
				$attachments = array_values( array_unique( array_map( 'absint', array_merge( $etch_objects, $ext_objects ) ) ) );
			}
			foreach ( $attachments as $attachment_id ) {
				$etch_terms = $this->etch->get_object_terms( (int) $attachment_id );
				$ext_terms  = $this->external->get_object_terms( (int) $attachment_id );
				if ( is_wp_error( $etch_terms ) || is_wp_error( $ext_terms ) ) {
					$stats['errors']++;
					continue;
				}
				if ( $ext_terms ) {
					$this->sync_attachment( (int) $attachment_id, $this->external, $this->etch, false );
				} elseif ( $etch_terms ) {
					$this->sync_attachment( (int) $attachment_id, $this->etch, $this->external, false );
				}
				$stats['attachments']++;
			}
		} finally {
			self::$syncing = false;
		}
		Logger::add( 'reconcile', 'Bridge reconciliation completed.', $stats );
		return $stats;
	}

	private function attachment_ids_for_provider( Provider_Interface $provider ): array|WP_Error {
		$term_ids = get_terms(
			array(
				'taxonomy'   => $provider->taxonomy(),
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);

		if ( is_wp_error( $term_ids ) || empty( $term_ids ) ) {
			return is_wp_error( $term_ids ) ? $term_ids : array();
		}

		$object_ids = get_objects_in_term( array_map( 'absint', $term_ids ), $provider->taxonomy() );
		if ( is_wp_error( $object_ids ) ) {
			return $object_ids;
		}

		return array_values( array_unique( array_map( 'absint', $object_ids ) ) );
	}

	private function reconcile_direction( Provider_Interface $source, Provider_Interface $target, array $stats ): array {
		$terms = $source->get_terms();
		if ( is_wp_error( $terms ) ) {
			$stats['errors']++;
			return $stats;
		}
		usort( $terms, fn( WP_Term $a, WP_Term $b ): int => $this->term_depth( $a, $source ) <=> $this->term_depth( $b, $source ) );
		foreach ( $terms as $term ) {
			if ( $source->id() !== 'etch' && $this->term_depth( $term, $source ) > 2 ) {
				$stats['skipped']++;
				continue;
			}
			$existing = $this->mapped_term_id( $source, (int) $term->term_id, $target );
			$id       = $this->ensure_counterpart( $source, $term, $target );
			if ( $id ) {
				$existing ? $stats['mapped']++ : $stats['created']++;
			} else {
				$stats['errors']++;
			}
		}
		return $stats;
	}

	private function sync_attachment( int $attachment_id, Provider_Interface $source, Provider_Interface $target, bool $manage_lock = true ): void {
		$source_ids = $source->get_object_terms( $attachment_id );
		if ( is_wp_error( $source_ids ) ) {
			Logger::add( 'error', 'Could not read attachment folders.', array( 'attachment' => $attachment_id ) );
			return;
		}

		$target_ids = array();
		foreach ( $source_ids as $source_id ) {
			$term = $source->get_term( (int) $source_id );
			if ( ! $term ) {
				continue;
			}
			if ( $target->id() === 'etch' && $this->term_depth( $term, $source ) > 2 ) {
				$term = $this->nearest_supported_ancestor( $term, $source );
				if ( ! $term ) {
					continue;
				}
			}
			$mapped = $this->ensure_counterpart( $source, $term, $target );
			if ( $mapped ) {
				$target_ids[] = $mapped;
			}
		}

		// Preserve external folders below Etch's supported depth when an Etch change is copied outward.
		if ( $source->id() === 'etch' && $target->id() !== 'etch' ) {
			$current = $target->get_object_terms( $attachment_id );
			if ( ! is_wp_error( $current ) ) {
				foreach ( $current as $current_id ) {
					$current_term = $target->get_term( (int) $current_id );
					if ( $current_term && $this->term_depth( $current_term, $target ) > 2 ) {
						$target_ids[] = (int) $current_id;
					}
				}
			}
		}

		if ( $manage_lock ) {
			self::$syncing = true;
		}
		try {
			$result = $target->set_object_terms( $attachment_id, $target_ids );
			if ( is_wp_error( $result ) ) {
				Logger::add( 'error', 'Could not synchronize attachment folders.', array( 'attachment' => $attachment_id, 'error' => $result->get_error_message() ) );
			} else {
				Logger::add( 'assignment', 'Attachment folder assignments synchronized.', array( 'attachment' => $attachment_id, 'source' => $source->label() ) );
			}
		} finally {
			if ( $manage_lock ) {
				self::$syncing = false;
			}
		}
	}

	private function ensure_counterpart( Provider_Interface $source, WP_Term $term, Provider_Interface $target ): int {
		$mapped = $this->mapped_term_id( $source, (int) $term->term_id, $target );
		if ( $mapped && $target->get_term( $mapped ) ) {
			return $mapped;
		}

		$parent_id = 0;
		if ( $term->parent ) {
			$parent = $source->get_term( (int) $term->parent );
			if ( $parent ) {
				$parent_id = $this->ensure_counterpart( $source, $parent, $target );
			}
		}

		$existing = get_terms(
			array(
				'taxonomy'   => $target->taxonomy(),
				'hide_empty' => false,
				'slug'       => $term->slug,
				'parent'     => $parent_id,
				'number'     => 1,
			)
		);
		if ( ! is_wp_error( $existing ) && ! empty( $existing ) ) {
			$target_id = (int) $existing[0]->term_id;
		} else {
			$created = $target->create_term(
				array(
					'name'        => $term->name,
					'slug'        => $term->slug,
					'description' => $term->description,
					'parent'      => $parent_id,
				)
			);
			if ( is_wp_error( $created ) ) {
				Logger::add( 'error', 'Could not create counterpart folder.', array( 'folder' => $term->name, 'error' => $created->get_error_message() ) );
				return 0;
			}
			$target_id = (int) $created['term_id'];
		}

		$position = $source->get_position( (int) $term->term_id );
		if ( null !== $position ) {
			$target->set_position( $target_id, $position );
		}
		$this->map_terms( $source, (int) $term->term_id, $target, $target_id );
		$this->touch_pair( $source, (int) $term->term_id, $target, $target_id, $source->id() );
		return $target_id;
	}

	private function nearest_supported_ancestor( WP_Term $term, Provider_Interface $provider ): ?WP_Term {
		while ( $this->term_depth( $term, $provider ) > 2 && $term->parent ) {
			$parent = $provider->get_term( (int) $term->parent );
			if ( ! $parent ) {
				return null;
			}
			$term = $parent;
		}
		return $term;
	}

	private function term_depth( WP_Term $term, Provider_Interface $provider ): int {
		$depth   = 1;
		$parent  = (int) $term->parent;
		$visited = array();
		while ( $parent && ! isset( $visited[ $parent ] ) ) {
			$visited[ $parent ] = true;
			$ancestor = $provider->get_term( $parent );
			if ( ! $ancestor ) {
				break;
			}
			$depth++;
			$parent = (int) $ancestor->parent;
		}
		return $depth;
	}

	private function map_terms( Provider_Interface $a, int $a_id, Provider_Interface $b, int $b_id ): void {
		update_term_meta( $a_id, '_mbe_map_' . $b->id(), $b_id );
		update_term_meta( $b_id, '_mbe_map_' . $a->id(), $a_id );
	}

	private function mapped_term_id( Provider_Interface $source, int $source_id, Provider_Interface $target ): int {
		if ( ! $source->get_term( $source_id ) ) {
			return 0;
		}

		$mapped = absint( get_term_meta( $source_id, '_mbe_map_' . $target->id(), true ) );
		if ( $mapped && ! $target->get_term( $mapped ) ) {
			delete_term_meta( $source_id, '_mbe_map_' . $target->id() );
			return 0;
		}

		return $mapped;
	}

	private function touch( Provider_Interface $provider, int $term_id, string $source ): void {
		update_term_meta( $term_id, '_mbe_updated_at', sprintf( '%.6F', microtime( true ) ) );
		update_term_meta( $term_id, '_mbe_updated_by', sanitize_key( $source ) );
	}

	private function touch_pair( Provider_Interface $a, int $a_id, Provider_Interface $b, int $b_id, string $source ): void {
		$stamp = sprintf( '%.6F', microtime( true ) );
		foreach ( array( $a_id, $b_id ) as $id ) {
			update_term_meta( $id, '_mbe_updated_at', $stamp );
			update_term_meta( $id, '_mbe_updated_by', sanitize_key( $source ) );
		}
	}

	private function deletion_key( int $term_id, string $taxonomy ): string {
		return $taxonomy . ':' . $term_id;
	}

	private function is_bridge_taxonomy( string $taxonomy ): bool {
		return in_array( $taxonomy, array( $this->etch->taxonomy(), $this->external->taxonomy() ), true );
	}

	private function provider_for_taxonomy( string $taxonomy ): Provider_Interface {
		return $taxonomy === $this->etch->taxonomy() ? $this->etch : $this->external;
	}

	private function other_provider( Provider_Interface $provider ): Provider_Interface {
		return $provider->id() === 'etch' ? $this->external : $this->etch;
	}
}
