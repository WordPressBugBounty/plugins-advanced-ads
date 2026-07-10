<?php
/**
 * Download and install paid add-on packages from the shop.
 *
 * @package AdvancedAds
 * @since   2.0.9
 */

namespace AdvancedAds\License;

use AdvancedAds\Admin\Plugin_Installer;
use AdvancedAds\Utilities\Addons;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin_Upgrader-based package install for license add-ons.
 */
final class License_Package_Installer {

	/**
	 * Download_url values already installed this request (All Access runs once per URL).
	 *
	 * @var array<string, true>
	 */
	private static $installed_download_urls = [];

	/**
	 * Plugin bootstrap file for an add-on id, or null when unknown.
	 *
	 * @param string $addon_id Short add-on id.
	 * @return string|null
	 */
	public static function plugin_file_for_addon_id( string $addon_id ): ?string {
		$addon_id = sanitize_key( $addon_id );
		if ( ! Addons::is_known_addon( $addon_id ) ) {
			return null;
		}

		return Addons::resolve_installed_plugin_file( $addon_id ) ?? Addons::plugin_file( $addon_id );
	}

	/**
	 * Whether the add-on plugin is installed (detected by TextDomain, not folder name).
	 *
	 * @param string $addon_id Short add-on id.
	 * @return bool
	 */
	public static function is_addon_on_disk( string $addon_id ): bool {
		return Addons::is_addon_on_disk( $addon_id );
	}

	/**
	 * Download and install an add-on package.
	 *
	 * @param string        $download_url           Package URL.
	 * @param string        $addon_id               Add-on id.
	 * @param string        $cache_key              Per-request dedupe key.
	 * @param bool          $skip_if_cached_url     When true, skip duplicate downloads in one request.
	 * @param callable|null $retry_with_fresh_url   Called on HTTP error; return alternate URL or empty string.
	 * @return true|false|WP_Error True when install ran, false when skipped.
	 */
	public static function install_package(
		string $download_url,
		string $addon_id,
		string $cache_key,
		bool $skip_if_cached_url = true,
		?callable $retry_with_fresh_url = null
	) {
		if ( $skip_if_cached_url && ! empty( self::$installed_download_urls[ $cache_key ] ) ) {
			return self::is_addon_on_disk( $addon_id ) ? false : self::run_install( $download_url, $addon_id, true );
		}

		$result = self::run_install( $download_url, $addon_id, true );
		if (
			is_wp_error( $result )
			&& in_array( $result->get_error_code(), [ 'advanced_ads_install_failed', 'advanced_ads_install_wrong_package', 'http_request_failed' ], true )
			&& null !== $retry_with_fresh_url
		) {
			$fresh_url = (string) $retry_with_fresh_url();
			if ( '' !== $fresh_url && $fresh_url !== $download_url ) {
				$result = self::run_install( $fresh_url, $addon_id, true );
			}
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		self::$installed_download_urls[ $cache_key ] = true;

		return true;
	}

	/**
	 * Download and unpack a plugin package into wp-content/plugins.
	 *
	 * @param string $download_url     Signed shop package URL.
	 * @param string $addon_id         Expected add-on id (validates extracted folder).
	 * @param bool   $replace_existing Replace an existing plugin directory when true.
	 * @return true|WP_Error
	 */
	public static function run_install( string $download_url, string $addon_id, bool $replace_existing = false ) {
		$result = Plugin_Installer::install_from_url( $download_url, $replace_existing );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( false === $result || null === $result ) {
			return new WP_Error(
				'advanced_ads_install_failed',
				__( 'Add-on install failed. Check that wp-content/plugins is writable and the download URL is reachable from this site.', 'advanced-ads' )
			);
		}

		wp_clean_plugins_cache();
		Addons::get_plugins( true );

		if ( ! self::is_addon_on_disk( $addon_id ) ) {
			$plugin_file = Addons::plugin_file( sanitize_key( $addon_id ) ) ?? '';

			return new WP_Error(
				'advanced_ads_install_wrong_package',
				sprintf(
					/* translators: %s: expected plugin folder, e.g. advanced-ads-tracking */
					__( 'Downloaded package did not install the expected add-on (%s).', 'advanced-ads' ),
					'' !== $plugin_file ? dirname( $plugin_file ) : $addon_id
				)
			);
		}

		return true;
	}
}
