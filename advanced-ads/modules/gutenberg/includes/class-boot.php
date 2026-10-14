<?php
/**
 * Gutenberg module boot — ported from the old modules/gutenberg/main.php.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

namespace AdvancedAds\Modules\Gutenberg;

use AdvancedAds\Modules\Bootable_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Boot class.
 */
class Boot implements Bootable_Interface {

	/**
	 * Boot the Gutenberg module.
	 *
	 * @return void
	 */
	public function boot(): void {
		new \Advanced_Ads_Gutenberg();
	}
}
