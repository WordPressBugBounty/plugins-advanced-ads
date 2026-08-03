<?php
/**
 * License helper utilities.
 *
 * @package AdvancedAds
 */

namespace AdvancedAds\License;

use AdvancedAds\Utilities\Addons;

defined( 'ABSPATH' ) || exit;

/**
 * Stateless license helpers.
 */
final class License_Utils {

	/**
	 * Unix timestamp of the last successful license sync.
	 */
	public const OPTION_LAST_SYNC = 'advanced-ads-license-last-sync';

	/**
	 * Option: licenseKey => expiry notice level (month|ten_days).
	 */
	public const OPTION_EXPIRY_NOTICES = 'advanced-ads-license-expiry-notices';

	/**
	 * License admin screen URL with optional query args.
	 *
	 * @param array<string, int|string> $query_args Query arguments.
	 * @return string
	 */
	public static function admin_screen_url( array $query_args = [] ): string {
		$base = add_query_arg(
			[
				'page' => 'advanced-ads-app',
				'path' => '/license',
			],
			admin_url( 'admin.php' )
		);

		return $query_args ? add_query_arg( $query_args, $base ) : $base;
	}

	/**
	 * Normalize legacy license map.
	 *
	 * @param array<string, mixed> $legacy Raw option value.
	 * @return array<string, string>
	 */
	public static function normalize_legacy_map( array $legacy ): array {
		$out = [];
		foreach ( $legacy as $id => $val ) {
			if ( is_string( $val ) && '' !== $val ) {
				$out[ (string) $id ] = $val;
				continue;
			}
			if ( is_array( $val ) && ! empty( $val['license'] ) ) {
				$out[ (string) $id ] = (string) $val['license'];
			}
		}

		return $out;
	}

	/**
	 * Get expiry timestamp.
	 *
	 * @param array<string, mixed> $row Rich license row.
	 * @return int Zero when missing, lifetime, or unparseable.
	 */
	public static function license_expiry_timestamp( array $row ): int {
		$raw = strtolower( trim( (string) ( $row['expiryDate'] ?? '' ) ) );
		if ( '' === $raw || 'lifetime' === $raw ) {
			return 0;
		}

		$candidates = [
			[ 'd-m-Y', str_replace( '/', '-', $raw ) ],
			[ 'd/m/Y', $raw ],
		];
		foreach ( $candidates as $candidate ) {
			$format = $candidate[0];
			$value  = $candidate[1];
			$dt     = \DateTimeImmutable::createFromFormat( '!' . $format, $value );
			if ( $dt instanceof \DateTimeImmutable && $dt->format( $format ) === $value ) {
				return $dt->getTimestamp();
			}
		}

		$ts = strtotime( str_replace( '/', '-', $raw ) );

		return false === $ts ? 0 : $ts;
	}

	/**
	 * Check if license valid.
	 *
	 * @param array<string, mixed> $row Rich license row.
	 * @return bool
	 */
	public static function license_expiry_is_future( array $row ): bool {
		return self::license_expiry_timestamp( $row ) > time();
	}

	/**
	 * Last sync time.
	 *
	 * @return int
	 */
	public static function get_last_sync(): int {
		return (int) get_option( self::OPTION_LAST_SYNC, 0 );
	}

	/**
	 * Apply local expiry status.
	 *
	 * @param array<string, mixed> $row Rich license row.
	 * @return array<string, mixed>
	 */
	public static function apply_local_expiry_to_row( array $row ): array {
		$expiry_ts = self::license_expiry_timestamp( $row );
		if ( $expiry_ts > 0 && $expiry_ts <= time() ) {
			$row['status'] = 'expired';
		}

		return $row;
	}

	/**
	 * Expiring soon check.
	 *
	 * @param array<string, mixed> $row  Rich license row.
	 * @param int                  $days Days threshold.
	 * @return bool
	 */
	public static function is_license_expiring_soon( array $row, int $days = 30 ): bool {
		$ts = self::license_expiry_timestamp( $row );

		return $ts > time() && ( $ts - time() ) <= $days * DAY_IN_SECONDS;
	}

