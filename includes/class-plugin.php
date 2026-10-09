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

	public static function bootstrap(): void {
		add_action( 'admin_enqueue_scripts', array( self::class, 'toast_assets' ), 1 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'toast_assets' ), 1 );
		if ( ! self::etch_is_active() ) {
			if ( is_admin() ) {
				add_action( 'admin_notices', array( self::class, 'dependency_notice' ) );
			}
			return;
		}
		self::instance();
	}

	public static function toast_assets(): void {
		// Frontend controls only load in the authenticated Etch builder.
		if ( ! is_admin() && ( ! current_user_can( 'upload_files' ) || 'magic' !== ( $_GET['etch'] ?? '' ) ) ) return; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		wp_enqueue_style( 'uplink-mbe-toasts', UPLINK_MBE_URL . 'assets/toasts.css', array(), UPLINK_MBE_ASSET_VERSION );
		wp_enqueue_script( 'uplink-mbe-toasts', UPLINK_MBE_URL . 'assets/toasts.js', array(), UPLINK_MBE_ASSET_VERSION, false );
		wp_localize_script( 'uplink-mbe-toasts', 'uplinkMbeToastStrings', array( 'dismiss' => __( 'Dismiss notification', 'media-bridge-for-etch' ) ) );
	}

	public static function activate(): void {
		if ( ! self::etch_is_active() ) {
			wp_die(
				esc_html__( 'Uplink Media Bridge for Etch requires the Etch plugin to be installed and active.', 'media-bridge-for-etch' ),
				esc_html__( 'Etch is required', 'media-bridge-for-etch' ),
				array( 'back_link' => true )
			);
		}
		self::migrate_options();
	}

	public static function dependency_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		?>
		<div hidden data-uplink-mbe-toast="error"><p><?php esc_html_e( 'Uplink Media Bridge for Etch is inactive because the required Etch plugin is not active.', 'media-bridge-for-etch' ); ?></p></div>
		<?php
	}

	private static function etch_is_active(): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( 'etch/etch.php' );
	}

	private function __construct() {
		self::migrate_options();
		$this->providers = array(
			'etch'       => new Etch_Provider(),
			'wicked'     => new Wicked_Provider(),
			'happyfiles' => new HappyFiles_Provider(),
		);
		if ( ! empty( self::settings()['collection_gallery'] ) ) {
			new Collection_Gallery( $this->providers['etch'] );
		}
		if ( ! empty( self::settings()['exif_dynamic_data'] ) ) {
			new Etch_Dynamic_Data();
		}
		new File_Replacement();
		new Etch_Asset_Manager();
		new Native_Media_Library( $this->providers['etch'], is_admin() );
		add_action( 'init', array( $this, 'boot' ), 100 );
		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'command_palette_assets' ), 20 );
			new Admin( $this );
			if ( is_multisite() ) {
				new Network_Admin();
			}
		}
	}

	/**
	 * Add searchable shortcuts for the two plugin screens to the wp-admin
	 * Command Palette. The command names intentionally match the commands
	 * WordPress generates from the Media submenu, enriching those entries
	 * instead of displaying duplicates.
	 */
	public function command_palette_assets(): void {
		$settings = self::settings();
		$commands = array();
		$menu_routes = array();

		if ( 'native' === $settings['provider'] && ! empty( $settings['default_media_screen'] ) && current_user_can( 'upload_files' ) ) {
			$manager_label = trim( (string) ( $settings['manager_label'] ?? '' ) );
			if ( '' === $manager_label ) {
				$manager_label = __( 'Etch Collections', 'media-bridge-for-etch' );
			}
			$commands[] = array(
				'name'        => 'upload.php-etch-collections',
				/* translators: %s is the administrator-defined media manager label. */
				'label'       => sprintf( __( 'Go to: Media > %s', 'media-bridge-for-etch' ), $manager_label ),
				'searchLabel' => sprintf( '%s Etch Etch Collections media manager', $manager_label ),
				'keywords'    => array( 'Etch', 'Etch Collections', 'media', 'manager', $manager_label ),
				'url'         => admin_url( 'upload.php?page=etch-collections' ),
			);

			$menu_routes = array(
				'managerUrl' => admin_url( 'upload.php?page=etch-collections' ),
				'uploadUrl'  => admin_url( 'upload.php?page=etch-collections&uplink_mbe_action=upload' ),
			);
			$commands[] = array(
				'name'        => 'upload.php',
				'label'       => __( 'Go to: Media', 'media-bridge-for-etch' ),
				'searchLabel' => __( 'Media', 'media-bridge-for-etch' ),
				'keywords'    => array( 'media' ),
				'url'         => admin_url( 'upload.php?page=etch-collections' ),
			);
		}

		if ( current_user_can( 'manage_options' ) ) {
			$commands[] = array(
				'name'        => 'upload.php-media-bridge-for-etch',
				'label'       => __( 'Go to: Media > Media Bridge', 'media-bridge-for-etch' ),
				'searchLabel' => __( 'Uplink Media Bridge for Etch settings', 'media-bridge-for-etch' ),
				'keywords'    => array( 'Etch', 'Media Bridge', 'settings', 'synchronization' ),
				'url'         => admin_url( 'upload.php?page=media-bridge-for-etch' ),
			);
		}

		if ( ! $commands ) {
			return;
		}

		wp_enqueue_script(
			'uplink-mbe-command-palette',
			UPLINK_MBE_URL . 'assets/command-palette.js',
			array( 'wp-core-commands', 'wp-data', 'wp-dom-ready' ),
			UPLINK_MBE_ASSET_VERSION,
			true
		);
		wp_localize_script(
			'uplink-mbe-command-palette',
			'uplinkMbeCommandPalette',
			array(
				'commands'   => $commands,
				'menuRoutes' => $menu_routes,
			)
		);
	}

	public function boot(): void {
		$settings = self::settings();
		if ( 'native' === $settings['provider'] || empty( $settings['enabled'] ) ) {
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
			if ( 'native' === $settings['provider'] ) {
				return null;
			}
			$external = $this->providers[ $settings['provider'] ] ?? null;
			if ( $external && $this->providers['etch']->is_available() && $external->is_available() ) {
				$this->engine = new Sync_Engine( $this->providers['etch'], $external );
			}
		}
		return $this->engine;
	}

	public static function defaults(): array {
		$defaults = array(
			'enabled'          => 1,
			'provider'         => 'native',
			'conflict_mode'    => 'latest',
			'sync_deletions'   => 0,
			'native_pagination' => 0,
			'parent_count_display' => 'direct',
			'etch_collection_depth' => 2,
			'appearance'       => 'auto',
			'manager_label'    => 'Etch Collections',
			'default_media_screen' => 0,
			'native_media_collections' => 1,
			'etch_asset_manager_controls' => 1,
			'file_replacement' => 0,
			'collection_gallery' => 1,
			'exif_dynamic_data' => 1,
			'exif_gps'         => 0,
			'delete_data_on_deactivation' => 0,
			'delete_data_on_uninstall' => 0,
			'deep_folder_mode' => 'nearest_parent',
		);
		foreach ( self::appearance_defaults() as $mode => $colors ) {
			foreach ( $colors as $name => $value ) {
				$defaults[ $mode . '_' . $name ] = $value;
			}
		}
		return $defaults;
	}

	/**
	 * Return the configured Etch Collection depth, including the top level.
	 */
	public static function etch_collection_depth(): int {
		$settings = self::settings();
		return min( 5, max( 2, absint( $settings['etch_collection_depth'] ?? 2 ) ) );
	}

	/**
	 * Return every editable color used by the media manager and media modal.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function appearance_defaults(): array {
		return array(
			'light' => array(
				'background'       => '#f6f7f7',
				'surface'          => '#ffffff',
				'card'             => '#ffffff',
				'surface_muted'    => '#f6f7f7',
				'text'             => '#1d2327',
				'muted'            => '#646970',
				'border'           => '#dcdcde',
				'soft_border'      => '#edf0f2',
				'control'          => '#ffffff',
				'control_border'   => '#c3c4c7',
				'preview'          => '#f0f0f1',
				'preview_text'     => '#646970',
				'badge'            => '#f0f0f1',
				'badge_text'       => '#646970',
				'accent'           => '#2271b1',
				'accent_text'      => '#0a4b78',
				'accent_icon'      => '#ffffff',
				'accent_soft'      => '#eaf3fb',
				'accent_hover'     => '#135e96',
				'alt'              => '#e8f7ee',
				'alt_text'         => '#176b3a',
				'guide'            => '#f6fafe',
				'guide_border'     => '#c7d7e6',
				'guide_text'       => '#3c434a',
				'drop_bg'          => '#d7f0df',
				'drop_border'      => '#007a1f',
				'drop_text'        => '#1d2327',
				'danger'           => '#b32d2e',
				'danger_solid'     => '#b32d2e',
				'danger_icon'      => '#ffffff',
				'scrollbar_track'  => '#f0f0f1',
				'scrollbar_thumb'  => '#787c82',
				'scrollbar_hover'  => '#50575e',
			),
			'dark' => array(
				'background'       => '#171a1d',
				'surface'          => '#23272b',
				'card'             => '#23272b',
				'surface_muted'    => '#1b1f23',
				'text'             => '#f0f0f1',
				'muted'            => '#b2b8bf',
				'border'           => '#444c56',
				'soft_border'      => '#383f47',
				'control'          => '#2c3338',
				'control_border'   => '#59636e',
				'preview'          => '#111417',
				'preview_text'     => '#b2b8bf',
				'badge'            => '#343a40',
				'badge_text'       => '#b2b8bf',
				'accent'           => '#72aee6',
				'accent_text'      => '#b9dcfa',
				'accent_icon'      => '#1d2327',
				'accent_soft'      => '#20384c',
				'accent_hover'     => '#b9dcfa',
				'alt'              => '#173c28',
				'alt_text'         => '#b7f7ca',
				'guide'            => '#202a33',
				'guide_border'     => '#40566a',
				'guide_text'       => '#d7dde3',
				'drop_bg'          => '#173c28',
				'drop_border'      => '#62c77d',
				'drop_text'        => '#dff7e6',
				'danger'           => '#ff8085',
				'danger_solid'     => '#b32d2e',
				'danger_icon'      => '#ffffff',
				'scrollbar_track'  => '#171a1d',
				'scrollbar_thumb'  => '#6f7a86',
				'scrollbar_hover'  => '#8c98a4',
			),
		);
	}

	/**
	 * Sanitize the appearance controls and preserve accessible foreground pairs.
	 */
	public static function sanitize_appearance( array $input, ?array $base = null ): array {
		$base       = wp_parse_args( $base ?? self::settings(), self::defaults() );
		$appearance = sanitize_key( $input['appearance'] ?? $base['appearance'] );
		if ( ! in_array( $appearance, array( 'auto', 'light', 'dark' ), true ) ) {
			$appearance = 'auto';
		}
		$result = array( 'appearance' => $appearance );
		foreach ( self::appearance_defaults() as $mode => $defaults ) {
			foreach ( $defaults as $name => $fallback ) {
				$key = $mode . '_' . $name;
				$result[ $key ] = sanitize_hex_color( (string) ( $input[ $key ] ?? $base[ $key ] ?? $fallback ) ) ?: $fallback;
			}
			$against = array(
				'text'            => array( 'background', 'surface', 'card', 'surface_muted', 'control' ),
				'muted'           => array( 'background', 'surface', 'card', 'surface_muted', 'control' ),
				'preview_text'    => array( 'preview', 'surface_muted' ),
				'badge_text'      => array( 'badge' ),
				'accent'          => array( 'background', 'surface', 'card', 'control' ),
				'accent_text'     => array( 'background', 'surface', 'card', 'control', 'accent_soft' ),
				'accent_icon'     => array( 'accent', 'accent_hover' ),
				'danger_icon'     => array( 'danger_solid' ),
				'accent_hover'    => array( 'background', 'surface', 'card', 'control' ),
				'alt_text'        => array( 'alt' ),
				'guide_text'      => array( 'guide' ),
				'drop_text'       => array( 'drop_bg' ),
				'danger'          => array( 'background', 'surface', 'card', 'control' ),
				'scrollbar_thumb' => array( 'scrollbar_track' ),
				'scrollbar_hover' => array( 'scrollbar_track' ),
			);
			foreach ( $against as $foreground => $backgrounds ) {
				$minimum = in_array( $foreground, array( 'scrollbar_thumb', 'scrollbar_hover' ), true ) ? 3.0 : 4.5;
				foreach ( $backgrounds as $background ) {
					if ( self::contrast_ratio( $result[ $mode . '_' . $foreground ], $result[ $mode . '_' . $background ] ) < $minimum ) {
						$result[ $mode . '_' . $foreground ] = $defaults[ $foreground ];
						break;
					}
				}
			}
		}
		return $result;
	}

	/**
	 * Find the smallest black or white mix that gives text sufficient contrast.
	 */
	private static function accessible_background( string $background, string $foreground, float $minimum ): string {
		$source = array_map( 'hexdec', str_split( ltrim( $background, '#' ), 2 ) );
		$best = '';
		$best_step = 257;

		foreach ( array( 0, 255 ) as $target ) {
			for ( $step = 1; $step <= 256; $step++ ) {
				$mix = $step / 256;
				$channels = array_map(
					static fn( int $channel ): int => (int) round( $channel + ( $target - $channel ) * $mix ),
					$source
				);
				$candidate = sprintf( '#%02x%02x%02x', $channels[0], $channels[1], $channels[2] );
				if ( self::contrast_ratio( $foreground, $candidate ) >= $minimum ) {
					if ( $step < $best_step ) {
						$best = $candidate;
						$best_step = $step;
					}
					break;
				}
			}
		}

		return $best ?: ( self::contrast_ratio( $foreground, '#000000' ) >= $minimum ? '#000000' : '#ffffff' );
	}

	public static function update_settings( array $settings ): bool {
		return update_option( self::SETTINGS_OPTION, wp_parse_args( $settings, self::defaults() ), false );
	}

	private static function contrast_ratio( string $first, string $second ): float {
		$values = array();
		foreach ( array( $first, $second ) as $color ) {
			$channels = array_map( static fn( string $hex ): float => hexdec( $hex ) / 255, str_split( ltrim( $color, '#' ), 2 ) );
			$channels = array_map( static fn( float $channel ): float => $channel <= 0.04045 ? $channel / 12.92 : ( ( $channel + 0.055 ) / 1.055 ) ** 2.4, $channels );
			$values[] = ( 0.2126 * $channels[0] ) + ( 0.7152 * $channels[1] ) + ( 0.0722 * $channels[2] );
		}
		return ( max( $values ) + 0.05 ) / ( min( $values ) + 0.05 );
	}

	public static function settings(): array {
		$saved = get_option( self::SETTINGS_OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		foreach ( array( 'light', 'dark' ) as $mode ) {
			if ( ! isset( $saved[ $mode . '_card' ] ) && isset( $saved[ $mode . '_surface' ] ) ) {
				$saved[ $mode . '_card' ] = $saved[ $mode . '_surface' ];
			}
		}
		return wp_parse_args( $saved, self::defaults() );
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
