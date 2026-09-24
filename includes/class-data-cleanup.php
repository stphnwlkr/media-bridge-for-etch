<?php
namespace UplinkPress\MediaBridgeForEtch;

/**
 * Remove only the database records owned by Media Bridge.
 */
final class Data_Cleanup {
	private const NETWORK_SETTINGS_OPTION = 'uplink_mbe_network_settings';

	/**
	 * Network cleanup defaults.
	 */
	public static function network_defaults(): array {
		return array(
			'delete_data_on_network_deactivation' => 0,
			'delete_data_on_network_uninstall'    => 0,
		);
	}

	/**
	 * Get network cleanup settings with defaults.
	 */
	public static function network_settings(): array {
		$saved = is_multisite() ? get_site_option( self::NETWORK_SETTINGS_OPTION, array() ) : array();
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::network_defaults() );
	}

	/**
	 * Run opt-in cleanup when the plugin is deactivated.
	 *
	 * @param bool $network_deactivating Whether the plugin is being network-deactivated.
	 */
	public static function deactivate( bool $network_deactivating = false ): void {
		if ( is_multisite() && $network_deactivating ) {
			$network_settings = self::network_settings();
			if ( ! empty( $network_settings['delete_data_on_network_deactivation'] ) ) {
				self::delete_all_sites();
			} else {
				self::delete_all_sites_if_enabled( 'delete_data_on_deactivation' );
			}
			return;
		}

		self::delete_current_site_if_enabled( 'delete_data_on_deactivation' );
	}

	/**
	 * Remove opted-in data across every site in a multisite network.
	 *
	 * @param string $setting_key Setting that authorizes cleanup.
	 */
	public static function delete_all_sites_if_enabled( string $setting_key ): void {
		if ( ! is_multisite() ) {
			self::delete_current_site_if_enabled( $setting_key );
			return;
		}

		$site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );
			self::delete_current_site_if_enabled( $setting_key );
			restore_current_blog();
		}
	}

	/**
	 * Unconditionally remove plugin-owned records across the network.
	 */
	public static function delete_all_sites(): void {
		if ( ! is_multisite() ) {
			self::delete_current_site_data();
			return;
		}

		$site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );
			self::delete_current_site_data();
			restore_current_blog();
		}

		delete_site_option( self::NETWORK_SETTINGS_OPTION );
	}

	/**
	 * Remove plugin-owned records for the current site when authorized.
	 *
	 * @param string $setting_key Setting that authorizes cleanup.
	 */
	public static function delete_current_site_if_enabled( string $setting_key ): void {
		$settings = get_option( 'uplink_mbe_settings', array() );
		if ( ! is_array( $settings ) || empty( $settings[ $setting_key ] ) ) {
			return;
		}

		self::delete_current_site_data();
	}

	/**
	 * Unconditionally remove plugin-owned records for the current site.
	 */
	public static function delete_current_site_data(): void {
		$options = array(
			'uplink_mbe_settings',
			'uplink_mbe_sync_log',
			'mbe_settings',
			'mbe_sync_log',
		);
		foreach ( $options as $option ) {
			delete_option( $option );
		}
		delete_transient( 'uplink_mbe_etch_exif_without_gps' );
		delete_transient( 'uplink_mbe_etch_exif_with_gps' );

		$term_meta_keys = array(
			'_mbe_map_etch',
			'_mbe_map_wicked',
			'_mbe_map_happyfiles',
			'_mbe_updated_at',
			'_mbe_updated_by',
		);
		foreach ( $term_meta_keys as $meta_key ) {
			delete_metadata( 'term', 0, $meta_key, '', true );
		}
	}
}
