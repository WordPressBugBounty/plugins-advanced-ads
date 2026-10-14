<?php
/**
 * Licenses Rest route and endpoints.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.50.0
 */

namespace AdvancedAds\Rest;

use AdvancedAds\Updater\Auto_Update;
use AdvancedAds\Constants;
use AdvancedAds\Framework\Interfaces\Routes_Interface;
use AdvancedAds\License\License;
use AdvancedAds\License\License_Shop_Client;
use AdvancedAds\License\License_Site_Activation;
use AdvancedAds\License\License_Utils;
use AdvancedAds\Utilities\Conditional;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
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
						'default'  => Auto_Update::MAIN_ADDON_KEY,
					],
					'state'   => [
						'required' => true,
						'type'     => 'string',
						'enum'     => [ Auto_Update::STATE_ON, Auto_Update::STATE_OFF ],
					],
				],
			]
		);

		register_rest_route(
			Constants::REST_BASE,
			'/licenses/sync',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'sync_licenses' ],
				'permission_callback' => [ $this, 'can_manage' ],
			]
		);

		register_rest_route(
			Constants::REST_BASE,
			'/licenses/disconnect',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'disconnect_account' ],
				'permission_callback' => [ $this, 'can_manage' ],
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
	 * @return array{licenses: array<int, array<string, mixed>>, appliedAddonKeyMap: array<string, string>, currentActiveLicenses: string, autoUpdateStates: array<string, string>, addonInstallStates: array<string, array{installed: bool, active: bool}>, lastSyncAt: mixed, expiryNoticeFlags: mixed}
	 */
	public function get_licenses(): array {
		License::maybe_complete_legacy_license_migration();

		$rich     = License::get_licenses();
		$hostname = License::get_site_hostname();
		$before   = wp_json_encode( $rich );

		// Heal Sites count when advanced-ads-licenses marks a key active but sitesActivated is empty.
		if ( '' !== $hostname ) {
			foreach ( License_Site_Activation::get_active_license_keys() as $license_key ) {
				$row = License_Utils::get_rich_license_row_by_key( $rich, $license_key );
				if ( ! is_array( $row ) ) {
					continue;
				}
				if ( License::is_site_activated_on_license( $row, $hostname ) ) {
					continue;
				}
				$rich = License::ensure_local_site_slot_on_license( $rich, $license_key );
			}

			if ( wp_json_encode( $rich ) !== $before ) {
				update_option( License::OPTION_RICH, $rich, false );
			}
		}

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
	 * @return array{licenses: array<int, array<string, mixed>>, appliedAddonKeyMap: array<string, string>, currentActiveLicenses: string, autoUpdateStates: array<string, string>, addonInstallStates: array<string, array{installed: bool, active: bool}>, lastSyncAt: mixed, expiryNoticeFlags: mixed}|WP_Error
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
			[
				'activate_new'             => $activate,
				'activating_license_key'   => $activating_license_key,
				'activating_addon_id'      => $activating_addon_id,
				'install_only'             => $install_only,
				'deactivating_addon_id'    => $deactivating_addon_id,
				'deactivating_license_key' => $deactivating_license_key,
			]
		);

		if ( is_wp_error( $rich ) ) {
			return $rich;
		}

		return $this->licenses_api_response( $rich );
	}

	/**
	 * Toggle per-plugin auto-update.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array{addonId: string, state: string, autoUpdateStates: array<string, string>}|WP_Error
	 */
	public function set_plugin_autoupdate( WP_REST_Request $request ) {
		$addon_id = (string) $request->get_param( 'addonId' );
		$state    = (string) $request->get_param( 'state' );

		$normalized = Auto_Update::MAIN_ADDON_KEY === $addon_id || '' === $addon_id
			? null
			: sanitize_key( $addon_id );

		$result = Auto_Update::set_state( $normalized, $state );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$response_key = null === $normalized ? Auto_Update::MAIN_ADDON_KEY : $normalized;

		return [
			'addonId'          => $response_key,
			'state'            => Auto_Update::get_state( $normalized ),
			'autoUpdateStates' => Auto_Update::get_all_states(),
		];
	}

	/**
	 * Sync persisted licenses with the shop.
	 *
	 * @return array{licenses: array<int, array<string, mixed>>, appliedAddonKeyMap: array<string, string>, currentActiveLicenses: string, autoUpdateStates: array<string, string>, addonInstallStates: array<string, array{installed: bool, active: bool}>, lastSyncAt: mixed, expiryNoticeFlags: mixed}|WP_Error
	 */
	public function sync_licenses() {
		$rich = License::get_licenses();
		$site = License::get_site_hostname();

		foreach ( License::normalize_list( $rich ) as $row ) {
			$key = trim( (string) ( $row['licenseKey'] ?? '' ) );

			if ( '' === $key ) {
				continue;
			}

			$result = License_Shop_Client::exchange(
				[
					'license' => $key,
					'site'    => $site,
				]
			);

			if ( is_wp_error( $result ) ) {
				if ( 'not_found' === $result->get_error_code() ) {
					$rich = array_values(
						array_filter(
							$rich,
							static function ( $item ) use ( $key ) {
								return trim(
									(string) ( $item['licenseKey'] ?? '' )
								) !== $key;
							}
						)
					);

					continue;
				}

				return $result;
			}

			if ( is_array( $result ) && [] !== $result ) {
				$rich = License::merge_license_lists(
					$rich,
					License::normalize_list( $result )
				);
			}
		}

		update_option( License::OPTION_RICH, $rich, false );

		$rich = License::reconcile_persisted_licenses(
			$rich,
			false,
			false
		);
		$rich = License::finalize_license_sync( $rich );

		return $this->licenses_api_response( $rich );
	}

	/**
	 * Disconnect the currently connected account by removing all locally stored
	 * license data. This does not remove any license from the shop itself.
	 *
	 * @return WP_REST_Response Confirmation response with empty license state.
	 */
	public function disconnect_account(): WP_REST_Response {
		// Remove all locally stored license data.
		delete_option( License::OPTION_RICH );
		delete_option( License::OPTION_SITE_ACTIVATION );
		delete_option( License::OPTION_AA_ACTIVATED_ADDONS );
		delete_option( License::OPTION_FLAT_MAP_RETIRED );

		// Remove legacy per-addon mirror options (status/expiry).
		License::delete_legacy_addon_mirror_options();

		// Return the now-empty license state so the UI resets.
		return new WP_REST_Response( $this->licenses_api_response( [] ), 200 );
	}

	/**
	 * REST payload: rich rows plus derived addon key map / site activation state.
	 *
	 * @param array<int, array<string, mixed>> $rich Rich license list.
	 * @return array{licenses: array<int, array<string, mixed>>, appliedAddonKeyMap: array<string, string>, currentActiveLicenses: string, autoUpdateStates: array<string, string>, addonInstallStates: array<string, array{installed: bool, active: bool}>, lastSyncAt: mixed, expiryNoticeFlags: mixed}
	 */
	private function licenses_api_response( array $rich ): array {
		return [
			'licenses'              => $rich,
			'appliedAddonKeyMap'    => License::get_addon_key_map(),
			'currentActiveLicenses' => implode( ',', License_Site_Activation::get_active_license_keys() ),
			'autoUpdateStates'      => Auto_Update::get_all_states(),
			'addonInstallStates'    => License::get_addon_install_states(),
			'lastSyncAt'            => License_Utils::get_last_sync(),
			'expiryNoticeFlags'     => License_Utils::get_expiry_notice_flags(),
		];
	}
}
