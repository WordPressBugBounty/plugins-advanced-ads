<?php
/**
 * Legacy license migration: exchange patch then flat-map retirement.
 *
 * Phase A: POST shop /license/exchange for each unique legacy key → advanced-ads-app-licenses.
 * Phase B: Local retire advanced-ads-licenses flat map + bootstrap AA add-ons.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   2.1.0
 */

use AdvancedAds\Crons\Licenses as License_Cron;
use AdvancedAds\License\License;
use AdvancedAds\License\License_Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Exchange legacy keys for rich rows, then retire the flat addon map when ready.
 *
 * @return void
 */
function advads_upgrade_2_1_0_migrate_legacy_licenses(): void {
	if ( License::is_flat_map_retired() ) {
		return;
	}

	$map = License_Utils::normalize_legacy_map( get_option( License::OPTION_LEGACY_MAP, [] ) );
	if ( [] === $map ) {
		update_option( License::OPTION_FLAT_MAP_RETIRED, '1', false );
		return;
	}

	License::maybe_complete_legacy_license_migration();

	if ( License::is_flat_map_retired() ) {
		License_Cron::schedule_license_expiry( License::get_licenses() );
	}
}

advads_upgrade_2_1_0_migrate_legacy_licenses();
