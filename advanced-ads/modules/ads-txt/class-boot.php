<?php
/**
 * Ads.txt module boot.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

namespace AdvancedAds\Modules\AdsTxt;

use AdvancedAds\Modules\Bootable_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Boot class.
 */
class Boot implements Bootable_Interface {

	/**
	 * Boot the ads.txt module.
	 *
	 * @return void
	 */
	public function boot(): void {
		add_action( 'init', __NAMESPACE__ . '\advanced_ads_ads_txt_init', 9 );
		add_action( 'advanced-ads-register-settings', [ new Settings(), 'register' ] );
	}
}

if ( ! function_exists( 'advanced_ads_ads_txt_init' ) ) {
	/**
	 * Initializes the ads.txt module.
	 *
	 * @return void
	 */
	function advanced_ads_ads_txt_init(): void { // phpcs:ignore Universal.Files.SeparateFunctionsFromOO.Mixed -- the init callback sits with the boot class that hooks it.
		if (
			! is_multisite()
			|| ( function_exists( 'is_site_meta_supported' ) && is_site_meta_supported() )
		) {
			( new Frontend() );
		}
	}
}
