<?php
/**
 * The placement factory.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.50.0
 */

namespace AdvancedAds\Placements;

use Exception;
use AdvancedAds\Constants;
use AdvancedAds\Abstracts\Factory;
use AdvancedAds\Abstracts\Placement;

defined( 'ABSPATH' ) || exit;

/**
 * Placements Factory.
 */
class Placement_Factory extends Factory {

	/**
	 * Create an empty placement object
	 *
	 * @param string $type Type of placement to create.
	 *
	 * @return Placement|bool Placement object or false if the placement type not found.
	 */
	public function create_placement( $type = 'default' ) {
		return $this->create_empty_from_type( wp_advads_get_placement_type( $type ) );
	}

	/**
	 * Get the placement object.
	 *
	 * @param Placement|WP_Post|int|bool $placement_id Placement instance, post instance, numeric or false to use global $post.
	 * @param string                     $new_type     Change type of placement.
	 *
	 * @return Placement|bool Placement object or false if the placement cannot be loaded.
	 */
	public function get_placement( $placement_id = false, $new_type = '' ) {
		$placement_id = $this->get_placement_id( $placement_id );

		if ( ! $placement_id ) {
			return false;
		}

		if ( $this->has_cached_instance( (int) $placement_id, $new_type ) ) {
			return $this->get_cached_instance( (int) $placement_id, $new_type );
		}

		$placement_type = '' !== $new_type ? $new_type : $this->get_placement_type( $placement_id );
		$classname      = wp_advads()->placements->types->get_classname_for_type( $placement_type );
		try {
			$placement = new $classname( $placement_id );
		} catch ( Exception $e ) {
			return false;
		}

		$this->store_cached_instance( (int) $placement_id, $new_type, $placement );

		return $placement;
	}

	/**
	 * Get the type of the placement.
	 *
	 * @param int $placement_id Placement ID.
	 *
	 * @return string The type of the placement.
	 */
	private function get_placement_type( $placement_id ): string {
		// Allow the overriding of the lookup in this function. Return the placement type here.
		$override = apply_filters( 'advanced-ads-placement-type', false, $placement_id );
		if ( $override ) {
			return $override;
		}

		$options = get_post_meta( $placement_id, 'type', true );
		return $options['type'] ?? 'default';
	}

	/**
	 * Get the placement ID depending on what was passed.
	 *
	 * @param Placement|WP_Post|int|bool $placement Placement instance, post instance, numeric or false to use global $post.
	 *
	 * @return int|bool false on failure
	 */
	private function get_placement_id( $placement ) {
		global $post;

		if ( false === $placement && isset( $post, $post->ID ) && Constants::POST_TYPE_AD === get_post_type( $post->ID ) ) {
			return absint( $post->ID );
		}

		if ( is_numeric( $placement ) ) {
			return $placement;
		}

		if ( is_a_placement( $placement ) ) {
			return $placement->get_id();
		}

		if ( ! empty( $placement->ID ) ) {
			return $placement->ID;
		}

		return false;
	}
}
