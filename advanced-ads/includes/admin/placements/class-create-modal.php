<?php
/**
 * Placement Create Modal.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.50.0
 */

namespace AdvancedAds\Admin\Placements;

use AdvancedAds\Constants;
use AdvancedAds\Entities;
use AdvancedAds\Modal;

defined( 'ABSPATH' ) || exit;

/**
 * Placement Create Modal.
 */
class Create_Modal {

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'admin_footer', [ $this, 'render_modal' ] );
	}

	/**
	 * Load the modal for creating a new Placement.
	 *
	 * @return void
	 */
	public function render_modal(): void {
		$counts = (array) wp_count_posts( Constants::POST_TYPE_PLACEMENT, 'readable' );
		unset( $counts['trash'], $counts['auto-draft'] );

		ob_start();
		$placements_description = 0 === array_sum( $counts ) ? Entities::get_placement_description() : '';
		include ADVADS_ABSPATH . 'views/admin/placements/create-modal/new-modal-content.php';

		// @TODO Add old JS validation errors to the Modal - advads_validate_new_form in `admin/assets/js/admin.js`.
		Modal::create(
			[
				'modal_slug'       => 'placement-new',
				'modal_content'    => ob_get_clean(),
				'modal_title'      => __( 'New Placement', 'advanced-ads' ),
				'close_action'     => __( 'Save New Placement', 'advanced-ads' ),
				'close_form'       => 'advads-placements-new-form',
				'close_validation' => 'advads_validate_new_form',
			]
		);
	}
}
