<?php
/**
 * Object cache helper for Advanced Ads entity data.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   2.0.14
 */

namespace AdvancedAds\Utilities;

defined( 'ABSPATH' ) || exit;

/**
 * Cache.
 *
 * Wraps wp_cache_* with versioned keys per prefix so flush_group() can invalidate
 * a whole namespace without deleting individual entries (Redis-friendly).
 */
class Cache {

	/**
	 * wp_cache group for all Advanced Ads cache entries.
	 *
	 * @var string
	 */
	public const GROUP = 'advanced-ads';

	/**
	 * Cache prefix for ad list / entity data.
	 *
	 * @var string
	 */
	public const PREFIX_ADS = 'ads';

	/**
	 * Cache prefix for group list / entity data.
	 *
	 * @var string
	 */
	public const PREFIX_GROUPS = 'groups';

	/**
	 * Cache prefix for placement list / entity data.
	 *
	 * @var string
	 */
	public const PREFIX_PLACEMENTS = 'placements';

	/**
	 * Logical key: ID => lightweight list row (all entity prefixes).
	 *
	 * @var string
	 */
	public const KEY_SUMMARIES = 'summaries';

	/**
	 * Logical key: ID => lightweight list row for post_status any (includes trash).
	 *
	 * @var string
	 */
	public const KEY_SUMMARIES_ALL_STATUSES = 'summaries_all_statuses';

	/**
	 * Logical key: all entity IDs.
	 *
	 * @var string
	 */
	public const KEY_ALL_IDS = 'all_ids';

	/**
	 * Suffix for version counter keys stored in the object cache.
	 *
	 * @var string
	 */
	private const VERSION_SUFFIX = ':version';

	/**
	 * Known prefixes that flush_all() invalidates.
	 *
	 * @var string[]
	 */
	private const PREFIXES = [
		self::PREFIX_ADS,
		self::PREFIX_GROUPS,
		self::PREFIX_PLACEMENTS,
	];

	/**
	 * Read a cached value.
	 *
	 * Returns null on cache miss. Callers should build the value and store it with set().
	 *
	 * @param string $prefix Namespace prefix (e.g. self::PREFIX_ADS).
	 * @param string $key    Logical cache key (e.g. self::KEY_SUMMARIES).
	 *
	 * @return mixed|null Cached value, or null when not found.
	 */
	public static function get( string $prefix, string $key ) {
		$cache_key = self::build_key( $prefix, $key );
		$found     = false;
		$value     = wp_cache_get( $cache_key, self::GROUP, false, $found );

		if ( ! $found ) {
			return null;
		}

		return $value;
	}

	/**
	 * Store a value in the cache.
	 *
	 * @param string $prefix Namespace prefix.
	 * @param string $key    Logical cache key.
	 * @param mixed  $value  Value to store.
	 *
	 * @return bool
	 */
	public static function set( string $prefix, string $key, $value ): bool {
		return wp_cache_set( self::build_key( $prefix, $key ), $value, self::GROUP );
	}

	/**
	 * Delete a single cached value.
	 *
	 * @param string $prefix Namespace prefix.
	 * @param string $key    Logical cache key.
	 *
	 * @return bool
	 */
	public static function delete( string $prefix, string $key ): bool {
		return wp_cache_delete( self::build_key( $prefix, $key ), self::GROUP );
	}

	/**
	 * Invalidate all entries under a prefix by bumping its version counter.
	 *
	 * @param string $prefix Namespace prefix.
	 *
	 * @return void
	 */
	public static function flush_group( string $prefix ): void {
		$version_key = $prefix . self::VERSION_SUFFIX;
		wp_cache_set( $version_key, self::get_version( $prefix ) + 1, self::GROUP );
	}

	/**
	 * Invalidate all known entity cache prefixes.
	 *
	 * @return void
	 */
	public static function flush_all(): void {
		foreach ( self::PREFIXES as $prefix ) {
			self::flush_group( $prefix );
		}
	}

	/**
	 * Build the full wp_cache key for a logical key under a prefix.
	 *
	 * @param string $prefix Namespace prefix.
	 * @param string $key    Logical cache key.
	 *
	 * @return string
	 */
	public static function build_key( string $prefix, string $key ): string {
		return $prefix . ':v' . self::get_version( $prefix ) . ':' . $key;
	}

	/**
	 * Get the current version counter for a prefix.
	 *
	 * @param string $prefix Namespace prefix.
	 *
	 * @return int
	 */
	public static function get_version( string $prefix ): int {
		$version_key = $prefix . self::VERSION_SUFFIX;
		$found       = false;
		$version     = wp_cache_get( $version_key, self::GROUP, false, $found );

		if ( $found ) {
			return (int) $version;
		}

		return 1;
	}
}
