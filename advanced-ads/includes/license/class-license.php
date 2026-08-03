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

use AdvancedAds\Admin\Plugin_Installer;
use AdvancedAds\Crons\Licenses as License_Cron;
use AdvancedAds\Utilities\Addons;
use AdvancedAds\Utilities\Data;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * License persistence, shop sync, and add-on install/activation on this site.
 *
 * Shop HTTP → {@see License_Shop_Client}. Site activation list → {@see License_Site_Activation}.
 * Call those directly; this class orchestrates install + All-Access rules.
 */
class License {

	/**
	 * Download_url values already installed this request (All Access runs once per URL).
	 *
	 * @var array<string, true>
	 */
	private static $installed_download_urls = [];

	/**
	 * Request-local cache for {@see get_addon_key_map()}.
	 *
	 * @var array{fp: string, map: array<string, string>}|null
	 */
	private static $addon_key_map_cache = null;

	/**
	 * Site activation list option (`[{ license, status }, …]`).
	 * Option key reused from the retired addon⇒key flat map.
	 */
	public const OPTION_SITE_ACTIVATION = 'advanced-ads-licenses';

	/**
	 * Rich license records (app / exchange).
	 */
	public const OPTION_RICH = 'advanced-ads-app-licenses';

	/**
	 * Set to '1' when advanced-ads-licenses flat map has been retired.
	 */
	public const OPTION_FLAT_MAP_RETIRED = 'advanced_ads_licenses_flat_map_retired';

	/**
	 * All Access add-on ids the user activated individually on this site.
	 */
	public const OPTION_AA_ACTIVATED_ADDONS = 'advanced-ads-aa-activated-addons';


	/**
	 * Whether the legacy addon => key flat map has been retired.
	 *
	 * @return bool
	 */
	public static function is_flat_map_retired(): bool {
		return '1' === (string) get_option( self::OPTION_FLAT_MAP_RETIRED, '' );
	}

	/**
	 * Current site hostname for license activation (no scheme).
	 *
	 * @return string
	 */
	public static function get_site_hostname(): string {
		$parts = wp_parse_url( site_url() );
		if ( ! is_array( $parts ) ) {
			return '';
		}

		$host = (string) ( $parts['host'] ?? '' );
		if ( '' === $host ) {
			return '';
		}

		$path = (string) ( $parts['path'] ?? '' );
		$path = '' !== $path ? untrailingslashit( $path ) : '';

		return $host . $path;
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

		$legacy = get_option( self::OPTION_SITE_ACTIVATION, false );

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
		return self::has_stored_licenses()
			|| [] !== License_Site_Activation::get_active_license_keys();
	}

	/**
	 * Whether to call shop /license/activate automatically (not on button click — JS calls shop first).
	 *
	 * @param bool $requested Caller wants activation (first rich save or exchange with activate flag).
	 * @return bool
	 */
	public static function should_run_shop_auto_activate( bool $requested ): bool {
		return $requested;
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
		return self::classify_incoming_keys( $existing, $incoming )['has_new_keys'];
	}

	/**
	 * Classify keys in $list that are not already in $existing.
	 *
	 * @param array<int, array<string, mixed>> $existing Stored licenses.
	 * @param array<int, array<string, mixed>> $licenses     Incoming or merged list.
	 * @return array{has_new_keys: bool, needs_shop_activation: bool, has_upgrade_successor: bool}
	 */
	private static function classify_incoming_keys( array $existing, array $licenses ): array {
		$hostname      = self::get_site_hostname();
		$existing_keys = [];

		foreach ( self::normalize_list( $existing ) as $row ) {
			$key = (string) ( $row['licenseKey'] ?? '' );
			if ( '' !== $key ) {
				$existing_keys[ $key ] = true;
			}
		}

		$has_new_keys          = false;
		$needs_shop_activation = false;
		$has_upgrade_successor = false;

		foreach ( self::normalize_list( $licenses ) as $row ) {
			$key = (string) ( $row['licenseKey'] ?? '' );
			if ( '' === $key || isset( $existing_keys[ $key ] ) ) {
				continue;
			}

			$has_new_keys = true;

			if ( ! self::is_license_entitled( $row ) ) {
				continue;
			}

			if ( 'migrate' === self::successor_shop_action( $licenses, $row ) ) {
				$has_upgrade_successor = true;
				continue;
			}

			if ( ! self::is_site_activated_on_license( $row, $hostname ) ) {
				$needs_shop_activation = true;
			}
		}

		return [
			'has_new_keys'          => $has_new_keys,
			'needs_shop_activation' => $needs_shop_activation,
			'has_upgrade_successor' => $has_upgrade_successor,
		];
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
					$kept_status    = $row['status'] ?? null;
					$merged[ $idx ] = array_merge( $row, $license );
					if ( null !== $kept_status ) {
						$merged[ $idx ]['status'] = $kept_status;
					}
					$updated = true;
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

			$result = License_Shop_Client::exchange(
				[
					'license' => $license_key,
					'site'    => self::get_site_hostname(),
				]
			);
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

		$merged = self::dedupe_rich_rows( $merged );

		return $merged;
	}

	/**
	 * Finish legacy migration: patch rich from shop if needed, then retire flat map.
	 *
	 * Always retires locally after a best-effort exchange, even when the shop fails
	 * or rich still does not cover every legacy key.
	 *
	 * @return void
	 */
	public static function maybe_complete_legacy_license_migration(): void {

		$map = License_Utils::normalize_legacy_map( get_option( self::OPTION_SITE_ACTIVATION, [] ) );
		if ( [] === $map ) {
			return;
		}

		$rich = self::get_licenses();

		if ( [] === $rich || ! License_Utils::rich_covers_legacy_keys( $map, $rich ) ) {
			$patched = self::exchange_legacy_map_for_rich( $map, $rich );
			if ( ! is_wp_error( $patched ) ) {
				update_option( self::OPTION_RICH, $patched, false );
				$rich = $patched;
			}
		}

		License_Site_Activation::maybe_retire_legacy_flat_map( $rich, $map );
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

		if ( in_array( $status, [ 'expired', 'invalid', 'disabled' ], true ) ) {
			$name = (string) ( $row['name'] ?? '' );
			if ( License_Product_Map::is_all_access_bundle_name( $name ) ) {
				return false;
			}

			return License_Utils::license_expiry_is_future( $row );
		}

		return false;
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

		$license['sitesActivated']  = $filtered;
		$license['activationCount'] = count( $filtered );

		return $license;
	}

