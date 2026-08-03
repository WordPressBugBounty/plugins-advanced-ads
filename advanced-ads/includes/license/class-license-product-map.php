<?php
/**
 * Map exchange/API product names to add-on ids used in legacy license storage.
 *
 * @package AdvancedAds
 * @since   2.0.9
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

namespace AdvancedAds\License;

use AdvancedAds\Utilities\Addons;

defined( 'ABSPATH' ) || exit;

/**
 * Product name ↔ addon id helpers.
 */
final class License_Product_Map {

	/**
	 * Add-on manifest for license name resolution (always includes missing add-ons).
	 *
	 * @return array<string, array{id: string, name: string, options_slug: string, path: string}>
	 */
	public static function addon_manifest(): array {
		$slug_base = defined( 'ADVADS_SLUG' ) ? ADVADS_SLUG : 'advanced-ads';
		$names     = [
			'pro'        => 'Pro',
			'responsive' => 'AMP Ads',
			'gam'        => 'Google Ad Manager Integration',
			'layer'      => 'PopUp and Layer Ads',
			'selling'    => 'Selling Ads',
			'sticky'     => 'Sticky Ads',
			'tracking'   => 'Tracking',
		];
		$out       = [];

		foreach ( Addons::plugin_files() as $addon_id => $file ) {
			$slug         = $slug_base . '-' . $addon_id;
			$display_name = $names[ $addon_id ] ?? $addon_id;
			$normalized   = self::normalize_name( $display_name );
			$out[ $slug ] = [
				'id'           => $addon_id,
				'name'         => $display_name,
				'options_slug' => $slug,
				'path'         => $file,
				'aliases'      => array_values(
					array_unique(
						array_filter(
							[
								$normalized,
								'' !== $normalized ? 'advanced ads ' . $normalized : '',
							]
						)
					)
				),
			];
		}

		return $out;
	}

	/**
	 * Normalize a display name for comparison.
	 *
	 * @param string $name Raw name.
	 * @return string
	 */
	public static function normalize_name( string $name ): string {
		$name = strtolower( trim( $name ) );
		return (string) preg_replace( '/\s+/', ' ', $name );
	}

	/**
	 * Remove site-tier suffix from a normalized product name (e.g. "advanced ads pro / 2 sites").
	 *
	 * @param string $normalized Output of normalize_name().
	 * @return string
	 */
	public static function strip_tier_suffix( string $normalized ): string {
		$stripped = preg_replace( '#\s*/\s*\d+\s*sites?.*$#i', '', $normalized );
		$stripped = preg_replace( '#\s*\(\s*\d+\s*sites?\s*\).*$#i', '', (string) $stripped );

		return trim( (string) $stripped );
	}

	/**
	 * Whether this product name represents an All Access–style bundle row.
	 *
	 * @param string $name From exchange payload `name`.
	 * @return bool
	 */
	public static function is_all_access_bundle_name( string $name ): bool {
		$normalized = self::normalize_name( $name );

		return '' !== $normalized && str_starts_with( $normalized, 'all access' );
	}

	/**
	 * Resolve addon id (e.g. "tracking") from API product name using installed add-on metadata.
	 *
	 * @param string                                                                                                          $product_name From exchange payload `name`.
	 * @param array<string, array{id: string, name: string, options_slug: string, path: string, aliases?: list<string>}>|null $addons Manifest rows; defaults to addon_manifest().
	 * @return string|null Addon id or null if unknown / bundle row.
	 */
	public static function addon_id_from_product_name( string $product_name, ?array $addons = null ): ?string {
		if ( self::is_all_access_bundle_name( $product_name ) ) {
			return null;
		}

		$target = self::normalize_name( $product_name );
		if ( '' === $target ) {
			return null;
		}

		$target = self::strip_tier_suffix( $target );
		if ( '' === $target ) {
			return null;
		}

		$addons = $addons ?? self::addon_manifest();

		foreach ( $addons as $row ) {
			if ( empty( $row['id'] ) || empty( $row['name'] ) ) {
				continue;
			}

			$addon_name = self::normalize_name( (string) $row['name'] );
			$aliases    = isset( $row['aliases'] ) && is_array( $row['aliases'] )
				? $row['aliases']
				: array_values(
					array_filter(
						[
							$addon_name,
							'' !== $addon_name ? 'advanced ads ' . $addon_name : '',
						]
					)
				);

			if ( in_array( $target, $aliases, true ) || ( '' !== $addon_name && str_ends_with( $target, ' ' . $addon_name ) ) ) {
				return (string) $row['id'];
			}
		}

		return null;
	}
}
