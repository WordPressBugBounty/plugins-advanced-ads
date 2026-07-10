<?php
/**
 * Group CRUD Helpers
 *
 * @since 2.0.0
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 *
 *  Content:
 *   1. Template
 *   2. Repositories functions
 *   3. CRUD functions
 *   4. Conditional functions
 *   5. Getter functions
 *   6. Finder functions
 */

use AdvancedAds\Abstracts\Group;
use AdvancedAds\Groups\Group_Types;
use AdvancedAds\Groups\Group_Factory;
use AdvancedAds\Interfaces\Group_Type;
use AdvancedAds\Groups\Group_Repository;

/** 1. Template --------------- */

/**
 * Get the group object.
 *
 * @param Group|WP_Term|int|bool $group_id Group instance, term instance or numeric.
 * @param string                 $new_type Change type of group.
 * @param array                  $args     Additional arguments.
 *
 * @return string|mixed The group output or whatever entity or string that is overriding the return value.
 */
function get_the_group( $group_id = 0, $new_type = '', $args = [] ) {
	if ( ! \Advanced_Ads::get_instance()->can_display_ads() ) {
		return '';
	}
	if ( defined( 'ADVANCED_ADS_DISABLE_CHANGE' ) && ADVANCED_ADS_DISABLE_CHANGE ) {
		$args = [];
	}

	// Early bail!!
	// TODO: remove later once we move to new hooks.
	if ( isset( $args['override'] ) ) {
		return $args['override'];
	}

	$group = is_a_group( $group_id ) ? $group_id : wp_advads_get_group( $group_id, $new_type );
	if ( ! $group || 0 === $group->get_id() ) {
		return '';
	}

	$args = wp_advads_default_entity_arguments( 'group', $group->get_id(), $args );
	wp_advads_set_additional_args( $group, $args );

	$override = apply_filters( 'advanced-ads-ad-select-override-by-group', false, $group, $group->get_ordered_ad_ids(), $args );

	if ( false !== $override ) {
		return $override;
	}

	return $group->output();
}

/**
 * Echo an ad from an ad group
 *
 * @since 1.0.0
 *
 * @param int   $id   ID of the ad group.
 * @param array $args Additional arguments.
 *
 * @return void
 */
function the_ad_group( $id = 0, $args = [] ): void {
	echo get_the_group( $id, '', $args ); // phpcs:ignore
}

/* 2. Repositories ------------------- */

/**
 * Get Group Factory
 *
 * @deprecated 2.0.24 Use wp_advads()->groups->factory instead.
 *
 * @return Group_Factory
 */
function wp_advads_get_group_factory(): Group_Factory {
	return wp_advads()->groups->factory;
}

/**
 * Get Group Repository
 *
 * @deprecated 2.0.24 Use wp_advads()->groups->repository instead.
 *
 * @return Group_Repository
 */
function wp_advads_get_group_repository(): Group_Repository {
	return wp_advads()->groups->repository;
}

/**
 * Get Group Types
 *
 * @deprecated 2.0.24 Use wp_advads()->groups->types instead.
 *
 * @return Group_Types
 */
function wp_advads_get_group_type_manager(): Group_Types {
	return wp_advads()->groups->types;
}

/* 3. CRUD ------------------- */

/**
 * Create an empty group object
 *
 * @param string $type Type of group to create.
 *
 * @return Group|bool Group object or false if the group type not found.
 */
function wp_advads_create_new_group( $type = 'default' ) {
	return wp_advads()->groups->factory->create_group( $type );
}

/**
 * Delete an group from the database.
 *
 * @param int|Group $group Group object or Id.
 *
 * @return void
 */
function wp_advads_delete_group( &$group ): void {
	if ( ! $group instanceof Group ) {
		$group = wp_advads_get_group( $group );
	}

	$group->delete();
}

/**
 * Create missing group type.
 *
 * @param string $type Missing type.
 *
 * @return void
 */
function wp_advads_create_group_type( $type ): void {
	wp_advads()->groups->types->create_missing( $type );
}

/**
 * Register custom group type.
 *
 * @param string $classname Type class name.
 *
 * @return void
 */
function wp_advads_register_group_type( $classname ): void {
	wp_advads()->groups->types->register_type( $classname );
}

/* 4. Conditional ------------------- */

/**
 * Has group type.
 *
 * @param string $type Type to check.
 *
 * @return bool
 */
function wp_advads_has_group_type( $type ): bool {
	return wp_advads()->groups->types->has_type( $type );
}

/**
 * Checks whether the given variable is a group.
 *
 * @param mixed $thing The variable to check.
 *
 * @return bool
 */
function is_a_group( $thing ): bool {
	return $thing instanceof Group;
}

/* 5. Getter ------------------- */

/**
 * Get all groups object.
 *
 * @deprecated 2.0.24 Use wp_advads_get_group_summaries() or wp_advads_get_groups_by_ids().
 *
 * @return Group[]
 */
function wp_advads_get_all_groups(): array {
	return wp_advads()->groups->repository->get_all_groups();
}

/**
 * Get lightweight group summaries for admin list UIs.
 *
 * @return array<int, array{id: int, title: string, slug: string, type: string, ad_weights: array<int, int>, publish_date: string, modified_date: string}>
 */
function wp_advads_get_group_summaries(): array {
	return wp_advads()->groups->repository->get_group_summaries();
}

/**
 * Get all group as dropdown.
 *
 * @return array
 */
function wp_advads_get_groups_dropdown() {
	return wp_advads()->groups->repository->get_groups_dropdown();
}

/**
 * Get the registered group type.
 *
 * @param string $type Type to get.
 *
 * @return Group_Type|bool
 */
function wp_advads_get_group_type( $type ) {
	return wp_advads()->groups->types->get_type( $type );
}

/**
 * Get the registered group types.
 *
 * @return Group_Type[]
 */
function wp_advads_get_group_types() {
	return wp_advads()->groups->types->get_types();
}

/* 6. Finder ------------------- */

/**
 * Get the group object.
 *
 * @param Group|WP_Term|int|bool $group_id Group instance, term instance or numeric.
 * @param string                 $new_type Change type of group.
 *
 * @return Group|bool Group object or false if the group cannot be loaded.
 */
function wp_advads_get_group( $group_id = false, $new_type = '' ) {
	return wp_advads()->groups->factory->get_group( $group_id, $new_type );
}

/**
 * Get groups associated with a given ad id.
 *
 * @param int $ad_id The ID of the ad.
 *
 * @return Group[]
 */
function wp_advads_get_groups_by_ad_id( $ad_id ): array {
	return wp_advads()->groups->repository->get_groups_by_ad_id( $ad_id );
}

/**
 * Hydrate group objects for the given term IDs.
 *
 * @param int[] $group_ids Group term IDs.
 *
 * @return array<int, Group>
 */
function wp_advads_get_groups_by_ids( array $group_ids ): array {
	return wp_advads()->groups->repository->get_groups_by_ids( $group_ids );
}
