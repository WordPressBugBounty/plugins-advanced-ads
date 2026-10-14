<?php
/**
 * OneClick Connect module boot — ported from the old modules/one-click/main.php.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

namespace AdvancedAds\Modules\OneClick;

use AdvancedAds\Modules\Bootable_Interface;
use AdvancedAds\Modules\OneClick\Admin\Admin;
use AdvancedAds\Modules\OneClick\Admin\Ajax;

defined( 'ABSPATH' ) || exit;

/**
 * Boot class.
 */
class Boot implements Bootable_Interface {

	/**
	 * Boot the OneClick Connect module.
	 *
	 * @return void
	 */
	public function boot(): void {
		( new Workflow() )->hooks();

		if ( is_admin() ) {
			( new Ajax() )->hooks();
			( new Admin() )->hooks();
		}
	}
}
