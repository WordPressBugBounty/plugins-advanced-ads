<?php
/**
 * Privacy module bootstrap.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

if ( class_exists( 'Advanced_Ads', false ) ) {
	if ( defined( 'ADVADS_PRIVACY_SLUG' ) ) {
		return;
	}

	define( 'ADVADS_PRIVACY_SLUG', 'advanced-ads-privacy' );
	define( 'ADVADS_PRIVACY_BASE_PATH', plugin_dir_path( __FILE__ ) );
	define( 'ADVADS_PRIVACY_BASE_URL', plugins_url( basename( ADVADS_ABSPATH ) . '/modules/' . basename( ADVADS_PRIVACY_BASE_PATH ) . '/' ) );

	Advanced_Ads_Privacy::get_instance();

	if ( is_admin() ) {
		Advanced_Ads_Privacy_Admin::get_instance();
	}
}
