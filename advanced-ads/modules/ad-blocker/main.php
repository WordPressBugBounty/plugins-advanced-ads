<?php
/**
 * Ad Blocker module.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

// Only load if not already existing (maybe included from another plugin).
if ( defined( 'ADVADS_AB_BASE_PATH' ) ) {
	return;
}

define( 'ADVADS_AB_BASE_PATH', plugin_dir_path( __FILE__ ) );
define( 'ADVADS_AB_SLUG', 'advanced-ads-ab-module' );

add_action( 'advanced-ads-plugin-loaded', 'advanced_ads_load_adblocker' );

/**
 * Load ad blocker functionality.
 */
function advanced_ads_load_adblocker() {
	Advanced_Ads_Ad_Blocker::get_instance();

	if ( is_admin() && ! wp_doing_ajax() ) {
		Advanced_Ads_Ad_Blocker_Admin::get_instance();
	}
}
