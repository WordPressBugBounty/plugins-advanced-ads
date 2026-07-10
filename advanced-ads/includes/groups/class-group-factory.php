<?php
/**
 * The group Factory.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.48.0
 */

namespace AdvancedAds\Groups;

use WP_Term;
use Exception;
use AdvancedAds\Abstracts\Group;
use AdvancedAds\Abstracts\Factory;

defined( 'ABSPATH' ) || exit;

/**
 * Groups Factory.
 */
class Group_Factory extends Factory {

	/**
	 * Create an empty group object
	 *
	 * @param string $type Type of group to create.
	 *
	 * @return Group|bool Group object or false if the group type not found.
	 */
	public function create_group( $type = 'default' ) {
		return $this->create_empty_from_type( wp_advads_get_group_type( $type ) );
	}

	/**
	 * Get the group object.
	 *
	 * @param Group|WP_Term|int|bool $group_id Group instance, term instance or numeric.
	 * @param string                 $new_type Change type of group.
	 *
	 * @return Group|bool Group object or false if the group cannot be loaded.
	 */
	public function get_group( $group_id, $new_type = '' ) {
		$group_id = $this->get_group_id( $group_id );

		if ( ! $group_id ) {
			return false;
		}

		if ( $this->has_cached_instance( (int) $group_id, $new_type ) ) {
			return $this->get_cached_instance( (int) $group_id, $new_type );
		}

		$group_type = '' !== $new_type ? $new_type : $this->get_group_type( $group_id );
		$classname  = wp_advads()->groups->types->get_classname_for_type( $group_type );
		try {
			$group = new $classname( $group_id );
		} catch ( Exception $e ) {
			return false;
		}

		$this->store_cached_instance( (int) $group_id, $new_type, $group );

		return $group;
	}

	/**
	 * Get the type of the group.
	 *
	 * @param int $group_id Group ID.
	 *
	 * @return string The type of the group.
	 */
	private function get_group_type( $group_id ): string {
		// Allow the overriding of the lookup in this function. Return the group type here.
		$override = apply_filters( 'advanced-ads-group-type', false, $group_id );
		if ( $override ) {
			return $override;
		}

		$type = get_term_meta( $group_id, Group_Repository::TYPE_METAKEY, true );

		if ( empty( $type ) ) {
			$meta_values = get_term_meta( $group_id, Group_Repository::OPTION_METAKEY, true );

			if ( is_array( $meta_values ) && ! empty( $meta_values['type'] ) ) {
				$type = $meta_values['type'];
			}
		}

		return $this->normalize_group_type( $type );
	}

	/**
	 * Normalize stored group type to a registered type slug.
	 *
	 * @param string $type Raw type from meta or legacy storage.
	 *
	 * @return string
	 */
	private function normalize_group_type( string $type ): string {
		if ( empty( $type ) || 'refresh' === $type ) {
			return 'default';
		}

		return $type;
	}

	/**
	 * Get the group ID depending on what was passed.
	 *
	 * @param Group|WP_Term|int|bool $group Group instance, term instance or numeric.
	 *
	 * @return int|bool false on failure
	 */
	private function get_group_id( $group ) {
		if ( is_numeric( $group ) ) {
			return $group;
		}

		if ( is_a_group( $group ) ) {
			return $group->get_id();
		}

		if ( ! empty( $group->term_id ) ) {
			return $group->term_id;
		}

		return false;
	}
}
