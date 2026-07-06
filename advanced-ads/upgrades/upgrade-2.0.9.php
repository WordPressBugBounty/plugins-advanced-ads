<?php
/**
 * Update routine
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   2.0.9
 */

use AdvancedAds\Cache_Invalidator;
use AdvancedAds\Constants;
use AdvancedAds\Groups\Group_Repository;

/**
 * Migrate legacy group options into term meta and remove dual storage.
 *
 * @since 2.0.9
 *
 * @return void
 */
function advads_upgrade_2_0_9_migrate_group_legacy_options(): void {
	$all_groups = get_option( 'advads-ad-groups', [] );
	$ad_weights = get_option( 'advads-ad-weights', [] );

	if ( ! is_array( $all_groups ) ) {
		$all_groups = [];
	}

	if ( ! is_array( $ad_weights ) ) {
		$ad_weights = [];
	}

	if ( empty( $all_groups ) && empty( $ad_weights ) ) {
		return;
	}

	$group_ids = array_unique( array_merge( array_keys( $all_groups ), array_keys( $ad_weights ) ) );

	foreach ( $group_ids as $group_id ) {
		$group_id = (int) $group_id;

		if ( ! term_exists( $group_id, Constants::TAXONOMY_GROUP ) ) {
			continue;
		}

		$group = wp_advads_get_group( $group_id );

		if ( ! $group ) {
			continue;
		}

		$needs_save = false;
		$legacy     = is_array( $all_groups[ $group_id ] ?? null ) ? $all_groups[ $group_id ] : [];

		if ( empty( get_term_meta( $group_id, Group_Repository::OPTION_METAKEY, true ) ) && ! empty( $legacy ) ) {
			if ( ! empty( $legacy['type'] ) ) {
				$group->set_type( $legacy['type'] );
			}

			$group->set_props( $legacy );
			$needs_save = true;
		}

		if ( ! empty( $ad_weights[ $group_id ] ) && is_array( $ad_weights[ $group_id ] ) && empty( $group->get_ad_weights() ) ) {
			$group->set_ad_weights( $ad_weights[ $group_id ] );
			$needs_save = true;
		}

		if ( $needs_save ) {
			$group->save();
		}
	}

	delete_option( 'advads-ad-groups' );
	delete_option( 'advads-ad-weights' );

	Cache_Invalidator::invalidate_groups();
}

advads_upgrade_2_0_9_migrate_group_legacy_options();
