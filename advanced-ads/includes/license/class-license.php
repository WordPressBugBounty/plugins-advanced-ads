<?php
/**
 * License class.
 * Handles license management for Advanced Ads add-ons.
 *
 * @since   2.0.17
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

namespace AdvancedAds\License;

use AdvancedAds\Crons\Licenses as License_Cron;
use AdvancedAds\Utilities\Addons;
use AdvancedAds\Utilities\Data;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * License persistence, shop sync, and add-on install/activation on this site.
 *
 * Implementation is split across focused classes; this file remains the public facade:
 * - {@see License_Shop_Client} — shop REST + local `.test` HTTP bypass
 * - {@see License_Site_Activation} — plan-level site activation list
 * - {@see License_Package_Installer} — add-on zip download/install
 * - {@see License_Exchange} — legacy key / token exchange
 * - {@see License_Product_Map} — product name ↔ add-on id
 * - {@see License_Utils} — stateless helpers
 *
 * Public surface used by {@see \AdvancedAds\Rest\Licenses} and admin flows:
 *
 * - {@see self::get_licenses()} — rich license rows (`OPTION_RICH`).
 * - {@see self::save_licenses()} — merge incoming rows; optional `activating_license_key`,
 *   `activating_addon_id` (per add-on install/activate), `install_only` (download package only),
 *   `deactivating_addon_id`, `deactivating_license_key` (remove site from a license card).
 * - {@see self::get_addon_key_map()} — persisted addon id => license key for REST/UI
 *   (`build_persisted_addon_key_map_from_rich`, user-activated All Access add-ons only).
 * - {@see self::get_addon_install_states()} — per add-on `installed` / `active` for the Licenses UI.
 * - {@see self::get_aa_activated_addon_ids()} — add-ons explicitly activated under All Access on this site.
 * - {@see self::reconcile_persisted_licenses()} — passive on GET (addon mirrors only); mutates assignments on save when appropriate.
 */
class License {

	/**
	 * Legacy flat option: addon id => license key (string) or legacy hybrid array rows.
	 */
	public const OPTION_LEGACY_MAP = 'advanced-ads-licenses';

	/**
	 * Rich license records (app / exchange).
	 */
	public const OPTION_RICH = 'advanced-ads-app-licenses';

	/**
	 * Set to '1' when legacy → rich migration finished successfully.
	 *
	 * @deprecated Use {@see self::OPTION_FLAT_MAP_RETIRED}.
	 */
	public const OPTION_MIGRATION_DONE = 'advanced_ads_licenses_migration';

	/**
	 * Set to '1' when advanced-ads-licenses flat map has been retired.
	 */
	public const OPTION_FLAT_MAP_RETIRED = 'advanced_ads_licenses_flat_map_retired';

	/**
	 * All Access add-on ids the user activated individually on this site.
	 */
	public const OPTION_AA_ACTIVATED_ADDONS = 'advanced-ads-aa-activated-addons';


	/**
	 * Main instance
	 *
	 * Ensure only one instance is loaded or can be loaded.
	 *
	 * @return License
	 */
	public static function get() {
		static $instance;

		if ( null === $instance ) {
			$instance = new License();
		}

		return $instance;
	}

	/**
	 * Whether rich migration has completed.
	 *
	 * @deprecated Use {@see self::is_flat_map_retired()}.
	 *
	 * @return bool
	 */
	public static function is_migration_done(): bool {
		return '1' === (string) get_option( self::OPTION_MIGRATION_DONE, '' );
	}

	/**
	 * Whether the legacy addon => key flat map has been retired.
	 *
	 * @return bool
	 */
	public static function is_flat_map_retired(): bool {
		return '1' === (string) get_option( self::OPTION_FLAT_MAP_RETIRED, '' );
	}

	/**
	 * Shop REST activate endpoint URL.
	 *
	 * @return string
	 */
	public static function get_shop_activate_endpoint(): string {
		return License_Shop_Client::get_activate_endpoint();
	}

	/**
	 * Shop REST deactivate endpoint URL.
	 *
	 * @return string
	 */
	public static function get_shop_deactivate_endpoint(): string {
		return License_Shop_Client::get_deactivate_endpoint();
	}

	/**
	 * Shop REST validate endpoint URL (fresh package download URLs for this site).
	 *
	 * @return string
	 */
	public static function get_shop_validate_endpoint(): string {
		return License_Shop_Client::get_validate_endpoint();
	}

	/**
	 * HTTP args for server-side shop REST calls (activate, validate).
	 *
	 * @param array<string, mixed> $args Request args to merge.
	 * @return array<string, mixed>
	 */
	private static function shop_http_request_args( array $args = [] ): array {
		return License_Shop_Client::http_request_args( $args );
	}

	/**
	 * Whether outbound HTTPS to {@see AA_SHOP_URL} should verify certificates.
	 *
	 * @return bool
	 */
	private static function should_verify_ssl_for_shop_host(): bool {
		return License_Shop_Client::should_verify_ssl();
	}

	/**
	 * Current site hostname for license activation (no scheme).
	 *
	 * @return string
	 */
	public static function get_site_hostname(): string {
		$host = wp_parse_url( site_url(), PHP_URL_HOST );

		return is_string( $host ) ? $host : '';
	}

	/**
	 * Rich license records from the app option.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_licenses(): array {
		$value = get_option( self::OPTION_RICH, [] );

		return is_array( $value ) ? self::normalize_list( $value ) : [];
	}

	/**
	 * Whether rich licenses are already stored (option exists with at least one row).
	 *
	 * @return bool
	 */
	public static function has_stored_licenses(): bool {
		$value = get_option( self::OPTION_RICH, false );

		if ( false === $value ) {
			return false;
		}

		return [] !== self::get_licenses();
	}

	/**
	 * Whether advanced-ads-licenses already has addon => key entries.
	 *
	 * @return bool
	 */
	public static function has_stored_legacy_license_map(): bool {
		if ( self::is_flat_map_retired() ) {
			return false;
		}

		$legacy = get_option( self::OPTION_LEGACY_MAP, false );

		if ( false === $legacy || ! is_array( $legacy ) ) {
			return false;
		}

		if ( License_Utils::is_site_activation_list_storage( $legacy ) ) {
			return false;
		}

		return [] !== License_Utils::normalize_legacy_map( $legacy );
	}

	/**
	 * Whether this site already had license data before an exchange merge.
	 *
	 * @return bool
	 */
	public static function is_legacy_license_store(): bool {
		return self::has_stored_licenses() || self::has_stored_legacy_license_map();
	}

	/**
	 * Whether to call shop /license/activate automatically (not on button click — JS calls shop first).
	 *
	 * @param bool $requested Caller wants activation (first rich save or exchange with activate flag).
	 * @return bool
	 */
	public static function should_run_shop_auto_activate( bool $requested ): bool {
		if ( ! $requested ) {
			return false;
		}

		return ! self::has_stored_legacy_license_map();
	}

