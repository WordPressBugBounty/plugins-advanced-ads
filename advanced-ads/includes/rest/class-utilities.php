<?php
/**
 * Rest Utilities.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.50.0
 */

namespace AdvancedAds\Rest;

use AdvancedAds\Constants;
use AdvancedAds\Framework\Interfaces\Routes_Interface;
use AdvancedAds\Utilities\Conditional;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Rest Utilities.
 */
class Utilities implements Routes_Interface {

	/**
	 * Remote support content sources keyed by transient/cache id.
	 *
	 * @var array<string, string>
	 */
	private const SUPPORT_LINK_SOURCES = [
		'advads_support_getting_started_links'  => '/bwl_kb?bkb_category=32&_fields=title,link&orderby=date&order=desc',
		'advads_support_latest_tutorials_links' => '/posts?category=1&_fields=title,link&orderby=date&order=desc&per_page=4',
		'advads_support_articles_links'         => '/bwl_kb?_fields=title,link&orderby=date&order=desc&per_page=4',
	];

	/**
	 * Registers routes with WordPress.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			Constants::REST_BASE,
			'/user-email',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_user_email' ],
				'permission_callback' => function () {
					return Conditional::user_can( 'advanced_ads_edit_ads' );
				},
			]
		);

		register_rest_route(
			Constants::REST_BASE,
			'/support-links',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'get_support_links' ],
				'permission_callback' => function () {
					return Conditional::user_can( 'advanced_ads_manage_options' );
				},
				'args'                => [
					'cache_key' => [
						'required'          => true,
						'type'              => 'string',
						'enum'              => array_keys( self::SUPPORT_LINK_SOURCES ),
						'sanitize_callback' => 'sanitize_key',
					],
				],
			]
		);
	}

	/**
	 * Retrieves the user email address.
	 *
	 * @return string Loggedin user email address.
	 */
	public function get_user_email() {
		return wp_get_current_user()->user_email;
	}

	/**
	 * Retrieves cached support links from wpadvancedads.com.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_support_links( WP_REST_Request $request ) {
		$cache_key = (string) $request->get_param( 'cache_key' );
		$endpoint  = self::SUPPORT_LINK_SOURCES[ $cache_key ] ?? '';

		if ( '' === $endpoint ) {
			return new WP_Error(
				'advanced_ads_invalid_support_source',
				__( 'Unknown support link source.', 'advanced-ads' ),
				[ 'status' => 400 ]
			);
		}

		$cache = get_transient( $cache_key );
		if ( is_array( $cache ) && ! empty( $cache ) ) {
			return rest_ensure_response( $cache );
		}

		$response = wp_remote_get( 'https://wpadvancedads.com/wp-json/wp/v2' . $endpoint );
		if ( is_wp_error( $response ) ) {
			return rest_ensure_response( [] );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data ) || ! is_array( $data ) ) {
			return rest_ensure_response( [] );
		}

		set_transient( $cache_key, $data, DAY_IN_SECONDS );

		return rest_ensure_response( $data );
	}
}
