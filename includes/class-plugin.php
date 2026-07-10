<?php
namespace MediaBridgeForEtch;

use MediaBridgeForEtch\Providers\Etch_Provider;
use MediaBridgeForEtch\Providers\HappyFiles_Provider;
use MediaBridgeForEtch\Providers\Wicked_Provider;

final class Plugin {
	private static ?self $instance = null;
	private array $providers = array();
	private ?Sync_Engine $engine = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function activate(): void {
		if ( ! get_option( 'mbe_settings' ) ) {
			add_option( 'mbe_settings', self::defaults(), '', false );
		}
	}

	private function __construct() {
		$this->providers = array(
			'etch'       => new Etch_Provider(),
			'wicked'     => new Wicked_Provider(),
			'happyfiles' => new HappyFiles_Provider(),
		);
		add_action( 'init', array( $this, 'boot' ), 100 );
		if ( is_admin() ) {
			new Admin( $this );
		}
	}

	public function boot(): void {
		$settings = self::settings();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}
		$external = $this->providers[ $settings['provider'] ] ?? null;
		if ( ! $external || ! $this->providers['etch']->is_available() || ! $external->is_available() ) {
			return;
		}
		$this->engine = new Sync_Engine( $this->providers['etch'], $external );
		$this->engine->register_hooks();
	}

	public function providers(): array {
		return $this->providers;
	}

	public function engine(): ?Sync_Engine {
		if ( ! $this->engine ) {
			$settings = self::settings();
			$external = $this->providers[ $settings['provider'] ] ?? null;
			if ( $external && $this->providers['etch']->is_available() && $external->is_available() ) {
				$this->engine = new Sync_Engine( $this->providers['etch'], $external );
			}
		}
		return $this->engine;
	}

	public static function defaults(): array {
		return array(
			'enabled'          => 1,
			'provider'         => 'wicked',
			'conflict_mode'    => 'latest',
			'sync_deletions'   => 0,
			'deep_folder_mode' => 'nearest_parent',
		);
	}

	public static function settings(): array {
		$saved = get_option( 'mbe_settings', array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}
}