	/**
	 * Expiry milestones.
	 *
	 * @param array<string, mixed> $row Rich license row.
	 * @return array{month:int, ten_days:int, grace:int}
	 */
	public static function license_expiry_timestamps( array $row ): array {
		$expiry_ts = self::license_expiry_timestamp( $row );
		if ( $expiry_ts <= 0 ) {
			return [ 'month' => 0, 'ten_days' => 0, 'grace' => 0 ];
		}

		return [
			'month'    => $expiry_ts - 30 * DAY_IN_SECONDS,
			'ten_days' => $expiry_ts - 10 * DAY_IN_SECONDS,
			'grace'    => $expiry_ts + 2 * HOUR_IN_SECONDS,
		];
	}

	/**
	 * Addon option slug.
	 *
	 * @param string $addon_id Short id.
	 * @return string
	 */
	public static function options_slug_for_addon_id( string $addon_id ): string {
		return ( defined( 'ADVADS_SLUG' ) ? ADVADS_SLUG : 'advanced-ads' ) . '-' . $addon_id;
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
	 * Get expiry flags.
	 *
	 * @return array<string, string>
	 */
	public static function get_expiry_notice_flags(): array {
		$flags = get_option( self::OPTION_EXPIRY_NOTICES, [] );

		return is_array( $flags ) ? $flags : [];
	}

	/**
	 * Update expiry notices.
	 *
	 * @param string      $license_key License key; empty with null level clears all.
	 * @param string|null $level       month|ten_days to set; null to clear one key.
	 * @return void
	 */
	public static function update_expiry_notices( string $license_key, ?string $level ): void {
		$license_key = trim( $license_key );

		if ( '' === $license_key && null === $level ) {
			delete_option( self::OPTION_EXPIRY_NOTICES );
			return;
		}

		if ( '' === $license_key || ( null !== $level && ! in_array( $level, [ 'month', 'ten_days' ], true ) ) ) {
			return;
		}

		if ( null === $level ) {
			$flags = self::get_expiry_notice_flags();
			if ( ! isset( $flags[ $license_key ] ) ) {
				return;
			}

			unset( $flags[ $license_key ] );
			update_option( self::OPTION_EXPIRY_NOTICES, $flags, false );
			return;
		}

		$flags                 = self::get_expiry_notice_flags();
		$flags[ $license_key ] = $level;
		update_option( self::OPTION_EXPIRY_NOTICES, $flags, false );
	}

	/**
	 * Unique non-empty license keys from a legacy addon map.
	 *
	 * @param array<string, string> $map Normalized legacy map.
	 * @return string[]
	 */
	public static function unique_legacy_keys( array $map ): array {
		return array_values( array_unique( array_filter( array_map( 'strval', $map ) ) ) );
	}

	/**
	 * Whether every unique legacy key appears on at least one rich row.
	 *
	 * @param array<string, string>            $map  Normalized legacy map.
	 * @param array<int, array<string, mixed>> $rich Rich license list.
	 * @return bool
	 */
	public static function rich_covers_legacy_keys( array $map, array $rich ): bool {
		$needed = self::unique_legacy_keys( $map );
		if ( [] === $needed ) {
			return true;
		}

		$present = [];
		foreach ( $rich as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$key = trim( (string) ( $row['licenseKey'] ?? '' ) );
			if ( '' !== $key ) {
				$present[ $key ] = true;
			}
		}

		foreach ( $needed as $key ) {
			if ( ! isset( $present[ $key ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Classify raw advanced-ads-licenses storage shape.
	 *
	 * @param array<string|int, mixed> $raw Raw option value.
	 * @return string `empty` | `list` | `compact` | `legacy`
	 */
	public static function site_activation_storage_shape( array $raw ): string {
		if ( [] === $raw ) {
			return 'empty';
		}

		$is_list = true;

		foreach ( $raw as $key => $val ) {
			$addon_key = is_string( $key ) && ! is_numeric( $key ) && Addons::is_known_addon( (string) $key );

			if ( $addon_key && is_array( $val ) ) {
				return 'compact';
			}

			if ( is_string( $val ) && '' !== trim( $val ) ) {
				$is_list = false;
				continue;
			}

			if ( ! is_array( $val ) || '' === trim( (string) ( $val['license'] ?? '' ) ) ) {
				$is_list = false;
				continue;
			}

			if ( $addon_key ) {
				$is_list = false;
			}
		}

		return $is_list ? 'list' : 'legacy';
	}

	/**
	 * Whether stored value is a plan-level site activation list (not legacy flat strings).
	 *
	 * @param array<string|int, mixed> $raw Raw option value.
	 * @return bool
	 */
	public static function is_site_activation_list_storage( array $raw ): bool {
		return 'list' === self::site_activation_storage_shape( $raw );
	}

	/**
	 * Whether raw activation data is per-addon shaped and should be compacted to unique keys.
	 *
	 * @param array<string|int, mixed> $raw Raw option value.
	 * @return bool
	 */
	public static function needs_site_activation_compaction( array $raw ): bool {
		return 'compact' === self::site_activation_storage_shape( $raw );
	}

	/**
	 * Normalize plan-level site activation list: one row per unique license key.
	 *
	 * Accepts legacy flat strings, per-addon rows (compacted), or a numeric list.
	 *
	 * @param array<string|int, mixed> $raw Raw option value.
	 * @return array<int, array{license: string, status: string}>
	 */
	public static function normalize_site_activation_list( array $raw ): array {
		$by_key = [];

		foreach ( $raw as $val ) {
			if ( is_string( $val ) && '' !== trim( $val ) ) {
				self::merge_site_activation_status( $by_key, trim( $val ), 'inactive' );
				continue;
			}
			if ( ! is_array( $val ) ) {
				continue;
			}
			$license = trim( (string) ( $val['license'] ?? '' ) );
			if ( '' === $license ) {
				continue;
			}
			$status = strtolower( trim( (string) ( $val['status'] ?? 'inactive' ) ) );
			self::merge_site_activation_status( $by_key, $license, 'active' === $status ? 'active' : 'inactive' );
		}

		$out = [];
		foreach ( $by_key as $license => $status ) {
			$out[] = [
				'license' => (string) $license,
				'status'  => $status,
			];
		}

		return $out;
	}

	/**
	 * Site activation status
	 *
	 * @param array<string, string> $by_key License => status accumulator.
	 * @param string                $license License key.
	 * @param string                $status active|inactive.
	 * @return void
	 */
	private static function merge_site_activation_status( array &$by_key, string $license, string $status ): void {
		if ( ! isset( $by_key[ $license ] ) ) {
			$by_key[ $license ] = $status;
			return;
		}
		if ( 'active' === $status ) {
			$by_key[ $license ] = 'active';
		}
	}

	/**
	 * Map legacy EDD mirror status to site activation status.
	 *
	 * @param mixed $status Legacy mirror value.
	 * @return string active|inactive
	 */
	public static function legacy_mirror_status_to_activation( $status ): string {
		return 'valid' === $status ? 'active' : 'inactive';
	}

	/**
	 * Whether outbound HTTPS to a URL should verify SSL certificates.
	 *
	 * Local dev hosts (.test, .local, localhost) skip verification for self-signed certs.
	 *
	 * @param string $url Request URL.
	 * @return bool
	 */
	public static function should_verify_ssl_for_url( string $url ): bool {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) {
			return true;
		}

		if ( in_array( $host, [ 'localhost', '127.0.0.1' ], true ) ) {
			return false;
		}

		foreach ( [ '.test', '.local', '.localhost' ] as $local_tld ) {
			if ( strlen( $host ) > strlen( $local_tld ) && str_ends_with( $host, $local_tld ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Find license by key.
	 *
	 * @param array<int, array<string, mixed>> $rich        Rich license list (already loaded).
	 * @param string                           $license_key License key.
	 * @return array<string, mixed>|null
	 */
	public static function get_rich_license_row_by_key( array $rich, string $license_key ): ?array {
		$license_key = trim( $license_key );
		if ( '' === $license_key ) {
			return null;
		}

		foreach ( $rich as $row ) {
			if ( is_array( $row ) && (string) ( $row['licenseKey'] ?? '' ) === $license_key ) {
				return $row;
			}
		}

		return null;
	}
}
