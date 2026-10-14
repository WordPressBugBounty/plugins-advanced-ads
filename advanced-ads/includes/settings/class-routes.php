<?php
/**
 * REST routes for the settings framework.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.0.0
 */

namespace AdvancedAds\Settings;

use AdvancedAds\Constants;
use AdvancedAds\Framework\Interfaces\Routes_Interface;
use AdvancedAds\Utilities\Conditional;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Routes class.
 */
class Routes implements Routes_Interface {

	/**
	 * Validator instance.
	 *
	 * @var Validator
	 */
	private $validator;

	/**
	 * Routes constructor.
	 */
	public function __construct() {
		$this->validator = new Validator();
	}

	/**
	 * Registers routes with WordPress.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			Constants::REST_BASE,
			'/settings',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_settings' ],
					'permission_callback' => [ $this, 'can_manage' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'save_settings' ],
					'permission_callback' => [ $this, 'can_manage' ],
					'args'                => [
						'values' => [
							'required' => true,
							'type'     => 'object',
						],
					],
				],
			]
		);
	}

	/**
	 * Permission check: same capability as the legacy settings page.
	 *
	 * @return bool
	 */
	public function can_manage(): bool {
		return Conditional::user_can( 'advanced_ads_manage_options' );
	}

	/**
	 * GET /settings — schema, current values, and system context.
	 *
	 * @return array{tabs: array, values: array, context: array}
	 */
	public function get_settings(): array {
		$registry = wp_advads()->settings_registry;

		return array_merge(
			$registry->to_schema(),
			[
				'values'  => $registry->get_values(),
				'context' => $registry->get_context(),
			]
		);
	}

	/**
	 * POST /settings — validate every submitted field, then batch-save per store.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return array{values: array}|WP_Error
	 */
	public function save_settings( WP_REST_Request $request ) {
		$registry = wp_advads()->settings_registry;
		$values   = (array) $request->get_param( 'values' );

		$errors   = [];
		$by_store = [];

		foreach ( $values as $id => $value ) {
			$field = $registry->get_field( $id );

			if ( null === $field ) {
				continue; // Unknown id: ignore rather than error, schema may be ahead of an older client.
			}

			if (
				in_array( $field['type'], [ 'button', 'raw' ], true )
				|| ! empty( $field['locked'] )
				|| ! empty( $field['isUpgradeable'] )
			) {
				continue;
			}

			$error = $this->validator->validate( $field, $value );

			if ( null !== $error ) {
				$errors[ $id ] = $error;
				continue;
			}

			$by_store[ $field['store'] ][ $field['key'] ] = $this->validator->sanitize( $field, $value );
		}

		if ( ! empty( $errors ) ) {
			return new WP_Error(
				'advanced_ads_settings_invalid',
				__( 'One or more fields are invalid.', 'advanced-ads' ),
				[
					'status' => 400,
					'errors' => $errors,
				]
			);
		}

		foreach ( $by_store as $store => $pairs ) {
			$registry->store->save( $store, $pairs );
		}

		return [ 'values' => $registry->get_values() ];
	}
}
