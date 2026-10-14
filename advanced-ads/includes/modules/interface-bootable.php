<?php
/**
 * Contract for a Module's `entry` class.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

namespace AdvancedAds\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Bootable interface.
 */
interface Bootable_Interface {

	/**
	 * Boot the module's real functionality. Only called for a module that is both real (not upgradeable) and currently active.
	 *
	 * @return void
	 */
	public function boot(): void;
}
