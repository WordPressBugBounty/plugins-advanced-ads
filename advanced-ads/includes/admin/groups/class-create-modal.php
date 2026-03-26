<?php
/**
 * Group Create Modal.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.50.0
 */

namespace AdvancedAds\Admin\Groups;

use AdvancedAds\Modal;
use AdvancedAds\Entities;
use AdvancedAds\Framework\Interfaces\Integration_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Group Create Modal.
 */
class Create_Modal implements Integration_Interface {

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'admin_footer', [ $this, 'render_modal' ] );
	}

	/**
	 * Load the modal for creating a new Group.
	 *
	 * @return void
	 */
	public function render_modal(): void {
		Modal::create_from_file(
			[
				'modal_slug'       => 'group-new',
				'modal_title'      => __( 'New Ad Group', 'advanced-ads' ),
				'close_validation' => 'advads_validate_new_form',
			],
			ADVADS_ABSPATH . 'views/admin/groups/create-modal.php'
		);
	}
}
