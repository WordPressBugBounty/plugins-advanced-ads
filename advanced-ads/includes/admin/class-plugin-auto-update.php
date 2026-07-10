<?php
/**
 * Per-plugin auto-update preferences (advanced-ads-{addon}-autoupdate = on|off).
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   2.0.21
 */

namespace AdvancedAds\Admin;

use AdvancedAds\Framework\Interfaces\Integration_Interface;
use AdvancedAds\Utilities\Addons;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin auto-update option storage and WordPress auto_update_plugins sync.
 */
class Plugin_Auto_Update implements Integration_Interface {

	/**
	 * Admin hooks (Plugins screen sync).
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'admin_init', [ self::class, 'maybe_sync_on_admin' ], 20 );
		add_filter( 'site_option_auto_update_plugins', [ self::class, 'filter_site_auto_update_plugins' ] );
	}

	public const STATE_ON  = 'on';
	public const STATE_OFF = 'off';

	/**
	 * Main plugin key in state maps.
	 */
	public const MAIN_ADDON_KEY = 'main';

	/**
	 * All known short add-on ids.
	 *
	 * @return string[]
	 */
	public static function known_addon_ids(): array {
		return Addons::known_addon_ids();
	}

	/**
	 * Option name for an add-on or the main plugin.
	 *
	 * @param string|null $addon_id Short id, empty, null, or "main" for base plugin.
	 * @return string
	 */
	public static function option_name( ?string $addon_id = null ): string {
		$slug_base = defined( 'ADVADS_SLUG' ) ? ADVADS_SLUG : 'advanced-ads';
		$addon_id  = self::normalize_addon_id( $addon_id );

		if ( null === $addon_id ) {
			return $slug_base . '-autoupdate';
		}

		return $slug_base . '-' . $addon_id . '-autoupdate';
	}

	/**
	 * Whether auto-updates are enabled for the given plugin.
	 *
	 * @param string|null $addon_id Short id or main.
	 * @return bool
	 */
	public static function is_enabled( ?string $addon_id = null ): bool {
		return self::STATE_ON === self::get_state( $addon_id );
	}

	/**
	 * Stored state (on|off), default off.
	 *
	 * @param string|null $addon_id Short id or main.
	 * @return string
	 */
	public static function get_state( ?string $addon_id = null ): string {
		$value = get_option( self::option_name( $addon_id ), self::STATE_OFF );

		return self::STATE_ON === $value ? self::STATE_ON : self::STATE_OFF;
	}

	/**
	 * Persist state and sync WordPress auto_update_plugins when applicable.
	 *
	 * @param string|null $addon_id Short id or main.
	 * @param string      $state    on|off.
	 * @return true|WP_Error
	 */
	public static function set_state( ?string $addon_id, string $state ) {
		$addon_id = self::normalize_addon_id( $addon_id );
		$state    = strtolower( sanitize_text_field( $state ) );

		if ( self::STATE_ON !== $state && self::STATE_OFF !== $state ) {
			return new WP_Error( 'invalid_state', __( 'Invalid auto-update state.', 'advanced-ads' ) );
		}

		if ( null !== $addon_id && ! Addons::is_known_addon( $addon_id ) ) {
			return new WP_Error( 'invalid_addon', __( 'Unknown add-on.', 'advanced-ads' ) );
		}

		update_option( self::option_name( $addon_id ), $state, false );
		self::sync_all_from_custom_options();

		return true;
	}

	/**
	 * Rebuild WordPress auto_update_plugins from all advanced-ads-*-autoupdate options.
	 *
	 * @return void
	 */
	public static function sync_all_from_custom_options(): void {
		$auto_updates = (array) get_site_option( 'auto_update_plugins', [] );
		$targets      = array_merge( [ null ], self::known_addon_ids() );

		foreach ( $targets as $addon_id ) {
			$plugin_file = self::resolve_installed_plugin_file( $addon_id );
			if ( null === $plugin_file ) {
				continue;
			}

			if ( self::is_enabled( $addon_id ) ) {
				if ( ! in_array( $plugin_file, $auto_updates, true ) ) {
					$auto_updates[] = $plugin_file;
				}
			} else {
				$auto_updates = array_values( array_diff( $auto_updates, [ $plugin_file ] ) );
			}
		}

		$installed = array_keys( Addons::get_plugins() );
		if ( ! empty( $installed ) ) {
			$auto_updates = array_values(
				array_unique( array_intersect( $auto_updates, $installed ) )
			);
			// Re-apply enabled plugins that use a non-canonical basename key.
			foreach ( $targets as $addon_id ) {
				if ( ! self::is_enabled( $addon_id ) ) {
					continue;
				}
				$plugin_file = self::resolve_installed_plugin_file( $addon_id );
				if ( null !== $plugin_file && ! in_array( $plugin_file, $auto_updates, true ) ) {
					$auto_updates[] = $plugin_file;
				}
			}
		}

		update_site_option( 'auto_update_plugins', $auto_updates );
	}

