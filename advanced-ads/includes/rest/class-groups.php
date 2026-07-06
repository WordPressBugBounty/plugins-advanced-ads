<?php
/**
 * Groups Rest route and endpoints
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.50.0
 */

namespace AdvancedAds\Rest;

use WP_REST_Server;
use AdvancedAds\Constants;
use AdvancedAds\Utilities\Conditional;
use AdvancedAds\Framework\Interfaces\Routes_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Rest Groups.
 */
class Groups implements Routes_Interface {
	/**
	 * Registers routes with WordPress.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			Constants::REST_BASE,
			'/group',
			[
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'call_endpoint' ],
				'permission_callback' => function () {
					return Conditional::user_can( 'advanced_ads_edit_ads' );
				},
			]
		);
	}

	/**
	 * Run a callback depending on the request method.
	 *
	 * @param \WP_REST_Request $request the request.
	 *
	 * @return array
	 */
	public function call_endpoint( $request ): array {
		switch ( $request->get_method() ) {
			case 'POST':
				return $this->create( $request );
			case 'PUT':
				return $this->update( $request );
			case 'DELETE':
				return $this->delete( $request );
			default:
		}

		return [ 'error' => esc_html__( 'No endpoint found', 'advanced-ads' ) ];
	}

	/**
	 * Delete a group
	 *
	 * @param \WP_REST_Request $request the request.
	 *
	 * @return array
	 */
	public function delete( $request ): array {
		$payload = json_decode( $request->get_body(), true );

		if ( ! wp_verify_nonce( $payload['nonce'], "delete-tag_{$payload['id']}" ) ) {
			return [ 'error' => esc_html__( 'Sorry, you are not allowed to access this feature.', 'advanced-ads' ) ];
		}

		$group = wp_advads_get_group( $payload['id'] );

		if ( $group ) {
			$group->delete();
		}

		return [ 'done' => true ];
	}

	/**
	 * Create a new group
	 *
	 * @param \WP_REST_Request $request the request.
	 *
	 * @return array
	 */
	public function create( $request ): array {
		$payload = $this->get_payload( $request );

		if ( ! wp_verify_nonce( $payload['nonce'], 'advads-create-group' ) ) {
			return [ 'error' => esc_html__( 'Invalid nonce', 'advanced-ads' ) ];
		}

		$group_name = sanitize_text_field( $payload['advads-group-name'] );

		if ( empty( $group_name ) ) {
			return [ 'reload' => true ];
		}

		$posted_type = $payload['advads-group-type'] ?? 'default';

		if ( ! wp_advads_has_group_type( $posted_type ) ) {
			$posted_type = 'default';
		}

		$group = wp_advads_create_new_group( $posted_type );
		$group->set_name( sanitize_text_field( wp_unslash( $group_name ) ) );
		$group->set_type( $posted_type );
		$group->set_ad_count( 1 );
		$group->set_options( [] );
		$group->save();

		return [
			'action'     => 'create',
			'group_data' => $group->get_data(),
		];
	}

	/**
	 * Update an existing group
	 *
	 * @param \WP_REST_Request $request the request.
	 *
	 * @return array
	 */
	public function update( $request ): array {
		$payload = $this->get_payload( $request );

		if ( ! wp_verify_nonce( $payload['nonce'] ?? '', 'advads-update-group' ) ) {
			return [ 'error' => esc_html__( 'Invalid nonce', 'advanced-ads' ) ];
		}

		$groups_data = wp_unslash( $payload['advads-groups'] ?? [] );
		$data        = is_array( $groups_data ) ? reset( $groups_data ) : null;

		if ( ! is_array( $data ) ) {
			return [ 'error' => esc_html__( 'Invalid group data.', 'advanced-ads' ) ];
		}

		$group = wp_advads_get_group( absint( $data['id'] ?? 0 ) );

		if ( ! $group ) {
			return [ 'error' => esc_html__( 'Group not found.', 'advanced-ads' ) ];
		}

		$name = sanitize_text_field( $data['name'] ?? '' );

		if ( '' !== $name ) {
			$group->set_name( $name );
		}

		$type = $data['type'] ?? $group->get_type();

		if ( ! wp_advads_has_group_type( $type ) ) {
			$type = 'default';
		}

		$group->set_type( $type );
		$group->set_ad_count( $data['ad_count'] ?? $group->get_ad_count() );

		$options = $data['options'] ?? [];
		$group->set_options( is_array( $options ) ? $options : [] );
		$group->set_ad_weights( $this->sanitize_ad_weights( $data['ads'] ?? [] ) );
		$group->save();

		return [
			'action'     => 'update',
			'group_data' => $group->get_data(),
			'reload'     => true,
		];
	}

	/**
	 * Sanitize ad weights from REST payload.
	 *
	 * @param mixed $ads Raw ads payload.
	 *
	 * @return array<int, int>
	 */
	private function sanitize_ad_weights( $ads ): array {
		if ( ! is_array( $ads ) ) {
			return [];
		}

		$weights = [];

		foreach ( $ads as $ad_id => $weight ) {
			$ad_id = absint( $ad_id );

			if ( $ad_id > 0 ) {
				$weights[ $ad_id ] = absint( $weight );
			}
		}

		return $weights;
	}

	/**
	 * Get variable serialized with `jQuery.serialize()`
	 *
	 * @param \WP_REST_Request $request the request.
	 *
	 * @return array
	 */
	private function get_payload( $request ): array {
		$body = json_decode( $request->get_body(), JSON_UNESCAPED_UNICODE );
		parse_str( $body['fields'], $payload );

		return $payload;
	}
}
