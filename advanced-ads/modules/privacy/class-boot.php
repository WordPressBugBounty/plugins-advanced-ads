<?php
/**
 * Privacy module boot
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

namespace AdvancedAds\Modules\Privacy;

use AdvancedAds\Modules\Bootable_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Boot class.
 */
class Boot implements Bootable_Interface {

	/**
	 * Boot the Privacy module.
	 *
	 * @return void
	 */
	public function boot(): void {
		add_action( 'advanced-ads-register-settings', [ new Settings(), 'register' ] );
		wp_advads()->register_integration( Frontend::class, 'privacy' );

		if ( is_admin() ) {
			wp_advads()->register_integration( Admin::class );
		}
	}
}
