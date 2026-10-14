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
use AdvancedAds\Utilities\Conditional;
use AdvancedAds\Crons\Data_Usage as Data_Usage_Cron;

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
		add_action( 'admin_init', [ $this, 'maybe_redirect_to_welcome' ] );
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
		Data_Usage_Cron::unschedule_all();
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
			update_option( '_advads_welcome_page_redirect', 0 );
		}
	}

	/**
	 * Redirect to welcome page after first activation.
	 *
	 * @return void
	 */
	public function maybe_redirect_to_welcome(): void {
		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( ! Conditional::user_can( 'advanced_ads_manage_options' ) ) {
			return;
		}

		$option = get_option( '_advads_welcome_page_redirect', 0 );
		if ( is_numeric( $option ) && 0 !== absint( $option ) ) {
			return;
		}

		update_option( '_advads_welcome_page_redirect', time() );
		wp_safe_redirect( admin_url( 'admin.php?page=advanced-ads-app&path=/welcome' ) );

		exit;
	}
}
