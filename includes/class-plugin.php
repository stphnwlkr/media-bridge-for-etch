<?php
namespace UplinkPress\MediaBridgeForEtch;

use UplinkPress\MediaBridgeForEtch\Providers\Etch_Provider;
use UplinkPress\MediaBridgeForEtch\Providers\HappyFiles_Provider;
use UplinkPress\MediaBridgeForEtch\Providers\Wicked_Provider;

final class Plugin {
	private const SETTINGS_OPTION = 'uplink_mbe_settings';
	private const LOG_OPTION      = 'uplink_mbe_sync_log';

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
		self::migrate_options();
	}

	private function __construct() {
		self::migrate_options();
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
		$saved = get_option( self::SETTINGS_OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	private static function migrate_options(): void {
		$legacy_settings_option = 'mbe' . '_settings';
		$legacy_log_option      = 'mbe' . '_sync_log';

		if ( false === get_option( self::SETTINGS_OPTION, false ) ) {
			$legacy_settings = get_option( $legacy_settings_option, false );
			add_option( self::SETTINGS_OPTION, is_array( $legacy_settings ) ? $legacy_settings : self::defaults(), '', false );
		}

		if ( false === get_option( self::LOG_OPTION, false ) ) {
			$legacy_log = get_option( $legacy_log_option, false );
			if ( is_array( $legacy_log ) ) {
				add_option( self::LOG_OPTION, $legacy_log, '', false );
			}
		}
	}
}
