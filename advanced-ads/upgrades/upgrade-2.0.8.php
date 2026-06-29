<?php
/**
 * Update routine
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   2.0.8
 */

use AdvancedAds\Constants;

/**
 * Save group ids into ad options.
 *
 * @since 2.0.8
 *
 * @return void
 */
function advads_upgrade_2_0_8_save_group_ids(): void {
	$ad_group_ids = [];

	foreach ( wp_advads_get_group_summaries() as $group_id => $summary ) {
		foreach ( array_keys( $summary['ad_weights'] ?? [] ) as $ad_id ) {
			$ad_group_ids[ (int) $ad_id ][] = (int) $group_id;
		}
	}

	foreach ( wp_advads_get_ad_summaries() as $ad_id => $summary ) {
		if ( get_post_meta( $ad_id, Constants::AD_META_GROUP_IDS, true ) ) {
			continue;
		}

		$group_ids = $ad_group_ids[ $ad_id ] ?? [];

		if ( empty( $group_ids ) ) {
			$group_ids = wp_get_object_terms( $ad_id, Constants::TAXONOMY_GROUP, [ 'fields' => 'ids' ] );

			if ( is_wp_error( $group_ids ) || empty( $group_ids ) ) {
				continue;
			}
		}

		update_post_meta( $ad_id, Constants::AD_META_GROUP_IDS, $group_ids );
	}
}

advads_upgrade_2_0_8_save_group_ids();
