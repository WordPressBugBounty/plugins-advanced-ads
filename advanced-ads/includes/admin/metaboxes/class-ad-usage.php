<?php
/**
 * Ad Usage.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.48.2
 */

namespace AdvancedAds\Admin\Metaboxes;

use AdvancedAds\Constants;

defined( 'ABSPATH' ) || exit;

/**
 * Ad Usage.
 */
class Ad_Usage {

	/**
	 * Get metabox id
	 *
	 * @return string
	 */
	public function get_box_id(): string {
		return 'ad-usage-box';
	}

	/**
	 * Hook into WordPress.
	 *
	 * @param \AdvancedAds\Admin\Metabox_Ad $manager Manager instance.
	 *
	 * @return void
	 */
	public function register( $manager ): void {
		global $post;

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		if ( 'edit' === $post->filter ) {
			add_meta_box(
				$this->get_box_id(),
				__( 'Usage', 'advanced-ads' ),
				[ $manager, 'display' ],
				Constants::POST_TYPE_AD,
				'normal',
				'high'
			);
		}
	}

	/**
	 * Get metaboxe view file
	 *
	 * @param mixed $ad Ad instance.
	 *
	 * @return string
	 */
	public function get_view( $ad = null ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		return ADVADS_ABSPATH . 'views/admin/metaboxes/ads/ad-usage.php';
	}

	/**
	 * Return manual link
	 *
	 * @return array<string, mixed>|string
	 */
	public function get_handle_link() {
		return '';
	}
}
