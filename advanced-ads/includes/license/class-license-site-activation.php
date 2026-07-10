<?php
/**
 * Plan-level site activation list (`advanced-ads-licenses`).
 *
 * @package AdvancedAds
 * @since   2.0.9
 */

namespace AdvancedAds\License;

defined( 'ABSPATH' ) || exit;

/**
 * One `{ license, status }` row per unique license key on this site.
 */
final class License_Site_Activation {

	/**
	 * Plan-level site activation list from advanced-ads-licenses.
	 *
	 * @return array<int, array{license: string, status: string}>
	 */
	public static function get_list(): array {
		$raw  = get_option( License::OPTION_LEGACY_MAP, [] );
		$list = License_Utils::normalize_site_activation_list( is_array( $raw ) ? $raw : [] );

		if ( License::is_flat_map_retired() && License_Utils::needs_site_activation_compaction( is_array( $raw ) ? $raw : [] ) ) {
			self::persist( $list );
		}

		return $list;
	}

	/**
	 * Persist plan-level site activation list to advanced-ads-licenses.
	 *
	 * @param array<int, array{license?: string, status?: string}|string> $list Site activation rows.
	 * @return void
	 */
	public static function persist( array $list ): void {
		update_option( License::OPTION_LEGACY_MAP, License_Utils::normalize_site_activation_list( $list ), false );
	}

	/**
	 * License keys marked active on this site.
	 *
	 * @return string[]
	 */
	public static function get_active_license_keys(): array {
		$keys = [];
		foreach ( self::get_list() as $entry ) {
			if ( 'active' !== ( $entry['status'] ?? '' ) ) {
				continue;
			}
			$key = trim( (string) ( $entry['license'] ?? '' ) );
			if ( '' !== $key ) {
				$keys[] = $key;
			}
		}

		return array_values( array_unique( $keys ) );
	}

	/**
	 * Whether a license key is active on this site.
	 *
	 * @param string $license_key License key.
	 * @return bool
	 */
	public static function is_license_active_on_site( string $license_key ): bool {
		$license_key = trim( $license_key );
		if ( '' === $license_key ) {
			return false;
		}

		foreach ( self::get_list() as $entry ) {
			if ( trim( (string) ( $entry['license'] ?? '' ) ) !== $license_key ) {
				continue;
			}

			return 'active' === ( $entry['status'] ?? '' );
		}

		return false;
	}

	/**
	 * Upsert one license key in the site activation list.
	 *
	 * @param string $license_key License key.
	 * @param string $status      active|inactive.
	 * @return void
	 */
	public static function upsert_status( string $license_key, string $status ): void {
		$license_key = trim( $license_key );
		if ( '' === $license_key ) {
			return;
		}

		$list    = self::get_list();
		$updated = false;
		$status  = 'active' === strtolower( trim( $status ) ) ? 'active' : 'inactive';

		foreach ( $list as $index => $entry ) {
			if ( trim( (string) ( $entry['license'] ?? '' ) ) !== $license_key ) {
				continue;
			}
			$list[ $index ]['status'] = $status;
			$updated                  = true;
			break;
		}

		if ( ! $updated ) {
			$list[] = [
				'license' => $license_key,
				'status'  => $status,
			];
		}

		self::persist( $list );
	}

	/**
	 * Build site activation list from legacy flat keys and per-addon mirror status options.
	 *
	 * @return array<int, array{license: string, status: string}>
	 */
	public static function build_from_legacy_storage(): array {
		$raw    = get_option( License::OPTION_LEGACY_MAP, [] );
		$flat   = License_Utils::normalize_legacy_map( is_array( $raw ) ? $raw : [] );
		$by_key = [];

		foreach ( $flat as $addon_id => $license_key ) {
			$options_slug  = License_Utils::options_slug_for_addon_id( (string) $addon_id );
			$mirror_status = get_option( $options_slug . '-license-status', false );
			$status        = License_Utils::legacy_mirror_status_to_activation( $mirror_status );
			if ( ! isset( $by_key[ $license_key ] ) ) {
				$by_key[ $license_key ] = $status;
				continue;
			}
			if ( 'active' === $status ) {
				$by_key[ $license_key ] = 'active';
			}
		}

		$list = [];
		foreach ( $by_key as $license => $status ) {
			$list[] = [
				'license' => (string) $license,
				'status'  => $status,
			];
		}

		return License_Utils::normalize_site_activation_list( $list );
	}

	/**
	 * Local-only flat map retirement when rich covers all legacy keys (does not write OPTION_RICH).
	 *
	 * @param array<int, array<string, mixed>> $rich Rich license list (read only).
	 * @param array<string, string>            $map  Normalized legacy map.
	 * @return void
	 */
	public static function maybe_retire_legacy_flat_map( array $rich, array $map ): void {
		if ( License::is_flat_map_retired() || [] === $map || [] === $rich ) {
			return;
		}

		if ( ! License_Utils::rich_covers_legacy_keys( $map, $rich ) ) {
			return;
		}

		License::bootstrap_aa_activated_addons_from_legacy_map( $map, $rich );

		$derived = License::build_persisted_addon_key_map_from_rich( $rich );
		$stored  = License_Utils::normalize_legacy_map( get_option( License::OPTION_LEGACY_MAP, [] ) );

		foreach ( $stored as $addon_id => $key ) {
			if ( ( $derived[ (string) $addon_id ] ?? '' ) !== $key ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log(
						sprintf(
							'advanced-ads: flat map retirement skipped — addon %s key mismatch (stored %s, derived %s).',
							(string) $addon_id,
							$key,
							(string) ( $derived[ (string) $addon_id ] ?? '' )
						)
					);
				}
			}
		}

		self::persist( self::build_from_legacy_storage() );
		License::delete_legacy_addon_mirror_options();
		delete_option( License::OPTION_AA_ACTIVATED_ADDONS );
		update_option( License::OPTION_FLAT_MAP_RETIRED, '1', false );
		delete_option( License::OPTION_MIGRATION_DONE );
	}
}