	/**
	 * When the user picks one license on this site:
	 * - All Access: remove this site from every other license row (including singles).
	 * - Single product: remove this site from All Access only (Pro + Tracking may coexist).
	 *
	 * @param array<int, array<string, mixed>> $rich        Rich license list.
	 * @param string                           $license_key License key the user activated.
	 * @return array<int, array<string, mixed>>
	 */
	public static function apply_manual_license_activation_on_site( array $rich, string $license_key ): array {
		return self::apply_manual_activation( $rich, $license_key, true );
	}

	/**
	 * Add this site to a license row without stripping single-product slots under All Access.
	 *
	 * Used when healing sitesActivated from the site-activation list or activating an AA add-on.
	 *
	 * @param array<int, array<string, mixed>> $rich        Rich license list.
	 * @param string                           $license_key License key.
	 * @return array<int, array<string, mixed>>
	 */
	public static function ensure_local_site_slot_on_license( array $rich, string $license_key ): array {
		return self::apply_manual_activation( $rich, $license_key, false );
	}

	/**
	 * Mirror this site onto one license row; optionally strip conflicting singles under All Access.
	 *
	 * @param array<int, array<string, mixed>> $rich                  Rich license list.
	 * @param string                           $license_key           License key.
	 * @param bool                             $strip_single_products When All Access activates: also strip single-product rows.
	 * @return array<int, array<string, mixed>>
	 */
	private static function apply_manual_activation( array $rich, string $license_key, bool $strip_single_products ): array {
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
					if ( $strip_single_products ) {
						$rich[ $index ] = self::remove_site_hostname_from_license_row( $row, $hostname );
						if ( '' !== $key ) {
							License_Site_Activation::upsert_status( $key, 'inactive' );
						}
					}
				} elseif ( $key !== $license_key ) {
					$rich[ $index ] = self::remove_site_hostname_from_license_row( $row, $hostname );
					if ( '' !== $key ) {
						License_Site_Activation::upsert_status( $key, 'inactive' );
					}
				}
				continue;
			}

			// Single product: drop All Access and same-line siblings on this site.
			if ( License_Product_Map::is_all_access_bundle_name( $name ) ) {
				$rich[ $index ] = self::remove_site_hostname_from_license_row( $row, $hostname );
			} elseif ( self::product_line_key_for_row( $target_row ) === self::product_line_key_for_row( $row ) && $key !== $license_key ) {
				$rich[ $index ] = self::remove_site_hostname_from_license_row( $row, $hostname );
				if ( '' !== $key ) {
					License_Site_Activation::upsert_status( $key, 'inactive' );
				}
			}
		}

		if ( ! self::is_site_activated_on_license( $target_row, $hostname ) ) {
			$sites = isset( $target_row['sitesActivated'] ) && is_array( $target_row['sitesActivated'] )
				? $target_row['sitesActivated']
				: [];

			// $hostname is already get_site_hostname() → e.g. test-setup.com/license6
			$host_only = strtolower(
				(string) wp_parse_url( 'https://' . ltrim( $hostname, '/' ), PHP_URL_HOST )
			);
			$full_site = self::get_site_hostname();

			// Drop shop/local bare host (test-setup.com) so only subdirectory remains.
			if ( '' !== $host_only && $full_site !== $host_only ) {
				$sites = array_values(
					array_filter(
						$sites,
						static function ( $site ) use ( $host_only ) {
							if ( ! is_array( $site ) ) {
								return false;
							}
							$domain = strtolower( untrailingslashit( (string) ( $site['domain'] ?? '' ) ) );
							return '' !== $domain && $domain !== $host_only;
						}
					)
				);
			}

			$found = false;
			foreach ( $sites as &$site ) {
				if ( isset( $site['domain'] ) && strtolower( untrailingslashit( (string) $site['domain'] ) ) === strtolower( $full_site ) ) {
					$site['domain']    = $full_site;
					$site['createdAt'] = gmdate( 'd-m-Y' );
					$found             = true;
					break;
				}
			}
			unset( $site );

			if ( ! $found ) {
				$sites[] = [
					'domain'    => $full_site,
					'createdAt' => gmdate( 'd-m-Y' ),
				];
			}

			$target_row['sitesActivated']  = $sites;
			$target_row['activationCount'] = count( $sites );
			$rich[ $target_index ]         = $target_row;
		}

		License_Site_Activation::upsert_status( $license_key, 'active' );

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
				$manifest = License_Product_Map::addon_manifest();
				$addon_id = License_Product_Map::addon_id_from_product_name( $name, $manifest );
				if ( null !== $addon_id ) {
					self::remove_aa_activated_addon_id( $addon_id );
				}
			}
			break;
		}

		License_Site_Activation::upsert_status( $license_key, 'inactive' );

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

		$sites = $license['sitesActivated'] ?? [];
		$list  = is_array( $sites ) ? count( $sites ) : 0;
		$used  = max( $list, (int) ( $license['activationCount'] ?? 0 ) );
		$total = (int) ( $license['availableSites'] ?? 0 );

		return $total > 0 && $used < $total;
	}

	/**
	 * Shop activate then mirror this site on the license row (never local-only).
	 *
	 * @param string                           $license_key License key.
	 * @param array<int, array<string, mixed>> $rich        Rich license list.
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	public static function activate_on_shop_then_local( string $license_key, array $rich ) {
		$merged = self::shop_activate_and_merge( $license_key, $rich );
		if ( is_wp_error( $merged ) ) {
			return $merged;
		}

		return self::apply_manual_license_activation_on_site( $merged, $license_key );
	}

	/**
	 * Shop activate and merge response into the rich list.
	 *
	 * @param string                           $license_key License key.
	 * @param array<int, array<string, mixed>> $rich        Rich license list.
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	private static function shop_activate_and_merge( string $license_key, array $rich ) {
		$license_key = trim( $license_key );
		if ( '' === $license_key ) {
			return new WP_Error(
				'advanced_ads_license_activate_invalid',
				__( 'Missing license key.', 'advanced-ads' )
			);
		}

		$shop = License_Shop_Client::request_activate(
			$license_key,
			self::get_site_hostname(),
			self::resolve_license_id_for_key( $rich, $license_key )
		);
		if ( is_wp_error( $shop ) ) {
			return $shop;
		}

		if ( [] !== $shop ) {
			$rich = self::merge_license_lists( $rich, $shop );
		}

		return $rich;
	}

	/**
	 * Connect/reconnect: activate site-activation keys (and locally slotted rows) on the shop.
	 *
	 * @param array<int, array<string, mixed>> $rich     Rich license list (post-merge).
	 * @param array<int, array<string, mixed>> $existing Stored licenses before merge.
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	private static function sync_active_keys_to_shop( array $rich, array $existing = [] ) {
		$hostname = self::get_site_hostname();
		if ( '' === $hostname ) {
			return $rich;
		}

		$keys_to_sync = [];
		foreach ( License_Site_Activation::get_active_license_keys() as $key ) {
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
			$row = License_Utils::get_rich_license_row_by_key( $rich, $license_key );

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

			return 1;
		}

		$result = self::save_licenses(
			self::get_licenses(),
			[
				'deactivating_license_key' => $license_key,
			]
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

		$all_access = self::find_all_access_row( $rich, 'entitled' );
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

			if ( 'migrate' === self::successor_shop_action( $rich, $row ) ) {
				$rich = self::promote_upgrade_successor( $rich, $license_key, true );
				continue;
			}

			$activated = self::activate_on_shop_then_local( $license_key, $rich );
			if ( is_wp_error( $activated ) ) {
				continue;
			}
			$rich = $activated;
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
			$row = License_Utils::get_rich_license_row_by_key( $rich, $license_key );

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
				if ( self::product_line_key_for_row( $row ) !== self::product_line_key_for_row( $other ) ) {
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
	 * After upgrade, move this site's slot from a predecessor row to the entitled successor.
	 *
	 * Updates local sitesActivated and syncs the successor key on the shop so EDD SL
	 * activation counts match the plugin UI.
	 *
	 * @param array<int, array<string, mixed>> $rich Rich license list.
	 * @return array<int, array<string, mixed>>
	 */
	public static function migrate_site_activation_to_successor_licenses( array $rich ): array {
		if ( '' === self::get_site_hostname() ) {
			return $rich;
		}

		$legacy_migrations = [];

		foreach ( self::normalize_list( $rich ) as $successor ) {
			if ( ! self::is_license_entitled( $successor ) ) {
				continue;
			}

			$key = (string) ( $successor['licenseKey'] ?? '' );
			if ( '' === $key ) {
				continue;
			}

			$action = self::successor_shop_action( $rich, $successor );
			if ( '' === $action ) {
				continue;
			}

			$rich = self::promote_upgrade_successor(
				$rich,
				$key,
				'migrate' === $action,
				$legacy_migrations
			);
		}

		$rich = self::ensure_site_slots_match_active_assignments( $rich );

		foreach ( $legacy_migrations as $migration ) {
			self::migrate_legacy_map_keys( (string) $migration['from'], (string) $migration['to'] );
		}

		return $rich;
	}

	/**
	 * Activate the upgrade successor on the shop; optionally mirror the local site slot.
	 *
	 * @param array<int, array<string, mixed>>            $rich             Rich license list.
	 * @param string                                      $successor_key    Successor license key.
	 * @param bool                                        $apply_local_slot When true, move the site slot locally after shop success.
	 * @param array<int, array{from: string, to: string}> $legacy_migrations Collect predecessor → successor map updates (applied after ensure).
	 * @return array<int, array<string, mixed>>
	 */
	private static function promote_upgrade_successor( array $rich, string $successor_key, bool $apply_local_slot, array &$legacy_migrations = [] ): array {
		$successor_key = trim( $successor_key );
		$hostname      = self::get_site_hostname();

		if ( '' === $successor_key || '' === $hostname ) {
			return $rich;
		}

		$successor = License_Utils::get_rich_license_row_by_key( $rich, $successor_key );
		if ( null === $successor ) {
			return $rich;
		}

		$predecessor_key = self::find_predecessor_license_key_for_successor( $rich, $successor );

		$activated = License_Shop_Client::request_activate( $successor_key, $hostname );
		if ( is_wp_error( $activated ) ) {
			return $rich;
		}

		if ( [] !== $activated ) {
			$rich = self::merge_license_lists( $rich, $activated );
		}

		if ( $apply_local_slot ) {
			$rich = self::apply_manual_license_activation_on_site( $rich, $successor_key );
		}

		if ( '' !== $predecessor_key ) {
			$legacy_migrations[] = [
				'from' => $predecessor_key,
				'to'   => $successor_key,
			];
		}

		return $rich;
	}

	/**
	 * Whether an entitled row needs shop successor handling: migrate slot or sync shop only.
	 *
	 * @param array<int, array<string, mixed>> $rich Rich license list.
	 * @param array<string, mixed>             $row  Candidate successor row.
	 * @return string '' | 'migrate' | 'sync'
	 */
	private static function successor_shop_action( array $rich, array $row ): string {
		$hostname = self::get_site_hostname();
		if ( '' === $hostname ) {
			return '';
		}

		$on_site = self::is_site_activated_on_license( $row, $hostname );

		if ( ! $on_site ) {
			if ( self::has_predecessor_addon_assignment_for_successor( $row ) ) {
				return 'migrate';
			}

			$row_key = (string) ( $row['licenseKey'] ?? '' );
			foreach ( self::normalize_list( $rich ) as $other ) {
				if ( (string) ( $other['licenseKey'] ?? '' ) === $row_key ) {
					continue;
				}

				if ( ! self::is_site_activated_on_license( $other, $hostname ) ) {
					continue;
				}

				if ( self::product_line_key_for_row( $row ) !== self::product_line_key_for_row( $other ) ) {
					continue;
				}

				if ( self::is_predecessor_license_row( $other, $row ) ) {
					return 'migrate';
				}
			}

			return '';
		}

		if ( ! self::is_license_entitled( $row ) ) {
			return '';
		}

		if ( self::has_predecessor_addon_assignment_for_successor( $row ) ) {
			return 'sync';
		}

		$row_key = (string) ( $row['licenseKey'] ?? '' );
		if ( '' === $row_key ) {
			return '';
		}

		foreach ( self::normalize_list( $rich ) as $other ) {
			if ( self::product_line_key_for_row( $row ) !== self::product_line_key_for_row( $other ) ) {
				continue;
			}

			$other_key = (string) ( $other['licenseKey'] ?? '' );
			if ( '' === $other_key || $other_key === $row_key ) {
				continue;
			}

			if ( self::is_license_active( $row ) && ! self::is_license_active( $other ) ) {
				return 'sync';
			}

			$row_is_aa   = License_Product_Map::is_all_access_bundle_name( (string) ( $row['name'] ?? '' ) );
			$other_is_aa = License_Product_Map::is_all_access_bundle_name( (string) ( $other['name'] ?? '' ) );

			if ( self::license_priority_score( $row, $row_is_aa ) > self::license_priority_score( $other, $other_is_aa ) ) {
				return 'sync';
			}
		}

		return '';
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
	 * Stable product-line id for mutual exclusion (All Access or single add-on).
	 *
	 * @param array<string, mixed> $row Rich license row.
	 * @return string|null `all-access`, add-on id, or null when unrecognized.
	 */
	private static function product_line_key_for_row( array $row ): ?string {
		$name = (string) ( $row['name'] ?? '' );
		if ( License_Product_Map::is_all_access_bundle_name( $name ) ) {
			return 'all-access';
		}

		return License_Product_Map::addon_id_from_product_name( $name );
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

		if ( self::product_line_key_for_row( $predecessor ) !== self::product_line_key_for_row( $successor ) ) {
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

		foreach ( License_Site_Activation::get_active_license_keys() as $mapped_key ) {
			$mapped_key = trim( (string) $mapped_key );
			if ( '' !== $mapped_key && $mapped_key !== $new_key ) {
				return true;
			}
		}

		foreach ( self::get_addon_key_map() as $mapped_key ) {
			$mapped_key = trim( (string) $mapped_key );
			if ( '' !== $mapped_key && $mapped_key !== $new_key ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Mirror rich licenses to per-addon options; optionally install only one license's add-on(s).
	 *
	 * @param array<int, array<string, mixed>> $rich Rich license list.
	 * @param array<string, mixed>             $args {
	 *     Optional. Sync options.
	 *
	 *     @type string $install_only_license_key License key to install packages for.
	 *     @type bool   $install_packages         When false, only update option mirrors.
	 *     @type string $install_only_addon_id    Scoped add-on id for All Access.
	 *     @type bool   $install_only             Download package only (no plugin activation).
	 *     @type bool   $update_legacy_map        Whether to update site-activation / legacy map.
	 * }
	 * @return WP_Error|null Null on success, error when package install fails during scoped activation.
	 */
	public static function sync_addon_options_from_rich( array $rich, array $args = [] ): ?WP_Error {
		$install_only_license_key = (string) ( $args['install_only_license_key'] ?? '' );
		$install_packages         = (bool) ( $args['install_packages'] ?? true );
		$install_only_addon_id    = (string) ( $args['install_only_addon_id'] ?? '' );
		$install_only             = (bool) ( $args['install_only'] ?? false );
		$update_legacy_map        = (bool) ( $args['update_legacy_map'] ?? true );

		$assignments = self::resolve_addon_license_assignments( $rich );
		$addons      = License_Product_Map::addon_manifest();

		$install_only_license_key = trim( $install_only_license_key );
		$install_only_addon_id    = sanitize_key( trim( $install_only_addon_id ) );
		$install_addon_ids        = null;
		$activating_row           = null;

		if ( '' !== $install_only_license_key ) {
			$install_addon_ids = [];
			$activating_row    = License_Utils::get_rich_license_row_by_key( $rich, $install_only_license_key );

			if ( is_array( $activating_row ) ) {
				if ( '' !== $install_only_addon_id ) {
					$install_addon_ids = [ $install_only_addon_id ];
				} elseif ( License_Product_Map::is_all_access_bundle_name( (string) ( $activating_row['name'] ?? '' ) ) ) {
					// All Access: install each add-on from the Licenses UI (one request per package).
					$install_addon_ids = [];
				} else {
					$install_addon_ids = self::get_addon_ids_for_license_row( $activating_row );
				}
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
				continue;
			}

			$row         = $assignments[ $addon_id ]['row'];
			$license_key = (string) $assignments[ $addon_id ]['licenseKey'];
			$expires     = ! empty( $row['expiryDate'] ) ? (string) $row['expiryDate'] : false;
			$aa_on_site  = self::find_all_access_row( $rich, 'on_site' );

			$mirror_aa = in_array( $addon_id, self::get_aa_activated_addon_ids(), true );
			if ( ! $mirror_aa && '' !== $install_only_addon_id && $install_only_addon_id === $addon_id && ! $install_only ) {
				self::add_aa_activated_addon_id( $addon_id );
				$mirror_aa = true;
			}

			if (
				null !== $aa_on_site
				&& (string) ( $aa_on_site['licenseKey'] ?? '' ) === $license_key
				&& ! $mirror_aa
			) {
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

		$addon_id = License_Product_Map::addon_id_from_product_name( (string) ( $row['name'] ?? '' ) );

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

		return License_Product_Map::addon_id_from_product_name( $name ) === $addon_id;
	}

	/**
	 * Rich license row that provides download_url for a given add-on.
	 *
	 * @param string               $addon_id        Add-on to install.
	 * @param array<string, mixed> $assignment_row  Row from license assignment (fallback).
	 * @return array<string, mixed>
	 */
	private static function resolve_install_license_row( string $addon_id, array $assignment_row ): array {
		if ( '' !== self::get_download_url_for_addon( $assignment_row, $addon_id ) ) {
			return $assignment_row;
		}

		$rich = self::get_licenses();
		if ( ! is_array( $rich ) || [] === $rich ) {
			return $assignment_row;
		}

		$manifest   = License_Product_Map::addon_manifest();
		$best       = null;
		$best_score = -1;

		foreach ( $rich as $license_row ) {
			if ( ! is_array( $license_row ) || ! self::is_license_entitled( $license_row ) ) {
				continue;
			}

			if ( '' === self::get_download_url_for_addon( $license_row, $addon_id ) ) {
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
		$catalog = [];
		foreach ( License_Product_Map::addon_manifest() as $row ) {
			if ( empty( $row['id'] ) ) {
				continue;
			}
			$catalog[ (string) $row['id'] ] = [
				'id'    => (string) $row['id'],
				'file'  => (string) $row['path'],
				'title' => (string) $row['name'],
			];
		}

		return $catalog;
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
		$stored   = License_Utils::get_rich_license_row_by_key( $rich, $license_key );
		if ( is_array( $stored ) ) {
			$row = $stored;
		}

		if ( '' !== $hostname && self::is_site_activated_on_license( $row, $hostname ) ) {
			if ( is_array( $stored ) && 'sync' === self::successor_shop_action( $rich, $stored ) ) {
				$rich = self::promote_upgrade_successor( $rich, $license_key, false );
				update_option( self::OPTION_RICH, $rich, false );
				$fresh = License_Utils::get_rich_license_row_by_key( $rich, $license_key );

				return is_array( $fresh ) ? $fresh : $stored;
			}

			return $row;
		}

		if ( 'migrate' === self::successor_shop_action( $rich, $row ) ) {
			$rich = self::promote_upgrade_successor( $rich, $license_key, true );
			update_option( self::OPTION_RICH, $rich, false );
			$fresh = License_Utils::get_rich_license_row_by_key( $rich, $license_key );

			return is_array( $fresh ) ? $fresh : $row;
		}

		$activated = self::activate_on_shop_then_local( $license_key, $rich );
		if ( is_wp_error( $activated ) ) {
			return $activated;
		}

		update_option( self::OPTION_RICH, $activated, false );
		$fresh_row = License_Utils::get_rich_license_row_by_key( $activated, $license_key );
		if ( ! is_array( $fresh_row ) ) {
			$fresh_row = $row;
		}

		$refreshed = License_Shop_Client::fetch_license_row( $fresh_row );
		if ( is_wp_error( $refreshed ) ) {
			return $fresh_row;
		}

		return is_array( $refreshed ) ? $refreshed : $fresh_row;
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
		$fresh = License_Shop_Client::fetch_license_row( $row );

		if ( is_wp_error( $fresh ) ) {
			if ( License_Shop_Client::is_validate_not_found_error( $fresh ) ) {
				$row['status']  = 'expired';
				$rich[ $index ] = $row;
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
		if ( class_exists( \ActionScheduler_QueueRunner::class, false ) ) {
			\ActionScheduler_QueueRunner::instance()->unhook_dispatch_async_request();
		}

		if ( ! self::is_license_entitled( $row ) ) {
			return true;
		}

		$plugin_file = self::plugin_file_for_addon_id( $addon_id );
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

		$is_on_disk = Addons::is_addon_on_disk( $addon_id );

		if ( ! $is_on_disk ) {
			$installed = self::install_addon_package( $download_url, $addon_id, $row, ! $force_install );
			if ( is_wp_error( $installed ) ) {
				return $installed;
			}
		}

		$hostname            = self::get_site_hostname();
		$same_site_entitled  = self::is_license_entitled( $row )
			&& '' !== $hostname
			&& self::is_site_activated_on_license( $row, $hostname );
		$may_activate_plugin = self::is_license_active( $row ) || $same_site_entitled;

		if ( $skip_activation || ! $may_activate_plugin || ! $can_activate ) {
			return true;
		}

		if ( ! Addons::is_addon_on_disk( $addon_id ) ) {
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
	 * Plugin bootstrap file for an add-on id, or null when unknown.
	 *
	 * @param string $addon_id Short add-on id.
	 * @return string|null
	 */
	private static function plugin_file_for_addon_id( string $addon_id ): ?string {
		$addon_id = sanitize_key( $addon_id );
		if ( ! Addons::is_known_addon( $addon_id ) ) {
			return null;
		}

		return Addons::resolve_installed_plugin_file( $addon_id ) ?? Addons::plugin_file( $addon_id );
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
		$cache_key = md5( $download_url . '|' . $addon_id );

		if ( $skip_if_cached_url && ! empty( self::$installed_download_urls[ $cache_key ] ) ) {
			return Addons::is_addon_on_disk( $addon_id ) ? false : self::run_package_install( $download_url, $addon_id );
		}

		$result = self::run_package_install( $download_url, $addon_id );
		if (
			is_wp_error( $result )
			&& in_array( $result->get_error_code(), [ 'advanced_ads_install_failed', 'advanced_ads_install_wrong_package', 'http_request_failed' ], true )
		) {
			$ready = self::ensure_shop_license_ready_for_package_download( $row );
			if ( ! is_wp_error( $ready ) && is_array( $ready ) ) {
				$fresh_url = self::get_download_url_for_addon( $ready, $addon_id );
				if ( '' !== $fresh_url && $fresh_url !== $download_url ) {
					$result = self::run_package_install( $fresh_url, $addon_id );
				}
			}
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		self::$installed_download_urls[ $cache_key ] = true;

		return true;
	}

	/**
	 * Download and unpack a plugin package into wp-content/plugins.
	 *
	 * @param string $download_url Signed shop package URL.
	 * @param string $addon_id     Expected add-on id (validates extracted folder).
	 * @return true|WP_Error
	 */
	private static function run_package_install( string $download_url, string $addon_id ) {
		$result = Plugin_Installer::install_from_url( $download_url, true );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( false === $result || null === $result ) {
			return new WP_Error(
				'advanced_ads_install_failed',
				__( 'Add-on install failed. Check that wp-content/plugins is writable and the download URL is reachable from this site.', 'advanced-ads' )
			);
		}

		wp_clean_plugins_cache();
		Addons::get_plugins( true );

		if ( ! Addons::is_addon_on_disk( $addon_id ) ) {
			$plugin_file = Addons::plugin_file( sanitize_key( $addon_id ) ) ?? '';

			return new WP_Error(
				'advanced_ads_install_wrong_package',
				sprintf(
					/* translators: %s: expected plugin folder, e.g. advanced-ads-tracking */
					__( 'Downloaded package did not install the expected add-on (%s).', 'advanced-ads' ),
					'' !== $plugin_file ? dirname( $plugin_file ) : $addon_id
				)
			);
		}

		return true;
	}

	/**
	 * Save rich licenses: option + addon map + status mirrors.
	 *
	 * @param array<int, array<string, mixed>> $licenses Incoming rich licenses.
	 * @param array<string, mixed>             $args {
	 *     Optional. Save options.
	 *
	 *     @type bool   $activate_new             Whether exchange/connect asked for activation.
	 *     @type string $activating_license_key   License key activated on the licenses UI.
	 *     @type string $activating_addon_id      When set with All Access, install only this add-on.
	 *     @type bool   $install_only             Download package only (no plugin activation).
	 *     @type string $deactivating_addon_id    Deactivate this add-on plugin on the site.
	 *     @type string $deactivating_license_key Remove this site from that license row.
	 *     @type bool   $preserve_legacy_map      Shop connect/buy: do not overwrite site activation list.
	 * }
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	public static function save_licenses( array $licenses, array $args = [] ) {
		$activate_new             = (bool) ( $args['activate_new'] ?? false );
		$activating_license_key   = (string) ( $args['activating_license_key'] ?? '' );
		$activating_addon_id      = (string) ( $args['activating_addon_id'] ?? '' );
		$install_only             = (bool) ( $args['install_only'] ?? false );
		$deactivating_addon_id    = (string) ( $args['deactivating_addon_id'] ?? '' );
		$deactivating_license_key = (string) ( $args['deactivating_license_key'] ?? '' );
		$preserve_legacy_map      = (bool) ( $args['preserve_legacy_map'] ?? false );

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
				if ( is_wp_error( $merged ) ) {
					return $merged;
				}

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

			$merged = self::apply_manual_license_deactivation_on_site( $merged, $deactivating_license_key );

			if ( is_array( $deactivated_row ) ) {
				$name = (string) ( $deactivated_row['name'] ?? '' );
				if ( ! License_Product_Map::is_all_access_bundle_name( $name ) ) {
					$addon_id = License_Product_Map::addon_id_from_product_name( $name );

					$still_active = false;
					$hostname     = self::get_site_hostname();
					foreach ( self::normalize_list( $merged ) as $row ) {
						if ( ! is_array( $row ) ) {
							continue;
						}
						if ( self::product_line_key_for_row( $row ) !== self::product_line_key_for_row( $deactivated_row ) ) {
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
			self::sync_addon_options_from_rich( $merged, [ 'install_packages' => false ] );
			self::persist_derived_addon_key_map( $merged );

			return self::finalize_license_sync( $merged );
		}

		$deactivating_addon_id = sanitize_key( trim( $deactivating_addon_id ) );
		if ( '' !== $deactivating_addon_id ) {
			$deactivate_error = self::deactivate_addon_on_site( $deactivating_addon_id );
			if ( is_wp_error( $deactivate_error ) ) {
				return $deactivate_error;
			}

			self::sync_addon_options_from_rich( $merged, [ 'install_packages' => false ] );
			self::persist_derived_addon_key_map( $merged );

			return self::finalize_license_sync( $merged );
		}

		$incoming_flags    = self::classify_incoming_keys( $existing, $merged );
		$has_new_keys      = $activate_new && $incoming_flags['has_new_keys'];
		$try_shop_activate = self::should_run_shop_auto_activate( $was_empty || $activate_new );
		if ( ! $try_shop_activate && $has_new_keys ) {
			$try_shop_activate = $incoming_flags['needs_shop_activation'];
		}
		if ( $has_new_keys && $incoming_flags['has_upgrade_successor'] ) {
			$try_shop_activate = false;
		}

		$is_legacy = self::is_legacy_license_store();
		if (
			$is_legacy
			&& ! $activate_new
			&& '' === trim( $activating_license_key )
			&& ! $has_new_keys
		) {
			$synced = self::sync_active_keys_to_shop( $merged, $existing );
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
			trim( $activating_license_key ) === ''
				&& '' === trim( $deactivating_license_key )
				&& '' === trim( $deactivating_addon_id )
				&& ! $try_shop_activate
		);

		$sync_error = self::sync_addon_options_from_rich(
			$merged,
			[
				'install_only_license_key' => trim( $activating_license_key ),
				'install_packages'         => true,
				'install_only_addon_id'    => trim( $activating_addon_id ),
				'install_only'             => $install_only,
				'update_legacy_map'        => ! $preserve_legacy_map,
			]
		);
		if ( is_wp_error( $sync_error ) ) {
			return $sync_error;
		}

		if ( ! $install_only && ! $preserve_legacy_map ) {
			self::persist_derived_addon_key_map( $merged );
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
	 * When one add-on is activated under All Access, deactivate that product's single license on this site.
	 *
	 * @param array<int, array<string, mixed>> $rich                  Rich license list.
	 * @param string                           $all_access_license_key All Access license key from the UI.
	 * @param string                           $addon_id               Short add-on id (pro, tracking, …).
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	private static function detach_single_product_license_from_site_for_addon( array $rich, string $all_access_license_key, string $addon_id ) {
		$addon_id = sanitize_key( $addon_id );
		$hostname = self::get_site_hostname();

		if ( '' === $addon_id || '' === $hostname || '' === trim( $all_access_license_key ) ) {
			return $rich;
		}

		$all_access_license_key = trim( $all_access_license_key );
		$manifest               = License_Product_Map::addon_manifest();
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

		$keys_to_detach = [];
		foreach ( $rich as $row ) {
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

			$key = trim( (string) ( $row['licenseKey'] ?? '' ) );
			if ( '' === $key ) {
				continue;
			}

			$on_site = self::is_site_activated_on_license( $row, $hostname )
				|| License_Site_Activation::is_license_active_on_site( $key );
			if ( ! $on_site ) {
				continue;
			}

			$keys_to_detach[ $key ] = true;
		}

		foreach ( array_keys( $keys_to_detach ) as $license_key ) {
			$rich = self::apply_manual_license_deactivation_on_site( $rich, $license_key );
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

		$manifest = License_Product_Map::addon_manifest();

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

		$plugin_file = self::plugin_file_for_addon_id( $addon_id );
		if ( null === $plugin_file ) {
			return true;
		}

		if ( is_plugin_active( $plugin_file ) ) {
			deactivate_plugins( $plugin_file, true );
		}

		self::remove_aa_activated_addon_id( $addon_id );

		return true;
	}

	/**
	 * Best All Access row for the given selection mode.
	 *
	 * @param array<int, array<string, mixed>> $rich Rich license list.
	 * @param string                           $mode `entitled`, `on_site`, or `aa_addons`.
	 * @return array<string, mixed>|null
	 */
	public static function find_all_access_row( array $rich, string $mode = 'on_site' ): ?array {
		if ( 'entitled' === $mode ) {
			$best       = null;
			$best_score = 0;

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

				$score = self::license_priority_score( $row, true );
				if ( $score > $best_score ) {
					$best_score = $score;
					$best       = $row;
				}
			}

			return $best;
		}

		$hostname = self::get_site_hostname();
		$map_keys = array_fill_keys( License_Site_Activation::get_active_license_keys(), true );

		$best_on_site  = null;
		$score_on_site = 0;
		$best_mapped   = null;
		$score_mapped  = 0;

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

			$row_score = self::license_priority_score( $row, true );
			$key       = (string) $row['licenseKey'];

			if ( self::is_site_activated_on_license( $row, $hostname ) && $row_score > $score_on_site ) {
				$score_on_site = $row_score;
				$best_on_site  = $row;
			}

			if ( isset( $map_keys[ $key ] ) && $row_score > $score_mapped ) {
				$score_mapped = $row_score;
				$best_mapped  = $row;
			}
		}

		if ( null !== $best_on_site ) {
			return $best_on_site;
		}

		if ( 'aa_addons' === $mode ) {
			return [] !== self::get_aa_activated_addon_ids() ? $best_mapped : null;
		}

		return $best_mapped;
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

		$row = License_Utils::get_rich_license_row_by_key( $rich, $license_key );
		if ( ! is_array( $row ) ) {
			return $rich;
		}
		if ( ! License_Product_Map::is_all_access_bundle_name( (string) ( $row['name'] ?? '' ) ) ) {
			return $rich;
		}

		$merged = self::shop_activate_and_merge( $license_key, $rich );
		if ( is_wp_error( $merged ) ) {
			return $merged;
		}

		return self::ensure_local_site_slot_on_license( $merged, $license_key );
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

		$row = License_Utils::get_rich_license_row_by_key( $rich, $license_key );

		return is_array( $row ) ? (int) ( $row['licenseId'] ?? 0 ) : 0;
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

		// One winner license key per product line (higher priority score wins).
		$line_winners = [];

		foreach ( self::normalize_list( $rich ) as $row ) {
			$key = (string) ( $row['licenseKey'] ?? '' );
			if ( '' === $key || ! isset( $allowed[ $key ] ) ) {
				continue;
			}
			if ( ! self::is_site_activated_on_license( $row, $hostname ) ) {
				continue;
			}

			$line  = (string) ( self::product_line_key_for_row( $row ) ?? '' );
			$score = self::license_priority_score(
				$row,
				License_Product_Map::is_all_access_bundle_name( (string) ( $row['name'] ?? '' ) )
			);

			if ( ! isset( $line_winners[ $line ] ) || $score > $line_winners[ $line ]['score'] ) {
				$line_winners[ $line ] = [
					'key'   => $key,
					'score' => $score,
				];
			}
		}

		foreach ( $rich as $index => $row ) {
			if ( ! is_array( $row ) || ! self::is_site_activated_on_license( $row, $hostname ) ) {
				continue;
			}

			$key  = (string) ( $row['licenseKey'] ?? '' );
			$line = (string) ( self::product_line_key_for_row( $row ) ?? '' );
			$drop = ! isset( $allowed[ $key ] )
				|| ( isset( $line_winners[ $line ] ) && $key !== $line_winners[ $line ]['key'] );

			if ( $drop ) {
				$rich[ $index ] = self::remove_site_hostname_from_license_row( $row, $hostname );
			}
		}

		return $rich;
	}

	/**
	 * License keys that may hold this site's activation slot (site-activation list + entitled rows with site).
	 *
	 * @param array<int, array<string, mixed>> $rich Rich license list.
	 * @return array<string, bool>
	 */
	public static function resolve_allowed_site_license_keys( array $rich ): array {
		$allowed = array_fill_keys( License_Site_Activation::get_active_license_keys(), true );

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
	 * AA add-on ids still valid for this rich list (pure; does not write options).
	 *
	 * @param array<int, array<string, mixed>> $rich Rich license list.
	 * @return string[]
	 */
	private static function prune_aa_activated_addon_ids( array $rich ): array {
		$aa          = self::find_all_access_row( $rich, 'on_site' );
		$aa_key      = null !== $aa ? (string) ( $aa['licenseKey'] ?? '' ) : '';
		$assignments = self::resolve_addon_license_assignments( $rich );
		$active_keys = array_flip( License_Site_Activation::get_active_license_keys() );

		return array_values(
			array_filter(
				self::get_aa_activated_addon_ids(),
				static function ( string $addon_id ) use ( $assignments, $aa_key, $active_keys, $rich ): bool {
					if ( isset( $assignments[ $addon_id ] ) ) {
						$mapped_key = trim( (string) $assignments[ $addon_id ]['licenseKey'] );
						if (
							'' !== $mapped_key
							&& isset( $active_keys[ $mapped_key ] )
							&& null !== License_Utils::get_rich_license_row_by_key( $rich, $mapped_key )
						) {
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
	}

	/**
	 * Persist pruned All Access UI list when another license owns add-ons on site.
	 *
	 * @param array<int, array<string, mixed>> $rich Rich license list.
	 * @return void
	 */
	public static function sync_aa_activated_addon_ids_with_assignments( array $rich ): void {
		update_option( self::OPTION_AA_ACTIVATED_ADDONS, self::prune_aa_activated_addon_ids( $rich ), false );
		self::$addon_key_map_cache = null;
	}

	/**
	 * Legacy addon map to persist: All Access only includes user-activated add-ons.
	 *
	 * Pure derive — does not write options. Call {@see sync_aa_activated_addon_ids_with_assignments()}
	 * on write paths before persisting the map.
	 *
	 * @param array<int, array<string, mixed>> $rich Rich license list.
	 * @return array<string, string>
	 */
	public static function build_persisted_addon_key_map_from_rich( array $rich ): array {
		$full = self::get_active_addon_key_map_from_rich( $rich );
		$aa   = self::find_all_access_row( $rich, 'on_site' );

		if ( null === $aa ) {
			return $full;
		}

		$aa_key    = (string) ( $aa['licenseKey'] ?? '' );
		$activated = self::prune_aa_activated_addon_ids( $rich );
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
	 * Sync AA activated list then persist the derived addon⇒key map.
	 *
	 * @param array<int, array<string, mixed>> $rich Rich license list.
	 * @return void
	 */
	private static function persist_derived_addon_key_map( array $rich ): void {
		self::sync_aa_activated_addon_ids_with_assignments( $rich );
		self::persist_addon_key_map( self::build_persisted_addon_key_map_from_rich( $rich ) );
	}

	/**
	 * Whether each add-on package is on disk and the plugin is active.
	 *
	 * @return array<string, array{installed: bool, active: bool}>
	 */
	public static function get_addon_install_states(): array {
		$out = [];
		foreach ( Addons::plugin_files() as $addon_id => $canonical_file ) {
			// Same as before — do not change installed detection.
			$installed = Addons::is_addon_on_disk( $addon_id );

			$active = false;
			if ( $installed ) {
				$resolved = Addons::resolve_installed_plugin_file( $addon_id );

				// Case 1: alternate / resolved path (TextDomain, different folder).
				if ( null !== $resolved && is_plugin_active( $resolved ) ) {
					$active = true;
				}

				// Case 2: canonical path (advanced-ads-tracking/tracking.php, etc.).
				if ( ! $active && is_plugin_active( $canonical_file ) ) {
					$active = true;
				}
			}

			$out[ $addon_id ] = [
				'installed' => $installed,
				'active'    => $active,
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

		self::sync_addon_options_from_rich( $rich, [ 'install_packages' => false ] );

		if ( ! $mutate_activation_state ) {
			return self::get_licenses();
		}

		$rich      = self::get_licenses();
		$coalesced = self::dedupe_rich_rows( $rich );
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
	 * Drop duplicate rich rows (All Access coalesce + map stub cleanup).
	 *
	 * @param array<int, array<string, mixed>> $rich Rich license list.
	 * @return array<int, array<string, mixed>>
	 */
	public static function dedupe_rich_rows( array $rich ): array {
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

		if ( [] !== $all_access_keys ) {
			$rich = array_values(
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
	 *  Sync expiry
	 *
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
		self::sync_addon_options_from_rich( $rich, [ 'install_packages' => false ] );

		if ( null !== $only_key ) {
			License_Utils::update_expiry_notices( $only_key, null );
		}

		return $rich;
	}

	/**
	 *  Finalized The License sync
	 *
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
	 * @param array<int, array<string, mixed>>                                                                                $rich   Records from app/exchange.
	 * @param array<string, array{id: string, name: string, options_slug: string, path: string, aliases?: list<string>}>|null $addons Optional; defaults to Data::get_addons().
	 * @return array<string, array{licenseKey: string, row: array<string, mixed>, score: int}>
	 */
	public static function resolve_addon_license_assignments( array $rich, ?array $addons = null ): array {
		$hostname   = self::get_site_hostname();
		$addons     = $addons ?? License_Product_Map::addon_manifest();
		$candidates = [];

		if ( '' !== $hostname && self::has_site_activation_rows( $rich ) ) {
			$all_access_on_site = self::find_all_access_row( $rich, 'on_site' );
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

			$aa_for_addons = self::find_all_access_row( $rich, 'aa_addons' );
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

		$all_access = self::find_all_access_row( $rich, 'entitled' );
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
	 * Effective addon_id => license_key map for admin and EDD persistence.
	 *
	 * Request-local cache keyed by rich + activation options (no writes).
	 *
	 * @return array<string, string>
	 */
	public static function get_addon_key_map(): array {
		$rich = self::get_licenses();
		$fp   = md5(
			(string) wp_json_encode(
				[
					$rich,
					get_option( self::OPTION_AA_ACTIVATED_ADDONS, [] ),
					get_option( self::OPTION_SITE_ACTIVATION, [] ),
				]
			)
		);

		if ( null !== self::$addon_key_map_cache && self::$addon_key_map_cache['fp'] === $fp ) {
			return self::$addon_key_map_cache['map'];
		}

		$derived     = self::build_persisted_addon_key_map_from_rich( $rich );
		$list        = License_Site_Activation::get_list();
		$active_keys = array_flip( License_Site_Activation::get_active_license_keys() );

		if ( [] !== $list ) {
			$derived = array_filter(
				$derived,
				static fn( string $key ): bool => isset( $active_keys[ $key ] )
			);
		}

		self::$addon_key_map_cache = [
			'fp'  => $fp,
			'map' => $derived,
		];

		return $derived;
	}

	/**
	 * Replace predecessor license keys in the site-activation list after an upgrade successor is promoted.
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

		$list    = License_Site_Activation::get_list();
		$changed = false;

		foreach ( $list as $index => $entry ) {
			if ( trim( (string) ( $entry['license'] ?? '' ) ) !== $from_key ) {
				continue;
			}
			$list[ $index ]['license'] = $to_key;
			$changed                   = true;
		}

		if ( $changed ) {
			License_Site_Activation::persist( $list );
			self::$addon_key_map_cache = null;
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
	 * Delete all legacy per-addon EDD mirror options.
	 *
	 * @return void
	 */
	public static function delete_legacy_addon_mirror_options(): void {
		$slugs = [];
		foreach ( License_Product_Map::addon_manifest() as $row ) {
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
	 * License key assigned to one add-on from rich rows (post-migration).
	 *
	 * @param string $addon_id Short add-on id.
	 * @return string
	 */
	private static function derived_license_key_for_addon_id( string $addon_id ): string {
		return trim( (string) ( self::get_addon_key_map()[ $addon_id ] ?? '' ) );
	}

	/**
	 * EDD-compatible license status for one add-on (valid|invalid|expired|false).
	 *
	 * @param string $options_slug Add-on options slug.
	 * @return string|false
	 */
	public static function get_mirror_status_for_options_slug( string $options_slug ) {
		$addon_id = License_Utils::addon_id_from_options_slug( $options_slug );
		$row      = self::resolve_mirror_license_row_for_addon( $addon_id );
		if ( null === $row ) {
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
		$addon_id = License_Utils::addon_id_from_options_slug( $options_slug );
		$row      = self::resolve_mirror_license_row_for_addon( $addon_id );
		if ( null === $row ) {
			return '';
		}

		$expiry = trim( (string) ( $row['expiryDate'] ?? '' ) );

		return '' !== $expiry ? $expiry : false;
	}

	/**
	 * Rich license row used for EDD mirror status/expiry for one add-on.
	 *
	 * @param string $addon_id Short add-on id.
	 * @return array<string, mixed>|null
	 */
	private static function resolve_mirror_license_row_for_addon( string $addon_id ): ?array {
		$license_key = self::derived_license_key_for_addon_id( $addon_id );
		$rich        = self::get_licenses();

		if ( '' !== $license_key && License_Site_Activation::is_license_active_on_site( $license_key ) ) {
			$row = License_Utils::get_rich_license_row_by_key( $rich, $license_key );
			if ( is_array( $row ) ) {
				return $row;
			}
		}

		$manifest = License_Product_Map::addon_manifest();
		foreach ( License_Site_Activation::get_active_license_keys() as $key ) {
			$row = License_Utils::get_rich_license_row_by_key( $rich, $key );
			if ( ! is_array( $row ) ) {
				continue;
			}

			$name = (string) ( $row['name'] ?? '' );
			if ( License_Product_Map::is_all_access_bundle_name( $name ) ) {
				if ( in_array( $addon_id, self::get_aa_activated_addon_ids(), true ) ) {
					return $row;
				}
				continue;
			}

			if ( License_Product_Map::addon_id_from_product_name( $name, $manifest ) === $addon_id ) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * Ensure license keys from an addon⇒key map exist in the site-activation list.
	 * Listing uses advanced-ads-app-licenses only — do not merge map rows into rich list.
	 *
	 * @param array<string, string> $map Addon id => key.
	 * @return void
	 */
	public static function persist_addon_key_map( array $map ): void {
		self::$addon_key_map_cache = null;

		$keys = [];
		foreach ( $map as $key ) {
			$key = is_string( $key ) ? trim( $key ) : '';
			if ( '' !== $key ) {
				$keys[ $key ] = true;
			}
		}
		foreach ( array_keys( $keys ) as $license_key ) {
			if ( ! License_Site_Activation::is_license_active_on_site( $license_key ) ) {
				License_Site_Activation::upsert_status( $license_key, 'inactive' );
			}
		}
	}

	/**
	 * Mark a license key active on this site (site-activation list).
	 *
	 * @param string       $slug        Add-on id (unused; kept for call-site compatibility).
	 * @param string       $license_key License key.
	 * @param string       $status      Unused; activation is always active.
	 * @param string|false $expires     Unused.
	 * @param bool         $update_map  Unused.
	 * @return void
	 */
	public static function update_license_details( $slug, $license_key, $status = 'valid', $expires = false, bool $update_map = false ): void {
		unset( $slug, $status, $expires, $update_map );
		License_Site_Activation::upsert_status( (string) $license_key, 'active' );
	}
}