	/**
	 * Whether incoming exchange/checkout rows include license keys not already stored.
	 *
	 * Used after upgrade or additional purchase: EDD issues a new key while the legacy
	 * addon map still exists, so normal auto-activate is skipped without this check.
	 *
	 * @param array<int, array<string, mixed>> $existing Stored licenses.
	 * @param array<int, array<string, mixed>> $incoming New or updated licenses.
	 * @return bool
	 */
	public static function has_new_incoming_license_keys( array $existing, array $incoming ): bool {
		$existing_keys = [];

		foreach ( self::normalize_list( $existing ) as $row ) {
			$key = (string) ( $row['licenseKey'] ?? '' );
			if ( '' !== $key ) {
				$existing_keys[ $key ] = true;
			}
		}

		foreach ( self::normalize_list( $incoming ) as $row ) {
			$key = (string) ( $row['licenseKey'] ?? '' );
			if ( '' !== $key && ! isset( $existing_keys[ $key ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Keep only list rows; preserve order.
	 *
	 * @param array<int, mixed> $licenses Raw option or API value.
	 * @return array<int, array<string, mixed>>
	 */
	public static function normalize_list( array $licenses ): array {
		$out = [];
		foreach ( $licenses as $row ) {
			if ( is_array( $row ) ) {
				$out[] = $row;
			}
		}

		return $out;
	}

	/**
	 * Whether a rich license row already exists (by licenseId or licenseKey).
	 *
	 * @param array<int, array<string, mixed>> $rich       Existing records.
	 * @param array<string, mixed>             $candidate  Incoming record.
	 * @return bool
	 */
	public static function license_exists( array $rich, array $candidate ): bool {
		$candidate_id  = isset( $candidate['licenseId'] ) ? (int) $candidate['licenseId'] : 0;
		$candidate_key = (string) ( $candidate['licenseKey'] ?? '' );

		foreach ( $rich as $row ) {
			if ( $candidate_id && isset( $row['licenseId'] ) && (int) $row['licenseId'] === $candidate_id ) {
				return true;
			}
			if ( '' !== $candidate_key && (string) ( $row['licenseKey'] ?? '' ) === $candidate_key ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Merge incoming rich licenses into existing (update by licenseId / licenseKey).
	 *
	 * @param array<int, array<string, mixed>> $existing Stored licenses.
	 * @param array<int, array<string, mixed>> $incoming New or updated licenses.
	 * @return array<int, array<string, mixed>>
	 */
	public static function merge_license_lists( array $existing, array $incoming ): array {
		$merged = $existing;

		foreach ( self::normalize_list( $incoming ) as $license ) {
			$license_id  = isset( $license['licenseId'] ) ? (int) $license['licenseId'] : 0;
			$license_key = (string) ( $license['licenseKey'] ?? '' );
			$updated     = false;

			foreach ( $merged as $idx => $row ) {
				$match_id  = $license_id && isset( $row['licenseId'] ) && (int) $row['licenseId'] === $license_id;
				$match_key = '' !== $license_key && (string) ( $row['licenseKey'] ?? '' ) === $license_key;

				if ( $match_id || $match_key ) {
					$merged[ $idx ] = array_merge( $row, $license );
					$updated        = true;
					break;
				}
			}

			if ( ! $updated ) {
				$merged[] = $license;
			}
		}

		return array_values( $merged );
	}

	/**
	 * Exchange each unique legacy license key with the shop and merge rich rows.
	 *
	 * Shop exchange returns one row per key; legacy maps may contain several keys.
	 *
	 * @param array<string, string>            $map           Normalized legacy addon => key map.
	 * @param array<int, array<string, mixed>> $existing_rich Existing rich rows to merge into.
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	public static function exchange_legacy_map_for_rich( array $map, array $existing_rich = [] ) {
		$merged = self::normalize_list( $existing_rich );
		$keys   = License_Utils::unique_legacy_keys( $map );

		if ( [] === $keys ) {
			return new WP_Error(
				'advanced_ads_license_exchange_empty',
				__( 'No license keys to exchange.', 'advanced-ads' )
			);
		}

		foreach ( $keys as $license_key ) {
			$found = false;
			foreach ( $merged as $row ) {
				if ( is_array( $row ) && (string) ( $row['licenseKey'] ?? '' ) === $license_key ) {
					$found = true;
					break;
				}
			}
			if ( $found ) {
				continue;
			}

			$result = License_Exchange::request( [ 'legacy' => $license_key ] );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			if ( ! is_array( $result ) || [] === $result ) {
				continue;
			}

			$merged = self::merge_license_lists( $merged, self::normalize_list( $result ) );
		}

		if ( [] === $merged ) {
			return new WP_Error(
				'advanced_ads_license_exchange_empty',
				__( 'License exchange returned no license records.', 'advanced-ads' )
			);
		}

		$merged = self::coalesce_all_access_duplicate_rich_rows( $merged );
		$merged = self::drop_map_stub_duplicate_rows( $merged );

		return $merged;
	}

	/**
	 * Finish legacy migration: patch rich from shop if needed, then retire flat map.
	 *
	 * @return void
	 */
	public static function maybe_complete_legacy_license_migration(): void {

		$map = License_Utils::normalize_legacy_map( get_option( self::OPTION_LEGACY_MAP, [] ) );
		if ( [] === $map ) {
			return;
		}

		$rich = self::get_licenses();

		if ( [] === $rich || ! License_Utils::rich_covers_legacy_keys( $map, $rich ) ) {
			$patched = self::exchange_legacy_map_for_rich( $map, $rich );
			if ( is_wp_error( $patched ) ) {
				return;
			}

			update_option( self::OPTION_RICH, $patched, false );
			$rich = $patched;
		}

		if ( ! License_Utils::rich_covers_legacy_keys( $map, $rich ) ) {
			return;
		}

		self::maybe_retire_legacy_flat_map( $rich, $map );
	}

	/**
	 * Whether the license status is active (eligible for shop activation).
	 *
	 * @param array<string, mixed> $row Rich license row.
	 * @return bool
	 */
	public static function is_license_active( array $row ): bool {
		$status = strtolower( (string) ( $row['status'] ?? '' ) );

		return in_array( $status, [ 'valid', 'active' ], true );
	}

	/**
	 * Whether the license grants add-on access (active, or inactive with a valid subscription).
	 *
	 * Shop `inactive` means not activated on this site yet — not subscription expired.
	 *
	 * @param array<string, mixed> $row Rich license row.
	 * @return bool
	 */
	public static function is_license_entitled( array $row ): bool {
		if ( self::is_license_active( $row ) ) {
			return true;
		}

		$status = strtolower( (string) ( $row['status'] ?? '' ) );

		if ( 'inactive' === $status ) {
			$raw_expiry = (string) ( $row['expiryDate'] ?? '' );

			return '' === $raw_expiry || License_Utils::license_expiry_is_future( $row );
		}

		return self::is_license_effective( $row );
	}

	/**
	 * Map entitled, site-activated rows to `active` (shop exchange contract: active|expired).
	 *
	 * Renewal can leave legacy `inactive` or stale `expired` while expiryDate is already extended.
	 *
	 * @param array<string, mixed> $row Rich license row.
	 * @return array<string, mixed>
	 */
	public static function normalize_rich_license_status( array $row ): array {
		if ( self::is_license_active( $row ) ) {
			return $row;
		}

		if ( self::is_license_entitled( $row ) && self::is_site_activated_on_license( $row, self::get_site_hostname() ) ) {
			$row['status'] = 'active';
		}

		return $row;
	}

	/**
	 * Whether this rich row is in use on the current site (hostname in sitesActivated or addon map).
	 *
	 * @param array<string, mixed> $row Rich license row.
	 * @return bool
	 */
	public static function is_rich_license_applied_on_this_site( array $row ): bool {
		if ( self::is_site_activated_on_license( $row, self::get_site_hostname() ) ) {
			return true;
		}

		$license_key = trim( (string) ( $row['licenseKey'] ?? '' ) );
		if ( '' === $license_key ) {
			return false;
		}

		foreach ( self::get_addon_key_map() as $mapped_key ) {
			if ( $mapped_key === $license_key ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Normalize status on each rich license row for REST / admin UI.
	 *
	 * @param array<int, array<string, mixed>> $rich Rich license list.
	 * @return array<int, array<string, mixed>>
	 */
	public static function normalize_rich_license_list( array $rich ): array {
		return array_map(
			static function ( $row ) {
				return is_array( $row ) ? self::normalize_rich_license_status( $row ) : $row;
			},
			$rich
		);
	}

	/**
	 * Whether the current site hostname is listed on a license.
	 *
	 * @param array<string, mixed> $license         Rich license row.
	 * @param string               $site_hostname Site hostname.
	 * @return bool
	 */
	public static function is_site_activated_on_license( array $license, string $site_hostname ): bool {
		if ( '' === $site_hostname ) {
			return false;
		}

		$sites = $license['sitesActivated'] ?? [];
		if ( ! is_array( $sites ) ) {
			return false;
		}

		foreach ( $sites as $site ) {
			if ( ! is_array( $site ) ) {
				continue;
			}
			$domain = (string) ( $site['domain'] ?? '' );
			if ( '' !== $domain && str_contains( $domain, $site_hostname ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Remove the current site from a license row's sitesActivated list.
	 *
	 * @param array<string, mixed> $license       Rich license row.
	 * @param string               $site_hostname Site hostname.
	 * @return array<string, mixed>
	 */
	public static function remove_site_hostname_from_license_row( array $license, string $site_hostname ): array {
		if ( '' === $site_hostname ) {
			return $license;
		}

		$sites = $license['sitesActivated'] ?? [];
		if ( ! is_array( $sites ) ) {
			return $license;
		}

		$filtered = [];
		foreach ( $sites as $site ) {
			if ( ! is_array( $site ) ) {
				continue;
			}
			$domain = (string) ( $site['domain'] ?? '' );
			if ( '' === $domain || ! str_contains( $domain, $site_hostname ) ) {
				$filtered[] = $site;
			}
		}

		$license['sitesActivated'] = $filtered;
		$license['activationCount'] = count( $filtered );

		return $license;
	}

	/**
	 * When the user picks one license on this site:
	 * - All Access: remove this site from every other license row.
	 * - Single product: remove this site from All Access only (Pro + Tracking may coexist).
	 *
	 * @param array<int, array<string, mixed>> $rich        Rich license list.
	 * @param string                           $license_key License key the user activated.
	 * @return array<int, array<string, mixed>>
	 */
	public static function apply_manual_license_activation_on_site( array $rich, string $license_key ): array {
		$hostname    = self::get_site_hostname();
		$license_key = trim( $license_key );

		if ( '' === $hostname || '' === $license_key ) {
			return $rich;
		}

		$target_index = null;
		$target_row   = null;

		foreach ( $rich as $index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( (string) ( $row['licenseKey'] ?? '' ) === $license_key ) {
				$target_index = $index;
				$target_row   = $row;
				break;
			}
		}

		if ( null === $target_index || null === $target_row ) {
			return $rich;
		}

		$activating_all_access = License_Product_Map::is_all_access_bundle_name(
			(string) ( $target_row['name'] ?? '' )
		);

		foreach ( $rich as $index => $row ) {
			if ( ! is_array( $row ) || $index === $target_index ) {
				continue;
			}

			$name = (string) ( $row['name'] ?? '' );
			$key  = (string) ( $row['licenseKey'] ?? '' );

			if ( $activating_all_access ) {
				if ( ! License_Product_Map::is_all_access_bundle_name( $name ) ) {
					$rich[ $index ] = self::remove_site_hostname_from_license_row( $row, $hostname );
				} elseif ( $key !== $license_key ) {
					$rich[ $index ] = self::remove_site_hostname_from_license_row( $row, $hostname );
				}
				continue;
			}

			// Single product: drop All Access and same-line siblings on this site.
			if ( License_Product_Map::is_all_access_bundle_name( $name ) ) {
				$rich[ $index ] = self::remove_site_hostname_from_license_row( $row, $hostname );
			} elseif ( self::is_same_product_line_row( $target_row, $row ) && $key !== $license_key ) {
				$rich[ $index ] = self::remove_site_hostname_from_license_row( $row, $hostname );
			}
		}

		if ( ! self::is_site_activated_on_license( $target_row, $hostname ) ) {
			$sites = isset( $target_row['sitesActivated'] ) && is_array( $target_row['sitesActivated'] )
				? $target_row['sitesActivated']
				: [];
			$sites[] = [
				'domain'    => $hostname,
				'createdAt' => gmdate( 'd-m-Y' ),
			];
			$target_row['sitesActivated']  = $sites;
			$target_row['activationCount'] = max(
				(int) ( $target_row['activationCount'] ?? 0 ),
				count( $sites )
			);
			$rich[ $target_index ] = $target_row;
		}

		return $rich;
	}

	/**
	 * Rich rows of the same product line that already list this site (excluding target key).
	 *
	 * @param array<int, array<string, mixed>> $rich        Rich license list.
	 * @param string                           $license_key Target license key being activated.
	 * @return array<int, array<string, mixed>>
	 */
	public static function find_same_line_site_active_siblings( array $rich, string $license_key ): array {
		$hostname    = self::get_site_hostname();
		$license_key = trim( $license_key );
		$target_row  = License_Utils::get_rich_license_row_by_key( $rich, $license_key );

		if ( '' === $hostname || ! is_array( $target_row ) ) {
			return [];
		}

		$out = [];
		foreach ( self::normalize_list( $rich ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$key = (string) ( $row['licenseKey'] ?? '' );
			if ( '' === $key || $key === $license_key ) {
				continue;
			}
			if ( ! self::is_same_product_line_row( $target_row, $row ) ) {
				continue;
			}
			if ( ! self::is_site_activated_on_license( $row, $hostname ) ) {
				continue;
			}
			$out[] = $row;
		}

		return $out;
	}

	/**
	 * Shop-deactivate same-line siblings on this site before activating a new key.
	 *
	 * @param array<int, array<string, mixed>> $rich        Rich license list.
	 * @param string                           $license_key Target license key being activated.
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	private static function deactivate_same_line_siblings_on_shop( array $rich, string $license_key ) {
		$siblings = self::find_same_line_site_active_siblings( $rich, $license_key );
		foreach ( $siblings as $row ) {
			$sibling_key = trim( (string) ( $row['licenseKey'] ?? '' ) );
			if ( '' === $sibling_key ) {
				continue;
			}
			$shop = self::request_shop_deactivate( $sibling_key );
			if ( is_wp_error( $shop ) ) {
				return $shop;
			}
			if ( [] !== $shop ) {
				$rich = self::merge_license_lists( $rich, $shop );
			}
		}

		return $rich;
	}

	/**
	 * Remove the current site from one license row (Licenses UI Deactivate).
	 *
	 * @param array<int, array<string, mixed>> $rich        Rich license list.
	 * @param string                           $license_key License key to deactivate on this site.
	 * @return array<int, array<string, mixed>>
	 */
	public static function apply_manual_license_deactivation_on_site( array $rich, string $license_key ): array {
		$hostname    = self::get_site_hostname();
		$license_key = trim( $license_key );

		if ( '' === $hostname || '' === $license_key ) {
			return $rich;
		}

		foreach ( $rich as $index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( (string) ( $row['licenseKey'] ?? '' ) !== $license_key ) {
				continue;
			}

			$rich[ $index ] = self::remove_site_hostname_from_license_row( $row, $hostname );

			$name = (string) ( $row['name'] ?? '' );
			if ( ! License_Product_Map::is_all_access_bundle_name( $name ) ) {
				$manifest = self::build_addon_manifest();
				$addon_id = License_Product_Map::addon_id_from_product_name( $name, $manifest );
				if ( null !== $addon_id ) {
					self::remove_aa_activated_addon_id( $addon_id );
				}
			}
			break;
		}

		return $rich;
	}

	/**
	 * Whether the license has a free activation slot for this site.
	 *
	 * @param array<string, mixed> $license         Rich license row.
	 * @param string               $site_hostname Site hostname.
	 * @return bool
	 */
	public static function can_activate_license_on_site( array $license, string $site_hostname ): bool {
		if ( ! self::is_license_entitled( $license ) ) {
			return false;
		}

		if ( self::is_site_activated_on_license( $license, $site_hostname ) ) {
			return true;
		}

		$used  = (int) ( $license['activationCount'] ?? 0 );
		$total = (int) ( $license['availableSites'] ?? 0 );

		return $used < $total;
	}

	/**
	 * Activate on shop REST (/license/activate) before persisting license options.
	 *
	 * @param string $license_key License key string.
	 * @param int    $license_id  Optional EDD SL license post ID.
	 * @return array<int, array<string, mixed>>|WP_Error Rich list when shop returns one, else [].
	 */
	public static function request_shop_activate( string $license_key, int $license_id = 0 ) {
		return License_Shop_Client::request_activate(
			$license_key,
			self::get_site_hostname(),
			$license_id
		);
	}

	/**
	 * Shop activate then mirror this site on the license row (never local-only).
	 *
	 * @param string                           $license_key License key.
	 * @param array<int, array<string, mixed>> $rich        Rich license list.
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	public static function activate_on_shop_then_local( string $license_key, array $rich ) {
		$license_key = trim( $license_key );
		if ( '' === $license_key ) {
			return new WP_Error(
				'advanced_ads_license_activate_invalid',
				__( 'Missing license key.', 'advanced-ads' )
			);
		}

		$rich = self::deactivate_same_line_siblings_on_shop( $rich, $license_key );
		if ( is_wp_error( $rich ) ) {
			return $rich;
		}

		$shop = self::request_shop_activate(
			$license_key,
			self::resolve_license_id_for_key( $rich, $license_key )
		);
		if ( is_wp_error( $shop ) ) {
			return $shop;
		}

		if ( [] !== $shop ) {
			$rich = self::merge_license_lists( $rich, $shop );
		}

		return self::apply_manual_license_activation_on_site( $rich, $license_key );
	}

	/**
	 * Legacy connect: sync locally-assigned licenses that are not on shop for this site.
	 *
	 * @param array<int, array<string, mixed>> $rich     Rich license list (post-merge).
	 * @param array<int, array<string, mixed>> $existing Stored licenses before merge.
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	public static function sync_local_activations_to_shop( array $rich, array $existing = [] ) {
		$hostname = self::get_site_hostname();
		if ( '' === $hostname ) {
			return $rich;
		}

		$keys_to_sync = [];
		$legacy_map   = License_Utils::normalize_legacy_map( get_option( self::OPTION_LEGACY_MAP, [] ) );
		foreach ( $legacy_map as $key ) {
			$key = trim( (string) $key );
			if ( '' !== $key ) {
				$keys_to_sync[ $key ] = true;
			}
		}

		foreach ( self::normalize_list( $existing ) as $row ) {
			if ( ! self::is_site_activated_on_license( $row, $hostname ) ) {
				continue;
			}
			$key = trim( (string) ( $row['licenseKey'] ?? '' ) );
			if ( '' !== $key ) {
				$keys_to_sync[ $key ] = true;
			}
		}

		foreach ( array_keys( $keys_to_sync ) as $license_key ) {
			$row = null;
			foreach ( self::normalize_list( $rich ) as $candidate ) {
				if ( (string) ( $candidate['licenseKey'] ?? '' ) === $license_key ) {
					$row = $candidate;
					break;
				}
			}

			if ( ! is_array( $row ) || self::is_site_activated_on_license( $row, $hostname ) ) {
				continue;
			}

			$activated = self::activate_on_shop_then_local( $license_key, $rich );
			if ( is_wp_error( $activated ) ) {
				return $activated;
			}
			$rich = $activated;
		}

		return $rich;
	}

	/**
	 * Deactivate a site on the shop REST (/license/deactivate).
	 *
	 * @param string $license_key License key string.
	 * @return array<int, array<string, mixed>>|WP_Error Rich list when shop returns one, else [].
	 */
	public static function request_shop_deactivate( string $license_key ) {
		return License_Shop_Client::request_deactivate( $license_key, self::get_site_hostname() );
	}

	/**
	 * Shop deactivate then mirror this site off the license row (never local-only).
	 *
	 * @param string                           $license_key License key.
	 * @param array<int, array<string, mixed>> $rich        Rich license list.
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	public static function deactivate_on_shop_then_local( string $license_key, array $rich ) {
		$license_key = trim( $license_key );
		if ( '' === $license_key ) {
			return new WP_Error(
				'advanced_ads_license_deactivate_invalid',
				__( 'Missing license key.', 'advanced-ads' )
			);
		}

		$shop = self::request_shop_deactivate( $license_key );
		if ( is_wp_error( $shop ) ) {
			return $shop;
		}
		if ( [] !== $shop ) {
			$rich = self::merge_license_lists( $rich, $shop );
		}

		return self::apply_manual_license_deactivation_on_site( $rich, $license_key );
	}

	/**
	 * Deactivate the license for one add-on on this site (e.g. on add-on uninstall).
	 *
	 * @param string $addon_id Short add-on id (e.g. tracking).
	 * @return true|int|string True or 1 on success, error message on failure.
	 */
	public static function deactivate_license_for_addon( string $addon_id ) {
		$addon_id = sanitize_key( trim( $addon_id ) );
		if ( '' === $addon_id ) {
			return __( 'Error while trying to disable the license. Please contact support.', 'advanced-ads' );
		}

		$map         = self::get_addon_key_map();
		$license_key = trim( (string) ( $map[ $addon_id ] ?? '' ) );

		if ( '' === $license_key ) {
			return 1;
		}

		$shared_with = array_filter(
			$map,
			static function ( $key, $id ) use ( $license_key ) {
				if ( trim( (string) $key ) !== $license_key ) {
					return false;
				}

				$options_slug = License_Utils::options_slug_for_addon_id( (string) $id );

				return (bool) self::get_mirror_status_for_options_slug( $options_slug );
			},
			ARRAY_FILTER_USE_BOTH
		);

		if ( count( $shared_with ) > 1 ) {
			self::remove_aa_activated_addon_id( $addon_id );

			if ( ! self::is_flat_map_retired() ) {
				$options_slug = License_Utils::options_slug_for_addon_id( $addon_id );
				delete_option( $options_slug . '-license-status' );
				delete_option( $options_slug . '-license-expires' );
			}

			return 1;
		}

		$result = self::save_licenses(
			self::get_licenses(),
			false,
			'',
			'',
			false,
			'',
			$license_key
		);

		if ( is_wp_error( $result ) ) {
			return $result->get_error_message();
		}

		return 1;
	}

	/**
	 * Activate licenses on the shop when this site is not registered yet.
	 *
	 * @param array<int, array<string, mixed>> $rich Rich license list.
	 * @return array<int, array<string, mixed>>
	 */
	public static function maybe_activate_licenses_for_current_site( array $rich ): array {
		$hostname = self::get_site_hostname();
		if ( '' === $hostname ) {
			return $rich;
		}

		$all_access = self::find_entitled_all_access_license( $rich );
		$rows       = null !== $all_access ? [ $all_access ] : array_values(
			array_filter(
				$rich,
				static function ( $row ) {
					if ( ! is_array( $row ) || ! self::is_license_entitled( $row ) ) {
						return false;
					}

					return ! License_Product_Map::is_all_access_bundle_name( (string) ( $row['name'] ?? '' ) );
				}
			)
		);

		usort(
			$rows,
			static function ( $a, $b ) {
				$score_a = self::license_priority_score(
					$a,
					License_Product_Map::is_all_access_bundle_name( (string) ( $a['name'] ?? '' ) )
				);
				$score_b = self::license_priority_score(
					$b,
					License_Product_Map::is_all_access_bundle_name( (string) ( $b['name'] ?? '' ) )
				);

				return $score_b <=> $score_a;
			}
		);

		foreach ( $rows as $row ) {
			if ( self::is_site_activated_on_license( $row, $hostname ) ) {
				continue;
			}
			if ( ! self::can_activate_license_on_site( $row, $hostname ) ) {
				continue;
			}

			$license_key = (string) ( $row['licenseKey'] ?? '' );
			if ( '' === $license_key ) {
				continue;
			}

			if ( self::is_upgrade_successor_with_site_already_in_use( $rich, $row ) ) {
				$rich = self::promote_successor_license_on_shop( $rich, $license_key, true );
				continue;
			}

			$result = self::request_shop_activate( $license_key );
			if ( is_wp_error( $result ) ) {
				continue;
			}
			if ( [] !== $result ) {
				$rich = self::merge_license_lists( $rich, $result );
			}
		}

		return self::ensure_site_slots_match_active_assignments( $rich );
	}

	/**
	 * After shop auto-activate, persist sitesActivated for licenses that grant add-ons on this site.
	 *
	 * Exchange/activate responses may omit activation rows while EDD already has the site and
	 * legacy assignment still mirrors add-ons — the Licenses UI then shows "0 of N used".
	 *
	 * @param array<int, array<string, mixed>> $rich Rich license list.
	 * @return array<int, array<string, mixed>>
	 */
	public static function ensure_site_slots_match_active_assignments( array $rich ): array {
		$hostname = self::get_site_hostname();
		$keys     = [];
		foreach ( self::resolve_addon_license_assignments( $rich ) as $assignment ) {
			$key = (string) ( $assignment['licenseKey'] ?? '' );
			if ( '' !== $key ) {
				$keys[ $key ] = true;
			}
		}

		foreach ( array_keys( $keys ) as $license_key ) {
			$row = null;
			foreach ( self::normalize_list( $rich ) as $candidate ) {
				if ( (string) ( $candidate['licenseKey'] ?? '' ) === $license_key ) {
					$row = $candidate;
					break;
				}
			}

			if ( ! is_array( $row ) || self::is_site_activated_on_license( $row, $hostname ) ) {
				continue;
			}

			// Keep shop as source of truth: do not mirror locally while a predecessor still holds the slot.
			$skip = false;
			foreach ( self::normalize_list( $rich ) as $other ) {
				$other_key = (string) ( $other['licenseKey'] ?? '' );
				if ( '' === $other_key || $other_key === $license_key ) {
					continue;
				}
				if ( ! self::is_same_product_line_row( $row, $other ) ) {
					continue;
				}
				if ( self::is_site_activated_on_license( $other, $hostname ) ) {
					$skip = true;
					break;
				}
			}

			if ( $skip ) {
				continue;
			}

			$activated = self::activate_on_shop_then_local( $license_key, $rich );
			if ( is_wp_error( $activated ) ) {
				continue;
			}
			$rich = $activated;
		}

		return $rich;
	}

	/**
	 * Whether any rich row lists the current site in sitesActivated.
	 *
	 * @param array<int, array<string, mixed>> $rich Rich license list.
	 * @param string                           $site_hostname Site hostname.
	 * @return bool
	 */
	public static function is_site_on_any_license_row( array $rich, string $site_hostname ): bool {
		foreach ( self::normalize_list( $rich ) as $row ) {
			if ( self::is_site_activated_on_license( $row, $site_hostname ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * After upgrade, move this site's slot from a predecessor row to the entitled successor.
	 *
	 * Updates local sitesActivated and syncs the successor key on the shop so EDD SL
	 * activation counts match the plugin UI.
	 *
	 * @param array<int, array<string, mixed>> $rich Rich license list.
	 * @return array<int, array<string, mixed>>
	 */
	public static function migrate_site_activation_to_successor_licenses( array $rich ): array {
		$hostname = self::get_site_hostname();
		if ( '' === $hostname ) {
			return $rich;
		}

		$legacy_migrations = [];

		foreach ( self::normalize_list( $rich ) as $successor ) {
			if ( ! self::is_license_entitled( $successor ) ) {
				continue;
			}

			$key = (string) ( $successor['licenseKey'] ?? '' );
			if ( '' === $key || self::is_site_activated_on_license( $successor, $hostname ) ) {
				continue;
			}

			if ( ! self::is_upgrade_successor_with_site_already_in_use( $rich, $successor ) ) {
				continue;
			}

			$rich = self::promote_successor_license_on_shop( $rich, $key, true, $legacy_migrations );
		}

		foreach ( self::normalize_list( $rich ) as $successor ) {
			if ( ! self::is_license_entitled( $successor ) ) {
				continue;
			}

			$key = (string) ( $successor['licenseKey'] ?? '' );
			if ( '' === $key || ! self::is_site_activated_on_license( $successor, $hostname ) ) {
				continue;
			}

			if ( ! self::should_sync_successor_activation_to_shop( $rich, $successor ) ) {
				continue;
			}

			$rich = self::promote_successor_license_on_shop( $rich, $key, false, $legacy_migrations );
		}

		$rich = self::ensure_site_slots_match_active_assignments( $rich );

		foreach ( $legacy_migrations as $migration ) {
			self::migrate_legacy_map_keys( (string) $migration['from'], (string) $migration['to'] );
		}

		return $rich;
	}

	/**
	 * Deactivate the predecessor on the shop, activate the successor, then mirror locally.
	 *
	 * @param array<int, array<string, mixed>> $rich             Rich license list.
	 * @param string                           $successor_key    Successor license key.
	 * @param bool                             $apply_local_slot When true, move the site slot locally after shop success.
	 * @param array<int, array{from: string, to: string}> $legacy_migrations Collect predecessor → successor map updates (applied after ensure).
	 * @return array<int, array<string, mixed>>
	 */
	private static function promote_successor_license_on_shop( array $rich, string $successor_key, bool $apply_local_slot, array &$legacy_migrations = [] ): array {
		$successor_key = trim( $successor_key );
		$hostname      = self::get_site_hostname();

		if ( '' === $successor_key || '' === $hostname ) {
			return $rich;
		}

		\delete_transient( 'advads_shop_successor_' . \md5( $successor_key . '|' . $hostname ) );

		$successor = null;
		foreach ( self::normalize_list( $rich ) as $row ) {
			if ( is_array( $row ) && (string) ( $row['licenseKey'] ?? '' ) === $successor_key ) {
				$successor = $row;
				break;
			}
		}

		if ( null === $successor ) {
			return $rich;
		}

		$predecessor_key = self::find_predecessor_license_key_for_successor( $rich, $successor );
		if ( '' !== $predecessor_key ) {
			$deactivated = self::request_shop_deactivate( $predecessor_key );
			if ( ! is_wp_error( $deactivated ) && [] !== $deactivated ) {
				$rich = self::merge_license_lists( $rich, $deactivated );
			}
		}

		$activated = self::request_shop_activate( $successor_key );
		if ( is_wp_error( $activated ) ) {
			return $rich;
		}

		if ( [] !== $activated ) {
			$rich = self::merge_license_lists( $rich, $activated );
		}

		if ( $apply_local_slot ) {
			$rich = self::apply_manual_license_activation_on_site( $rich, $successor_key );
		}

		if ( ! is_wp_error( $activated ) && '' !== $predecessor_key ) {
			$legacy_migrations[] = [
				'from' => $predecessor_key,
				'to'   => $successor_key,
			];
		}

		if ( self::shop_response_confirms_site_activation( $activated, $hostname ) ) {
			self::mark_successor_shop_synced( $successor_key, $hostname );
		}

		return $rich;
	}

	/**
	 * Predecessor license key for an upgrade successor (legacy map or superseded row).
	 *
	 * @param array<int, array<string, mixed>> $rich      Rich license list.
	 * @param array<string, mixed>             $successor Successor row.
	 * @return string
	 */
	private static function find_predecessor_license_key_for_successor( array $rich, array $successor ): string {
		$hostname      = self::get_site_hostname();
		$successor_key = (string) ( $successor['licenseKey'] ?? '' );

		foreach ( self::normalize_list( $rich ) as $row ) {
			if ( ! self::is_predecessor_license_row( $row, $successor ) ) {
				continue;
			}

			if ( '' !== $hostname && self::is_site_activated_on_license( $row, $hostname ) ) {
				return (string) ( $row['licenseKey'] ?? '' );
			}
		}

		foreach ( self::get_addon_key_map() as $mapped_key ) {
			if ( '' !== $mapped_key && $mapped_key !== $successor_key ) {
				return $mapped_key;
			}
		}

		foreach ( self::normalize_list( $rich ) as $row ) {
			if ( self::is_predecessor_license_row( $row, $successor ) ) {
				return (string) ( $row['licenseKey'] ?? '' );
			}
		}

		return '';
	}

	/**
	 * Whether a shop activate/deactivate response lists the current site.
	 *
	 * @param array<int, array<string, mixed>>|WP_Error $result   Shop response rows.
	 * @param string                                      $hostname Site hostname.
	 * @return bool
	 */
	private static function shop_response_confirms_site_activation( $result, string $hostname ): bool {
		if ( is_wp_error( $result ) || ! is_array( $result ) ) {
			return false;
		}

		foreach ( self::normalize_list( $result ) as $row ) {
			if ( self::is_site_activated_on_license( $row, $hostname ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string $license_key License key.
	 * @param string $hostname    Site hostname.
	 * @return void
	 */
	private static function mark_successor_shop_synced( string $license_key, string $hostname ): void {
		\set_transient( 'advads_shop_successor_' . \md5( $license_key . '|' . $hostname ), 1, DAY_IN_SECONDS );
	}

	/**
	 * @param string $license_key License key.
	 * @param string $hostname    Site hostname.
	 * @return bool
	 */
	private static function is_successor_shop_sync_cached( string $license_key, string $hostname ): bool {
		return (bool) \get_transient( 'advads_shop_successor_' . \md5( $license_key . '|' . $hostname ) );
	}

	/**
	 * @param array<int, array<string, mixed>> $rich        Rich license list.
	 * @param string                           $license_key Successor license key.
	 * @return array<int, array<string, mixed>>
	 */
	private static function sync_successor_activation_to_shop( array $rich, string $license_key ): array {
		return self::promote_successor_license_on_shop( $rich, $license_key, false );
	}

	/**
	 * Whether a new entitled row replaces a license this site already uses (upgrade / renew).
	 *
	 * @param array<int, array<string, mixed>> $rich      Rich license list.
	 * @param array<string, mixed>             $successor Entitled incoming row.
	 * @return bool
	 */
	private static function is_upgrade_successor_with_site_already_in_use( array $rich, array $successor ): bool {
		$hostname = self::get_site_hostname();
		if ( '' === $hostname ) {
			return false;
		}

		if ( self::is_site_activated_on_license( $successor, $hostname ) ) {
			return false;
		}

		if ( self::has_superseded_site_activation_for_product( $rich, $successor, $hostname ) ) {
			return true;
		}

		return self::has_predecessor_addon_assignment_for_successor( $successor );
	}

	/**
	 * Whether a successor row with a local site slot still needs shop /license/activate.
	 *
	 * @param array<int, array<string, mixed>> $rich Rich license list.
	 * @param array<string, mixed>             $row  Entitled row with sitesActivated on this site.
	 * @return bool
	 */
	private static function should_sync_successor_activation_to_shop( array $rich, array $row ): bool {
		$hostname = self::get_site_hostname();
		if ( '' === $hostname || ! self::is_license_entitled( $row ) ) {
			return false;
		}

		if ( ! self::is_site_activated_on_license( $row, $hostname ) ) {
			return false;
		}

		if ( self::has_predecessor_addon_assignment_for_successor( $row ) ) {
			return true;
		}

		$row_key = (string) ( $row['licenseKey'] ?? '' );
		if ( '' === $row_key ) {
			return false;
		}

		foreach ( self::normalize_list( $rich ) as $other ) {
			if ( ! self::is_same_product_line_row( $row, $other ) ) {
				continue;
			}

			$other_key = (string) ( $other['licenseKey'] ?? '' );
			if ( '' === $other_key || $other_key === $row_key ) {
				continue;
			}

			if ( self::is_license_active( $row ) && ! self::is_license_active( $other ) ) {
				return true;
			}

			$row_is_aa   = License_Product_Map::is_all_access_bundle_name( (string) ( $row['name'] ?? '' ) );
			$other_is_aa = License_Product_Map::is_all_access_bundle_name( (string) ( $other['name'] ?? '' ) );
			$row_score   = self::license_priority_score( $row, $row_is_aa );
			$other_score = self::license_priority_score( $other, $other_is_aa );

			if ( $row_score > $other_score ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether two rich rows cover the same product line (All Access or single add-on).
	 *
	 * @param array<string, mixed> $a First row.
	 * @param array<string, mixed> $b Second row.
	 * @return bool
	 */
	private static function is_same_product_line_row( array $a, array $b ): bool {
		$a_name = (string) ( $a['name'] ?? '' );
		$b_name = (string) ( $b['name'] ?? '' );
		$a_is_aa = License_Product_Map::is_all_access_bundle_name( $a_name );
		$b_is_aa = License_Product_Map::is_all_access_bundle_name( $b_name );

		if ( $a_is_aa && $b_is_aa ) {
			return true;
		}

		if ( $a_is_aa || $b_is_aa ) {
			return false;
		}

		$manifest = self::build_addon_manifest();

		return License_Product_Map::addon_id_from_product_name( $a_name, $manifest )
			=== License_Product_Map::addon_id_from_product_name( $b_name, $manifest );
	}

	/**
	 * Another row of the same product line still lists this site (typical upgrade exchange).
	 *
	 * @param array<int, array<string, mixed>> $rich      Rich license list.
	 * @param array<string, mixed>             $successor Entitled successor row.
	 * @param string                           $hostname  Site hostname.
	 * @return bool
	 */
	private static function has_superseded_site_activation_for_product(
		array $rich,
		array $successor,
		string $hostname
	): bool {
		$successor_key = (string) ( $successor['licenseKey'] ?? '' );

		foreach ( self::normalize_list( $rich ) as $row ) {
			if ( (string) ( $row['licenseKey'] ?? '' ) === $successor_key ) {
				continue;
			}

			if ( ! self::is_site_activated_on_license( $row, $hostname ) ) {
				continue;
			}

			if ( ! self::is_same_product_line_row( $successor, $row ) ) {
				continue;
			}

			if ( self::is_predecessor_license_row( $row, $successor ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether $predecessor is the superseded license row for an upgrade successor.
	 *
	 * @param array<string, mixed> $predecessor Row that still holds or held the site slot.
	 * @param array<string, mixed> $successor   Incoming entitled row.
	 * @return bool
	 */
	private static function is_predecessor_license_row( array $predecessor, array $successor ): bool {
		$pred_key = (string) ( $predecessor['licenseKey'] ?? '' );
		$succ_key = (string) ( $successor['licenseKey'] ?? '' );

		if ( '' === $pred_key || '' === $succ_key || $pred_key === $succ_key ) {
			return false;
		}

		if ( ! self::is_same_product_line_row( $predecessor, $successor ) ) {
			return false;
		}

		if ( self::has_predecessor_addon_assignment_for_successor( $successor ) ) {
			foreach ( self::get_addon_key_map() as $mapped_key ) {
				if ( $mapped_key === $pred_key ) {
					return true;
				}
			}
		}

		return ! self::is_license_active( $predecessor ) && self::is_license_active( $successor );
	}

	/**
	 * All Access add-ons are already mirrored from a predecessor key (upgrade, old row dropped).
	 *
	 * @param array<string, mixed> $successor Entitled All Access row.
	 * @return bool
	 */
	private static function has_predecessor_addon_assignment_for_successor( array $successor ): bool {
		$name = (string) ( $successor['name'] ?? '' );
		if ( ! License_Product_Map::is_all_access_bundle_name( $name ) ) {
			return false;
		}

		$new_key = (string) ( $successor['licenseKey'] ?? '' );
		if ( '' === $new_key ) {
			return false;
		}

		foreach ( self::get_addon_key_map() as $mapped_key ) {
			if ( '' !== $mapped_key && $mapped_key !== $new_key ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether any incoming new key still needs a shop /license/activate call on this site.
	 *
	 * @param array<int, array<string, mixed>> $existing Stored licenses.
	 * @param array<int, array<string, mixed>> $merged   Merged list after exchange.
	 * @return bool
	 */
	private static function incoming_new_keys_need_shop_activation( array $existing, array $merged ): bool {
		$hostname      = self::get_site_hostname();
		$existing_keys = [];

		foreach ( self::normalize_list( $existing ) as $row ) {
			$key = (string) ( $row['licenseKey'] ?? '' );
			if ( '' !== $key ) {
				$existing_keys[ $key ] = true;
			}
		}

		foreach ( self::normalize_list( $merged ) as $row ) {
			$key = (string) ( $row['licenseKey'] ?? '' );
			if ( '' === $key || isset( $existing_keys[ $key ] ) ) {
				continue;
			}

			if ( ! self::is_license_entitled( $row ) ) {
				continue;
			}

			if ( self::is_upgrade_successor_with_site_already_in_use( $merged, $row ) ) {
				continue;
			}

			if ( ! self::is_site_activated_on_license( $row, $hostname ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether exchange rows include a new entitled license replacing one already used on site.
	 *
	 * @param array<int, array<string, mixed>> $existing Stored licenses.
	 * @param array<int, array<string, mixed>> $merged   Merged list after exchange.
	 * @return bool
	 */
	private static function incoming_has_upgrade_successor_with_site_in_use( array $existing, array $merged ): bool {
		$existing_keys = [];

		foreach ( self::normalize_list( $existing ) as $row ) {
			$key = (string) ( $row['licenseKey'] ?? '' );
			if ( '' !== $key ) {
				$existing_keys[ $key ] = true;
			}
		}

		foreach ( self::normalize_list( $merged ) as $row ) {
			$key = (string) ( $row['licenseKey'] ?? '' );
			if ( '' === $key || isset( $existing_keys[ $key ] ) ) {
				continue;
			}

			if ( ! self::is_license_entitled( $row ) ) {
				continue;
			}

			if ( self::is_upgrade_successor_with_site_already_in_use( $merged, $row ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Mirror rich licenses to per-addon options; optionally install only one license's add-on(s).
	 *
	 * @param array<int, array<string, mixed>> $rich                     Rich license list.
	 * @param string                           $install_only_license_key When set (licenses UI activate), only that row's package is installed.
	 * @param bool                             $install_packages         When false, only update option mirrors (no zip download).
	 * @param string                           $install_only_addon_id    installed addon id.
	 * @param bool                             $install_only             install only.
	 * @return WP_Error|null Null on success, error when package install fails during scoped activation.
	 */
	public static function sync_addon_options_from_rich( array $rich, string $install_only_license_key = '', bool $install_packages = true, string $install_only_addon_id = '', bool $install_only = false, bool $update_legacy_map = true ): ?WP_Error {
		$assignments = self::resolve_addon_license_assignments( $rich );
		$addons      = self::get_addons_for_license_map( null );

		$install_only_license_key = trim( $install_only_license_key );
		$install_only_addon_id    = sanitize_key( trim( $install_only_addon_id ) );
		$install_addon_ids        = null;
		$activating_row           = null;

		if ( '' !== $install_only_license_key ) {
			$install_addon_ids = [];
			foreach ( $rich as $license_row ) {
				if ( ! is_array( $license_row ) ) {
					continue;
				}
				if ( (string) ( $license_row['licenseKey'] ?? '' ) !== $install_only_license_key ) {
					continue;
				}
				$activating_row = $license_row;

				if ( '' !== $install_only_addon_id ) {
					$install_addon_ids = [ $install_only_addon_id ];
				} elseif ( License_Product_Map::is_all_access_bundle_name( (string) ( $license_row['name'] ?? '' ) ) ) {
					// All Access: install each add-on from the Licenses UI (one request per package).
					$install_addon_ids = [];
				} else {
					$install_addon_ids = self::get_addon_ids_for_license_row( $license_row );
				}
				break;
			}
		}

		// Licenses UI "activate": install from this row's download_url even if assignment resolution lags.
		if ( $install_packages && is_array( $activating_row ) && [] !== $install_addon_ids ) {
			$license_key = (string) ( $activating_row['licenseKey'] ?? $install_only_license_key );
			$expires     = ! empty( $activating_row['expiryDate'] ) ? (string) $activating_row['expiryDate'] : false;

			foreach ( $install_addon_ids as $scoped_addon_id ) {
				if ( ! $install_only ) {
					if ( License_Product_Map::is_all_access_bundle_name( (string) ( $activating_row['name'] ?? '' ) ) ) {
						self::add_aa_activated_addon_id( $scoped_addon_id );
					}
					self::update_license_details( $scoped_addon_id, $license_key, 'valid', $expires, $update_legacy_map );
				}

				$installed = self::install_addon_from_download_url( $activating_row, $scoped_addon_id, true, $install_only );
				if ( is_wp_error( $installed ) ) {
					return $installed;
				}
			}
		}

		foreach ( $addons as $addon_row ) {
			if ( empty( $addon_row['id'] ) || 'slider-ads' === $addon_row['id'] ) {
				continue;
			}

			$addon_id = (string) $addon_row['id'];

			if ( null !== $install_addon_ids && in_array( $addon_id, $install_addon_ids, true ) ) {
				continue;
			}

			if (
				! isset( $assignments[ $addon_id ] )
				|| ! self::is_license_entitled( $assignments[ $addon_id ]['row'] )
			) {
				self::clear_addon_license_mirror( $addon_id );
				continue;
			}

			$row         = $assignments[ $addon_id ]['row'];
			$license_key = (string) $assignments[ $addon_id ]['licenseKey'];
			$expires     = ! empty( $row['expiryDate'] ) ? (string) $row['expiryDate'] : false;
			$aa_on_site  = self::find_all_access_license_active_on_site( $rich );

			if (
				null !== $aa_on_site
				&& (string) ( $aa_on_site['licenseKey'] ?? '' ) === $license_key
				&& ! self::should_mirror_all_access_addon_on_site( $addon_id, $install_only_addon_id, $install_only )
			) {
				self::clear_addon_license_mirror( $addon_id );
				continue;
			}

			self::update_license_details(
				$addon_id,
				$license_key,
				'valid',
				$expires
			);

			if ( $install_packages && null === $install_addon_ids ) {
				$install_result = self::install_addon_from_download_url( $row, $addon_id );
				if ( is_wp_error( $install_result ) ) {
					return $install_result;
				}
			}
		}

		return null;
	}

	/**
	 * Add-on ids covered by one rich license row (single product or All Access bundle).
	 *
	 * @param array<string, mixed> $row Rich license row.
	 * @return string[]
	 */
	private static function get_addon_ids_for_license_row( array $row ): array {
		if ( License_Product_Map::is_all_access_bundle_name( (string) ( $row['name'] ?? '' ) ) ) {
			return array_values(
				array_filter(
					array_keys( self::get_addon_plugin_catalog() ),
					static fn( string $id ): bool => 'slider-ads' !== $id
				)
			);
		}

		$addon_id = License_Product_Map::addon_id_from_product_name(
			(string) ( $row['name'] ?? '' ),
			self::build_addon_manifest()
		);

		return null !== $addon_id ? [ $addon_id ] : [];
	}

	/**
	 * Whether a rich license row is for a given add-on (or All Access).
	 *
	 * @param array<string, mixed> $row      License row.
	 * @param string               $addon_id Short add-on id.
	 * @return bool
	 */
	private static function license_row_covers_addon( array $row, string $addon_id ): bool {
		$name = (string) ( $row['name'] ?? '' );

		if ( License_Product_Map::is_all_access_bundle_name( $name ) ) {
			return true;
		}

		return License_Product_Map::addon_id_from_product_name( $name, self::build_addon_manifest() ) === $addon_id;
	}

	/**
	 * Catalog entries relevant to installing one add-on (not filtered by installed plugins).
	 *
	 * @param array<string, mixed> $row      Assigned license row.
	 * @param string               $addon_id Add-on being installed (e.g. tracking).
	 * @return array<string, array<string, mixed>>
	 */
	private static function get_licenses_catalogs( array $row, string $addon_id ): array {
		$catalog = self::get_addon_plugin_catalog();

		if ( License_Product_Map::is_all_access_bundle_name( (string) ( $row['name'] ?? '' ) ) ) {
			return $catalog;
		}

		if ( ! isset( $catalog[ $addon_id ] ) ) {
			return [];
		}

		return [ $addon_id => $catalog[ $addon_id ] ];
	}

	/**
	 * Rich license row that provides download_url for a given add-on.
	 *
	 * @param string               $addon_id        Add-on to install.
	 * @param array<string, mixed> $assignment_row  Row from license assignment (fallback).
	 * @return array<string, mixed>
	 */
	private static function resolve_install_license_row( string $addon_id, array $assignment_row ): array {
		$rich = self::get_licenses();
		if ( ! is_array( $rich ) || [] === $rich ) {
			return $assignment_row;
		}

		$manifest   = self::build_addon_manifest();
		$best       = null;
		$best_score = -1;

		foreach ( $rich as $license_row ) {
			if ( ! is_array( $license_row ) || ! self::is_license_entitled( $license_row ) ) {
				continue;
			}

			$download_url = trim( (string) ( $license_row['download_url'] ?? '' ) );
			if ( '' === $download_url ) {
				continue;
			}

			$name         = (string) ( $license_row['name'] ?? '' );
			$covers_addon = License_Product_Map::is_all_access_bundle_name( $name )
				|| License_Product_Map::addon_id_from_product_name( $name, $manifest ) === $addon_id;

			if ( ! $covers_addon ) {
				continue;
			}

			$score = self::license_priority_score(
				$license_row,
				License_Product_Map::is_all_access_bundle_name( $name )
			);

			if ( $score > $best_score ) {
				$best_score = $score;
				$best       = $license_row;
			}
		}

		return is_array( $best ) ? $best : $assignment_row;
	}

	/**
	 * Full paid add-on catalog for install/activate (independent of wp-content/plugins).
	 *
	 * @return array<string, array{id: string, file: string, title: string}>
	 */
	private static function get_addon_plugin_catalog(): array {
		$titles = [
			'pro'        => 'Advanced Ads Pro',
			'responsive' => 'AMP Ads',
			'gam'        => 'Google Ad Manager Integration',
			'layer'      => 'PopUp and Layer Ads',
			'selling'    => 'Selling Ads',
			'sticky'     => 'Sticky Ads',
			'tracking'   => 'Tracking',
		];

		$catalog = [];
		foreach ( Addons::plugin_files() as $addon_id => $file ) {
			$catalog[ $addon_id ] = [
				'id'    => $addon_id,
				'file'  => $file,
				'title' => $titles[ $addon_id ] ?? $addon_id,
			];
		}

		return $catalog;
	}

	/**
	 * Plugin bootstrap file for an add-on id.
	 *
	 * @param string $addon_id Short add-on id.
	 * @return string|null
	 */
	private static function get_plugin_file_for_addon_id( string $addon_id ): ?string {
		return License_Package_Installer::plugin_file_for_addon_id( $addon_id );
	}

	/**
	 * Whether the add-on plugin directory / bootstrap file is already on disk.
	 *
	 * @param string $addon_id Short add-on id.
	 * @return bool
	 */
	private static function is_addon_plugin_on_disk( string $addon_id ): bool {
		return License_Package_Installer::is_addon_on_disk( $addon_id );
	}

	/**
	 * Package download URL for one add-on (row.addons[] or top-level download_url).
	 *
	 * @param array<string, mixed> $row      License row from shop.
	 * @param string               $addon_id Short add-on id.
	 * @return string
	 */
	private static function get_download_url_for_addon( array $row, string $addon_id ): string {
		$addons = $row['addons'] ?? [];
		if ( is_array( $addons ) ) {
			foreach ( $addons as $addon_entry ) {
				if ( ! is_array( $addon_entry ) ) {
					continue;
				}

				if ( (string) ( $addon_entry['name'] ?? '' ) !== $addon_id ) {
					continue;
				}

				$url = trim( (string) ( $addon_entry['download_url'] ?? '' ) );
				if ( '' !== $url ) {
					return wp_unslash( preg_replace( '/\s+/', '', $url ) );
				}
			}
		}

		return wp_unslash( preg_replace( '/\s+/', '', (string) ( $row['download_url'] ?? '' ) ) );
	}

	/**
	 * Whether an All Access row lists different download_url values per add-on.
	 *
	 * @param array<string, mixed> $row License row.
	 * @return bool
	 */
	private static function license_row_has_distinct_addon_download_urls( array $row ): bool {
		$addons = $row['addons'] ?? [];
		if ( ! is_array( $addons ) || [] === $addons ) {
			return false;
		}

		$urls = [];
		foreach ( $addons as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$url = trim( (string) ( $entry['download_url'] ?? '' ) );
			if ( '' !== $url ) {
				$urls[] = $url;
			}
		}

		return count( array_unique( $urls ) ) > 1;
	}

	/**
	 * Add-on manifest for license name resolution (always includes missing add-ons).
	 *
	 * @return array<string, array{id: string, name: string, options_slug: string}>
	 */
	private static function build_addon_manifest(): array {
		$slug_base = defined( 'ADVADS_SLUG' ) ? ADVADS_SLUG : 'advanced-ads';
		$names     = [
			'pro'        => 'Pro',
			'responsive' => 'AMP Ads',
			'gam'        => 'Google Ad Manager Integration',
			'layer'      => 'PopUp and Layer Ads',
			'selling'    => 'Selling Ads',
			'sticky'     => 'Sticky Ads',
			'tracking'   => 'Tracking',
		];
		$out       = [];

		foreach ( Addons::plugin_files() as $addon_id => $file ) {
			$slug         = $slug_base . '-' . $addon_id;
			$out[ $slug ] = [
				'id'           => $addon_id,
				'name'         => $names[ $addon_id ] ?? $addon_id,
				'options_slug' => $slug,
				'path'         => $file,
			];
		}

		return $out;
	}

	/**
	 * Ensure the license is activated on the shop and return a row with fresh package URLs.
	 *
	 * Package downloads are validated by EDD SL on the shop. Local sitesActivated rows alone are not enough.
	 *
	 * @param array<string, mixed> $row License row containing licenseKey.
	 * @return array<string, mixed>|WP_Error Updated row or error.
	 */
	private static function ensure_shop_license_ready_for_package_download( array $row ) {
		$license_key = trim( (string) ( $row['licenseKey'] ?? '' ) );
		if ( '' === $license_key || ! self::is_license_entitled( $row ) ) {
			return $row;
		}

		$hostname = self::get_site_hostname();
		$rich     = self::get_licenses();

		if ( '' !== $hostname ) {
			foreach ( $rich as $stored ) {
				if ( ! is_array( $stored ) || (string) ( $stored['licenseKey'] ?? '' ) !== $license_key ) {
					continue;
				}
				if ( self::is_site_activated_on_license( $stored, $hostname ) ) {
					if ( self::should_sync_successor_activation_to_shop( $rich, $stored ) ) {
						$rich = self::sync_successor_activation_to_shop( $rich, $license_key );
						update_option( self::OPTION_RICH, $rich, false );

						foreach ( $rich as $fresh ) {
							if ( is_array( $fresh ) && (string) ( $fresh['licenseKey'] ?? '' ) === $license_key ) {
								return $fresh;
							}
						}
					}

					return $stored;
				}
			}

			if ( self::is_site_activated_on_license( $row, $hostname ) ) {
				return $row;
			}
		}

		if ( self::is_upgrade_successor_with_site_already_in_use( $rich, $row ) ) {
			$rich = self::promote_successor_license_on_shop( $rich, $license_key, true );
			update_option( self::OPTION_RICH, $rich, false );

			foreach ( $rich as $fresh ) {
				if ( is_array( $fresh ) && (string) ( $fresh['licenseKey'] ?? '' ) === $license_key ) {
					return $fresh;
				}
			}

			return $row;
		}

		$activated = self::request_shop_activate( $license_key );
		if ( is_wp_error( $activated ) ) {
			return $activated;
		}

		if ( [] !== $activated ) {
			foreach ( $activated as $fresh ) {
				if ( is_array( $fresh ) && (string) ( $fresh['licenseKey'] ?? '' ) === $license_key ) {
					$row = $fresh;
					break;
				}
			}
		}

		$refreshed = self::fetch_license_row_from_shop( $row );
		if ( is_wp_error( $refreshed ) ) {
			if ( [] !== $activated ) {
				return $row;
			}

			return $refreshed;
		}

		return is_array( $refreshed ) ? $refreshed : $row;
	}

	/**
	 * Validate all persisted licenses against the shop and refresh local rows + mirrors.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function validate_persisted_licenses_from_shop(): array {
		$rich = self::get_licenses();
		if ( [] === $rich ) {
			return [];
		}

		foreach ( $rich as $index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$license_key = trim( (string) ( $row['licenseKey'] ?? '' ) );
			if ( '' === $license_key ) {
				continue;
			}

			$fresh = self::fetch_license_row_from_shop( $row );
			if ( is_wp_error( $fresh ) ) {
				if ( self::is_shop_validate_not_found_error( $fresh ) ) {
					$row['status'] = 'expired';
					$rich[ $index ] = $row;
				}
				continue;
			}

			if ( is_array( $fresh ) ) {
				$rich[ $index ] = $fresh;
			}
		}

		update_option( self::OPTION_RICH, $rich, false );

		$rich = self::reconcile_persisted_licenses( $rich, false, false );

		return self::finalize_license_sync( $rich );
	}

	/**
	 * Refresh one persisted license from the shop and reconcile local mirrors + expiry crons.
	 *
	 * @param string $license_key License key.
	 * @return array<int, array<string, mixed>>
	 */
	public static function sync_persisted_license_from_shop( string $license_key ): array {
		$license_key = trim( $license_key );
		if ( '' === $license_key ) {
			return self::get_licenses();
		}

		$rich  = self::get_licenses();
		$index = null;

		foreach ( $rich as $i => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			if ( trim( (string) ( $row['licenseKey'] ?? '' ) ) === $license_key ) {
				$index = $i;
				break;
			}
		}

		if ( null === $index ) {
			return $rich;
		}

		$row   = $rich[ $index ];
		$fresh = self::fetch_license_row_from_shop( $row );

		if ( is_wp_error( $fresh ) ) {
			if ( self::is_shop_validate_not_found_error( $fresh ) ) {
				$row['status']    = 'expired';
				$rich[ $index ]   = $row;
				update_option( self::OPTION_RICH, $rich, false );
				$rich = self::reconcile_persisted_licenses( $rich, false, false );

				return self::finalize_license_sync( $rich );
			}

			return $rich;
		}

		if ( ! is_array( $fresh ) ) {
			return $rich;
		}

		$rich[ $index ] = $fresh;
		update_option( self::OPTION_RICH, $rich, false );
		$rich = self::reconcile_persisted_licenses( $rich, false, false );

		return self::finalize_license_sync( $rich );
	}

	/**
	 * Whether a shop validate error means the license key no longer exists.
	 *
	 * @param WP_Error $error Error from {@see self::fetch_license_row_from_shop()}.
	 * @return bool
	 */
	private static function is_shop_validate_not_found_error( WP_Error $error ): bool {
		return License_Shop_Client::is_validate_not_found_error( $error );
	}

	/**
	 * Fetch one license row from the shop validate endpoint (fresh status, URLs, activations).
	 *
	 * @param array<string, mixed> $row License row containing licenseKey.
	 * @return array<string, mixed>|WP_Error Updated row or error.
	 */
	public static function fetch_license_row_from_shop( array $row ) {
		return License_Shop_Client::fetch_license_row( $row );
	}

	/**
	 * Download add-on zip from license row, install into wp-content/plugins, activate when valid.
	 *
	 * Target path comes from the static add-on catalog ($addon_id), not from get_plugins().
	 * get_plugins() is only used to see whether that path already exists on disk.
	 *
	 * @param array<string, mixed> $row             License row (download_url from shop).
	 * @param string               $addon_id        Short add-on id from sync loop.
	 * @param bool                 $force_install   When true (licenses UI), remove existing add-on then install fresh.
	 * @param bool                 $skip_activation plugin activation.
	 * @return bool|WP_Error
	 */
	public static function install_addon_from_download_url( array $row, string $addon_id, bool $force_install = false, bool $skip_activation = false ) {
		if ( ! self::is_license_entitled( $row ) ) {
			return true;
		}

		$plugin_file = self::get_plugin_file_for_addon_id( $addon_id );
		if ( null === $plugin_file ) {
			return true;
		}

		if ( ! self::license_row_covers_addon( $row, $addon_id ) ) {
			$row = self::resolve_install_license_row( $addon_id, $row );
		}

		$download_url = self::get_download_url_for_addon( $row, $addon_id );
		if ( '' === $download_url ) {
			return true;
		}

		if ( $force_install ) {
			$hostname        = self::get_site_hostname();
			$needs_shop_prep = '' !== $hostname
				&& ! self::is_site_activated_on_license( $row, $hostname )
				&& '' === $download_url;

			if ( $needs_shop_prep ) {
				$ready = self::ensure_shop_license_ready_for_package_download( $row );
				if ( is_wp_error( $ready ) ) {
					return $ready;
				}
				if ( is_array( $ready ) ) {
					$row          = $ready;
					$download_url = self::get_download_url_for_addon( $row, $addon_id );
				}
			}
		}

		$can_install  = current_user_can( 'install_plugins' );
		$can_activate = current_user_can( 'activate_plugins' );
		if ( ! $can_install ) {
			return new WP_Error(
				'advanced_ads_install_cap',
				__( 'You do not have permission to install plugins.', 'advanced-ads' )
			);
		}

		$is_on_disk = self::is_addon_plugin_on_disk( $addon_id );

		if ( ! $is_on_disk ) {
			$installed = self::install_addon_package( $download_url, $addon_id, $row, ! $force_install );
			if ( is_wp_error( $installed ) ) {
				return $installed;
			}
		}

		if ( $skip_activation || ! self::is_license_active( $row ) || ! $can_activate ) {
			return true;
		}

		if ( ! self::is_addon_plugin_on_disk( $addon_id ) ) {
			return true;
		}

		if ( is_plugin_active( $plugin_file ) ) {
			return true;
		}

		// Second arg is redirect URL, not "silent" — use activate_plugin() to avoid REST redirects.
		$activated = activate_plugin( $plugin_file, '', false, true );
		if ( is_wp_error( $activated ) ) {
			return $activated;
		}

		return true;
	}

	/**
	 * Download and install an add-on package.
	 *
	 * @param string               $download_url       Package URL.
	 * @param string               $addon_id           Add-on id.
	 * @param array<string, mixed> $row                License row.
	 * @param bool                 $skip_if_cached_url When true, skip duplicate downloads in one request (background sync).
	 * @return true|false|WP_Error True when install ran, false when skipped.
	 */
	private static function install_addon_package( string $download_url, string $addon_id, array $row, bool $skip_if_cached_url = true ) {
		return License_Package_Installer::install_package(
			$download_url,
			$addon_id,
			self::package_download_cache_key( $download_url, $addon_id, $row ),
			$skip_if_cached_url,
			static function () use ( $row, $addon_id, $download_url ): string {
				$ready = self::ensure_shop_license_ready_for_package_download( $row );
				if ( is_wp_error( $ready ) || ! is_array( $ready ) ) {
					return '';
				}

				$fresh_url = self::get_download_url_for_addon( $ready, $addon_id );

				return ( '' !== $fresh_url && $fresh_url !== $download_url ) ? $fresh_url : '';
			}
		);
	}

	/**
	 * Cache key so All Access shares one zip per request, single products do not block each other.
	 *
	 * @param string               $download_url Package URL.
	 * @param string               $addon_id     Add-on id.
	 * @param array<string, mixed> $row          License row.
	 * @return string
	 */
	private static function package_download_cache_key( string $download_url, string $addon_id, array $row ): string {
		if (
			License_Product_Map::is_all_access_bundle_name( (string) ( $row['name'] ?? '' ) )
			&& ! self::license_row_has_distinct_addon_download_urls( $row )
		) {
			return md5( $download_url );
		}

		return md5( $download_url . '|' . $addon_id );
	}

	/**
	 * Whether the configured shop/API host is a local or development environment.
	 *
	 * Laragon, Valet, and similar setups use `.test` / `.local` hosts that resolve to
	 * 127.0.0.1. WordPress then rejects outbound requests via {@see wp_http_validate_url()}
	 * unless the host is treated as external.
	 *
	 * @return bool
	 */
	public static function shop_uses_local_development_host(): bool {
		return License_Shop_Client::uses_local_development_host();
	}

	/**
	 * Mark the configured shop host as external for local/development HTTP requests.
	 *
	 * @param bool   $is_external Whether WordPress considers the host external.
	 * @param string $host        Request host.
	 * @param string $url         Request URL.
	 * @return bool
	 */
	public static function allow_local_development_shop_http_request( $is_external, string $host, string $url ) {
		return License_Shop_Client::allow_local_development_http_request( $is_external, $host, $url );
	}

	/**
	 * Register the local/development shop HTTP bypass for admin add-on updates.
	 *
	 * @return void
	 */
	public static function register_local_development_shop_http_filters(): void {
		License_Shop_Client::register_local_development_http_filters();
	}

	/**
	 * Shop hosts from {@see AA_SHOP_URL} and {@see Constants::API_ENDPOINT}.
	 *
	 * @return string[]
	 */
	public static function get_configured_shop_api_hosts(): array {
		return License_Shop_Client::get_configured_api_hosts();
	}

	/**
	 * Temporarily allow shop HTTP during a single license API or download request.
	 *
	 * @return void
	 */
	private static function add_local_development_shop_http_filter(): void {
		License_Shop_Client::add_local_development_http_filter();
	}

	/**
	 * Remove the temporary local/development shop HTTP bypass.
	 *
	 * @return void
	 */
	private static function remove_local_development_shop_http_filter(): void {
		License_Shop_Client::remove_local_development_http_filter();
	}

	/**
	 * Whether a package download URL targets the configured shop host (local/staging).
	 *
	 * @param string $download_url Package download URL from the shop.
	 * @return bool
	 */
	private static function is_shop_download_url( string $download_url ): bool {
		return License_Shop_Client::is_shop_download_url( $download_url );
	}

	/**
	 * Mark per-addon EDD mirror options as expired (no valid license for this add-on).
	 *
	 * @param string $addon_id Short addon id.
	 * @return void
	 */
	private static function clear_addon_license_mirror( string $addon_id ): void {
		if ( self::is_flat_map_retired() ) {
			return;
		}

		$options_slug = License_Utils::options_slug_for_addon_id( $addon_id );

		update_option( $options_slug . '-license-status', 'expired', false );
		update_option( $options_slug . '-license-expires', '', false );
	}

	/**
	 * Save rich licenses like legacy save_licenses: option + addon map + status mirrors.
	 * Auto shop activate only when advanced-ads-licenses is empty (first setup). Otherwise use the UI button.
	 * Shop connect/buy passes $preserve_legacy_map so advanced-ads-licenses is not rebuilt from rich rows.
	 *
	 * @param array<int, array<string, mixed>> $licenses              Incoming rich licenses.
	 * @param bool                             $activate_new          Whether exchange/connect asked for activation.
	 * @param string                           $activating_license_key License key the user activated on the licenses UI.
	 * @param string                           $activating_addon_id    When set with All Access, install only this add-on.
	 * @param bool                             $install_only           When true, download package only (no license mirror / plugin activation).
	 * @param string                           $deactivating_addon_id    When set, deactivate this add-on plugin on the site.
	 * @param string                           $deactivating_license_key When set, remove this site from that license row.
	 * @param bool                             $preserve_legacy_map      When true (shop connect/buy), do not overwrite advanced-ads-licenses.
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	public static function save_licenses( array $licenses, bool $activate_new = false, string $activating_license_key = '', string $activating_addon_id = '', bool $install_only = false, string $deactivating_addon_id = '', string $deactivating_license_key = '', bool $preserve_legacy_map = false ) {
		$incoming  = self::normalize_list( $licenses );
		$existing  = self::get_licenses();
		$was_empty = [] === $existing;

		$merged = self::merge_license_lists( $existing, $incoming );

		$activating_addon_id_scoped = sanitize_key( trim( $activating_addon_id ) );

		if ( '' !== trim( $activating_license_key ) ) {
			if ( '' === $activating_addon_id_scoped ) {
				$activated = self::activate_on_shop_then_local( trim( $activating_license_key ), $merged );
				if ( is_wp_error( $activated ) ) {
					return $activated;
				}
				$merged = $activated;
			} elseif ( ! $install_only ) {
				$merged = self::detach_single_product_license_from_site_for_addon(
					$merged,
					trim( $activating_license_key ),
					$activating_addon_id_scoped
				);

				$merged = self::ensure_all_access_site_slot_for_addon_activation(
					$merged,
					trim( $activating_license_key )
				);
				if ( is_wp_error( $merged ) ) {
					return $merged;
				}
			}

			self::detach_addon_from_all_access_when_single_license_activates( $merged, trim( $activating_license_key ) );
		}

		update_option( self::OPTION_RICH, $merged, false );

		$deactivating_license_key = sanitize_text_field( trim( $deactivating_license_key ) );
		if ( '' !== $deactivating_license_key ) {
			$deactivated_row = License_Utils::get_rich_license_row_by_key( $merged, $deactivating_license_key );

			$merged = self::deactivate_on_shop_then_local( $deactivating_license_key, $merged );
			if ( is_wp_error( $merged ) ) {
				return $merged;
			}

			if ( is_array( $deactivated_row ) ) {
				$name = (string) ( $deactivated_row['name'] ?? '' );
				if ( ! License_Product_Map::is_all_access_bundle_name( $name ) ) {
					$addon_id = License_Product_Map::addon_id_from_product_name(
						$name,
						self::build_addon_manifest()
					);

					$still_active = false;
					$hostname     = self::get_site_hostname();
					foreach ( self::normalize_list( $merged ) as $row ) {
						if ( ! is_array( $row ) ) {
							continue;
						}
						if ( ! self::is_same_product_line_row( $row, $deactivated_row ) ) {
							continue;
						}
						if ( self::is_site_activated_on_license( $row, $hostname ) ) {
							$still_active = true;
							break;
						}
					}

					if ( ! $still_active && null !== $addon_id ) {
						$deactivate_error = self::deactivate_addon_on_site( $addon_id );
						if ( is_wp_error( $deactivate_error ) ) {
							return $deactivate_error;
						}
					}
				}
			}

			update_option( self::OPTION_RICH, $merged, false );
			self::sync_addon_options_from_rich( $merged, '', false );
			self::persist_addon_key_map( self::build_persisted_addon_key_map_from_rich( $merged ) );

			return self::finalize_license_sync( $merged );
		}

		$deactivating_addon_id = sanitize_key( trim( $deactivating_addon_id ) );
		if ( '' !== $deactivating_addon_id ) {
			$deactivate_error = self::deactivate_addon_on_site( $deactivating_addon_id );
			if ( is_wp_error( $deactivate_error ) ) {
				return $deactivate_error;
			}

			self::sync_addon_options_from_rich( $merged, '', false );
			self::persist_addon_key_map( self::build_persisted_addon_key_map_from_rich( $merged ) );

			return self::finalize_license_sync( $merged );
		}

		$has_new_keys      = $activate_new && self::has_new_incoming_license_keys( $existing, $incoming );
		$try_shop_activate = self::should_run_shop_auto_activate( $was_empty || $activate_new );
		if ( ! $try_shop_activate && $has_new_keys ) {
			$try_shop_activate = self::incoming_new_keys_need_shop_activation( $existing, $merged );
		}
		if ( $has_new_keys && self::incoming_has_upgrade_successor_with_site_in_use( $existing, $merged ) ) {
			$try_shop_activate = false;
		}

		$is_legacy = self::is_legacy_license_store();
		if (
			$is_legacy
			&& ! $activate_new
			&& '' === trim( $activating_license_key )
			&& ! $has_new_keys
		) {
			$synced = self::sync_local_activations_to_shop( $merged, $existing );
			if ( is_wp_error( $synced ) ) {
				return $synced;
			}
			$merged            = $synced;
			$try_shop_activate = false;
			update_option( self::OPTION_RICH, $merged, false );
		}

		$merged = self::reconcile_persisted_licenses(
			$merged,
			$try_shop_activate,
			'' === trim( $activating_license_key )
				&& '' === trim( $deactivating_license_key )
				&& '' === trim( $deactivating_addon_id )
				&& ! $try_shop_activate
		);

		$sync_error = self::sync_addon_options_from_rich(
			$merged,
			trim( $activating_license_key ),
			true,
			trim( $activating_addon_id ),
			$install_only,
			! $preserve_legacy_map
		);
		if ( is_wp_error( $sync_error ) ) {
			return $sync_error;
		}

		if ( ! $install_only && ! $preserve_legacy_map && ! self::has_stored_legacy_license_map() ) {
			self::persist_addon_key_map( self::build_persisted_addon_key_map_from_rich( $merged ) );
		}

		return self::finalize_license_sync( $merged );
	}

	/**
	 * Add-on ids the user explicitly activated under All Access on this site.
	 *
	 * @return string[]
	 */
	public static function get_aa_activated_addon_ids(): array {
		$raw = get_option( self::OPTION_AA_ACTIVATED_ADDONS, [] );
		if ( ! is_array( $raw ) ) {
			return [];
		}

		$out = [];
		foreach ( $raw as $id ) {
			$id = sanitize_key( (string) $id );
			if ( '' !== $id && Addons::is_known_addon( $id ) ) {
				$out[] = $id;
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Drop an add-on from the All Access UI list when a single-product license takes over on site.
	 *
	 * @param array<int, array<string, mixed>> $rich                  Rich license list.
	 * @param string                           $activating_license_key License key the user activated.
	 * @return void
	 */
	/**
	 * When one add-on is activated under All Access, drop that product's single license on this site.
	 *
	 * @param array<int, array<string, mixed>> $rich                  Rich license list.
	 * @param string                           $all_access_license_key All Access license key from the UI.
	 * @param string                           $addon_id               Short add-on id (pro, tracking, …).
	 * @return array<int, array<string, mixed>>
	 */
	private static function detach_single_product_license_from_site_for_addon( array $rich, string $all_access_license_key, string $addon_id ): array {
		$addon_id = sanitize_key( $addon_id );
		$hostname = self::get_site_hostname();

		if ( '' === $addon_id || '' === $hostname || '' === trim( $all_access_license_key ) ) {
			return $rich;
		}

		$all_access_license_key = trim( $all_access_license_key );
		$manifest               = self::build_addon_manifest();
		$is_all_access          = false;

		foreach ( $rich as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( (string) ( $row['licenseKey'] ?? '' ) !== $all_access_license_key ) {
				continue;
			}
			if ( License_Product_Map::is_all_access_bundle_name( (string) ( $row['name'] ?? '' ) ) ) {
				$is_all_access = true;
			}
			break;
		}

		if ( ! $is_all_access ) {
			return $rich;
		}

		foreach ( $rich as $index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$name = (string) ( $row['name'] ?? '' );
			if ( License_Product_Map::is_all_access_bundle_name( $name ) ) {
				continue;
			}

			$row_addon_id = License_Product_Map::addon_id_from_product_name( $name, $manifest );
			if ( $row_addon_id !== $addon_id ) {
				continue;
			}

			if ( ! self::is_site_activated_on_license( $row, $hostname ) ) {
				continue;
			}

			$rich[ $index ] = self::remove_site_hostname_from_license_row( $row, $hostname );
		}

		return $rich;
	}

	/**
	 * Drop an add-on from the All Access UI list when a single-product license takes over on site.
	 *
	 * @param array<int, array<string, mixed>> $rich                  Rich license list.
	 * @param string                           $activating_license_key License key the user activated.
	 * @return void
	 */
	private static function detach_addon_from_all_access_when_single_license_activates( array $rich, string $activating_license_key ): void {
		if ( '' === $activating_license_key ) {
			return;
		}

		$manifest = self::build_addon_manifest();

		foreach ( $rich as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( (string) ( $row['licenseKey'] ?? '' ) !== $activating_license_key ) {
				continue;
			}

			$name = (string) ( $row['name'] ?? '' );
			if ( License_Product_Map::is_all_access_bundle_name( $name ) ) {
				return;
			}

			$addon_id = License_Product_Map::addon_id_from_product_name( $name, $manifest );
			if ( null === $addon_id ) {
				return;
			}

			self::remove_aa_activated_addon_id( $addon_id );
			return;
		}
	}

	/**
	 * Record one All Access add-on as user-activated on this site.
	 *
	 * @param string $addon_id Short add-on id.
	 * @return void
	 */
	public static function add_aa_activated_addon_id( string $addon_id ): void {
		$addon_id = sanitize_key( $addon_id );
		if ( '' === $addon_id || ! Addons::is_known_addon( $addon_id ) ) {
			return;
		}

		$ids = self::get_aa_activated_addon_ids();
		if ( in_array( $addon_id, $ids, true ) ) {
			return;
		}

		$ids[] = $addon_id;
		update_option( self::OPTION_AA_ACTIVATED_ADDONS, $ids, false );
	}

	/**
	 * Remove one add-on from the user-activated All Access list on this site.
	 *
	 * @param string $addon_id Short add-on id.
	 * @return void
	 */
	public static function remove_aa_activated_addon_id( string $addon_id ): void {
		$addon_id = sanitize_key( $addon_id );
		if ( '' === $addon_id ) {
			return;
		}

		$ids = array_values(
			array_filter(
				self::get_aa_activated_addon_ids(),
				static fn( string $id ): bool => $id !== $addon_id
			)
		);

		update_option( self::OPTION_AA_ACTIVATED_ADDONS, $ids, false );
	}

	/**
	 * Deactivate one add-on plugin and clear its license mirror on this site.
	 *
	 * @param string $addon_id Short add-on id.
	 * @return true|WP_Error
	 */
	public static function deactivate_addon_on_site( string $addon_id ) {
		$addon_id = sanitize_key( $addon_id );
		if ( '' === $addon_id || ! Addons::is_known_addon( $addon_id ) ) {
			return new WP_Error(
				'advanced_ads_invalid_addon',
				__( 'Unknown add-on.', 'advanced-ads' )
			);
		}

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return new WP_Error(
				'advanced_ads_deactivate_cap',
				__( 'You do not have permission to deactivate plugins.', 'advanced-ads' )
			);
		}

		$plugin_file = self::get_plugin_file_for_addon_id( $addon_id );
		if ( null === $plugin_file ) {
			return true;
		}

		if ( is_plugin_active( $plugin_file ) ) {
			deactivate_plugins( $plugin_file, true );
		}

		self::remove_aa_activated_addon_id( $addon_id );
		self::clear_addon_license_mirror( $addon_id );

		return true;
	}

	/**
	 * Entitled All Access row activated for the current site hostname.
	 *
	 * @param array<int, array<string, mixed>> $rich Rich license list.
	 * @return array<string, mixed>|null
	 */
	private static function find_all_access_license_active_on_site( array $rich ): ?array {
		$hostname = self::get_site_hostname();
		$best     = null;
		$score    = 0;

		foreach ( self::normalize_list( $rich ) as $row ) {
			if ( ! is_array( $row ) || empty( $row['licenseKey'] ) || empty( $row['name'] ) ) {
				continue;
			}
			if ( ! License_Product_Map::is_all_access_bundle_name( (string) $row['name'] ) ) {
				continue;
			}
			if ( ! self::is_license_entitled( $row ) ) {
				continue;
			}
			if ( ! self::is_site_activated_on_license( $row, $hostname ) ) {
				continue;
			}

			$row_score = self::license_priority_score( $row, true );
			if ( $row_score > $score ) {
				$score = $row_score;
				$best  = $row;
			}
		}

		if ( null !== $best ) {
			return $best;
		}

		$map_keys = [];
		foreach ( License_Utils::normalize_legacy_map( get_option( self::OPTION_LEGACY_MAP, [] ) ) as $mapped_key ) {
			$mapped_key = trim( (string) $mapped_key );
			if ( '' !== $mapped_key ) {
				$map_keys[ $mapped_key ] = true;
			}
		}

		if ( [] === $map_keys ) {
			return null;
		}

		$score = 0;
		foreach ( self::normalize_list( $rich ) as $row ) {
			if ( ! is_array( $row ) || empty( $row['licenseKey'] ) || empty( $row['name'] ) ) {
				continue;
			}
			if ( ! License_Product_Map::is_all_access_bundle_name( (string) $row['name'] ) ) {
				continue;
			}
			if ( ! self::is_license_entitled( $row ) ) {
				continue;
			}

			$key = (string) $row['licenseKey'];
			if ( ! isset( $map_keys[ $key ] ) ) {
				continue;
			}

			$row_score = self::license_priority_score( $row, true );
			if ( $row_score > $score ) {
				$score = $row_score;
				$best  = $row;
			}
		}

		return $best;
	}

	/**
	 * Mirror this site on an All Access row when the user activates an add-on under it.
	 *
	 * @param array<int, array<string, mixed>> $rich        Rich license list.
	 * @param string                           $license_key All Access license key.
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	private static function ensure_all_access_site_slot_for_addon_activation( array $rich, string $license_key ) {
		$license_key = trim( $license_key );
		$hostname    = self::get_site_hostname();

		if ( '' === $license_key || '' === $hostname ) {
			return $rich;
		}

		foreach ( self::normalize_list( $rich ) as $row ) {
			if ( (string) ( $row['licenseKey'] ?? '' ) !== $license_key ) {
				continue;
			}
			if ( ! License_Product_Map::is_all_access_bundle_name( (string) ( $row['name'] ?? '' ) ) ) {
				return $rich;
			}

			if ( self::is_site_activated_on_license( $row, $hostname ) ) {
				return $rich;
			}

			foreach ( (array) ( $row['addons'] ?? [] ) as $addon ) {
				if ( is_array( $addon ) && '' !== trim( (string) ( $addon['download_url'] ?? '' ) ) ) {
					return $rich;
				}
			}

			return self::activate_on_shop_then_local( $license_key, $rich );
		}

		return $rich;
	}

	/**
	 * EDD SL license post ID for a rich row, when present.
	 *
	 * @param array<int, array<string, mixed>> $rich        Rich license list.
	 * @param string                           $license_key License key.
	 * @return int
	 */
	private static function resolve_license_id_for_key( array $rich, string $license_key ): int {
		$license_key = trim( $license_key );
		if ( '' === $license_key ) {
			return 0;
		}

		foreach ( self::normalize_list( $rich ) as $row ) {
			if ( (string) ( $row['licenseKey'] ?? '' ) !== $license_key ) {
				continue;
			}

			return (int) ( $row['licenseId'] ?? 0 );
		}

		return 0;
	}

	/**
	 * Keep site slots aligned with advanced-ads-licenses and one winner per product line.
	 *
	 * When the flat map is empty, no license is active on this site locally.
	 * When the map has keys, only mapped keys may keep the site slot; at most one row per product line.
	 *
	 * @param array<int, array<string, mixed>> $rich Rich license list.
	 * @return array<int, array<string, mixed>>
	 */
	public static function align_mutually_exclusive_site_slots( array $rich ): array {
		$hostname = self::get_site_hostname();
		if ( '' === $hostname ) {
			return $rich;
		}

		$allowed = self::resolve_allowed_site_license_keys( $rich );

		if ( [] === $allowed ) {
			foreach ( $rich as $index => $row ) {
				if ( ! is_array( $row ) || ! self::is_site_activated_on_license( $row, $hostname ) ) {
					continue;
				}
				$rich[ $index ] = self::remove_site_hostname_from_license_row( $row, $hostname );
			}

			return $rich;
		}

		$line_winners = [];
		$line_scores  = [];

		foreach ( self::normalize_list( $rich ) as $row ) {
			$key = (string) ( $row['licenseKey'] ?? '' );
			if ( '' === $key || ! isset( $allowed[ $key ] ) ) {
				continue;
			}
			if ( ! self::is_site_activated_on_license( $row, $hostname ) ) {
				continue;
			}

			$is_bundle = License_Product_Map::is_all_access_bundle_name( (string) ( $row['name'] ?? '' ) );
			$score     = self::license_priority_score( $row, $is_bundle );

			$rep_key = $key;
			foreach ( array_keys( $line_winners ) as $existing_rep ) {
				$rep_row = License_Utils::get_rich_license_row_by_key( $rich, $existing_rep );
				if ( is_array( $rep_row ) && self::is_same_product_line_row( $row, $rep_row ) ) {
					$rep_key = $existing_rep;
					break;
				}
			}

			if ( ! isset( $line_winners[ $rep_key ] ) || $score > $line_scores[ $rep_key ] ) {
				$line_winners[ $rep_key ] = $key;
				$line_scores[ $rep_key ]  = $score;
			}
		}

		foreach ( $rich as $index => $row ) {
			if ( ! is_array( $row ) || ! self::is_site_activated_on_license( $row, $hostname ) ) {
				continue;
			}

			$key  = (string) ( $row['licenseKey'] ?? '' );
			$drop = ! isset( $allowed[ $key ] );

			if ( ! $drop ) {
				$winner = $key;
				foreach ( $line_winners as $rep => $winner_key ) {
					$rep_row = License_Utils::get_rich_license_row_by_key( $rich, $rep );
					if ( is_array( $rep_row ) && self::is_same_product_line_row( $row, $rep_row ) ) {
						$winner = $winner_key;
						break;
					}
				}
				if ( $key !== $winner ) {
					$drop = true;
				}
			}

			if ( $drop ) {
				$rich[ $index ] = self::remove_site_hostname_from_license_row( $row, $hostname );
			}
		}

		return $rich;
	}

	/**
	 * License keys that may hold this site's activation slot (flat map + entitled rows with site).
	 *
	 * @param array<int, array<string, mixed>> $rich Rich license list.
	 * @return array<string, bool>
	 */
	public static function resolve_allowed_site_license_keys( array $rich ): array {
		$allowed    = [];
		$stored_map = License_Utils::normalize_legacy_map( get_option( self::OPTION_LEGACY_MAP, [] ) );

		foreach ( $stored_map as $key ) {
			$key = trim( (string) $key );
			if ( '' !== $key ) {
				$allowed[ $key ] = true;
			}
		}

		$hostname = self::get_site_hostname();
		foreach ( self::normalize_list( $rich ) as $row ) {
			if ( ! is_array( $row ) || ! self::is_license_entitled( $row ) ) {
				continue;
			}
			$key = (string) ( $row['licenseKey'] ?? '' );
			if ( '' === $key || ! self::is_site_activated_on_license( $row, $hostname ) ) {
				continue;
			}
			$allowed[ $key ] = true;
		}

		return $allowed;
	}

	/**
	 * Whether an All Access add-on should receive license mirror options on this site.
	 *
	 * @param string $addon_id              Short add-on id.
	 * @param string $install_only_addon_id Add-on from the current Licenses UI request.
	 * @param bool   $install_only          Download-only request (no activation).
	 * @return bool
	 */
	private static function should_mirror_all_access_addon_on_site( string $addon_id, string $install_only_addon_id, bool $install_only ): bool {
		if ( in_array( $addon_id, self::get_aa_activated_addon_ids(), true ) ) {
			return true;
		}

		if ( '' !== $install_only_addon_id && $install_only_addon_id === $addon_id && ! $install_only ) {
			self::add_aa_activated_addon_id( $addon_id );
			return true;
		}

		return false;
	}

	/**
	 * Remove add-ons from the All Access UI list when another license owns them on site.
	 *
	 * @param array<int, array<string, mixed>> $rich Rich license list.
	 * @return void
	 */
	public static function sync_aa_activated_addon_ids_with_assignments( array $rich ): void {
		$aa          = self::find_all_access_license_active_on_site( $rich );
		$aa_key      = null !== $aa ? (string) ( $aa['licenseKey'] ?? '' ) : '';
		$assignments = self::resolve_addon_license_assignments( $rich );
		$stored_map  = License_Utils::normalize_legacy_map( get_option( self::OPTION_LEGACY_MAP, [] ) );
		$pruned      = array_values(
			array_filter(
				self::get_aa_activated_addon_ids(),
				static function ( string $addon_id ) use ( $assignments, $aa_key, $stored_map, $rich ): bool {
					if ( isset( $stored_map[ $addon_id ] ) ) {
						$mapped_key = trim( (string) $stored_map[ $addon_id ] );
						if ( '' !== $mapped_key && License::license_row_exists_for_key( $rich, $mapped_key ) ) {
							return true;
						}
					}

					if ( '' === $aa_key ) {
						return false;
					}

					return isset( $assignments[ $addon_id ] )
						&& (string) $assignments[ $addon_id ]['licenseKey'] === $aa_key;
				}
			)
		);

		update_option( self::OPTION_AA_ACTIVATED_ADDONS, $pruned, false );
	}

	/**
	 * Legacy addon map to persist: All Access only includes user-activated add-ons.
	 *
	 * @param array<int, array<string, mixed>> $rich Rich license list.
	 * @return array<string, string>
	 */
	public static function build_persisted_addon_key_map_from_rich( array $rich ): array {
		self::sync_aa_activated_addon_ids_with_assignments( $rich );

		$full = self::get_active_addon_key_map_from_rich( $rich );
		$aa   = self::find_all_access_license_active_on_site( $rich );

		if ( null === $aa ) {
			return $full;
		}

		$aa_key    = (string) ( $aa['licenseKey'] ?? '' );
		$activated = self::get_aa_activated_addon_ids();
		$map       = [];

		foreach ( $full as $addon_id => $key ) {
			if ( $key !== $aa_key ) {
				$map[ $addon_id ] = $key;
			}
		}

		foreach ( $activated as $addon_id ) {
			$map[ $addon_id ] = $aa_key;
		}

		return $map;
	}

	/**
	 * Whether each add-on package is on disk and the plugin is active.
	 *
	 * @return array<string, array{installed: bool, active: bool}>
	 */
	public static function get_addon_install_states(): array {
		$out = [];
		foreach ( Addons::plugin_files() as $addon_id => $plugin_file ) {
			$installed        = self::is_addon_plugin_on_disk( $addon_id );
			$out[ $addon_id ] = [
				'installed' => $installed,
				'active'    => $installed && is_plugin_active( $plugin_file ),
			];
		}

		return $out;
	}

	/**
	 * Align legacy map and per-addon status options with entitled rich licenses.
	 *
	 * @param array<int, array<string, mixed>> $rich              Rich license list.
	 * @param bool                             $try_shop_activate Attempt shop activate when true (empty store / exchange / button save).
	 * @param bool                             $mutate_activation_state When false (GET), only sync addon mirrors; do not align sites or rebuild the flat map.
	 * @return array<int, array<string, mixed>>
	 */
	public static function reconcile_persisted_licenses( array $rich, bool $try_shop_activate = false, bool $mutate_activation_state = true ): array {
		if ( $mutate_activation_state && [] !== $rich ) {
			$migrated = self::migrate_site_activation_to_successor_licenses( $rich );
			if ( wp_json_encode( $migrated ) !== wp_json_encode( $rich ) ) {
				$rich = $migrated;
				update_option( self::OPTION_RICH, $rich, false );
			}
		}

		if ( $try_shop_activate && [] !== $rich ) {
			$rich = self::maybe_activate_licenses_for_current_site( $rich );
			update_option( self::OPTION_RICH, $rich, false );
		}

		self::sync_addon_options_from_rich( $rich, '', false );

		if ( ! $mutate_activation_state ) {
			return self::get_licenses();
		}

		$rich      = self::get_licenses();
		$coalesced = self::coalesce_all_access_duplicate_rich_rows( $rich );
		$coalesced = self::drop_map_stub_duplicate_rows( $coalesced );
		if ( wp_json_encode( $coalesced ) !== wp_json_encode( $rich ) ) {
			$rich = $coalesced;
			update_option( self::OPTION_RICH, $rich, false );
		}

		$aligned = self::align_mutually_exclusive_site_slots( $rich );
		if ( wp_json_encode( $aligned ) !== wp_json_encode( $rich ) ) {
			$rich = $aligned;
			update_option( self::OPTION_RICH, $rich, false );
		}

		return $rich;
	}

	/**
	 * Whether a rich license row should grant addon access (status or future expiry).
	 *
	 * @param array<string, mixed> $row Rich license row.
	 * @return bool
	 */
	public static function is_license_effective( array $row ): bool {
		if ( self::is_license_active( $row ) ) {
			return true;
		}

		$status = strtolower( (string) ( $row['status'] ?? '' ) );
		$name   = (string) ( $row['name'] ?? '' );

		// Inactive with valid subscription still grants access until truly expired.
		if ( 'inactive' === $status ) {
			return self::is_license_entitled( $row );
		}

		// Expired All Access must not override per-addon active licenses.
		if ( in_array( $status, [ 'expired', 'invalid', 'disabled' ], true ) ) {
			if ( License_Product_Map::is_all_access_bundle_name( $name ) ) {
				return false;
			}

			return License_Utils::license_expiry_is_future( $row );
		}

		return false;
	}

	/**
	 * @param int $min_interval Minimum seconds since last sync.
	 * @return bool
	 */
	public static function should_skip_shop_sync( int $min_interval = DAY_IN_SECONDS ): bool {
		$last = License_Utils::get_last_sync();

		return $last > 0 && ( time() - $last ) < $min_interval;
	}

	/**
	 * @param array<int, array<string, mixed>> $rich Rich license list.
	 * @param string|null                      $only_key When set, update only this license key.
	 * @param bool                             $clear_future_notices Clear notice flags for renewed licenses.
	 * @return array<int, array<string, mixed>>
	 */
	public static function sync_local_expiry( array $rich, ?string $only_key = null, bool $clear_future_notices = false ): array {
		$changed = false;

		foreach ( $rich as $i => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$key = trim( (string) ( $row['licenseKey'] ?? '' ) );
			if ( null !== $only_key && $key !== $only_key ) {
				continue;
			}

			$updated = License_Utils::apply_local_expiry_to_row( $row );
			if ( $updated !== $row ) {
				$rich[ $i ] = $updated;
				$changed    = true;
			}

			if ( $clear_future_notices && '' !== $key && License_Utils::license_expiry_is_future( $updated ) ) {
				License_Utils::update_expiry_notices( $key, null );
			}

			if ( null !== $only_key ) {
				break;
			}
		}

		if ( ! $changed ) {
			return $rich;
		}

		update_option( self::OPTION_RICH, $rich, false );
		self::sync_addon_options_from_rich( $rich, '', false );

		if ( null !== $only_key ) {
			License_Utils::update_expiry_notices( $only_key, null );
		}

		return $rich;
	}

	/**
	 * @param array<int, array<string, mixed>> $rich Rich license list.
	 * @return array<int, array<string, mixed>>
	 */
	public static function finalize_license_sync( array $rich ): array {
		$rich = self::sync_local_expiry( $rich, null, true );
		update_option( License_Utils::OPTION_LAST_SYNC, time(), false );
		License_Cron::schedule_license_expiry( $rich );

		return $rich;
	}

	/**
	 * Priority when multiple licenses map to the same addon (higher wins).
	 *
	 * @param array<string, mixed> $row       Rich license row.
	 * @param bool                 $is_bundle All Access–style bundle.
	 * @return int Zero when the license should not apply.
	 */
	public static function license_priority_score( array $row, bool $is_bundle ): int {
		if ( ! self::is_license_entitled( $row ) ) {
			return 0;
		}

		$score = 100;
		if ( $is_bundle ) {
			$score += 100;
		} else {
			$score += 50;
		}
		if ( self::is_license_active( $row ) ) {
			$score += 20;
		}

		// Single product activated on this site beats All Access for the same add-on.
		if ( ! $is_bundle && self::is_site_activated_on_license( $row, self::get_site_hostname() ) ) {
			$score += 250;
		}

		return $score;
	}

	/**
	 * Best entitled All Access row, if any.
	 *
	 * @param array<int, array<string, mixed>> $rich Rich license list.
	 * @return array<string, mixed>|null
	 */
	public static function find_entitled_all_access_license( array $rich ): ?array {
		$best       = null;
		$best_score = 0;

		foreach ( $rich as $row ) {
			if ( ! is_array( $row ) || empty( $row['licenseKey'] ) || empty( $row['name'] ) ) {
				continue;
			}
			if ( ! License_Product_Map::is_all_access_bundle_name( (string) $row['name'] ) ) {
				continue;
			}
			if ( ! self::is_license_entitled( $row ) ) {
				continue;
			}

			$score = self::license_priority_score( $row, true );
			if ( $score > $best_score ) {
				$best_score = $score;
				$best       = $row;
			}
		}

		return $best;
	}

	/**
	 * Addon id => licenseKey for active/valid rich licenses only.
	 *
	 * @param array<int, array<string, mixed>> $rich Rich license list.
	 * @param array<int, array<string, mixed>> $addons addons list.
	 * @return array<string, string>
	 */
	public static function get_active_addon_key_map_from_rich( array $rich, ?array $addons = null ): array {
		$assignments = self::resolve_addon_license_assignments( $rich, $addons );
		$out         = [];

		foreach ( $assignments as $addon_id => $data ) {
			if ( ! self::is_license_entitled( $data['row'] ) ) {
				continue;
			}
			$out[ $addon_id ] = $data['licenseKey'];
		}

		return $out;
	}

	/**
	 * Installed add-ons or static fallback when plugins are not loaded (e.g. unit tests).
	 *
	 * @param array<string, array>|null $addons Explicit list for tests.
	 * @return array<string, array>
	 */
	private static function get_addons_for_license_map( ?array $addons = null ): array {
		if ( null !== $addons ) {
			return $addons;
		}

		return self::build_addon_manifest();
	}

	/**
	 * Whether any rich row carries site activation data (per-site assignment mode).
	 *
	 * @param array<int, array<string, mixed>> $rich Rich license list.
	 * @return bool
	 */
	private static function has_site_activation_rows( array $rich ): bool {
		foreach ( $rich as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$sites = $row['sitesActivated'] ?? [];
			if ( is_array( $sites ) && [] !== $sites ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Pick the best license per addon when several rich rows overlap.
	 *
	 * @param array<int, array<string, mixed>> $rich   Records from app/exchange.
	 * @param array<string, array>|null        $addons Optional; defaults to Data::get_addons().
	 * @return array<string, array{licenseKey: string, row: array<string, mixed>, score: int}>
	 */
	public static function resolve_addon_license_assignments( array $rich, ?array $addons = null ): array {
		$hostname = self::get_site_hostname();

		if ( '' !== $hostname && self::has_site_activation_rows( $rich ) ) {
			return self::resolve_addon_license_assignments_per_site( $rich, $addons );
		}

		return self::resolve_addon_license_assignments_legacy( $rich, $addons );
	}

	/**
	 * Per-site: All Access on this site covers all add-ons; otherwise only activated single licenses apply.
	 *
	 * @param array<int, array<string, mixed>> $rich   Rich license list.
	 * @param array<string, array>|null        $addons Optional add-on manifest.
	 * @return array<string, array{licenseKey: string, row: array<string, mixed>, score: int}>
	 */
	private static function resolve_addon_license_assignments_per_site( array $rich, ?array $addons = null ): array {
		$addons     = self::get_addons_for_license_map( $addons );
		$hostname   = self::get_site_hostname();
		$candidates = [];

		$all_access_on_site = self::find_all_access_license_active_on_site( $rich );
		if ( null !== $all_access_on_site ) {
			$key   = (string) $all_access_on_site['licenseKey'];
			$score = self::license_priority_score( $all_access_on_site, true );

			foreach ( $addons as $addon_row ) {
				if ( empty( $addon_row['id'] ) || 'slider-ads' === $addon_row['id'] ) {
					continue;
				}
				self::offer_addon_license_candidate(
					$candidates,
					(string) $addon_row['id'],
					$key,
					$score,
					$all_access_on_site
				);
			}
		}

		foreach ( $rich as $row ) {
			if ( ! is_array( $row ) || empty( $row['licenseKey'] ) || empty( $row['name'] ) ) {
				continue;
			}

			$name = (string) $row['name'];
			if ( License_Product_Map::is_all_access_bundle_name( $name ) ) {
				continue;
			}

			if ( ! self::is_license_entitled( $row ) ) {
				continue;
			}

			if ( ! self::is_site_activated_on_license( $row, $hostname ) ) {
				continue;
			}

			$addon_id = License_Product_Map::addon_id_from_product_name( $name, $addons );
			if ( null === $addon_id ) {
				continue;
			}

			$key   = (string) $row['licenseKey'];
			$score = self::license_priority_score( $row, false );
			self::offer_addon_license_candidate( $candidates, $addon_id, $key, $score, $row );
		}

		$aa_for_addons = self::find_all_access_row_for_aa_addon_assignments( $rich );
		if ( null !== $aa_for_addons ) {
			$aa_key  = (string) $aa_for_addons['licenseKey'];
			$aa_take = self::license_priority_score( $aa_for_addons, true ) + 500;

			foreach ( self::get_aa_activated_addon_ids() as $addon_id ) {
				self::offer_addon_license_candidate(
					$candidates,
					$addon_id,
					$aa_key,
					$aa_take,
					$aa_for_addons
				);
			}
		}

		return $candidates;
	}

	/**
	 * All Access row that owns explicitly activated add-ons on this site (not highest-score bundle only).
	 *
	 * @param array<int, array<string, mixed>> $rich Rich license list.
	 * @return array<string, mixed>|null
	 */
	private static function find_all_access_row_for_aa_addon_assignments( array $rich ): ?array {
		$on_site = self::find_all_access_license_active_on_site( $rich );
		if ( null !== $on_site ) {
			return $on_site;
		}

		$stored_map = License_Utils::normalize_legacy_map( get_option( self::OPTION_LEGACY_MAP, [] ) );
		foreach ( self::get_aa_activated_addon_ids() as $addon_id ) {
			$mapped_key = isset( $stored_map[ $addon_id ] ) ? trim( (string) $stored_map[ $addon_id ] ) : '';
			if ( '' === $mapped_key ) {
				continue;
			}

			foreach ( self::normalize_list( $rich ) as $row ) {
				if ( (string) ( $row['licenseKey'] ?? '' ) !== $mapped_key ) {
					continue;
				}
				if ( ! License_Product_Map::is_all_access_bundle_name( (string) ( $row['name'] ?? '' ) ) ) {
					continue;
				}
				if ( ! self::is_license_entitled( $row ) ) {
					continue;
				}

				return $row;
			}
		}

		return null;
	}

	/**
	 * Whether a rich row exists for a license key.
	 *
	 * @param array<int, array<string, mixed>> $rich Rich license list.
	 * @param string                           $license_key License key.
	 * @return bool
	 */
	public static function license_row_exists_for_key( array $rich, string $license_key ): bool {
		$license_key = trim( $license_key );
		if ( '' === $license_key ) {
			return false;
		}

		foreach ( self::normalize_list( $rich ) as $row ) {
			if ( (string) ( $row['licenseKey'] ?? '' ) === $license_key ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Legacy assignment when rich data has no sitesActivated rows (score / All Access bundle).
	 *
	 * @param array<int, array<string, mixed>> $rich   Rich license list.
	 * @param array<string, array>|null        $addons Optional add-on manifest.
	 * @return array<string, array{licenseKey: string, row: array<string, mixed>, score: int}>
	 */
	private static function resolve_addon_license_assignments_legacy( array $rich, ?array $addons = null ): array {
		$addons     = self::get_addons_for_license_map( $addons );
		$candidates = [];

		$all_access = self::find_entitled_all_access_license( $rich );
		if ( null !== $all_access ) {
			$key   = (string) $all_access['licenseKey'];
			$score = self::license_priority_score( $all_access, true );

			foreach ( $addons as $addon_row ) {
				if ( empty( $addon_row['id'] ) || 'slider-ads' === $addon_row['id'] ) {
					continue;
				}
				self::offer_addon_license_candidate(
					$candidates,
					(string) $addon_row['id'],
					$key,
					$score,
					$all_access
				);
			}

			return $candidates;
		}

		foreach ( $rich as $row ) {
			if ( empty( $row['licenseKey'] ) || empty( $row['name'] ) ) {
				continue;
			}

			$key  = (string) $row['licenseKey'];
			$name = (string) $row['name'];

			if ( License_Product_Map::is_all_access_bundle_name( $name ) ) {
				continue;
			}

			$addon_id = License_Product_Map::addon_id_from_product_name( $name, $addons );
			if ( null === $addon_id ) {
				continue;
			}

			$score = self::license_priority_score( $row, false );
			self::offer_addon_license_candidate( $candidates, $addon_id, $key, $score, $row );
		}

		return $candidates;
	}

	/**
	 * Activating addon priority
	 *
	 * @param array<string, array{object}> $candidates Candidates by addon id.
	 * @param string                       $addon_id   Addon id.
	 * @param string                       $key        License key.
	 * @param int                          $score      Priority score.
	 * @param array<string, mixed>         $row        Rich license row.
	 * @return void
	 */
	private static function offer_addon_license_candidate(
		array &$candidates,
		string $addon_id,
		string $key,
		int $score,
		array $row
	): void {
		if ( $score <= 0 ) {
			return;
		}

		if ( ! isset( $candidates[ $addon_id ] ) || $score > $candidates[ $addon_id ]['score'] ) {
			$candidates[ $addon_id ] = [
				'licenseKey' => $key,
				'row'        => $row,
				'score'      => $score,
			];
		}
	}

	/**
	 * Flatten rich list to addon_id => licenseKey (best effective license per addon).
	 *
	 * @param array<int, array<string, mixed>> $rich   Records from app/exchange.
	 * @param array<string, array>|null        $addons Optional; defaults to Data::get_addons().
	 * @return array<string, string>
	 */
	public static function flatten_to_addon_keys( array $rich, ?array $addons = null ): array {
		return self::get_active_addon_key_map_from_rich( $rich, $addons );
	}

	/**
	 * Effective addon_id => license_key map for admin and EDD persistence.
	 *
	 * @return array<string, string>
	 */
	public static function get_addon_key_map(): array {
		$derived = self::build_persisted_addon_key_map_from_rich( self::get_licenses() );

		if ( self::is_flat_map_retired() ) {
			$list        = self::get_site_activation_list();
			$active_keys = array_flip( self::get_active_site_license_keys() );

			if ( [] === $list ) {
				return $derived;
			}

			return array_filter(
				$derived,
				static fn( string $key ): bool => isset( $active_keys[ $key ] )
			);
		}

		if ( ! self::has_stored_legacy_license_map() ) {
			return $derived;
		}

		$legacy = get_option( self::OPTION_LEGACY_MAP, [] );

		return is_array( $legacy ) ? License_Utils::normalize_legacy_map( $legacy ) : [];
	}

	/**
	 * Drop per-addon rich rows that duplicate an All Access row with the same key.
	 *
	 * @param array<int, array<string, mixed>> $rich Rich license list.
	 * @return array<int, array<string, mixed>>
	 */
	public static function coalesce_all_access_duplicate_rich_rows( array $rich ): array {
		$rich = self::normalize_list( $rich );
		if ( [] === $rich ) {
			return [];
		}

		$all_access_keys = [];
		foreach ( $rich as $row ) {
			if ( ! License_Product_Map::is_all_access_bundle_name( (string) ( $row['name'] ?? '' ) ) ) {
				continue;
			}
			$key = trim( (string) ( $row['licenseKey'] ?? '' ) );
			if ( '' !== $key ) {
				$all_access_keys[ $key ] = true;
			}
		}

		if ( [] === $all_access_keys ) {
			return $rich;
		}

		return array_values(
			array_filter(
				$rich,
				static function ( array $row ) use ( $all_access_keys ): bool {
					$key = trim( (string) ( $row['licenseKey'] ?? '' ) );
					if ( '' === $key || ! isset( $all_access_keys[ $key ] ) ) {
						return true;
					}

					return License_Product_Map::is_all_access_bundle_name( (string) ( $row['name'] ?? '' ) );
				}
			)
		);
	}

	/**
	 * Drop stub rows left in advanced-ads-app-licenses when a full row exists for the same key.
	 *
	 * @param array<int, array<string, mixed>> $rich Rich license list.
	 * @return array<int, array<string, mixed>>
	 */
	public static function drop_map_stub_duplicate_rows( array $rich ): array {
		$rich = self::normalize_list( $rich );
		if ( [] === $rich ) {
			return [];
		}

		$full_keys = [];
		foreach ( $rich as $row ) {
			$key = trim( (string) ( $row['licenseKey'] ?? '' ) );
			if ( '' !== $key && ! empty( $row['licenseId'] ) ) {
				$full_keys[ $key ] = true;
			}
		}

		if ( [] === $full_keys ) {
			return $rich;
		}

		return array_values(
			array_filter(
				$rich,
				static function ( array $row ) use ( $full_keys ): bool {
					$key = trim( (string) ( $row['licenseKey'] ?? '' ) );
					if ( '' === $key || ! isset( $full_keys[ $key ] ) ) {
						return true;
					}

					return ! empty( $row['licenseId'] );
				}
			)
		);
	}

	/**
	 * Whether a rich row is All Access entitled to the given license key.
	 *
	 * @param array<int, array<string, mixed>> $rich        Rich license list.
	 * @param string                           $license_key License key.
	 * @return bool
	 */
	private static function rich_has_all_access_for_key( array $rich, string $license_key ): bool {
		$license_key = trim( $license_key );
		if ( '' === $license_key ) {
			return false;
		}

		foreach ( $rich as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if (
				License_Product_Map::is_all_access_bundle_name( (string) ( $row['name'] ?? '' ) )
				&& trim( (string) ( $row['licenseKey'] ?? '' ) ) === $license_key
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Merge addon keys into rich records, preserving unrelated rows and fields.
	 *
	 * @param array<int, array<string, mixed>> $rich Existing rich list.
	 * @param array<string, string>            $map  Addon id => key.
	 * @return array<int, array<string, mixed>>
	 */
	public static function merge_map_into_rich( array $rich, array $map ): array {
		$addons       = Data::get_addons();
		$by_name_norm = [];
		foreach ( $rich as $idx => $row ) {
			if ( empty( $row['name'] ) ) {
				continue;
			}
			$by_name_norm[ License_Product_Map::normalize_name( (string) $row['name'] ) ] = (int) $idx;
		}

		foreach ( $map as $addon_id => $key ) {
			if ( '' === (string) $key ) {
				continue;
			}
			$name = '';
			foreach ( $addons as $row ) {
				if ( isset( $row['id'] ) && (string) $row['id'] === (string) $addon_id ) {
					$name = (string) $row['name'];
					break;
				}
			}
			if ( '' === $name ) {
				$name = (string) $addon_id;
			}
			$norm = License_Product_Map::normalize_name( $name );
			if ( isset( $by_name_norm[ $norm ] ) ) {
				$idx                        = $by_name_norm[ $norm ];
				$rich[ $idx ]['licenseKey'] = (string) $key;
			} elseif ( self::rich_has_all_access_for_key( $rich, (string) $key ) ) {
				continue;
			} else {
				$rich[]                = [
					'name'       => $name,
					'licenseKey' => (string) $key,
					'status'     => 'active',
				];
				$by_name_norm[ $norm ] = count( $rich ) - 1;
			}
		}

		return self::coalesce_all_access_duplicate_rich_rows( $rich );
	}

	/**
	 * Replace predecessor license keys in advanced-ads-licenses after an upgrade successor is promoted.
	 *
	 * @param string $from_key Predecessor license key.
	 * @param string $to_key   Successor license key.
	 * @return void
	 */
	public static function migrate_legacy_map_keys( string $from_key, string $to_key ): void {
		$from_key = trim( $from_key );
		$to_key   = trim( $to_key );

		if ( '' === $from_key || '' === $to_key || $from_key === $to_key ) {
			return;
		}

		$map     = License_Utils::normalize_legacy_map( get_option( self::OPTION_LEGACY_MAP, [] ) );
		$changed = false;

		foreach ( $map as $addon_id => $key ) {
			if ( $key === $from_key ) {
				$map[ (string) $addon_id ] = $to_key;
				$changed                   = true;
			}
		}

		if ( $changed ) {
			self::persist_addon_key_map( $map );
		}
	}

	/**
	 * Seed aa-activated-addons from legacy map rows that share an All Access key.
	 *
	 * @param array<string, string>            $map  Normalized legacy map.
	 * @param array<int, array<string, mixed>> $rich Rich license list (read only).
	 * @return void
	 */
	public static function bootstrap_aa_activated_addons_from_legacy_map( array $map, array $rich ): void {
		foreach ( $rich as $row ) {
			if ( ! is_array( $row ) || ! License_Product_Map::is_all_access_bundle_name( (string) ( $row['name'] ?? '' ) ) ) {
				continue;
			}
			$aa_key = trim( (string) ( $row['licenseKey'] ?? '' ) );
			if ( '' === $aa_key ) {
				continue;
			}
			foreach ( $map as $addon_id => $key ) {
				if ( $key === $aa_key ) {
					self::add_aa_activated_addon_id( (string) $addon_id );
				}
			}
		}
	}

	/**
	 * Local-only flat map retirement when rich covers all legacy keys (does not write OPTION_RICH).
	 *
	 * @param array<int, array<string, mixed>> $rich Rich license list (read only).
	 * @param array<string, string>            $map  Normalized legacy map.
	 * @return void
	 */
	public static function maybe_retire_legacy_flat_map( array $rich, array $map ): void {
		License_Site_Activation::maybe_retire_legacy_flat_map( $rich, $map );
	}

	/**
	 * Plan-level site activation list from advanced-ads-licenses (one row per license key).
	 *
	 * @return array<int, array{license: string, status: string}>
	 */
	public static function get_site_activation_list(): array {
		return License_Site_Activation::get_list();
	}

	/**
	 * License keys marked active on this site.
	 *
	 * @return string[]
	 */
	public static function get_active_site_license_keys(): array {
		return License_Site_Activation::get_active_license_keys();
	}

	/**
	 * Whether a license key is active on this site.
	 *
	 * @param string $license_key License key.
	 * @return bool
	 */
	public static function is_license_active_on_site( string $license_key ): bool {
		return License_Site_Activation::is_license_active_on_site( $license_key );
	}

	/**
	 * Persist plan-level site activation list to advanced-ads-licenses.
	 *
	 * @param array<int, array{license?: string, status?: string}|string> $list Site activation rows.
	 * @return void
	 */
	public static function persist_site_activation_list( array $list ): void {
		License_Site_Activation::persist( $list );
	}

	/**
	 * Upsert one license key in the site activation list.
	 *
	 * @param string $license_key License key.
	 * @param string $status      active|inactive.
	 * @return void
	 */
	public static function upsert_site_activation_status( string $license_key, string $status ): void {
		License_Site_Activation::upsert_status( $license_key, $status );
	}

	/**
	 * Build site activation list from legacy flat keys and per-addon mirror status options.
	 *
	 * @return array<int, array{license: string, status: string}>
	 */
	public static function build_site_activation_list_from_legacy_storage(): array {
		return License_Site_Activation::build_from_legacy_storage();
	}

	/**
	 * Delete all legacy per-addon EDD mirror options.
	 *
	 * @return void
	 */
	public static function delete_legacy_addon_mirror_options(): void {
		$slugs = [];
		foreach ( self::get_addons_for_license_map( null ) as $row ) {
			if ( ! empty( $row['options_slug'] ) ) {
				$slugs[] = (string) $row['options_slug'];
			}
		}
		foreach ( Addons::known_addon_ids() as $addon_id ) {
			$slugs[] = License_Utils::options_slug_for_addon_id( (string) $addon_id );
		}
		$slugs = array_values( array_unique( $slugs ) );

		foreach ( $slugs as $options_slug ) {
			delete_option( $options_slug . '-license-status' );
			delete_option( $options_slug . '-license-expires' );
		}
	}

	/**
	 * Resolve short add-on id from options slug (advanced-ads-pro → pro).
	 *
	 * @param string $options_slug Options slug.
	 * @return string
	 */
	public static function addon_id_from_options_slug( string $options_slug ): string {
		$options_slug = trim( $options_slug );
		if ( str_starts_with( $options_slug, 'advanced-ads-' ) ) {
			return substr( $options_slug, strlen( 'advanced-ads-' ) );
		}

		return $options_slug;
	}

	/**
	 * License key assigned to one add-on from rich rows (post-migration).
	 *
	 * @param string $addon_id Short add-on id.
	 * @return string
	 */
	private static function derived_license_key_for_addon_id( string $addon_id ): string {
		$derived = self::build_persisted_addon_key_map_from_rich( self::get_licenses() );

		return trim( (string) ( $derived[ $addon_id ] ?? '' ) );
	}

	/**
	 * EDD-compatible license status for one add-on (valid|invalid|expired|false).
	 *
	 * @param string $options_slug Add-on options slug.
	 * @return string|false
	 */
	public static function get_mirror_status_for_options_slug( string $options_slug ) {
		if ( ! self::is_flat_map_retired() ) {
			return get_option( $options_slug . '-license-status', false );
		}

		$addon_id    = self::addon_id_from_options_slug( $options_slug );
		$license_key = self::derived_license_key_for_addon_id( $addon_id );

		if ( '' === $license_key || ! self::is_license_active_on_site( $license_key ) ) {
			return false;
		}

		$row = License_Utils::get_rich_license_row_by_key( self::get_licenses(), $license_key );
		if ( ! is_array( $row ) ) {
			return false;
		}

		$row = License_Utils::apply_local_expiry_to_row( $row );
		if ( ! self::is_license_entitled( $row ) ) {
			return 'expired' === strtolower( (string) ( $row['status'] ?? '' ) ) ? 'expired' : 'invalid';
		}

		return 'valid';
	}

	/**
	 * EDD-compatible expiry for one add-on (lifetime, date string, or empty).
	 *
	 * @param string $options_slug Add-on options slug.
	 * @return string|false
	 */
	public static function get_mirror_expires_for_options_slug( string $options_slug ) {
		if ( ! self::is_flat_map_retired() ) {
			return get_option( $options_slug . '-license-expires', '' );
		}

		$addon_id    = self::addon_id_from_options_slug( $options_slug );
		$license_key = self::derived_license_key_for_addon_id( $addon_id );

		if ( '' === $license_key ) {
			return '';
		}

		$row = License_Utils::get_rich_license_row_by_key( self::get_licenses(), $license_key );
		if ( ! is_array( $row ) ) {
			return '';
		}

		$expiry = trim( (string) ( $row['expiryDate'] ?? '' ) );

		return '' !== $expiry ? $expiry : false;
	}

	/**
	 * Persist addon key map to advanced-ads-licenses (legacy flat map).
	 * Listing uses advanced-ads-app-licenses only — do not merge map rows into rich list.
	 *
	 * @param array<string, string> $map Addon id => key.
	 * @return void
	 */
	public static function persist_addon_key_map( array $map ): void {
		if ( self::is_flat_map_retired() ) {
			$keys = [];
			foreach ( $map as $key ) {
				$key = is_string( $key ) ? trim( $key ) : '';
				if ( '' !== $key ) {
					$keys[ $key ] = true;
				}
			}
			foreach ( array_keys( $keys ) as $license_key ) {
				if ( ! self::is_license_active_on_site( $license_key ) ) {
					self::upsert_site_activation_status( $license_key, 'inactive' );
				}
			}
			return;
		}

		$clean = [];
		foreach ( $map as $id => $key ) {
			$key = is_string( $key ) ? trim( $key ) : '';
			if ( '' !== $key ) {
				$clean[ (string) $id ] = $key;
			}
		}

		update_option( self::OPTION_LEGACY_MAP, $clean, false );
	}

	/**
	 * Whether options mirror indicates a valid license for one addon (aligned with admin checks).
	 *
	 * @param string $addon_id Short addon id.
	 * @return bool
	 */
	public static function addon_license_valid_by_options( string $addon_id ): bool {
		if ( self::is_flat_map_retired() ) {
			$license_key = self::derived_license_key_for_addon_id( $addon_id );
			if ( '' === $license_key || ! self::is_license_active_on_site( $license_key ) ) {
				return false;
			}
			$row = License_Utils::get_rich_license_row_by_key( self::get_licenses(), $license_key );

			return is_array( $row ) && self::is_license_entitled( License_Utils::apply_local_expiry_to_row( $row ) );
		}

		$options_slug = License_Utils::options_slug_for_addon_id( $addon_id );

		if ( class_exists( 'Advanced_Ads_Admin_Licenses' ) ) {
			$admin       = \Advanced_Ads_Admin_Licenses::get_instance();
			$status      = $admin->get_license_status( $options_slug );
			$expiry_date = $admin->get_license_expires( $options_slug );
		} else {
			$status      = get_option( $options_slug . '-license-status', false );
			$expiry_date = get_option( $options_slug . '-license-expires', '' );
		}

		return (
			( $expiry_date && strtotime( $expiry_date ) > time() )
			|| 'valid' === $status
			|| 'lifetime' === $expiry_date
		);
	}

	/**
	 * Get license details for UI (single addon id, e.g. pro).
	 *
	 * @param string $slug Add-on id.
	 * @return array<string, mixed>
	 */
	public static function get_license_details( $slug ): array {
		$map = self::get_addon_key_map();
		$key = $map[ $slug ] ?? '';

		if ( '' === $key ) {
			return [];
		}

		if ( self::is_flat_map_retired() ) {
			$options_slug = License_Utils::options_slug_for_addon_id( $slug );

			return [
				'license' => $key,
				'status'  => self::get_mirror_status_for_options_slug( $options_slug ) ?: 'invalid',
				'expires' => self::get_mirror_expires_for_options_slug( $options_slug ),
			];
		}

		$options_slug = License_Utils::options_slug_for_addon_id( $slug );

		return [
			'license' => $key,
			'status'  => get_option( $options_slug . '-license-status', 'invalid' ),
			'expires' => get_option( $options_slug . '-license-expires', false ),
		];
	}

	/**
	 * Save license key and options mirror for one addon.
	 *
	 * @param string       $slug        Add-on id.
	 * @param string       $license_key License key.
	 * @param string       $status      License status.
	 * @param string|false $expires     License expires.
	 * @param bool         $update_map  When true, also write advanced-ads-licenses (activation flows only).
	 * @return void
	 */
	public static function update_license_details( $slug, $license_key, $status = 'valid', $expires = false, bool $update_map = false ): void {
		if ( self::is_flat_map_retired() ) {
			self::upsert_site_activation_status(
				(string) $license_key,
				'valid' === $status ? 'active' : 'inactive'
			);
			return;
		}

		if ( 'lifetime' === $expires ) {
			$expires = time() + YEAR_IN_SECONDS * 200;
		}

		if ( $update_map && ! self::is_flat_map_retired() ) {
			$map          = self::get_addon_key_map();
			$map[ $slug ] = $license_key;
			self::persist_addon_key_map( $map );
		}

		$options_slug = License_Utils::options_slug_for_addon_id( $slug );
		update_option( $options_slug . '-license-status', $status, false );
		update_option( $options_slug . '-license-expires', $expires, false );
	}

	/**
	 * Whether one addon has a valid license by stored options.
	 *
	 * @param string $slug Add-on id.
	 * @return bool
	 */
	public static function has_valid_license( $slug ): bool {
		return self::addon_license_valid_by_options( (string) $slug );
	}

	/**
	 * Check if any installed add-on has valid license options.
	 *
	 * @return bool
	 */
	public static function has_any_valid_license(): bool {
		if ( [] !== Data::get_addons() && class_exists( 'Advanced_Ads_Admin_Licenses' ) ) {
			return \Advanced_Ads_Admin_Licenses::any_license_valid();
		}

		foreach ( Addons::known_addon_ids() as $addon_id ) {
			if ( 'slider-ads' === $addon_id ) {
				continue;
			}
			if ( self::addon_license_valid_by_options( $addon_id ) ) {
				return true;
			}
		}

		return false;
	}
}
