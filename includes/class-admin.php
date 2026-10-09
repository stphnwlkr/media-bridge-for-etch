<?php
namespace UplinkPress\MediaBridgeForEtch;

final class Admin {
	private Plugin $plugin;
	private Loop_Generator $loop_generator;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
		$this->loop_generator = new Loop_Generator( $plugin->providers()['etch'] );
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_uplink_mbe_reconcile', array( $this, 'reconcile' ) );
		add_action( 'admin_post_uplink_mbe_clear_log', array( $this, 'clear_log' ) );
		add_action( 'admin_post_uplink_mbe_reset_settings', array( $this, 'reset_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	public function menu(): void {
		add_media_page(
			__( 'Uplink Media Bridge for Etch', 'media-bridge-for-etch' ),
			__( 'Uplink Media Bridge', 'media-bridge-for-etch' ),
			'manage_options',
			'media-bridge-for-etch',
			array( $this, 'render' )
		);
	}

	public function register_settings(): void {
		register_setting(
			'uplink_mbe_settings_group',
			'uplink_mbe_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => Plugin::defaults(),
			)
		);
	}

	public function sanitize_settings( mixed $input ): array {
		$input     = is_array( $input ) ? $input : array();
		$current   = Plugin::settings();
		$providers = array_keys( $this->plugin->providers() );
		$provider  = sanitize_key( $input['provider'] ?? $current['provider'] ?? 'native' );
		if ( 'native' !== $provider && ( 'etch' === $provider || ! in_array( $provider, $providers, true ) ) ) {
			$provider = 'native';
		}
		$mode = sanitize_key( $input['conflict_mode'] ?? $current['conflict_mode'] ?? 'latest' );
		if ( ! in_array( $mode, array( 'latest', 'etch', 'external' ), true ) ) {
			$mode = 'latest';
		}
		$parent_count_display = sanitize_key( $input['parent_count_display'] ?? $current['parent_count_display'] ?? 'direct' );
		if ( ! in_array( $parent_count_display, array( 'direct', 'cumulative', 'direct_total' ), true ) ) {
			$parent_count_display = 'direct';
		}
		$etch_collection_depth = min( 5, max( 2, absint( $input['etch_collection_depth'] ?? $current['etch_collection_depth'] ?? 2 ) ) );
		$manager_label = sanitize_text_field( $input['manager_label'] ?? $current['manager_label'] ?? '' );
		if ( '' === trim( $manager_label ) ) {
			$manager_label = 'Etch Collections';
		}
		$flag = static function ( string $key ) use ( $input, $current ): int {
			return array_key_exists( $key, $input ) ? ( empty( $input[ $key ] ) ? 0 : 1 ) : ( empty( $current[ $key ] ) ? 0 : 1 );
		};
		$default_media_screen = $flag( 'default_media_screen' );
		$manager_experience   = sanitize_key( $input['manager_experience'] ?? '' );
		if ( in_array( $manager_experience, array( 'wordpress', 'manager_default' ), true ) ) {
			$default_media_screen = 'manager_default' === $manager_experience ? 1 : 0;
		}
		$appearance_settings = Plugin::sanitize_appearance( $input, $current );
		return array_merge( array(
			'enabled'          => $flag( 'enabled' ),
			'provider'         => $provider,
			'conflict_mode'    => $mode,
			'sync_deletions'   => $flag( 'sync_deletions' ),
			'native_pagination' => $flag( 'native_pagination' ),
			'parent_count_display' => $parent_count_display,
			'etch_collection_depth' => $etch_collection_depth,
			'manager_label'    => $manager_label,
			'default_media_screen' => $default_media_screen,
			'native_media_collections' => $flag( 'native_media_collections' ),
			'etch_asset_manager_controls' => $flag( 'etch_asset_manager_controls' ),
			'collection_gallery' => $flag( 'collection_gallery' ),
			'exif_dynamic_data' => $flag( 'exif_dynamic_data' ),
			'exif_gps'         => $flag( 'exif_gps' ),
			'delete_data_on_deactivation' => $flag( 'delete_data_on_deactivation' ),
			'delete_data_on_uninstall' => $flag( 'delete_data_on_uninstall' ),
			'deep_folder_mode' => 'nearest_parent',
		), $appearance_settings );
	}

	public function assets( string $hook ): void {
		if ( 'media_page_media-bridge-for-etch' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'uplink-mbe-admin', UPLINK_MBE_URL . 'assets/admin.css', array(), UPLINK_MBE_ASSET_VERSION );
		wp_enqueue_script( 'uplink-mbe-settings', UPLINK_MBE_URL . 'assets/settings.js', array(), UPLINK_MBE_ASSET_VERSION, true );
		wp_localize_script(
			'uplink-mbe-settings',
			'uplinkMbeSettings',
			array(
				'unsaved'     => __( 'Unsaved changes', 'media-bridge-for-etch' ),
				'resetConfirm' => __( 'Reset all Media Bridge settings to their defaults?', 'media-bridge-for-etch' ),
			)
		);
	}

