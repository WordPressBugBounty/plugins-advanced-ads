<?php
/**
 * Update routine
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.52.1
 */

use AdvancedAds\Constants;

/**
 * Migrates groups and ads to link them together.
 *
 * @since 1.52.1
 */
function advads_migrate_groups() {
	$groups = wp_advads_get_all_groups();
	$ads    = wp_advads_get_all_ads();

	// link group ads with ad post.
	foreach ( $groups as $group ) {
		foreach ( $group->get_ad_weights() as $ad_id => $weight ) {
			wp_set_object_terms( $ad_id, $group->get_id(), Constants::TAXONOMY_GROUP, true );
		}
	}

	// link ad post with group ads.
	foreach ( $ads as $ad ) {
		$group_ids = wp_get_object_terms( $ad->get_id(), Constants::TAXONOMY_GROUP, [ 'fields' => 'ids' ] );

		if ( is_wp_error( $group_ids ) || empty( $group_ids ) ) {
			continue;
		}

		foreach ( wp_advads_get_groups_by_ids( $group_ids ) as $group ) {
			$weights = $group->get_ad_weights();
			if ( ! isset( $weights[ $ad->get_id() ] ) ) {
				$weights[ $ad->get_id() ] = 10;
				$group->set_ad_weights( $weights );
				$group->save();
			}
		}
	}
}

advads_migrate_groups();
