<?php
namespace UplinkPress\MediaBridgeForEtch\Providers;

use UplinkPress\MediaBridgeForEtch\Taxonomy_Provider;

final class Etch_Provider extends Taxonomy_Provider {
	public function id(): string { return 'etch'; }
	public function label(): string { return 'Etch Collections'; }
	public function taxonomy(): string { return 'etch_collection'; }
	public function max_depth(): ?int { return 2; }
	public function order_meta_key(): string { return 'position'; }
}