	public function reset_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to reset these settings.', 'media-bridge-for-etch' ) );
		}
		check_admin_referer( 'uplink_mbe_reset_settings' );
		Plugin::update_settings( Plugin::defaults() );
		wp_safe_redirect( add_query_arg( 'uplink_mbe_settings_reset', 1, $this->page_url() ) );
		exit;
	}

	public function reconcile(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage this bridge.', 'media-bridge-for-etch' ) );
		}
		check_admin_referer( 'uplink_mbe_reconcile' );
		$engine = $this->plugin->engine();
		if ( ! $engine ) {
			$redirect = add_query_arg( 'uplink_mbe_error', 'provider', $this->page_url() );
		} else {
			$stats    = $engine->reconcile();
			$redirect = add_query_arg( array( 'uplink_mbe_reconciled' => 1, 'created' => $stats['created'], 'mapped' => $stats['mapped'], 'attachments' => $stats['attachments'], 'errors' => $stats['errors'] ), $this->page_url() );
		}
		wp_safe_redirect( $redirect );
		exit;
	}

	public function clear_log(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage this bridge.', 'media-bridge-for-etch' ) );
		}
		check_admin_referer( 'uplink_mbe_clear_log' );
		Logger::clear();
		wp_safe_redirect( add_query_arg( 'uplink_mbe_log_cleared', 1, $this->page_url() ) );
		exit;
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings  = Plugin::settings();
		$providers = $this->plugin->providers();
		$native    = 'native' === $settings['provider'];
		$external  = $native ? null : ( $providers[ $settings['provider'] ] ?? $providers['wicked'] );
		$etch_ok   = $providers['etch']->is_available();
		$ext_ok    = $native || $external->is_available();
		$connected = $etch_ok && ( $native || ( $settings['enabled'] && $ext_ok ) );
		$logs      = Logger::all();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Selects display-only admin content.
		$active_tab = isset( $_GET['tab'] ) && is_string( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings';
		$gallery_enabled = ! empty( $settings['collection_gallery'] );
		$manager_experience = ! empty( $settings['default_media_screen'] ) ? 'manager_default' : 'wordpress';
		$network_cleanup = is_multisite() ? Data_Cleanup::network_settings() : Data_Cleanup::network_defaults();
		$allowed_tabs = array( 'settings', 'exif', 'optimization', 'gallery', 'loops' );
		if ( ! in_array( $active_tab, $allowed_tabs, true ) ) {
			$active_tab = 'settings';
		}
		?>
		<div class="wrap uplink-mbe-wrap">
			<h1 class="screen-reader-text"><?php esc_html_e( 'Uplink Media Bridge for Etch', 'media-bridge-for-etch' ); ?></h1>
			<header class="uplink-mbe-hero">
				<div class="uplink-mbe-hero-brand">
					<span class="uplink-mbe-hero-mark" aria-hidden="true"><img src="<?php echo esc_url( UPLINK_MBE_URL . 'assets/media-bridge-for-etch-icon.svg' ); ?>" alt="" width="88" height="88"></span>
					<div>
						<div class="uplink-mbe-hero-meta"><span class="uplink-mbe-hero-eyebrow"><?php esc_html_e( 'By Stephen Walker', 'media-bridge-for-etch' ); ?></span><span class="uplink-mbe-hero-version"><?php
							/* translators: %s is the installed plugin version. */
							printf( esc_html__( 'Version %s', 'media-bridge-for-etch' ), esc_html( UPLINK_MBE_VERSION ) );
						?></span></div>
						<div class="uplink-mbe-hero-title" aria-hidden="true"><?php esc_html_e( 'Uplink Media Bridge for Etch', 'media-bridge-for-etch' ); ?></div>
						<p><?php esc_html_e( 'Organize Etch media collections in WordPress or keep them synchronized with a supported folder plugin.', 'media-bridge-for-etch' ); ?></p>
					</div>
				</div>
			</header>

			<?php $this->notices(); ?>
			<nav class="nav-tab-wrapper uplink-mbe-tabs" aria-label="<?php esc_attr_e( 'Media Bridge sections', 'media-bridge-for-etch' ); ?>">
				<a class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tab', 'settings', $this->page_url() ) ); ?>" <?php echo 'settings' === $active_tab ? 'aria-current="page"' : ''; ?>><?php esc_html_e( 'Settings', 'media-bridge-for-etch' ); ?></a>
				<a class="nav-tab <?php echo 'exif' === $active_tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tab', 'exif', $this->page_url() ) ); ?>" <?php echo 'exif' === $active_tab ? 'aria-current="page"' : ''; ?>><?php esc_html_e( 'Etch EXIF', 'media-bridge-for-etch' ); ?></a>
				<a class="nav-tab <?php echo 'optimization' === $active_tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tab', 'optimization', $this->page_url() ) ); ?>" <?php echo 'optimization' === $active_tab ? 'aria-current="page"' : ''; ?>><?php esc_html_e( 'Optimization', 'media-bridge-for-etch' ); ?></a>
				<a class="nav-tab <?php echo 'gallery' === $active_tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tab', 'gallery', $this->page_url() ) ); ?>" <?php echo 'gallery' === $active_tab ? 'aria-current="page"' : ''; ?>><?php esc_html_e( 'Gallery & shortcode', 'media-bridge-for-etch' ); ?></a>
				<a class="nav-tab <?php echo 'loops' === $active_tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tab', 'loops', $this->page_url() ) ); ?>" <?php echo 'loops' === $active_tab ? 'aria-current="page"' : ''; ?>><?php esc_html_e( 'Etch loops', 'media-bridge-for-etch' ); ?></a>
			</nav>

			<?php if ( 'settings' === $active_tab ) : ?>
			<form action="options.php" method="post" id="uplink-mbe-main-settings" class="uplink-mbe-settings-form uplink-mbe-settings-form-main">
				<?php settings_fields( 'uplink_mbe_settings_group' ); ?>
			<div class="uplink-mbe-grid">
				<div class="uplink-mbe-settings-stack">
					<section class="uplink-mbe-card">
						<h2><?php esc_html_e( 'Collection source', 'media-bridge-for-etch' ); ?></h2>
						<p><?php esc_html_e( 'Choose where media collections are stored. Etch Collections works without another folder plugin. Choose a supported plugin only when you want Media Bridge to synchronize its folders with Etch.', 'media-bridge-for-etch' ); ?></p>
						<label for="uplink-mbe-provider"><?php esc_html_e( 'Source', 'media-bridge-for-etch' ); ?></label>
						<select id="uplink-mbe-provider" name="uplink_mbe_settings[provider]">
							<option value="native" <?php selected( $settings['provider'], 'native' ); ?>><?php esc_html_e( 'Etch Collections, built in', 'media-bridge-for-etch' ); ?></option>
							<?php foreach ( $providers as $id => $provider ) : if ( 'etch' === $id ) { continue; } ?>
								<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $settings['provider'], $id ); ?>><?php echo esc_html( $provider->label() . ( $provider->is_available() ? '' : ' (' . __( 'not detected', 'media-bridge-for-etch' ) . ')' ) ); ?></option>
							<?php endforeach; ?>
						</select>
						<?php if ( $native ) : ?>
							<div class="uplink-mbe-settings-subsection">
								<h3><?php esc_html_e( 'Etch Collection behavior', 'media-bridge-for-etch' ); ?></h3>
								<label for="uplink-mbe-parent-count-display"><?php esc_html_e( 'Parent collection counts', 'media-bridge-for-etch' ); ?></label>
								<select id="uplink-mbe-parent-count-display" name="uplink_mbe_settings[parent_count_display]">
									<option value="direct" <?php selected( $settings['parent_count_display'], 'direct' ); ?>><?php esc_html_e( 'Direct assignments only', 'media-bridge-for-etch' ); ?></option>
									<option value="cumulative" <?php selected( $settings['parent_count_display'], 'cumulative' ); ?>><?php esc_html_e( 'Including sub-collections', 'media-bridge-for-etch' ); ?></option>
									<option value="direct_total" <?php selected( $settings['parent_count_display'], 'direct_total' ); ?>><?php esc_html_e( 'Direct / including sub-collections', 'media-bridge-for-etch' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Controls the number shown beside parent Etch Collections. For example, 17/23 means 17 items are assigned directly and 23 appear when the parent and its sub-collections are viewed together.', 'media-bridge-for-etch' ); ?></p>
								<label for="uplink-mbe-etch-collection-depth"><?php esc_html_e( 'Maximum collection depth (Experimental)', 'media-bridge-for-etch' ); ?></label>
								<select id="uplink-mbe-etch-collection-depth" name="uplink_mbe_settings[etch_collection_depth]">
									<?php for ( $depth = 2; $depth <= 5; ++$depth ) : ?>
										<option value="<?php echo (int) $depth; ?>" <?php selected( (int) $settings['etch_collection_depth'], $depth ); ?>><?php echo (int) $depth; ?></option>
									<?php endfor; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Experimental. Media Bridge can create and manage deeper collections. Etch 1.6.8 can display them, but its Asset Manager cannot create or move collections beyond two levels.', 'media-bridge-for-etch' ); ?></p>
							</div>
						<?php endif; ?>
					</section>

					<?php if ( $native ) : ?>
						<section class="uplink-mbe-card">
							<div class="uplink-mbe-section-heading">
								<h2><?php esc_html_e( 'Media Library experience', 'media-bridge-for-etch' ); ?></h2>
							</div>
							<p><?php esc_html_e( 'Choose one media workflow. WordPress keeps its familiar screens, while the Enhanced Manager replaces them with the complete Media Bridge interface.', 'media-bridge-for-etch' ); ?></p>
							<fieldset class="uplink-mbe-choice-group">
								<legend><?php esc_html_e( 'Default experience', 'media-bridge-for-etch' ); ?></legend>
								<label class="uplink-mbe-choice"><input type="radio" name="uplink_mbe_settings[manager_experience]" value="wordpress" <?php checked( $manager_experience, 'wordpress' ); ?>><span><strong><?php esc_html_e( 'WordPress Media Library', 'media-bridge-for-etch' ); ?></strong><small><?php esc_html_e( 'Use the familiar WordPress media screens with optional Etch Collection tools.', 'media-bridge-for-etch' ); ?></small></span></label>
								<label class="uplink-mbe-choice"><input type="radio" name="uplink_mbe_settings[manager_experience]" value="manager_default" <?php checked( $manager_experience, 'manager_default' ); ?>><span><strong><?php esc_html_e( 'Enhanced Manager as the default', 'media-bridge-for-etch' ); ?></strong><small><?php esc_html_e( 'Replace the Media screen and WordPress media-selection dialogs with the manager and its inserter.', 'media-bridge-for-etch' ); ?></small></span></label>
							</fieldset>
							<div class="uplink-mbe-settings-subsection" data-uplink-mbe-wordpress-settings <?php echo 'wordpress' === $manager_experience ? '' : 'hidden'; ?>>
								<input type="hidden" name="uplink_mbe_settings[native_media_collections]" value="0">
								<label class="uplink-mbe-toggle"><input type="checkbox" name="uplink_mbe_settings[native_media_collections]" value="1" <?php checked( $settings['native_media_collections'] ); ?>><span><?php esc_html_e( 'Show Etch Collections in the WordPress Media Library', 'media-bridge-for-etch' ); ?></span></label>
								<p class="description"><?php esc_html_e( 'Adds the collection sidebar, filtering, upload destinations, attachment assignments, collection editing, and drag-and-drop while retaining native WordPress controls.', 'media-bridge-for-etch' ); ?></p>
							</div>
							<div data-uplink-mbe-manager-settings <?php echo 'manager_default' === $manager_experience ? '' : 'hidden'; ?>>
								<p><?php esc_html_e( 'The Enhanced Manager includes Media Health, richer filters, bulk organization, expanded metadata editing, alternate layouts, and customizable colors.', 'media-bridge-for-etch' ); ?></p>
								<label for="uplink-mbe-manager-label"><?php esc_html_e( 'Manager label', 'media-bridge-for-etch' ); ?></label>
								<input type="text" class="regular-text" id="uplink-mbe-manager-label" name="uplink_mbe_settings[manager_label]" value="<?php echo esc_attr( $settings['manager_label'] ); ?>" maxlength="80">
								<p class="description"><?php esc_html_e( 'Changes the manager menu label, page title, information panel, and media-selection tab. The default is Etch Collections.', 'media-bridge-for-etch' ); ?></p>
								<input type="hidden" name="uplink_mbe_settings[native_pagination]" value="0">
								<label class="uplink-mbe-toggle"><input type="checkbox" name="uplink_mbe_settings[native_pagination]" value="1" <?php checked( $settings['native_pagination'] ); ?>><span><?php esc_html_e( 'Use numbered pagination in the Enhanced Media Manager', 'media-bridge-for-etch' ); ?></span></label>
								<p class="description"><?php esc_html_e( 'Leave this off to load more media automatically while scrolling.', 'media-bridge-for-etch' ); ?></p>
								<p class="description"><?php esc_html_e( 'Use the palette button in the Enhanced Media Manager to preview and save its colors.', 'media-bridge-for-etch' ); ?></p>
							</div>
							<input type="hidden" name="uplink_mbe_settings[enabled]" value="1">
						</section>
					<?php else : ?>
						<section class="uplink-mbe-card">
							<h2><?php esc_html_e( 'Folder synchronization', 'media-bridge-for-etch' ); ?></h2>
							<p><?php esc_html_e( 'Keep Etch collection assignments synchronized with the selected folder plugin.', 'media-bridge-for-etch' ); ?></p>
							<input type="hidden" name="uplink_mbe_settings[enabled]" value="0">
							<label class="uplink-mbe-toggle"><input type="checkbox" name="uplink_mbe_settings[enabled]" value="1" <?php checked( $settings['enabled'] ); ?>><span><?php esc_html_e( 'Enable automatic synchronization', 'media-bridge-for-etch' ); ?></span></label>
							<label for="uplink-mbe-conflict"><?php esc_html_e( 'Conflict resolution', 'media-bridge-for-etch' ); ?></label>
							<select id="uplink-mbe-conflict" name="uplink_mbe_settings[conflict_mode]">
								<option value="latest" <?php selected( $settings['conflict_mode'], 'latest' ); ?>><?php esc_html_e( 'Most recent change wins', 'media-bridge-for-etch' ); ?></option>
								<option value="etch" <?php selected( $settings['conflict_mode'], 'etch' ); ?>><?php esc_html_e( 'Etch always wins', 'media-bridge-for-etch' ); ?></option>
								<option value="external" <?php selected( $settings['conflict_mode'], 'external' ); ?>><?php
									/* translators: %s is the name of the selected media folder provider. */
									printf( esc_html__( '%s always wins', 'media-bridge-for-etch' ), esc_html( $external->label() ) );
								?></option>
							</select>
							<p class="description"><?php esc_html_e( 'The default uses the latest folder or assignment action as the authoritative change.', 'media-bridge-for-etch' ); ?></p>
							<input type="hidden" name="uplink_mbe_settings[sync_deletions]" value="0">
							<label class="uplink-mbe-toggle"><input type="checkbox" name="uplink_mbe_settings[sync_deletions]" value="1" <?php checked( $settings['sync_deletions'] ); ?>><span><?php esc_html_e( 'Synchronize folder deletions', 'media-bridge-for-etch' ); ?></span></label>
							<p class="description uplink-mbe-warning"><?php esc_html_e( 'Disabled by default. When enabled, deleting a mapped folder deletes its counterpart.', 'media-bridge-for-etch' ); ?></p>
						</section>
					<?php endif; ?>

					<section class="uplink-mbe-card">
						<h2><?php esc_html_e( 'Etch Asset Manager', 'media-bridge-for-etch' ); ?></h2>
						<p><?php esc_html_e( 'Choose whether Media Bridge adds its layout and thumbnail controls to the Etch builder Asset Manager.', 'media-bridge-for-etch' ); ?></p>
						<input type="hidden" name="uplink_mbe_settings[etch_asset_manager_controls]" value="0">
						<label class="uplink-mbe-toggle"><input type="checkbox" name="uplink_mbe_settings[etch_asset_manager_controls]" value="1" <?php checked( $settings['etch_asset_manager_controls'] ); ?>><span><?php esc_html_e( 'Enable Uniform Grid with thumbnail resizing', 'media-bridge-for-etch' ); ?></span></label>
						<p class="description"><?php esc_html_e( 'Enabled by default. Thumbnail resizing applies only to Uniform Grid. Turn this off to use only Etch’s built-in Asset Manager layouts and controls.', 'media-bridge-for-etch' ); ?></p>
					</section>
				</div>

				<div class="uplink-mbe-sidebar">
					<section class="uplink-mbe-card uplink-mbe-status-card">
						<div>
							<span class="uplink-mbe-eyebrow"><?php echo $native ? esc_html__( 'Current setup', 'media-bridge-for-etch' ) : esc_html__( 'Bridge status', 'media-bridge-for-etch' ); ?></span>
							<h2><?php echo $connected ? ( $native ? esc_html__( 'Ready', 'media-bridge-for-etch' ) : esc_html__( 'Connected', 'media-bridge-for-etch' ) ) : esc_html__( 'Needs attention', 'media-bridge-for-etch' ); ?></h2>
						</div>
						<span class="uplink-mbe-status <?php echo $connected ? 'is-connected' : 'is-disconnected'; ?>" role="status" aria-live="polite"><?php echo $connected ? esc_html__( 'Active', 'media-bridge-for-etch' ) : esc_html__( 'Inactive', 'media-bridge-for-etch' ); ?></span>
						<dl class="uplink-mbe-stats">
							<div><dt><?php esc_html_e( 'Version', 'media-bridge-for-etch' ); ?></dt><dd><?php echo esc_html( UPLINK_MBE_VERSION ); ?></dd></div>
							<div><dt><?php esc_html_e( 'Etch', 'media-bridge-for-etch' ); ?></dt><dd><?php echo $etch_ok ? esc_html__( 'Available', 'media-bridge-for-etch' ) : esc_html__( 'Not detected', 'media-bridge-for-etch' ); ?></dd></div>
							<?php if ( $native ) : ?>
								<div><dt><?php esc_html_e( 'Collection source', 'media-bridge-for-etch' ); ?></dt><dd><?php esc_html_e( 'Etch Collections', 'media-bridge-for-etch' ); ?></dd></div>
								<div><dt><?php esc_html_e( 'WordPress library', 'media-bridge-for-etch' ); ?></dt><dd><?php echo ! empty( $settings['default_media_screen'] ) ? esc_html__( 'Replaced by manager', 'media-bridge-for-etch' ) : ( ! empty( $settings['native_media_collections'] ) ? esc_html__( 'Collections enabled', 'media-bridge-for-etch' ) : esc_html__( 'Native controls only', 'media-bridge-for-etch' ) ); ?></dd></div>
								<div><dt><?php esc_html_e( 'Enhanced manager', 'media-bridge-for-etch' ); ?></dt><dd><?php echo 'manager_default' === $manager_experience ? esc_html__( 'Default', 'media-bridge-for-etch' ) : esc_html__( 'Not active', 'media-bridge-for-etch' ); ?></dd></div>
								<div><dt><?php esc_html_e( 'Folder sync', 'media-bridge-for-etch' ); ?></dt><dd><?php esc_html_e( 'Not used', 'media-bridge-for-etch' ); ?></dd></div>
							<?php else : ?>
								<div><dt><?php echo esc_html( $external->label() ); ?></dt><dd><?php echo $ext_ok ? esc_html__( 'Available', 'media-bridge-for-etch' ) : esc_html__( 'Not detected', 'media-bridge-for-etch' ); ?></dd></div>
								<div><dt><?php esc_html_e( 'Conflict policy', 'media-bridge-for-etch' ); ?></dt><dd><?php echo esc_html( $this->conflict_label( $settings['conflict_mode'], $external->label() ) ); ?></dd></div>
							<?php endif; ?>
						</dl>
					</section>

					<section class="uplink-mbe-card uplink-mbe-cleanup-card" aria-labelledby="uplink-mbe-cleanup-title">
						<h2 id="uplink-mbe-cleanup-title"><?php esc_html_e( 'Data cleanup', 'media-bridge-for-etch' ); ?></h2>
						<?php if ( ! empty( $network_cleanup['delete_data_on_network_deactivation'] ) || ! empty( $network_cleanup['delete_data_on_network_uninstall'] ) ) : ?>
							<div class="uplink-mbe-uninstall-impact" role="note" aria-labelledby="uplink-mbe-network-override-title">
								<h3 id="uplink-mbe-network-override-title"><?php esc_html_e( 'Network cleanup override active', 'media-bridge-for-etch' ); ?></h3>
								<p><?php esc_html_e( 'A network administrator has enabled cleanup for one or more network-wide actions. Those actions may clean this site regardless of the local choices below. Site-level deactivation still follows this site’s setting.', 'media-bridge-for-etch' ); ?></p>
							</div>
						<?php endif; ?>
						<input type="hidden" name="uplink_mbe_settings[delete_data_on_deactivation]" value="0">
						<label class="uplink-mbe-toggle"><input type="checkbox" id="uplink-mbe-delete-data-on-deactivation" name="uplink_mbe_settings[delete_data_on_deactivation]" value="1" aria-describedby="uplink-mbe-delete-data-on-deactivation-description" <?php checked( $settings['delete_data_on_deactivation'] ); ?>><span><?php esc_html_e( 'Delete Media Bridge data when the plugin is deactivated', 'media-bridge-for-etch' ); ?></span></label>
						<p class="description uplink-mbe-warning" id="uplink-mbe-delete-data-on-deactivation-description"><?php esc_html_e( 'Disabled by default. When enabled, WordPress deactivation removes Media Bridge settings, synchronization history, and bridge mapping metadata. Collections, folder assignments, media records, and files are always preserved. Removing plugin files without first running the WordPress deactivation hook cannot trigger cleanup.', 'media-bridge-for-etch' ); ?></p>
						<input type="hidden" name="uplink_mbe_settings[delete_data_on_uninstall]" value="0">
						<label class="uplink-mbe-toggle"><input type="checkbox" id="uplink-mbe-delete-data-on-uninstall" name="uplink_mbe_settings[delete_data_on_uninstall]" value="1" aria-describedby="uplink-mbe-delete-data-on-uninstall-description" <?php checked( $settings['delete_data_on_uninstall'] ); ?>><span><?php esc_html_e( 'Delete Media Bridge data when the plugin is deleted', 'media-bridge-for-etch' ); ?></span></label>
						<p class="description uplink-mbe-warning" id="uplink-mbe-delete-data-on-uninstall-description"><?php esc_html_e( 'Disabled by default. When enabled, deleting Media Bridge through WordPress removes its settings, synchronization history, and bridge mapping metadata. Collections, folder assignments, media records, and files are always preserved.', 'media-bridge-for-etch' ); ?></p>
						<div class="uplink-mbe-uninstall-impact" role="note" aria-labelledby="uplink-mbe-uninstall-impact-title">
							<h3 id="uplink-mbe-uninstall-impact-title"><?php esc_html_e( 'Before deactivating or deleting Media Bridge', 'media-bridge-for-etch' ); ?></h3>
							<p><?php esc_html_e( 'Collection Gallery blocks, gallery shortcodes, and their Etch builder passthrough stop rendering while the plugin is inactive or deleted. Their saved block and shortcode content is preserved and will render again after Media Bridge is reactivated or reinstalled.', 'media-bridge-for-etch' ); ?></p>
						</div>
					</section>
				</div>
			</div>
			</form>
			<div class="uplink-mbe-floating-save" data-uplink-mbe-floating-save>
				<span class="uplink-mbe-save-state" data-uplink-mbe-save-state role="status" aria-live="polite"></span>
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="uplink-mbe-reset-form" data-uplink-mbe-reset-form>
					<input type="hidden" name="action" value="uplink_mbe_reset_settings">
					<?php wp_nonce_field( 'uplink_mbe_reset_settings' ); ?>
					<button type="submit" class="button button-secondary" data-uplink-mbe-reset><?php esc_html_e( 'Reset to defaults', 'media-bridge-for-etch' ); ?></button>
				</form>
				<button type="submit" form="uplink-mbe-main-settings" class="button button-primary button-large"><?php esc_html_e( 'Save settings', 'media-bridge-for-etch' ); ?></button>
			</div>

			<?php if ( ! $native ) : ?>
			<section class="uplink-mbe-card uplink-mbe-tools">
				<div><h2><?php esc_html_e( 'Reconcile the bridge', 'media-bridge-for-etch' ); ?></h2><p><?php esc_html_e( 'Match existing folders by hierarchical path, create missing counterparts, and synchronize attachment assignments. Client-side folders deeper than the configured Etch Collection limit remain intact and use their nearest supported ancestor in Etch.', 'media-bridge-for-etch' ); ?></p></div>
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
					<input type="hidden" name="action" value="uplink_mbe_reconcile">
					<?php wp_nonce_field( 'uplink_mbe_reconcile' ); ?>
					<?php submit_button( __( 'Run reconciliation', 'media-bridge-for-etch' ), 'secondary', 'submit', false, $connected ? array() : array( 'disabled' => 'disabled' ) ); ?>
				</form>
			</section>
			<?php endif; ?>

			<?php if ( ! $native ) : ?>
			<section class="uplink-mbe-card">
				<div class="uplink-mbe-log-header"><div><h2><?php esc_html_e( 'Sync history', 'media-bridge-for-etch' ); ?></h2><p><?php esc_html_e( 'The latest 200 bridge events are retained.', 'media-bridge-for-etch' ); ?></p></div>
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="uplink_mbe_clear_log"><?php wp_nonce_field( 'uplink_mbe_clear_log' ); ?><?php submit_button( __( 'Clear history', 'media-bridge-for-etch' ), 'link-delete', 'submit', false ); ?></form></div>
				<?php if ( ! $logs ) : ?><p><?php esc_html_e( 'No synchronization events have been recorded yet.', 'media-bridge-for-etch' ); ?></p><?php else : ?>
				<table class="widefat striped uplink-mbe-log">
					<caption class="screen-reader-text"><?php esc_html_e( 'Media Bridge synchronization history', 'media-bridge-for-etch' ); ?></caption>
					<thead><tr><th scope="col"><?php esc_html_e( 'Time', 'media-bridge-for-etch' ); ?></th><th scope="col"><?php esc_html_e( 'Event', 'media-bridge-for-etch' ); ?></th><th scope="col"><?php esc_html_e( 'Details', 'media-bridge-for-etch' ); ?></th></tr></thead><tbody>
				<?php foreach ( $logs as $entry ) : ?><tr><td><?php echo esc_html( get_date_from_gmt( $entry['time'], 'M j, Y g:i:s a' ) ); ?></td><td><span class="uplink-mbe-event"><?php echo esc_html( ucfirst( $entry['type'] ) ); ?></span></td><td><?php echo esc_html( $entry['message'] ); ?><?php if ( ! empty( $entry['context'] ) ) : ?><small><?php echo esc_html( implode( ' · ', array_map( fn( $k, $v ) => "$k: $v", array_keys( $entry['context'] ), $entry['context'] ) ) ); ?></small><?php endif; ?></td></tr><?php endforeach; ?>
				</tbody></table><?php endif; ?>
			</section>
			<?php endif; ?>
			<?php elseif ( 'loops' === $active_tab ) : ?>
				<?php $this->loop_generator->render(); ?>
			<?php elseif ( 'exif' === $active_tab ) : ?>
				<?php $this->render_exif_docs(); ?>
			<?php elseif ( 'optimization' === $active_tab ) : ?>
				<?php $this->render_optimization_docs(); ?>
			<?php else : ?>
				<?php $this->render_gallery_docs( $native && ! empty( $settings['default_media_screen'] ) ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_exif_docs(): void {
		$settings = Plugin::settings();
		$fields = array(
			array( 'attachment_id', __( 'WordPress media attachment ID.', 'media-bridge-for-etch' ) ),
			array( 'file_format', __( 'Uppercase file extension.', 'media-bridge-for-etch' ) ),
			array( 'file_size / file_size_bytes', __( 'Readable size and raw byte count.', 'media-bridge-for-etch' ) ),
			array( 'width / height / dimensions', __( 'Pixel dimensions.', 'media-bridge-for-etch' ) ),
			array( 'camera_model / lens / iso', __( 'Camera, lens, and ISO values when embedded.', 'media-bridge-for-etch' ) ),
			array( 'exposure / exposure_display', __( 'Exposure time as seconds and a readable shutter-speed value.', 'media-bridge-for-etch' ) ),
			array( 'aperture / aperture_display', __( 'Numeric aperture and a readable f-stop.', 'media-bridge-for-etch' ) ),
			array( 'focal_mm / focal_display', __( 'Numeric focal length and a readable millimeter value.', 'media-bridge-for-etch' ) ),
			array( 'date_taken / date_taken_iso', __( 'Capture date in readable and ISO 8601 formats.', 'media-bridge-for-etch' ) ),
			array( 'credit / copyright', __( 'Embedded creator and rights information when available.', 'media-bridge-for-etch' ) ),
			array( 'alt_text / caption', __( 'Current WordPress accessibility and caption fields.', 'media-bridge-for-etch' ) ),
			array( 'gps.latitude / gps.longitude / gps.altitude_ft', __( 'Location values when GPS exposure is enabled and the source file contains them.', 'media-bridge-for-etch' ) ),
		);
		?>
		<section class="uplink-mbe-card uplink-mbe-feature-settings" aria-labelledby="uplink-mbe-exif-settings-title">
			<div class="uplink-mbe-section-heading">
				<div><h2 id="uplink-mbe-exif-settings-title"><?php esc_html_e( 'Etch image metadata', 'media-bridge-for-etch' ); ?></h2><p><?php esc_html_e( 'Choose which attachment metadata Etch can use as dynamic data.', 'media-bridge-for-etch' ); ?></p></div>
			</div>
			<form action="options.php" method="post" class="uplink-mbe-settings-form">
				<?php settings_fields( 'uplink_mbe_settings_group' ); ?>
				<input type="hidden" name="uplink_mbe_settings[exif_dynamic_data]" value="0">
				<label class="uplink-mbe-toggle"><input type="checkbox" name="uplink_mbe_settings[exif_dynamic_data]" value="1" <?php checked( $settings['exif_dynamic_data'] ); ?>><span><?php esc_html_e( 'Expose EXIF on Etch image objects', 'media-bridge-for-etch' ); ?></span></label>
				<p class="description"><?php esc_html_e( 'Adds an EXIF object to contextual Etch image data and a global attachment-ID lookup under options.upm.img.', 'media-bridge-for-etch' ); ?></p>
				<input type="hidden" name="uplink_mbe_settings[exif_gps]" value="0">
				<label class="uplink-mbe-toggle"><input type="checkbox" name="uplink_mbe_settings[exif_gps]" value="1" <?php checked( $settings['exif_gps'] ); ?>><span><?php esc_html_e( 'Include embedded GPS coordinates in Etch data and the attachment editor', 'media-bridge-for-etch' ); ?></span></label>
				<p class="description uplink-mbe-warning"><?php esc_html_e( 'Disabled by default. Location metadata can reveal where a photograph was taken.', 'media-bridge-for-etch' ); ?></p>
				<?php submit_button( __( 'Save EXIF settings', 'media-bridge-for-etch' ) ); ?>
			</form>
		</section>
		<section class="uplink-mbe-card uplink-mbe-docs">
			<h2><?php esc_html_e( 'EXIF dynamic data in Etch', 'media-bridge-for-etch' ); ?></h2>
			<p><?php esc_html_e( 'Media Bridge adds normalized image metadata to', 'media-bridge-for-etch' ); ?> <a href="<?php echo esc_url( 'https://etchwp.com/?aff=77d60d8c' ); ?>" target="_blank" rel="sponsored noopener noreferrer">Etch</a>. <?php esc_html_e( 'Missing fields resolve to null. WordPress may omit metadata that was removed before upload; Media Bridge reads the retained original image when WordPress generated a scaled derivative.', 'media-bridge-for-etch' ); ?></p>
			<div class="uplink-mbe-docs-callout">
				<h3><?php esc_html_e( 'Context-free attachment lookup', 'media-bridge-for-etch' ); ?></h3>
				<p><?php esc_html_e( 'Use the WordPress attachment ID when an image is selected directly on a page and no template or loop object is available.', 'media-bridge-for-etch' ); ?></p>
				<p><code>{options.upm.img[123].exif.camera_model}</code></p>
				<p><code>{options.upm.img[123].exif.aperture_display}</code></p>
				<p><code>{options.upm.img[123].exif.gps.latitude}</code></p>
				<p><?php esc_html_e( 'Replace 123 with the attachment ID shown in the WordPress media URL or attachment details.', 'media-bridge-for-etch' ); ?></p>
			</div>
			<h3><?php esc_html_e( 'Contextual image objects', 'media-bridge-for-etch' ); ?></h3>
			<p><?php esc_html_e( 'When an Etch post field or loop item already returns an image object, keep that object’s prefix and append exif plus the field name. In a component, this applies only to an Object prop receiving that enriched image object; a basic scalar prop does not gain EXIF fields.', 'media-bridge-for-etch' ); ?></p>
			<p><code>{this.acf.photo.exif.camera_model}</code></p>
			<p><code>{item.acf.photo.exif.focal_display}</code></p>
			<p><code>{props.photo.exif.date_taken}</code> — <?php esc_html_e( 'Object prop', 'media-bridge-for-etch' ); ?></p>
			<h3><?php esc_html_e( 'Available EXIF fields', 'media-bridge-for-etch' ); ?></h3>
			<div class="uplink-mbe-docs-table-wrap">
				<table class="widefat striped uplink-mbe-docs-table">
					<thead><tr><th scope="col"><?php esc_html_e( 'Field', 'media-bridge-for-etch' ); ?></th><th scope="col"><?php esc_html_e( 'Value', 'media-bridge-for-etch' ); ?></th></tr></thead>
					<tbody><?php foreach ( $fields as $field ) : ?><tr><th scope="row"><code><?php echo esc_html( $field[0] ); ?></code></th><td><?php echo esc_html( $field[1] ); ?></td></tr><?php endforeach; ?></tbody>
				</table>
			</div>
			<div class="uplink-mbe-docs-callout">
				<h3><?php esc_html_e( 'GPS privacy', 'media-bridge-for-etch' ); ?></h3>
				<p><?php esc_html_e( 'GPS fields are null unless the GPS setting above is enabled. Turn it on only when publishing the image location is intentional.', 'media-bridge-for-etch' ); ?></p>
			</div>
		</section>
		<?php
	}

	private function render_optimization_docs(): void {
		?>
		<section class="uplink-mbe-card uplink-mbe-docs">
			<h2><?php esc_html_e( 'Image optimization metadata', 'media-bridge-for-etch' ); ?></h2>
			<p><?php esc_html_e( 'Media Bridge reports optimization information stored with each attachment. It does not optimize images or change the optimizer’s saved settings.', 'media-bridge-for-etch' ); ?></p>
			<div class="uplink-mbe-docs-callout">
				<h3><a href="<?php echo esc_url( 'https://r.freemius.com/21431/2169296/' ); ?>" target="_blank" rel="sponsored noopener noreferrer">Cimo</a></h3>
				<p><?php esc_html_e( 'When Cimo records optimization data, Media Bridge can show the full-size result and, for bulk optimization, the combined result for the main image and generated image sizes.', 'media-bridge-for-etch' ); ?></p>
				<p><?php esc_html_e( 'When Cimo is active, the Upload screen includes a Keep originals switch. It is off by default and applies to files chosen while enabled, allowing the original file and its embedded EXIF data to reach WordPress.', 'media-bridge-for-etch' ); ?></p>
				<p><?php esc_html_e( 'Cimo documents AVIF output support and an output-format selector. Whether those options are available depends on the installed Cimo version and its settings.', 'media-bridge-for-etch' ); ?> <a href="<?php echo esc_url( 'https://docs.wpcimo.com/article/781-avif-support' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Read Cimo’s AVIF documentation', 'media-bridge-for-etch' ); ?></a>.</p>
			</div>
			<div class="uplink-mbe-docs-callout">
				<h3><a href="<?php echo esc_url( 'https://etchwp.com/?aff=77d60d8c' ); ?>" target="_blank" rel="sponsored noopener noreferrer">Etch</a></h3>
				<p><?php esc_html_e( 'Media Bridge can report optimization events performed through Etch when they occur while Media Bridge is active. Older optimization activity cannot be identified reliably when the attachment contains no retained optimization record.', 'media-bridge-for-etch' ); ?></p>
			</div>
			<p class="description"><?php esc_html_e( 'The Cimo and Etch links are affiliate links. Stephen Walker may receive a commission from a qualifying purchase at no additional cost to you.', 'media-bridge-for-etch' ); ?></p>
		</section>
		<?php
	}

	private function render_gallery_docs( bool $show_generator = false ): void {
		$settings        = Plugin::settings();
		$gallery_enabled = ! empty( $settings['collection_gallery'] );
		$basic_attributes = array(
			array( 'collection', __( 'Required', 'media-bridge-for-etch' ), __( 'Collection ID or slug used as the gallery source.', 'media-bridge-for-etch' ) ),
			array( 'include_children', 'false', __( 'Include images assigned to child collections.', 'media-bridge-for-etch' ) ),
			array( 'layout', 'grid', __( 'grid, tiled, circles, square, or columns.', 'media-bridge-for-etch' ) ),
			array( 'columns', '3', __( 'Number of gallery columns from 1 through 8.', 'media-bridge-for-etch' ) ),
			array( 'size', 'large', __( 'Registered WordPress image size used for thumbnails.', 'media-bridge-for-etch' ) ),
			array( 'crop', 'true', __( 'Crop thumbnails to the selected aspect ratio.', 'media-bridge-for-etch' ) ),
			array( 'aspect_ratio', '1/1', __( 'auto, 1/1, 4/3, 3/2, 16/9, or 3/4.', 'media-bridge-for-etch' ) ),
			array( 'random', 'false', __( 'Randomize the displayed image order.', 'media-bridge-for-etch' ) ),
			array( 'show_title', 'false', __( 'Show attachment titles on gallery thumbnails.', 'media-bridge-for-etch' ) ),
			array( 'captions', 'false', __( 'Show attachment captions on gallery thumbnails.', 'media-bridge-for-etch' ) ),
			array( 'lightbox', 'custom', __( 'custom, native, or none. Images are never linked directly.', 'media-bridge-for-etch' ) ),
			array( 'limit', '24', __( 'Maximum number of images to display, up to 100.', 'media-bridge-for-etch' ) ),
			array( 'gap', '8', __( 'Space between thumbnails in pixels.', 'media-bridge-for-etch' ) ),
		);
		$lightbox_attributes = array(
			array( 'lightbox_title', 'true', __( 'Show the attachment title in the custom lightbox.', 'media-bridge-for-etch' ) ),
			array( 'lightbox_caption', 'true', __( 'Show the attachment caption in the custom lightbox.', 'media-bridge-for-etch' ) ),
			array( 'lightbox_thumbnails', 'true', __( 'Show the lightbox thumbnail strip.', 'media-bridge-for-etch' ) ),
			array( 'lightbox_thumbnail_position', 'horizontal', __( 'horizontal or vertical.', 'media-bridge-for-etch' ) ),
			array( 'lightbox_info_position', 'bottom', __( 'Place the title and caption above or below the image.', 'media-bridge-for-etch' ) ),
			array( 'lightbox_fullscreen', 'true', __( 'Show the fullscreen control.', 'media-bridge-for-etch' ) ),
			array( 'lightbox_zoom', 'true', __( 'Show the zoom controls.', 'media-bridge-for-etch' ) ),
			array( 'lightbox_size', 'full', __( 'Registered WordPress image size used in the lightbox.', 'media-bridge-for-etch' ) ),
			array( 'lightbox_background', '#111315', __( 'Lightbox backdrop color.', 'media-bridge-for-etch' ) ),
			array( 'lightbox_panel', '#1d2125', __( 'Title and caption panel color.', 'media-bridge-for-etch' ) ),
			array( 'lightbox_title_color', '#ffffff', __( 'Lightbox title color.', 'media-bridge-for-etch' ) ),
			array( 'lightbox_caption_color', '#d9dde1', __( 'Lightbox caption color.', 'media-bridge-for-etch' ) ),
			array( 'lightbox_font', 'inherit', __( 'inherit, system, serif, or mono.', 'media-bridge-for-etch' ) ),
			array( 'lightbox_title_size', '20', __( 'Lightbox title size in pixels.', 'media-bridge-for-etch' ) ),
			array( 'lightbox_caption_size', '15', __( 'Lightbox caption size in pixels.', 'media-bridge-for-etch' ) ),
			array( 'lightbox_title_weight', '700', __( 'Title weight from 300 through 900.', 'media-bridge-for-etch' ) ),
			array( 'lightbox_caption_weight', '400', __( 'Caption weight from 300 through 900.', 'media-bridge-for-etch' ) ),
		);
		?>
		<section class="uplink-mbe-card uplink-mbe-feature-settings" aria-labelledby="uplink-mbe-gallery-settings-title">
			<div class="uplink-mbe-section-heading">
				<div><h2 id="uplink-mbe-gallery-settings-title"><?php esc_html_e( 'Collection Gallery', 'media-bridge-for-etch' ); ?></h2><p><?php esc_html_e( 'Turn on the gallery block, shortcode, visual generator, and frontend gallery assets.', 'media-bridge-for-etch' ); ?></p></div>
				<span class="uplink-mbe-badge is-optional"><?php esc_html_e( 'Optional', 'media-bridge-for-etch' ); ?></span>
			</div>
			<form action="options.php" method="post" class="uplink-mbe-settings-form">
				<?php settings_fields( 'uplink_mbe_settings_group' ); ?>
				<input type="hidden" name="uplink_mbe_settings[collection_gallery]" value="0">
				<label class="uplink-mbe-toggle"><input type="checkbox" name="uplink_mbe_settings[collection_gallery]" value="1" <?php checked( $gallery_enabled ); ?>><span><?php esc_html_e( 'Enable Collection Gallery', 'media-bridge-for-etch' ); ?></span></label>
				<?php submit_button( __( 'Save gallery setting', 'media-bridge-for-etch' ) ); ?>
			</form>
		</section>
		<section class="uplink-mbe-card uplink-mbe-docs">
			<h2><?php esc_html_e( 'Collection Gallery', 'media-bridge-for-etch' ); ?></h2>
			<p><?php esc_html_e( 'Build a gallery from a collection folder. New images added to the collection are reflected automatically.', 'media-bridge-for-etch' ); ?></p>
			<?php if ( $show_generator && $gallery_enabled ) : ?>
				<div class="uplink-mbe-docs-generator">
					<div><h3><?php esc_html_e( 'Shortcode generator', 'media-bridge-for-etch' ); ?></h3><p><?php esc_html_e( 'Build and preview a gallery in the media manager, then copy its ready-to-use shortcode.', 'media-bridge-for-etch' ); ?></p></div>
					<a class="button button-primary" href="<?php echo esc_url( add_query_arg( 'mbe_gallery_generator', '1', admin_url( 'upload.php?page=etch-collections' ) ) ); ?>"><?php esc_html_e( 'Open shortcode generator', 'media-bridge-for-etch' ); ?></a>
				</div>
			<?php endif; ?>
			<h3><?php esc_html_e( 'Block editor', 'media-bridge-for-etch' ); ?></h3>
			<p><?php esc_html_e( 'Add the Collection Gallery block, choose a collection, and use the block sidebar to control the grid, titles, captions, and accessible custom lightbox.', 'media-bridge-for-etch' ); ?></p>
			<h3><?php esc_html_e( 'Shortcode', 'media-bridge-for-etch' ); ?></h3>
			<p><?php esc_html_e( 'Use the shortcode when a block is unavailable or when the gallery must be inserted by a template or page builder.', 'media-bridge-for-etch' ); ?></p>
			<div class="uplink-mbe-docs-callout">
				<h3><?php esc_html_e( 'Quick start', 'media-bridge-for-etch' ); ?></h3>
				<p><code>[etch_collection_gallery collection="your-collection-slug"]</code></p>
				<p><?php esc_html_e( 'Replace the example slug with a collection slug or numeric collection ID.', 'media-bridge-for-etch' ); ?></p>
			</div>
			<h3><?php esc_html_e( 'Gallery attributes', 'media-bridge-for-etch' ); ?></h3>
			<?php $this->shortcode_table( $basic_attributes ); ?>
			<h3><?php esc_html_e( 'Custom lightbox attributes', 'media-bridge-for-etch' ); ?></h3>
			<p><?php esc_html_e( 'These attributes apply when lightbox="custom".', 'media-bridge-for-etch' ); ?></p>
			<?php $this->shortcode_table( $lightbox_attributes ); ?>
			<div class="uplink-mbe-docs-callout">
				<h3><?php esc_html_e( 'Complete example', 'media-bridge-for-etch' ); ?></h3>
				<code>[etch_collection_gallery collection="portfolio" layout="tiled" columns="4" crop="true" lightbox="custom" lightbox_thumbnails="true" lightbox_thumbnail_position="horizontal" lightbox_info_position="bottom"]</code>
			</div>
		</section>
		<?php
	}

	private function shortcode_table( array $rows ): void {
		?>
		<div class="uplink-mbe-docs-table-wrap">
			<table class="widefat striped uplink-mbe-docs-table">
				<thead><tr><th scope="col"><?php esc_html_e( 'Attribute', 'media-bridge-for-etch' ); ?></th><th scope="col"><?php esc_html_e( 'Default', 'media-bridge-for-etch' ); ?></th><th scope="col"><?php esc_html_e( 'Description', 'media-bridge-for-etch' ); ?></th></tr></thead>
				<tbody><?php foreach ( $rows as $row ) : ?><tr><th scope="row"><code><?php echo esc_html( $row[0] ); ?></code></th><td><code><?php echo esc_html( $row[1] ); ?></code></td><td><?php echo esc_html( $row[2] ); ?></td></tr><?php endforeach; ?></tbody>
			</table>
		</div>
		<?php
	}

	private function notices(): void {
		if ( filter_input( INPUT_GET, 'settings-updated', FILTER_VALIDATE_BOOLEAN ) ) {
			$messages = get_settings_errors();
			if ( ! $messages ) $messages = array( array( 'type' => 'success', 'message' => __( 'Media Bridge settings saved.', 'media-bridge-for-etch' ) ) );
			foreach ( $messages as $message ) {
				$type = 'error' === $message['type'] ? 'error' : 'success';
				printf( '<div hidden data-uplink-mbe-toast="%s">%s</div>', esc_attr( $type ), esc_html( wp_strip_all_tags( $message['message'] ) ) );
			}
		}
		if ( filter_input( INPUT_GET, 'uplink_mbe_reconciled', FILTER_VALIDATE_BOOLEAN ) ) {
			$created     = absint( filter_input( INPUT_GET, 'created', FILTER_SANITIZE_NUMBER_INT ) );
			$mapped      = absint( filter_input( INPUT_GET, 'mapped', FILTER_SANITIZE_NUMBER_INT ) );
			$attachments = absint( filter_input( INPUT_GET, 'attachments', FILTER_SANITIZE_NUMBER_INT ) );
			$errors      = absint( filter_input( INPUT_GET, 'errors', FILTER_SANITIZE_NUMBER_INT ) );

			/* translators: 1: folders created, 2: folders mapped, 3: attachments processed, 4: errors. */
			$message = sprintf( __( 'Reconciliation complete: %1$d created, %2$d mapped, %3$d attachments processed, %4$d errors.', 'media-bridge-for-etch' ), $created, $mapped, $attachments, $errors );
			printf( '<div hidden data-uplink-mbe-toast="success"><p>%s</p></div>', esc_html( $message ) );
		}
		if ( filter_input( INPUT_GET, 'uplink_mbe_error', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ) {
			echo '<div hidden data-uplink-mbe-toast="error"><p>' . esc_html__( 'The selected providers are not both available.', 'media-bridge-for-etch' ) . '</p></div>';
		}
		if ( filter_input( INPUT_GET, 'uplink_mbe_log_cleared', FILTER_VALIDATE_BOOLEAN ) ) {
			echo '<div hidden data-uplink-mbe-toast="success"><p>' . esc_html__( 'Synchronization history cleared.', 'media-bridge-for-etch' ) . '</p></div>';
		}
		if ( filter_input( INPUT_GET, 'uplink_mbe_settings_reset', FILTER_VALIDATE_BOOLEAN ) ) {
			echo '<div hidden data-uplink-mbe-toast="success"><p>' . esc_html__( 'Media Bridge settings were reset to their defaults.', 'media-bridge-for-etch' ) . '</p></div>';
		}
	}

	private function conflict_label( string $mode, string $external ): string {
		return match ( $mode ) {
			'etch'     => __( 'Etch always wins', 'media-bridge-for-etch' ),
			'external' => sprintf(
				/* translators: %s is the name of the selected media folder provider. */
				__( '%s always wins', 'media-bridge-for-etch' ),
				$external
			),
			default    => __( 'Most recent change wins', 'media-bridge-for-etch' ),
		};
	}

	private function page_url(): string {
		return admin_url( 'upload.php?page=media-bridge-for-etch' );
	}

	private function accessible_color( mixed $value, string $against, string $fallback, float $minimum_ratio = 4.5 ): string {
		$color      = sanitize_hex_color( (string) $value );
		$background = sanitize_hex_color( $against );
		if ( ! $color || ! $background || $this->contrast_ratio( $color, $background ) < $minimum_ratio ) {
			return $fallback;
		}
		return $color;
	}

	private function contrast_ratio( string $first, string $second ): float {
		$first_luminance  = $this->relative_luminance( $first );
		$second_luminance = $this->relative_luminance( $second );
		$light            = max( $first_luminance, $second_luminance );
		$dark             = min( $first_luminance, $second_luminance );
		return ( $light + 0.05 ) / ( $dark + 0.05 );
	}

	private function relative_luminance( string $color ): float {
		$channels = array(
			hexdec( substr( $color, 1, 2 ) ) / 255,
			hexdec( substr( $color, 3, 2 ) ) / 255,
			hexdec( substr( $color, 5, 2 ) ) / 255,
		);
		$channels = array_map(
			static fn( float $channel ): float => $channel <= 0.04045 ? $channel / 12.92 : ( ( $channel + 0.055 ) / 1.055 ) ** 2.4,
			$channels
		);
		return ( 0.2126 * $channels[0] ) + ( 0.7152 * $channels[1] ) + ( 0.0722 * $channels[2] );
	}
}
