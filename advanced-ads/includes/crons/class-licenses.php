<?php
/**
 * License expiry crons via Action Scheduler.
 *
 * @package AdvancedAds
 */

namespace AdvancedAds\Crons;

use AdvancedAds\Constants;
use AdvancedAds\Framework\Interfaces\Integration_Interface;
use AdvancedAds\License\License;
use AdvancedAds\License\License_Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Schedules and runs license expiry crons.
 */
class Licenses implements Integration_Interface {

	/**
	 * Register Action Scheduler callbacks for license expiry cron hooks.
	 *
	 * @return void
	 */
	public function hooks(): void {
		foreach ( array_keys( Constants::LICENSE_EXPIRY_CRONS ) as $hook ) {
			add_action( $hook, [ $this, 'run_license_expiry' ], 10, 1 );
		}
	}

	/**
	 * Unschedule all license validate and expiry actions (plugin deactivation).
	 *
	 * @return void
	 */
	public static function unschedule_all(): void {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		as_unschedule_all_actions( Constants::CRON_JOB_LICENSE_VALIDATE, [], Constants::CRON_GROUP_ADVANCED_ADS );

		foreach ( array_keys( Constants::LICENSE_EXPIRY_CRONS ) as $hook ) {
			as_unschedule_all_actions( $hook, null, Constants::CRON_GROUP_ADVANCED_ADS );
		}
	}

	/**
	 * Schedule one-time expiry actions for each stored license key.
	 *
	 * Creates up to three pending jobs per license (one month, ten days, grace)
	 * when their scheduled run times are still in the future. Existing jobs for
	 * the same hook, key, and group are replaced.
	 *
	 * @param array<int, array<string, mixed>> $rich Rich license list; loads from storage when empty.
	 *
	 * @return void
	 */
	public static function schedule_license_expiry( array $rich = [] ): void {
		if ( ! function_exists( 'as_schedule_single_action' ) || ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		if ( [] === $rich ) {
			if ( ! License::has_stored_licenses() ) {
				return;
			}
			$rich = License::get_licenses();
		}

		$now = time();

		foreach ( License::normalize_list( $rich ) as $row ) {
			$key = trim( (string) ( $row['licenseKey'] ?? '' ) );
			if ( '' === $key ) {
				continue;
			}

			$ts   = License_Utils::license_expiry_timestamps( $row );
			$args = [ $key ];

			foreach ( Constants::LICENSE_EXPIRY_CRONS as $hook => $level ) {
				as_unschedule_all_actions( $hook, $args, Constants::CRON_GROUP_ADVANCED_ADS );
				$t = $ts[ $level ] ?? 0;
				if ( $t > $now ) {
					as_schedule_single_action( $t, $hook, $args, Constants::CRON_GROUP_ADVANCED_ADS );
				}
			}
		}
	}

	/**
	 * Run a scheduled license expiry action for one license key.
	 *
	 * Refreshes the license from the shop, then either sets an admin notice flag
	 * (one month / ten days) or syncs local expiry state after the grace period.
	 *
	 * @param string $license_key License key (Action Scheduler passes indexed args).
	 *
	 * @return void
	 */
	public function run_license_expiry( string $license_key ): void {
		$key   = trim( $license_key );
		$level = Constants::LICENSE_EXPIRY_CRONS[ current_action() ] ?? null;

		if ( '' === $key || null === $level ) {
			return;
		}

		$rich = License::get_licenses();
		if ( null === License_Utils::get_rich_license_row_by_key( $rich, $key ) ) {
			return;
		}

		$rich = License::sync_persisted_license_from_shop( $key );
		$row  = License_Utils::get_rich_license_row_by_key( $rich, $key );
		if ( null === $row ) {
			return;
		}

		if ( 'grace' === $level ) {
			if ( ! License_Utils::license_expiry_is_future( $row ) ) {
				License::sync_local_expiry( $rich, $key );
			}

			return;
		}

		if (
			License_Utils::license_expiry_is_future( $row )
			&& License_Utils::is_license_expiring_soon( $row )
		) {
			License_Utils::set_expiry_notice_flag( $key, $level );
		}
	}
}
