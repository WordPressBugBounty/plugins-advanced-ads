<?php
/**
 * Licenses Rest route and endpoints.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.50.0
 */

namespace AdvancedAds\Rest;

use AdvancedAds\Admin\Plugin_Auto_Update;
use AdvancedAds\Constants;
use AdvancedAds\Framework\Interfaces\Routes_Interface;
use AdvancedAds\License\License;
use AdvancedAds\License\License_Utils;
use AdvancedAds\Utilities\Conditional;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Rest Licenses.
 */
class Licenses implements Routes_Interface {
	/**
	 * Registers routes with WordPress.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			Constants::REST_BASE,
			'/licenses',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_licenses' ],
					'permission_callback' => [ $this, 'can_manage' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'set_licenses' ],
					'permission_callback' => [ $this, 'can_manage' ],
					'args'                => [
						'licenses'               => [
							'required' => true,
							'type'     => 'array',
						],
						'activate'               => [
							'required' => false,
							'type'     => 'boolean',
							'default'  => false,
						],
						'activatingLicenseKey'   => [
							'required' => false,
							'type'     => 'string',
							'default'  => '',
						],
						'activatingAddonId'      => [
							'required' => false,
							'type'     => 'string',
							'default'  => '',
						],
						'installOnly'            => [
							'required' => false,
							'type'     => 'boolean',
							'default'  => false,
						],
						'deactivatingAddonId'    => [
							'required' => false,
							'type'     => 'string',
							'default'  => '',
						],
						'deactivatingLicenseKey' => [
							'required' => false,
							'type'     => 'string',
							'default'  => '',
						],
					],
				],
			]
		);

		register_rest_route(
			Constants::REST_BASE,
			'/plugin-autoupdate',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'set_plugin_autoupdate' ],
				'permission_callback' => [ $this, 'can_manage' ],
				'args'                => [
					'addonId' => [
						'required' => false,
						'type'     => 'string',
						'default'  => Plugin_Auto_Update::MAIN_ADDON_KEY,
					],
					'state'   => [
						'required' => true,
						'type'     => 'string',
						'enum'     => [ Plugin_Auto_Update::STATE_ON, Plugin_Auto_Update::STATE_OFF ],
					],
				],
			]
		);
	}

	/**
	 * Permission callback.
	 *
	 * @return bool
	 */
	public function can_manage(): bool {
		return Conditional::user_can( 'advanced_ads_manage_options' );
	}

	/**
	 * Get persisted licenses.
	 *
	 * @return array
	 */
	public function get_licenses(): array {
		License::maybe_complete_legacy_license_migration();

		$rich = License::get_licenses();

		// Passive reconcile on read: mirror addon status only; never reshuffle activations.
		$rich = License::reconcile_persisted_licenses( $rich, false, false );
		$rich = License::finalize_license_sync( $rich );

		return $this->licenses_api_response( $rich );
	}

	/**
	 * Persist licenses.
	 *
	 * @param WP_REST_Request $request the request object.
	 *
	 * @return array|WP_Error
	 */
	public function set_licenses( WP_REST_Request $request ) {
		$licenses = $request->get_param( 'licenses' );
		$licenses = is_array( $licenses ) ? $licenses : [];
		$licenses = wp_unslash( $licenses );

		$activate                 = (bool) $request->get_param( 'activate' );
		$activating_license_key   = (string) $request->get_param( 'activatingLicenseKey' );
		$activating_license_key   = sanitize_text_field( $activating_license_key );
		$activating_addon_id      = (string) $request->get_param( 'activatingAddonId' );
		$activating_addon_id      = sanitize_key( $activating_addon_id );
		$install_only             = (bool) $request->get_param( 'installOnly' );
		$deactivating_addon_id    = (string) $request->get_param( 'deactivatingAddonId' );
		$deactivating_addon_id    = sanitize_key( $deactivating_addon_id );
		$deactivating_license_key = (string) $request->get_param( 'deactivatingLicenseKey' );
		$deactivating_license_key = sanitize_text_field( $deactivating_license_key );

		$rich = License::save_licenses(
			$licenses,
			$activate,
			$activating_license_key,
			$activating_addon_id,
			$install_only,
			$deactivating_addon_id,
			$deactivating_license_key
		);

		if ( is_wp_error( $rich ) ) {
			return $rich;
		}

		return $this->licenses_api_response( $rich );
	}

	/**
	 * Toggle per-plugin auto-update (advanced-ads-{addon}-autoupdate).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public function set_plugin_autoupdate( WP_REST_Request $request ) {
		$addon_id = (string) $request->get_param( 'addonId' );
		$state    = (string) $request->get_param( 'state' );

		$normalized = Plugin_Auto_Update::MAIN_ADDON_KEY === $addon_id || '' === $addon_id
			? null
			: sanitize_key( $addon_id );

		$result = Plugin_Auto_Update::set_state( $normalized, $state );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$response_key = null === $normalized ? Plugin_Auto_Update::MAIN_ADDON_KEY : $normalized;

		return [
			'addonId'          => $response_key,
			'state'            => Plugin_Auto_Update::get_state( $normalized ),
			'autoUpdateStates' => Plugin_Auto_Update::get_all_states(),
		];
	}

	/**
	 * REST payload: rich rows plus legacy addon key map (advanced-ads-licenses).
	 *
	 * @param array<int, array<string, mixed>> $rich Rich license list.
	 * @return array{licenses: array<int, array<string, mixed>>, appliedAddonKeyMap: array<string, string>}
	 */
	private function licenses_api_response( array $rich ): array {
		$rich = License::normalize_rich_license_list( $rich );

		return [
			'licenses'           => $rich,
			'appliedAddonKeyMap' => License::get_addon_key_map(),
			'autoUpdateStates'   => Plugin_Auto_Update::get_all_states(),
			'addonInstallStates' => License::get_addon_install_states(),
			'lastSyncAt'         => License_Utils::get_last_sync(),
			'expiryNoticeFlags'  => License_Utils::get_expiry_notice_flags(),
		];
	}
}
