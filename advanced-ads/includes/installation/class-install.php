<?php
/**
 * The class provides plugin installation routines.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.47.0
 */

namespace AdvancedAds\Installation;

use AdvancedAds\Crons\Licenses as License_Cron;
use AdvancedAds\Framework\Installation\Install as Base;

defined( 'ABSPATH' ) || exit;

/**
 * Install.
 */
class Install extends Base {

	/**
	 * Runs this initializer.
	 *
	 * @return void
	 */
	public function initialize(): void {
		$this->base_file = ADVADS_FILE;
		parent::initialize();
	}

	/**
	 * Plugin activation callback.
	 *
	 * @return void
	 */
	protected function activate(): void {
		$this->save_first_activation_time();

		// TODO: inform modules.
		( new Capabilities() )->create_capabilities();

		// Addons compatibility check.
		if ( ! get_option( 'advanced-ads-2-compatibility-flag' ) ) {
			( new Compatibility() )->deactivate_plugins();
		}
	}

	/**
	 * Plugin deactivation callback.
	 *
	 * @return void
	 */
	protected function deactivate(): void {
		// TODO: inform modules.
		( new Capabilities() )->remove_capabilities();
		License_Cron::unschedule_all();
	}

	/**
	 * Plugin uninstall callback.
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		( new Uninstall() )->initialize();
	}

	/**
	 * Save first activation time
	 *
	 * @return void
	 */
	private function save_first_activation_time(): void {
		$first_activation_date = get_option( '_advads_first_activation_time' );
		if ( ! $first_activation_date ) {
			update_option( '_advads_first_activation_time', time() );
			set_transient( '_advads_welcome_page_redirect', true, 2 * MINUTE_IN_SECONDS );
		}
	}
}
