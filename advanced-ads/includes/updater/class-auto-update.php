<?php
/**
 * Auto-update preference of Advanced Ads and its add-ons — reads and writes the list WordPress itself uses for the "Enable auto-updates" links on the Plugins screen.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   x.x.x
 */

namespace AdvancedAds\Updater;

use AdvancedAds\Utilities\Addons;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Auto-update preference.
 */
class Auto_Update {

	/**
	 * State of a plugin that WordPress updates automatically.
	 */
	public const STATE_ON = 'on';

	/**
	 * State of a plugin that is only updated manually.
	 */
	public const STATE_OFF = 'off';

	/**
	 * Key of the main plugin in REST payloads.
	 */
	public const MAIN_ADDON_KEY = 'main';

	/**
	 * Get the auto-update state of the main plugin or an add-on.
	 *
	 * @param string|null $addon_id Short add-on id, null for the main plugin.
	 *
	 * @return string
	 */
	public static function get_state( ?string $addon_id = null ): string {
		return in_array( self::plugin_file( $addon_id ), (array) get_site_option( 'auto_update_plugins', [] ), true )
			? self::STATE_ON
			: self::STATE_OFF;
	}

	/**
	 * Set the auto-update state of the main plugin or an add-on.
	 *
	 * @param string|null $addon_id Short add-on id, null for the main plugin.
	 * @param string      $state    on|off.
	 *
	 * @return true|WP_Error
	 */
	public static function set_state( ?string $addon_id, string $state ) {
		if ( self::STATE_ON !== $state && self::STATE_OFF !== $state ) {
			return new WP_Error( 'invalid_state', __( 'Invalid auto-update state.', 'advanced-ads' ) );
		}

		$plugin_file = self::plugin_file( $addon_id );
		if ( null === $plugin_file ) {
			return new WP_Error( 'invalid_addon', __( 'Unknown add-on.', 'advanced-ads' ) );
		}

		$auto_updates = array_diff( (array) get_site_option( 'auto_update_plugins', [] ), [ $plugin_file ] );
		if ( self::STATE_ON === $state ) {
			$auto_updates[] = $plugin_file;
		}

		update_site_option( 'auto_update_plugins', array_values( $auto_updates ) );

		return true;
	}

	/**
	 * Get the auto-update states of the main plugin and all known add-ons.
	 *
	 * @return array<string, string>
	 */
	public static function get_all_states(): array {
		$states = [ self::MAIN_ADDON_KEY => self::get_state() ];

		foreach ( Addons::known_addon_ids() as $addon_id ) {
			$states[ $addon_id ] = self::get_state( $addon_id );
		}

		return $states;
	}

	/**
	 * Get the plugin file WordPress knows the main plugin or an add-on by.
	 *
	 * @param string|null $addon_id Short add-on id, null for the main plugin.
	 *
	 * @return string|null Null for an unknown add-on.
	 */
	private static function plugin_file( ?string $addon_id ): ?string {
		$addon_id = sanitize_key( (string) $addon_id );

		if ( '' === $addon_id || self::MAIN_ADDON_KEY === $addon_id ) {
			return ADVADS_PLUGIN_BASENAME;
		}

		return Addons::resolve_installed_plugin_file( $addon_id ) ?? Addons::plugin_file( $addon_id );
	}
}
