<?php
namespace MediaBridgeForEtch;

final class Admin {
	private Plugin $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_mbe_reconcile', array( $this, 'reconcile' ) );
		add_action( 'admin_post_mbe_clear_log', array( $this, 'clear_log' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	public function menu(): void {
		add_media_page(
			__( 'Media Bridge for Etch', 'media-bridge-for-etch' ),
			__( 'Media Bridge', 'media-bridge-for-etch' ),
			'manage_options',
			'media-bridge-for-etch',
			array( $this, 'render' )
		);
	}

	public function register_settings(): void {
		register_setting(
			'mbe_settings_group',
			'mbe_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => Plugin::defaults(),
			)
		);
	}

	public function sanitize_settings( mixed $input ): array {
		$input     = is_array( $input ) ? $input : array();
		$providers = array_keys( $this->plugin->providers() );
		$provider  = sanitize_key( $input['provider'] ?? 'wicked' );
		if ( 'etch' === $provider || ! in_array( $provider, $providers, true ) ) {
			$provider = 'wicked';
		}
		$mode = sanitize_key( $input['conflict_mode'] ?? 'latest' );
		if ( ! in_array( $mode, array( 'latest', 'etch', 'external' ), true ) ) {
			$mode = 'latest';
		}
		return array(
			'enabled'          => empty( $input['enabled'] ) ? 0 : 1,
			'provider'         => $provider,
			'conflict_mode'    => $mode,
			'sync_deletions'   => empty( $input['sync_deletions'] ) ? 0 : 1,
			'deep_folder_mode' => 'nearest_parent',
		);
	}

	public function assets( string $hook ): void {
		if ( 'media_page_media-bridge-for-etch' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'mbe-admin', MBE_URL . 'assets/admin.css', array(), MBE_VERSION );
	}

	public function reconcile(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage this bridge.', 'media-bridge-for-etch' ) );
		}
		check_admin_referer( 'mbe_reconcile' );
		$engine = $this->plugin->engine();
		if ( ! $engine ) {
			$redirect = add_query_arg( 'mbe_error', 'provider', $this->page_url() );
		} else {
			$stats    = $engine->reconcile();
			$redirect = add_query_arg( array( 'mbe_reconciled' => 1, 'created' => $stats['created'], 'mapped' => $stats['mapped'], 'attachments' => $stats['attachments'], 'errors' => $stats['errors'] ), $this->page_url() );
		}
		wp_safe_redirect( $redirect );
		exit;
	}

	public function clear_log(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage this bridge.', 'media-bridge-for-etch' ) );
		}
		check_admin_referer( 'mbe_clear_log' );
		Logger::clear();
		wp_safe_redirect( add_query_arg( 'mbe_log_cleared', 1, $this->page_url() ) );
		exit;
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings  = Plugin::settings();
		$providers = $this->plugin->providers();
		$external  = $providers[ $settings['provider'] ] ?? $providers['wicked'];
		$etch_ok   = $providers['etch']->is_available();
		$ext_ok    = $external->is_available();
		$connected = $settings['enabled'] && $etch_ok && $ext_ok;
		$logs      = Logger::all();
		?>
		<div class="wrap mbe-wrap">
			<h1><?php esc_html_e( 'Media Bridge for Etch', 'media-bridge-for-etch' ); ?></h1>
			<p class="mbe-intro"><?php esc_html_e( 'Keep Etch Collections synchronized with a client-facing WordPress media folder provider.', 'media-bridge-for-etch' ); ?></p>

			<?php $this->notices(); ?>

