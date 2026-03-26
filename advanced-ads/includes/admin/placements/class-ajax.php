<?php
/**
 * Ajax functions.
 *
 * @since   2.0.14
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

namespace AdvancedAds\Admin\Placements;

use AdvancedAds\Utilities\Conditional;
use AdvancedAds\Framework\Utilities\Params;
use AdvancedAds\Framework\Interfaces\Integration_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Ajax functions for placements.
 */
class Ajax implements Integration_Interface {

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'wp_ajax_advads_placements_allowed_ads', [ $this, 'get_allowed_items' ] );
		add_action( 'wp_ajax_advads_placement_update_item', [ $this, 'placement_update_item' ] );
	}

	/**
	 * Get allowed ads per placement.
	 *
	 * @return void
	 */
	public function get_allowed_items(): void {
		check_ajax_referer( 'advads-create-new-placement', 'security' );
		$type = sanitize_text_field( Params::post( 'placement_type' ) );

		wp_send_json_success(
			[
				'items' => wp_advads_get_placement_type( $type )->get_allowed_items(),
			]
		);
	}

	/**
	 * Update the item for the placement.
	 *
	 * @return void
	 */
	public function placement_update_item(): void {
		check_ajax_referer( 'placement-update-item', 'security' );

		if ( ! Conditional::user_can( 'advanced_ads_manage_placements' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'User don\'t have permission to update the placement.', 'advanced-ads' ),
				],
				400
			);
		}

		$placement     = wp_advads_get_placement( Params::post( 'placement_id', false, FILTER_VALIDATE_INT ) );
		$new_item      = sanitize_text_field( Params::post( 'item_id' ) );
		$new_item_type = 0 === strpos( $new_item, 'ad' ) ? 'ad_' : 'group_';

		try {
			if ( empty( $new_item ) ) {
				$placement->remove_item();
				wp_send_json_success(
					[
						'edit_href'    => '#',
						'placement_id' => $placement->get_id(),
						'item_id'      => '',
					]
				);
			}

			$new_item = $placement->update_item( $new_item );
			wp_send_json_success(
				[
					'edit_href'    => $new_item->get_edit_link(),
					'placement_id' => $placement->get_id(),
					'item_id'      => $new_item_type . $new_item->get_id(),
				]
			);
		} catch ( \RuntimeException $e ) {
			wp_send_json_error(
				[
					'message' => $e->getMessage(),
					'item_id' => $placement->get_item_object() ? $placement->get_item_object()->get_id() : 0,
				],
				400
			);
		}
	}
}
