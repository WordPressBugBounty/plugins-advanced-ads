<?php
/**
 * Map exchange/API product names to add-on ids used in legacy license storage.
 *
 * @package AdvancedAds
 * @since   2.0.9
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

namespace AdvancedAds\License;

defined( 'ABSPATH' ) || exit;

/**
 * Product name ↔ addon id helpers.
 */
final class License_Product_Map {

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
	 * @param string               $product_name From exchange payload `name`.
	 * @param array<string, array> $addons       Output shape from Data::get_addons().
	 * @return string|null Addon id or null if unknown / bundle row.
	 */
	public static function addon_id_from_product_name( string $product_name, array $addons ): ?string {
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

		foreach ( $addons as $row ) {
			if ( empty( $row['id'] ) || empty( $row['name'] ) ) {
				continue;
			}

			$addon_name = self::normalize_name( (string) $row['name'] );

			if ( $addon_name === $target ) {
				return (string) $row['id'];
			}

			// API often sends "Advanced Ads Pro"; installed add-on label is "Pro".
			if ( 'advanced ads ' . $addon_name === $target ) {
				return (string) $row['id'];
			}

			if ( str_ends_with( $target, ' ' . $addon_name ) ) {
				return (string) $row['id'];
			}
		}

		return null;
	}
}