			<div class="mbe-grid">
				<section class="mbe-card mbe-status-card">
					<div>
						<span class="mbe-eyebrow"><?php esc_html_e( 'Bridge status', 'media-bridge-for-etch' ); ?></span>
						<h2><?php echo $connected ? esc_html__( 'Connected', 'media-bridge-for-etch' ) : esc_html__( 'Needs attention', 'media-bridge-for-etch' ); ?></h2>
					</div>
					<span class="mbe-status <?php echo $connected ? 'is-connected' : 'is-disconnected'; ?>" role="status" aria-live="polite"><?php echo $connected ? esc_html__( 'Active', 'media-bridge-for-etch' ) : esc_html__( 'Inactive', 'media-bridge-for-etch' ); ?></span>
					<dl class="mbe-stats">
						<div><dt><?php esc_html_e( 'Etch', 'media-bridge-for-etch' ); ?></dt><dd><?php echo $etch_ok ? esc_html__( 'Available', 'media-bridge-for-etch' ) : esc_html__( 'Not detected', 'media-bridge-for-etch' ); ?></dd></div>
						<div><dt><?php echo esc_html( $external->label() ); ?></dt><dd><?php echo $ext_ok ? esc_html__( 'Available', 'media-bridge-for-etch' ) : esc_html__( 'Not detected', 'media-bridge-for-etch' ); ?></dd></div>
						<div><dt><?php esc_html_e( 'Conflict policy', 'media-bridge-for-etch' ); ?></dt><dd><?php echo esc_html( $this->conflict_label( $settings['conflict_mode'], $external->label() ) ); ?></dd></div>
					</dl>
				</section>

				<section class="mbe-card">
					<h2><?php esc_html_e( 'Bridge settings', 'media-bridge-for-etch' ); ?></h2>
					<form action="options.php" method="post">
						<?php settings_fields( 'mbe_settings_group' ); ?>
						<label class="mbe-toggle"><input type="checkbox" name="mbe_settings[enabled]" value="1" <?php checked( $settings['enabled'] ); ?>><span><?php esc_html_e( 'Enable automatic synchronization', 'media-bridge-for-etch' ); ?></span></label>

