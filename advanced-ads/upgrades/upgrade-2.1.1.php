<?php
/**
 * Post-2.1.0 one-time upgrades (merged).
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   2.1.1
 */

use AdvancedAds\Utilities\Addons;

defined( 'ABSPATH' ) || exit;

/**
 * Merge the legacy ab-module option into the adblocker option.
 *
 * @since 2.1.1
 *
 * @return void
 */
function advads_upgrade_2_1_1_merge_adblocker_module_options(): void {
	$module_options = get_option( 'advanced-ads-ab-module', [] );

	if ( ! is_array( $module_options ) || [] === $module_options ) {
		delete_option( 'advanced-ads-ab-module' );
		return;
	}

	$adblocker_options = get_option( 'advanced-ads-adblocker', [] );

	if ( ! is_array( $adblocker_options ) ) {
		$adblocker_options = [];
	}

	foreach ( [ 'folder_name', 'module_can_work', 'lookup_table' ] as $key ) {
		if ( isset( $module_options[ $key ] ) ) {
			$adblocker_options[ $key ] = $module_options[ $key ];
		}
	}

	update_option( 'advanced-ads-adblocker', $adblocker_options );
	delete_option( 'advanced-ads-ab-module' );
}

/**
 * Delete the auto-update preferences and the cached shop answers of the replaced add-on updater.
 *
 * @since 2.1.1
 *
 * @return void
 */
function advads_upgrade_2_1_2_remove_old_updater_data(): void {
	global $wpdb;

	// The preferences live in the WordPress auto-update list now.
	delete_option( 'advanced-ads-autoupdate' );
	foreach ( Addons::known_addon_ids() as $addon_id ) {
		delete_option( 'advanced-ads-' . $addon_id . '-autoupdate' );
	}

	// Other plugins cache under the same `edd_sl_` prefix, so only rows holding an answer of our shop are removed.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$names = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'edd\_sl\_%' AND option_value LIKE '%wpadvancedads.com%'" );
	foreach ( $names as $name ) {
		delete_option( $name );
	}

	// The flag of the removed one-time check for add-ons from before 2.0.
	delete_option( 'advanced-ads-2-compatibility-flag' );
}

/**
 * One-time: copy previous Activate / enabled state into advanced-ads-modules.
 *
 * @since 2.1.1
 *
 * @return void
 */
function advads_upgrade_2_1_3_migrate_module_active_flags(): void {
	$modules = get_option( ADVADS_SLUG . '-modules', [] );
	if ( ! is_array( $modules ) ) {
		$modules = [];
	}

	$privacy = get_option( 'advanced-ads-privacy', [] );
	if ( is_array( $privacy ) && array_key_exists( 'enabled', $privacy ) ) {
		$modules['privacy'] = ! empty( $privacy['enabled'] );
	}

	$ads_txt = get_option( 'advanced_ads_ads_txt', [] );
	if ( is_array( $ads_txt ) && array_key_exists( 'enabled', $ads_txt ) ) {
		$modules['ads-txt'] = ! empty( $ads_txt['enabled'] );
	}

	$adblocker = get_option( 'advanced-ads-adblocker', [] );
	if ( is_array( $adblocker ) && array_key_exists( 'use-adblocker', $adblocker ) ) {
		$modules['ad-blocker'] = ! empty( $adblocker['use-adblocker'] );
	}

	update_option( ADVADS_SLUG . '-modules', $modules );
}

advads_upgrade_2_1_1_merge_adblocker_module_options();
advads_upgrade_2_1_2_remove_old_updater_data();
advads_upgrade_2_1_3_migrate_module_active_flags();
