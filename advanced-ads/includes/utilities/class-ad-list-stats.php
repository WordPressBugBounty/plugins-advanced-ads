<?php
/**
 * Request-scoped ad list statistics from the admin list query.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.x.x
 */

namespace AdvancedAds\Utilities;

defined( 'ABSPATH' ) || exit;

/**
 * Ad_List_Stats.
 */
class Ad_List_Stats {

	/**
	 * Stats keyed by ad ID for the current request only.
	 *
	 * @var array<int, array{clicks: int, impressions: int, ctr: float}>
	 */
	private static $stats = [];

	/**
	 * Store list stats for an ad (current request only).
	 *
	 * @param int   $ad_id Ad post ID.
	 * @param array $stats clicks, impressions, ctr.
	 *
	 * @return void
	 */
	public static function set( int $ad_id, array $stats ): void {
		if ( $ad_id <= 0 ) {
			return;
		}

		self::$stats[ $ad_id ] = [
			'clicks'      => isset( $stats['clicks'] ) ? absint( $stats['clicks'] ) : 0,
			'impressions' => isset( $stats['impressions'] ) ? absint( $stats['impressions'] ) : 0,
			'ctr'         => isset( $stats['ctr'] ) ? (float) $stats['ctr'] : 0.0,
		];
	}

	/**
	 * Get list stats for an ad in the current request.
	 *
	 * @param int $ad_id Ad post ID.
	 *
	 * @return array{clicks: int, impressions: int, ctr: float}|null
	 */
	public static function get( int $ad_id ): ?array {
		return self::$stats[ $ad_id ] ?? null;
	}
}