						<label for="mbe-provider"><?php esc_html_e( 'Client-side provider', 'media-bridge-for-etch' ); ?></label>
						<select id="mbe-provider" name="mbe_settings[provider]">
							<?php foreach ( $providers as $id => $provider ) : if ( 'etch' === $id ) { continue; } ?>
								<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $settings['provider'], $id ); ?>><?php echo esc_html( $provider->label() . ( $provider->is_available() ? '' : ' — not detected' ) ); ?></option>
							<?php endforeach; ?>
						</select>

						<label for="mbe-conflict"><?php esc_html_e( 'Conflict resolution', 'media-bridge-for-etch' ); ?></label>
						<select id="mbe-conflict" name="mbe_settings[conflict_mode]">
							<option value="latest" <?php selected( $settings['conflict_mode'], 'latest' ); ?>><?php esc_html_e( 'Most recent change wins', 'media-bridge-for-etch' ); ?></option>
							<option value="etch" <?php selected( $settings['conflict_mode'], 'etch' ); ?>><?php esc_html_e( 'Etch always wins', 'media-bridge-for-etch' ); ?></option>
							<option value="external" <?php selected( $settings['conflict_mode'], 'external' ); ?>><?php
								/* translators: %s is the name of the selected media folder provider. */
								printf( esc_html__( '%s always wins', 'media-bridge-for-etch' ), esc_html( $external->label() ) );
							?></option>
						</select>
						<p class="description"><?php esc_html_e( 'The default uses the latest folder or assignment action as the authoritative change.', 'media-bridge-for-etch' ); ?></p>

						<label class="mbe-toggle"><input type="checkbox" name="mbe_settings[sync_deletions]" value="1" <?php checked( $settings['sync_deletions'] ); ?>><span><?php esc_html_e( 'Synchronize folder deletions', 'media-bridge-for-etch' ); ?></span></label>
						<p class="description mbe-warning"><?php esc_html_e( 'Disabled by default. When enabled, deleting a mapped folder deletes its counterpart.', 'media-bridge-for-etch' ); ?></p>
						<?php submit_button(); ?>
					</form>
				</section>
			</div>

			<section class="mbe-card mbe-tools">
				<div><h2><?php esc_html_e( 'Reconcile the bridge', 'media-bridge-for-etch' ); ?></h2><p><?php esc_html_e( 'Match existing folders by hierarchical path, create missing counterparts, and synchronize attachment assignments. Client-side folders deeper than two levels remain intact and use their nearest two-level ancestor in Etch.', 'media-bridge-for-etch' ); ?></p></div>
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
					<input type="hidden" name="action" value="mbe_reconcile">
					<?php wp_nonce_field( 'mbe_reconcile' ); ?>
					<?php submit_button( __( 'Run reconciliation', 'media-bridge-for-etch' ), 'secondary', 'submit', false, $connected ? array() : array( 'disabled' => 'disabled' ) ); ?>
				</form>
			</section>

			<section class="mbe-card">
				<div class="mbe-log-header"><div><h2><?php esc_html_e( 'Sync history', 'media-bridge-for-etch' ); ?></h2><p><?php esc_html_e( 'The latest 200 bridge events are retained.', 'media-bridge-for-etch' ); ?></p></div>
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="mbe_clear_log"><?php wp_nonce_field( 'mbe_clear_log' ); ?><?php submit_button( __( 'Clear history', 'media-bridge-for-etch' ), 'link-delete', 'submit', false ); ?></form></div>
				<?php if ( ! $logs ) : ?><p><?php esc_html_e( 'No synchronization events have been recorded yet.', 'media-bridge-for-etch' ); ?></p><?php else : ?>
				<table class="widefat striped mbe-log">
					<caption class="screen-reader-text"><?php esc_html_e( 'Media Bridge synchronization history', 'media-bridge-for-etch' ); ?></caption>
					<thead><tr><th scope="col"><?php esc_html_e( 'Time', 'media-bridge-for-etch' ); ?></th><th scope="col"><?php esc_html_e( 'Event', 'media-bridge-for-etch' ); ?></th><th scope="col"><?php esc_html_e( 'Details', 'media-bridge-for-etch' ); ?></th></tr></thead><tbody>
				<?php foreach ( $logs as $entry ) : ?><tr><td><?php echo esc_html( get_date_from_gmt( $entry['time'], 'M j, Y g:i:s a' ) ); ?></td><td><span class="mbe-event"><?php echo esc_html( ucfirst( $entry['type'] ) ); ?></span></td><td><?php echo esc_html( $entry['message'] ); ?><?php if ( ! empty( $entry['context'] ) ) : ?><small><?php echo esc_html( implode( ' · ', array_map( fn( $k, $v ) => "$k: $v", array_keys( $entry['context'] ), $entry['context'] ) ) ); ?></small><?php endif; ?></td></tr><?php endforeach; ?>
				</tbody></table><?php endif; ?>
			</section>
		</div>
		<?php
	}

	private function notices(): void {
		if ( isset( $_GET['mbe_reconciled'] ) ) {
			$created     = absint( wp_unslash( $_GET['created'] ?? 0 ) );
			$mapped      = absint( wp_unslash( $_GET['mapped'] ?? 0 ) );
			$attachments = absint( wp_unslash( $_GET['attachments'] ?? 0 ) );
			$errors      = absint( wp_unslash( $_GET['errors'] ?? 0 ) );

			/* translators: 1: folders created, 2: folders mapped, 3: attachments processed, 4: errors. */
			$message = sprintf( __( 'Reconciliation complete: %1$d created, %2$d mapped, %3$d attachments processed, %4$d errors.', 'media-bridge-for-etch' ), $created, $mapped, $attachments, $errors );
			printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $message ) );
		}
		if ( isset( $_GET['mbe_error'] ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'The selected providers are not both available.', 'media-bridge-for-etch' ) . '</p></div>';
		}
		if ( isset( $_GET['mbe_log_cleared'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Synchronization history cleared.', 'media-bridge-for-etch' ) . '</p></div>';
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
}
