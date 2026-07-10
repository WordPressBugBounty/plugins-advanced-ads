<?php
/**
 * Paid add-on registry and installed-plugin discovery.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   2.0.24
 */

namespace AdvancedAds\Utilities;

defined( 'ABSPATH' ) || exit;

/**
 * Canonical add-on paths and shared get_plugins() lookups.
 */
final class Addons {

	/**
	 * Plugin bootstrap paths keyed by short add-on id.
	 *
	 * @var array<string, string>
	 */
	private const PLUGIN_FILES = [
		'pro'        => 'advanced-ads-pro/advanced-ads-pro.php',
		'responsive' => 'advanced-ads-responsive/responsive-ads.php',
		'gam'        => 'advanced-ads-gam/advanced-ads-gam.php',
		'layer'      => 'advanced-ads-layer/layer-ads.php',
		'selling'    => 'advanced-ads-selling/advanced-ads-selling.php',
		'sticky'     => 'advanced-ads-sticky-ads/sticky-ads.php',
		'tracking'   => 'advanced-ads-tracking/tracking.php',
		'slider-ads' => 'advanced-ads-slider/slider.php',
	];

	/**
	 * Cached get_plugins() result for the current request.
	 *
	 * @var array<string, array>|null
	 */
	private static $plugins = null;

	/**
	 * Canonical plugin bootstrap paths for paid add-ons.
	 *
	 * @return array<string, string> Short add-on id => plugins-relative bootstrap path.
	 */
	public static function plugin_files(): array {
		return self::PLUGIN_FILES;
	}

	/**
	 * All known paid add-on short ids.
	 *
	 * @return string[]
	 */
	public static function known_addon_ids(): array {
		return array_keys( self::PLUGIN_FILES );
	}

	/**
	 * Whether the short id belongs to a paid add-on in the catalog.
	 *
	 * @param string $addon_id Short add-on id.
	 *
	 * @return bool
	 */
	public static function is_known_addon( string $addon_id ): bool {
		return isset( self::PLUGIN_FILES[ sanitize_key( $addon_id ) ] );
	}

	/**
	 * Canonical bootstrap path for a catalog add-on.
	 *
	 * @param string $addon_id Short add-on id.
	 *
	 * @return string|null Plugins-relative bootstrap path, or null when unknown.
	 */
	public static function plugin_file( string $addon_id ): ?string {
		$addon_id = sanitize_key( $addon_id );

		return self::PLUGIN_FILES[ $addon_id ] ?? null;
	}

	/**
	 * Installed plugins from WordPress.
	 *
	 * @param bool $refresh When true, bust the plugins object cache first.
	 *
	 * @return array<string, array> Plugin bootstrap path => plugin header data.
	 */
	public static function get_plugins( bool $refresh = false ): array {
		if ( $refresh ) {
			self::$plugins = null;
			wp_cache_delete( 'plugins', 'plugins' );
		}

		if ( null !== self::$plugins ) {
			return self::$plugins;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		self::$plugins = get_plugins();

		return self::$plugins;
	}

	/**
	 * Installed plugins indexed by TextDomain.
	 *
	 * @param bool $refresh When true, bust the plugins object cache first.
	 *
	 * @return array<string, array{file: string, version: string}>
	 */
	public static function get_plugins_by_text_domain( bool $refresh = false ): array {
		$normalized = [];

		foreach ( self::get_plugins( $refresh ) as $plugin_file => $plugin_data ) {
			$text_domain = $plugin_data['TextDomain'] ?? '';
			if ( '' === $text_domain ) {
				continue;
			}

			$normalized[ $text_domain ] = [
				'file'    => $plugin_file,
				'version' => $plugin_data['Version'] ?? '0.0.1',
			];
		}

		return $normalized;
	}

	/**
	 * Resolve the installed bootstrap path for a catalog add-on.
	 *
	 * Falls back to TextDomain and plugin folder matching when the canonical path differs.
	 *
	 * @param string $addon_id Short add-on id.
	 *
	 * @return string|null Plugins-relative bootstrap path when installed, otherwise null.
	 */
	public static function resolve_installed_plugin_file( string $addon_id ): ?string {
		$addon_id = sanitize_key( $addon_id );
		$file     = self::plugin_file( $addon_id );
		if ( null === $file ) {
			return null;
		}

		$plugins = self::get_plugins();
		if ( isset( $plugins[ $file ] ) ) {
			return $file;
		}

		$text_domain = self::text_domain( $addon_id );
		foreach ( $plugins as $plugin_file => $plugin_data ) {
			if ( ( $plugin_data['TextDomain'] ?? '' ) === $text_domain ) {
				return $plugin_file;
			}
		}

		$folder = dirname( $file );
		foreach ( array_keys( $plugins ) as $plugin_file ) {
			if ( dirname( $plugin_file ) === $folder ) {
				return $plugin_file;
			}
		}

		return file_exists( WP_PLUGIN_DIR . '/' . $file ) ? $file : null;
	}

	/**
	 * Whether a catalog add-on is present on disk.
	 *
	 * @param string $addon_id Short add-on id.
	 *
	 * @return bool
	 */
	public static function is_addon_on_disk( string $addon_id ): bool {
		return null !== self::resolve_installed_plugin_file( $addon_id );
	}

	/**
	 * Installed paid add-ons with metadata for admin and license UIs.
	 *
	 * @return array<string, array{id: string, name: string, version: string, path: string, options_slug: string, uri: string}> TextDomain => add-on data.
	 */
	public static function get_installed_addons(): array {
		$installed = [];

		foreach ( self::get_plugins() as $plugin_file => $plugin_data ) {
			$text_domain = $plugin_data['TextDomain'] ?? '';
			$addon_id    = self::addon_id_for_text_domain( $text_domain );
			if ( null === $addon_id ) {
				continue;
			}

			$installed[ $text_domain ] = [
				'id'           => $addon_id,
				'name'         => str_replace( [ '– ', 'Advanced Ads ' ], '', $plugin_data['Name'] ),
				'version'      => $plugin_data['Version'] ?? '0.0.1',
				'path'         => $plugin_file,
				'options_slug' => $text_domain,
				'uri'          => $plugin_data['PluginURI'] ?? 'https://wpadvancedads.com',
			];
		}

		return $installed;
	}

	/**
	 * Expected TextDomain for a catalog add-on id.
	 *
	 * @param string $addon_id Short add-on id.
	 *
	 * @return string
	 */
	private static function text_domain( string $addon_id ): string {
		return 'slider-ads' === $addon_id ? 'slider-ads' : 'advanced-ads-' . $addon_id;
	}

	/**
	 * Map an installed plugin TextDomain back to a catalog add-on id.
	 *
	 * @param string $text_domain Plugin TextDomain.
	 *
	 * @return string|null Short add-on id when recognized, otherwise null.
	 */
	private static function addon_id_for_text_domain( string $text_domain ): ?string {
		foreach ( self::PLUGIN_FILES as $addon_id => $file ) {
			if ( self::text_domain( $addon_id ) === $text_domain ) {
				return $addon_id;
			}
		}

		return null;
	}
}