	/**
	 * Installed plugin basename for an add-on (matches get_plugins() keys).
	 *
	 * @param string|null $addon_id Short id or main.
	 * @return string|null
	 */
	public static function resolve_installed_plugin_file( ?string $addon_id ): ?string {
		$addon_id = self::normalize_addon_id( $addon_id );

		if ( null === $addon_id ) {
			$candidate = self::plugin_file_for_addon_id( null );
			if ( null === $candidate || ! self::plugin_path_exists( null, $candidate ) ) {
				return null;
			}

			return $candidate;
		}

		return Addons::resolve_installed_plugin_file( $addon_id );
	}

	/**
	 * Merge custom options into the value WordPress reads for the Plugins screen.
	 *
	 * @param mixed $value Site option value.
	 * @return array<string>
	 */
	public static function filter_site_auto_update_plugins( $value ): array {
		$value   = is_array( $value ) ? $value : [];
		$targets = array_merge( [ null ], self::known_addon_ids() );

		foreach ( $targets as $addon_id ) {
			$plugin_file = self::resolve_installed_plugin_file( $addon_id );
			if ( null === $plugin_file ) {
				continue;
			}

			if ( self::is_enabled( $addon_id ) ) {
				if ( ! in_array( $plugin_file, $value, true ) ) {
					$value[] = $plugin_file;
				}
			} else {
				$value = array_values( array_diff( $value, [ $plugin_file ] ) );
			}
		}

		return array_values( array_unique( $value ) );
	}

	/**
	 * Persist auto_update_plugins when an admin with update_plugins loads wp-admin.
	 *
	 * @return void
	 */
	public static function maybe_sync_on_admin(): void {
		if ( ! is_admin() || ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		self::sync_all_from_custom_options();
	}

	/**
	 * Map of addon id (or "main") => on|off.
	 *
	 * @param string[] $addon_ids Short ids to include.
	 * @return array<string, string>
	 */
	public static function get_states_for_addon_ids( array $addon_ids ): array {
		$states = [
			self::MAIN_ADDON_KEY => self::get_state( null ),
		];

		foreach ( $addon_ids as $addon_id ) {
			$addon_id = sanitize_key( (string) $addon_id );
			if ( '' === $addon_id || ! Addons::is_known_addon( $addon_id ) ) {
				continue;
			}
			$states[ $addon_id ] = self::get_state( $addon_id );
		}

		return $states;
	}

	/**
	 * Full map for all known add-ons plus main.
	 *
	 * @return array<string, string>
	 */
	public static function get_all_states(): array {
		return self::get_states_for_addon_ids( self::known_addon_ids() );
	}

	/**
	 * Plugin basename relative to wp-content/plugins.
	 *
	 * @param string|null $addon_id Short id or main.
	 * @return string|null
	 */
	public static function plugin_file_for_addon_id( ?string $addon_id ): ?string {
		$addon_id = self::normalize_addon_id( $addon_id );

		if ( null === $addon_id ) {
			if ( defined( 'ADVADS_FILE' ) ) {
				return basename( dirname( ADVADS_FILE ) ) . '/' . basename( ADVADS_FILE );
			}

			return 'advanced-ads/advanced-ads.php';
		}

		return Addons::plugin_file( $addon_id );
	}

	/**
	 * Normalize the addon ID
	 *
	 * @param string|null $addon_id Raw id from request.
	 * @return string|null Normalized short id or null for main plugin.
	 */
	private static function normalize_addon_id( ?string $addon_id ): ?string {
		$addon_id = sanitize_key( (string) $addon_id );

		if ( '' === $addon_id || self::MAIN_ADDON_KEY === $addon_id ) {
			return null;
		}

		return $addon_id;
	}

	/**
	 * Whether the plugin bootstrap file exists on disk.
	 *
	 * @param string|null $addon_id    Normalized id or null for main.
	 * @param string      $plugin_file Plugins-relative path.
	 * @return bool
	 */
	private static function plugin_path_exists( ?string $addon_id, string $plugin_file ): bool {
		if ( null === $addon_id && defined( 'ADVADS_FILE' ) ) {
			return file_exists( ADVADS_FILE );
		}

		return file_exists( wp_normalize_path( WP_PLUGIN_DIR . '/' . $plugin_file ) );
	}
}
