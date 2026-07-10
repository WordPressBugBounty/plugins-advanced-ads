<?php
/**
 * App screen.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.47.0
 */

namespace AdvancedAds\Admin;

use AdvancedAds\Abstracts\Screen;
use AdvancedAds\Framework\Interfaces\Integration_Interface;
use AdvancedAds\Framework\Utilities\Params;
use AdvancedAds\Utilities\Conditional;

defined( 'ABSPATH' ) || exit;

/**
 * App.
 *
 * phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
 */
class App extends Screen implements Integration_Interface {

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'admin_menu', [ $this, 'register_screen' ], 99 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ], 10, 0 );
		add_action( 'in_admin_header', [ $this, 'remove_notices' ], PHP_INT_MAX );
	}

	/**
	 * Screen unique id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'app';
	}

	/**
	 * Register screen into WordPress admin area.
	 *
	 * @return void
	 */
	public function register_screen(): void {
		$this->register_submenus();

		$hook = add_submenu_page(
			ADVADS_SLUG,
			__( 'App', 'advanced-ads' ),
			__( 'App', 'advanced-ads' ),
			Conditional::user_cap( 'advanced_ads_manage_options' ),
			ADVADS_SLUG . '-app',
			[ $this, 'display' ],
			999
		);

		$this->set_hook( $hook );
	}

	/**
	 * Enqueue assets
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( ! $this->is_screen() ) {
			return;
		}

		wp_advads()->registry->enqueue_style( 'app' );
		wp_advads()->registry->enqueue_script( 'app' );
	}

	/**
	 * Remove notices from the app screen.
	 *
	 * @return void
	 */
	public function remove_notices(): void {
		echo '<style>li#toplevel_page_advanced-ads .wp-submenu li:last-child {display: none}</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( ! $this->is_screen() ) {
			return;
		}

		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'wpstg.admin_notices' );
	}

	/**
	 * Display screen content.
	 *
	 * @return void
	 */
	public function display(): void {
		echo '<div id="advads-app"></div>';
	}

	/**
	 * Check if the current screen is the app screen.
	 *
	 * @return bool
	 */
	private function is_screen(): bool {
		$wp_screen = get_current_screen();
		return $wp_screen->id === $this->get_hook();
	}

	/**
	 * Register submenus.
	 *
	 * @return void
	 */
	private function register_submenus(): void {
		global $submenu;

		$button_class = 'advads-app-link';
		$is_app       = 'advanced-ads-app' === Params::get( 'page' );
		$base_url     = $is_app ? '' : admin_url( 'admin.php?page=advanced-ads-app&path=' );

		$submenu['advanced-ads'][] = [
			__( 'Support', 'advanced-ads' ),
			Conditional::user_cap( 'advanced_ads_manage_options' ),
			$base_url . '/support',
			__( 'Support', 'advanced-ads' ),
			$button_class,
		];

		$submenu['advanced-ads'][] = [
			__( 'Licenses', 'advanced-ads' ),
			Conditional::user_cap( 'advanced_ads_manage_options' ),
			$base_url . '/license',
			__( 'Licenses', 'advanced-ads' ),
			$button_class,
		];
	}
}
