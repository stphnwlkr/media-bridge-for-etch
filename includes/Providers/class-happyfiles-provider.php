<?php
namespace MediaBridgeForEtch\Providers;

use MediaBridgeForEtch\Taxonomy_Provider;

final class HappyFiles_Provider extends Taxonomy_Provider {
	public function id(): string { return 'happyfiles'; }
	public function label(): string { return 'HappyFiles'; }
	public function taxonomy(): string { return 'happyfiles_category'; }
	public function max_depth(): ?int { return null; }
	public function order_meta_key(): string { return 'happyfiles_position'; }
}
