<?php
/**
 * AdSense module boot
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

namespace AdvancedAds\Modules\AdSense;

use AdvancedAds\Modules\AdSense\Types\Adsense;
use AdvancedAds\Modules\Bootable_Interface;
use AdvancedAds\Modules\AdSense\Types\Ad_Adsense;

defined( 'ABSPATH' ) || exit;

/**
 * Boot class.
 */
class Boot implements Bootable_Interface {

	/**
	 * Boot the AdSense module.
	 *
	 * @return void
	 */
	public function boot(): void {
		define( 'GADSENSE_OPT_NAME', ADVADS_SLUG . '-adsense' );

		add_action( 'advanced-ads-register-settings', [ new Settings(), 'register' ] );

		if ( is_admin() ) {
			\Advanced_Ads_AdSense_MAPI::get_instance();
		}

		if ( ! wp_doing_ajax() && is_admin() ) {
			wp_advads()->register_integration( Admin::class );
		} else {
			wp_advads()->register_integration( Frontend::class );
		}

		$network = \Advanced_Ads_Network_Adsense::get_instance();
		$network->register();

		add_action(
			'advanced-ads-ad-types-manager',
			static function ( $manager ) {
				$manager->register_type( Adsense::class );
			}
		);
	}
}
