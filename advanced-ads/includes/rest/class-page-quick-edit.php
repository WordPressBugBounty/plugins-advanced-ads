<?php
/**
 * Rest Page Quick Edit.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.50.0
 */

namespace AdvancedAds\Rest;

use AdvancedAds\Constants;
use AdvancedAds\Utilities\Conditional;
use AdvancedAds\Framework\Interfaces\Routes_Interface;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Rest Page Quick Edit.
 */
class Page_Quick_Edit implements Routes_Interface {
	/**
	 * Register rest route for disabled ads status
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			Constants::REST_BASE,
			'/page_quick_edit',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_disable_ads' ],
				'permission_callback' => [ $this, 'can_read_quick_edit_settings' ],
				'args'                => [
					'id'    => [
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
					'nonce' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	/**
	 * Whether the current user may read quick-edit settings for the requested post.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return bool
	 */
	public function can_read_quick_edit_settings( WP_REST_Request $request ): bool {
		if ( ! Conditional::user_can( 'edit_posts' ) ) {
			return false;
		}

		$post_id = absint( $request->get_param( 'id' ) );
		if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}

		$post = get_post( $post_id );
		if ( ! $post || ! in_array( $post->post_type, [ 'post', 'page' ], true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Endpoint callback
	 *
	 * @param WP_REST_Request $request the request.
	 *
	 * @return array
	 */
	public function get_disable_ads( WP_REST_Request $request ) {
		$nonce = sanitize_text_field( $request->get_param( 'nonce' ) );

		if ( ! wp_verify_nonce( $nonce, 'advads-post-quick-edit' ) ) {
			return [];
		}

		$id = absint( $request->get_param( 'id' ) );

		return (array) get_post_meta( $id, '_advads_ad_settings', true );
	}
}
