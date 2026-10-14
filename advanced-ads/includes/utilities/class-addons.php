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
	 * The paid add-ons keyed by short add-on id: plugin bootstrap path, shop product ID and shop product name — the text domain is `advanced-ads-{id}` unless set, and the add-on is part of All Access unless `all_access` is false.
	 *
	 * @var array<string, array{file: string, item_id: int, name: string, text_domain?: string, all_access?: bool}>
	 */
	private const ADDONS = [
		'pro'        => [
			'file'    => 'advanced-ads-pro/advanced-ads-pro.php',
			'item_id' => 1742,
			'name'    => 'Pro',
		],
		'responsive' => [
			'file'    => 'advanced-ads-responsive/responsive-ads.php',
			'item_id' => 678,
			'name'    => 'AMP Ads',
		],
		'gam'        => [
			'file'    => 'advanced-ads-gam/advanced-ads-gam.php',
			'item_id' => 215545,
			'name'    => 'Google Ad Manager Integration',
		],
		'geo'        => [
			'file'    => 'advanced-ads-geo/advanced-ads-geo.php',
			'item_id' => 8387,
			'name'    => 'Geo Targeting',
		],
		'layer'      => [
			'file'    => 'advanced-ads-layer/layer-ads.php',
			'item_id' => 686,
			'name'    => 'PopUp and Layer Ads',
		],
		'selling'    => [
			'file'    => 'advanced-ads-selling/advanced-ads-selling.php',
			'item_id' => 35300,
			'name'    => 'Selling Ads',
		],
		'sticky'     => [
			'file'    => 'advanced-ads-sticky-ads/sticky-ads.php',
			'item_id' => 683,
			'name'    => 'Sticky Ads',
		],
		'tracking'   => [
			'file'    => 'advanced-ads-tracking/advanced-ads-tracking.php',
			'item_id' => 638,
			'name'    => 'Tracking',
		],
		'slider-ads' => [
			'file'        => 'advanced-ads-slider/slider.php',
			'item_id'     => 1168,
			'name'        => 'Ad Slider',
			'text_domain' => 'slider-ads',
			'all_access'  => false,
		],
	];

	/**
	 * Cached get_plugins() result for the current request.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private static $plugins = null;

	/**
	 * Canonical plugin bootstrap paths for paid add-ons.
	 *
	 * @return array<string, string> Short add-on id => plugins-relative bootstrap path.
	 */
	public static function plugin_files(): array {
		return wp_list_pluck( self::ADDONS, 'file' );
	}

	/**
	 * All known paid add-on short ids.
	 *
	 * @return string[]
	 */
	public static function known_addon_ids(): array {
		return array_keys( self::ADDONS );
	}

	/**
	 * Whether the short id belongs to a paid add-on in the catalog.
	 *
	 * @param string $addon_id Short add-on id.
	 *
	 * @return bool
	 */
	public static function is_known_addon( string $addon_id ): bool {
		return isset( self::ADDONS[ sanitize_key( $addon_id ) ] );
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

		return self::ADDONS[ $addon_id ]['file'] ?? null;
	}

	/**
	 * Shop product ID of a catalog add-on.
	 *
	 * @param string $addon_id Short add-on id.
	 *
	 * @return int 0 when unknown.
	 */
	public static function item_id( string $addon_id ): int {
		return self::ADDONS[ sanitize_key( $addon_id ) ]['item_id'] ?? 0;
	}

	/**
	 * Shop product name of a catalog add-on, used to match licenses to add-ons.
	 *
	 * @param string $addon_id Short add-on id.
	 *
	 * @return string The add-on id for an unknown add-on.
	 */
	public static function product_name( string $addon_id ): string {
		return self::ADDONS[ $addon_id ]['name'] ?? $addon_id;
	}

	/**
	 * Whether an All Access license covers the add-on.
	 *
	 * @param string $addon_id Short add-on id.
	 *
	 * @return bool
	 */
	public static function is_all_access_addon( string $addon_id ): bool {
		return isset( self::ADDONS[ $addon_id ] ) && ( self::ADDONS[ $addon_id ]['all_access'] ?? true );
	}

	/**
	 * The add-on list the license screen works with.
	 *
	 * @return array<string, array{name: string, allAccess: bool}> Keyed by short add-on id.
	 */
	public static function license_screen_data(): array {
		$data = [];
		foreach ( self::ADDONS as $addon_id => $addon ) {
			$data[ $addon_id ] = [
				'name'      => self::product_name( $addon_id ),
				'allAccess' => self::is_all_access_addon( $addon_id ),
			];
		}

		return $data;
	}

	/**
	 * Installed plugins from WordPress.
	 *
	 * @param bool $refresh When true, bust the plugins object cache first.
	 *
	 * @return array<string, array<string, mixed>> Plugin bootstrap path => plugin header data.
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
	 * Resolve the installed bootstrap path for a catalog add-on — falls back to TextDomain and plugin folder matching when the canonical path differs.
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
		return self::ADDONS[ $addon_id ]['text_domain'] ?? 'advanced-ads-' . $addon_id;
	}

	/**
	 * Map an installed plugin TextDomain back to a catalog add-on id.
	 *
	 * @param string $text_domain Plugin TextDomain.
	 *
	 * @return string|null Short add-on id when recognized, otherwise null.
	 */
	public static function addon_id_for_text_domain( string $text_domain ): ?string {
		foreach ( array_keys( self::ADDONS ) as $addon_id ) {
			if ( self::text_domain( $addon_id ) === $text_domain ) {
				return $addon_id;
			}
		}

		return null;
	}
}
