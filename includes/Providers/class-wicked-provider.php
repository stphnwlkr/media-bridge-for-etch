<?php
namespace UplinkPress\MediaBridgeForEtch\Providers;

use UplinkPress\MediaBridgeForEtch\Taxonomy_Provider;

final class Wicked_Provider extends Taxonomy_Provider {
	public function id(): string { return 'wicked'; }
	public function label(): string { return 'Wicked Folders'; }
	public function taxonomy(): string { return 'wf_attachment_folders'; }
	public function max_depth(): ?int { return null; }
	public function order_meta_key(): string { return 'wf_order'; }
}
