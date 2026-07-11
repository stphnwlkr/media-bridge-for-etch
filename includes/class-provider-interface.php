<?php
namespace UplinkPress\MediaBridgeForEtch;

use WP_Error;
use WP_Term;

interface Provider_Interface {
	public function id(): string;
	public function label(): string;
	public function taxonomy(): string;
	public function is_available(): bool;
	public function max_depth(): ?int;
	public function order_meta_key(): string;
	public function get_term( int $term_id ): ?WP_Term;
	public function get_terms(): array|WP_Error;
	public function get_object_terms( int $attachment_id ): array|WP_Error;
	public function create_term( array $args ): array|WP_Error;
	public function update_term( int $term_id, array $args ): array|WP_Error;
	public function delete_term( int $term_id ): bool|int|WP_Error;
	public function set_object_terms( int $attachment_id, array $term_ids ): array|WP_Error;
	public function get_position( int $term_id ): int;
	public function set_position( int $term_id, int $position ): void;
}
