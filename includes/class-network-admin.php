<?php
namespace UplinkPress\MediaBridgeForEtch;

/**
 * Network Admin controls for multisite cleanup overrides.
 */
final class Network_Admin {
	private const PAGE_SLUG = 'media-bridge-for-etch-network';
	private string $hook_suffix = '';

	public function __construct() {
		add_action( 'network_admin_menu', array( $this, 'menu' ) );
		add_action( 'network_admin_edit_uplink_mbe_save_network_settings', array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	public function menu(): void {
		$this->hook_suffix = (string) add_submenu_page(
			'settings.php',
			__( 'Media Bridge network settings', 'media-bridge-for-etch' ),
			__( 'Media Bridge', 'media-bridge-for-etch' ),
			'manage_network_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	public function assets( string $hook ): void {
		if ( $this->hook_suffix !== $hook ) {
			return;
		}

		wp_enqueue_style( 'uplink-mbe-admin', UPLINK_MBE_URL . 'assets/admin.css', array(), UPLINK_MBE_ASSET_VERSION );
	}

	public static function sanitize( mixed $input ): array {
		$input = is_array( $input ) ? $input : array();
		return array(
			'delete_data_on_network_deactivation' => empty( $input['delete_data_on_network_deactivation'] ) ? 0 : 1,
			'delete_data_on_network_uninstall'    => empty( $input['delete_data_on_network_uninstall'] ) ? 0 : 1,
		);
	}

	public function save(): void {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage network cleanup settings.', 'media-bridge-for-etch' ) );
		}

		check_admin_referer( 'uplink_mbe_save_network_settings' );
		$input = isset( $_POST['uplink_mbe_network_settings'] ) && is_array( $_POST['uplink_mbe_network_settings'] )
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The allow-listed fields are converted to booleans by sanitize().
			? wp_unslash( $_POST['uplink_mbe_network_settings'] )
			: array();
		update_site_option( 'uplink_mbe_network_settings', self::sanitize( $input ) );

		wp_safe_redirect(
			add_query_arg(
				'updated',
				'true',
				network_admin_url( 'settings.php?page=' . self::PAGE_SLUG )
			)
		);
		exit;
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return;
		}

		$settings = Data_Cleanup::network_settings();
		?>
		<div class="wrap uplink-mbe-wrap uplink-mbe-network-wrap">
			<h1><?php esc_html_e( 'Media Bridge network settings', 'media-bridge-for-etch' ); ?></h1>
			<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only success notice. ?>
				<div hidden data-uplink-mbe-toast="success"><p><?php esc_html_e( 'Network cleanup settings saved.', 'media-bridge-for-etch' ); ?></p></div>
			<?php endif; ?>
			<section class="uplink-mbe-card" aria-labelledby="uplink-mbe-network-cleanup-title">
				<h2 id="uplink-mbe-network-cleanup-title"><?php esc_html_e( 'Network-wide cleanup overrides', 'media-bridge-for-etch' ); ?></h2>
				<p><?php esc_html_e( 'These off-by-default settings override individual site choices only for network-wide plugin actions. Site-level deactivation continues to use that site’s local setting.', 'media-bridge-for-etch' ); ?></p>
				<form action="<?php echo esc_url( network_admin_url( 'edit.php?action=uplink_mbe_save_network_settings' ) ); ?>" method="post">
					<?php wp_nonce_field( 'uplink_mbe_save_network_settings' ); ?>
					<input type="hidden" name="uplink_mbe_network_settings[delete_data_on_network_deactivation]" value="0">
					<label class="uplink-mbe-toggle"><input type="checkbox" id="uplink-mbe-delete-network-data-on-deactivation" name="uplink_mbe_network_settings[delete_data_on_network_deactivation]" value="1" aria-describedby="uplink-mbe-delete-network-data-on-deactivation-description" <?php checked( $settings['delete_data_on_network_deactivation'] ); ?>><span><?php esc_html_e( 'Delete Media Bridge data across all sites on network deactivation', 'media-bridge-for-etch' ); ?></span></label>
					<p class="description uplink-mbe-warning" id="uplink-mbe-delete-network-data-on-deactivation-description"><?php esc_html_e( 'Overrides every site’s local deactivation choice when the plugin is network-deactivated.', 'media-bridge-for-etch' ); ?></p>
					<input type="hidden" name="uplink_mbe_network_settings[delete_data_on_network_uninstall]" value="0">
					<label class="uplink-mbe-toggle"><input type="checkbox" id="uplink-mbe-delete-network-data-on-uninstall" name="uplink_mbe_network_settings[delete_data_on_network_uninstall]" value="1" aria-describedby="uplink-mbe-delete-network-data-on-uninstall-description" <?php checked( $settings['delete_data_on_network_uninstall'] ); ?>><span><?php esc_html_e( 'Delete Media Bridge data across all sites when the plugin is deleted', 'media-bridge-for-etch' ); ?></span></label>
					<p class="description uplink-mbe-warning" id="uplink-mbe-delete-network-data-on-uninstall-description"><?php esc_html_e( 'Overrides every site’s local uninstall choice when the plugin is deleted from the network.', 'media-bridge-for-etch' ); ?></p>
					<div class="uplink-mbe-uninstall-impact" role="note" aria-labelledby="uplink-mbe-network-cleanup-impact-title">
						<h3 id="uplink-mbe-network-cleanup-impact-title"><?php esc_html_e( 'What network cleanup preserves', 'media-bridge-for-etch' ); ?></h3>
						<p><?php esc_html_e( 'Collections, folder assignments, attachments, media records, and files remain intact on every site. Only Media Bridge settings, synchronization history, legacy options, and bridge mapping metadata are removed.', 'media-bridge-for-etch' ); ?></p>
					</div>
					<?php submit_button( __( 'Save network settings', 'media-bridge-for-etch' ) ); ?>
				</form>
			</section>
		</div>
		<?php
	}
}
