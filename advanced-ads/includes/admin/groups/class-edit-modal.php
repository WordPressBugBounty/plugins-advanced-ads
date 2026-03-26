<?php
/**
 * Group Edit Modal.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.50.0
 */

namespace AdvancedAds\Admin\Groups;

use AdvancedAds\Framework\Interfaces\Integration_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Group Edit Modal.
 */
class Edit_Modal implements Integration_Interface {

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
	}
}
