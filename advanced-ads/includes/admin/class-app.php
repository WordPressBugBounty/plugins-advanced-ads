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
use AdvancedAds\Modules\AdSense\Utils;
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
		add_action( 'admin_enqueue_scripts', [ $this, 'add_context' ] );
		add_action( 'in_admin_header', [ $this, 'remove_notices' ], PHP_INT_MAX );
		add_filter( 'admin_footer_text', [ $this, 'admin_footer_text' ], 100 );
		add_filter( 'update_footer', [ $this, 'update_footer' ], 100 );
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

		// The page itself opens for anyone who edits ads; each route declares what it needs on
		// top (`capability`, manage options by default), and the app drops the ones the user lacks.
		$hook = add_submenu_page(
			ADVADS_SLUG,
			__( 'App', 'advanced-ads' ),
			__( 'App', 'advanced-ads' ),
			Conditional::user_cap( 'advanced_ads_edit_ads' ),
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
		// Hide the App submenu entry sitewide (registered last under Advanced Ads).
		wp_register_style( 'advanced-ads-app-menu', false, [], ADVADS_VERSION );
		wp_enqueue_style( 'advanced-ads-app-menu' );
		wp_add_inline_style(
			'advanced-ads-app-menu',
			'li#toplevel_page_advanced-ads .wp-submenu li:last-child{display:none}'
		);

		if ( ! $this->is_screen() ) {
			return;
		}

		wp_advads()->registry->enqueue_style( 'app' );
		wp_advads()->registry->enqueue_script( 'app' );
		wp_enqueue_media();
	}

	/**
	 * Remove notices from the app screen.
	 *
	 * @return void
	 */
	public function remove_notices(): void {
		if ( ! $this->is_screen() ) {
			return;
		}

		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'wpstg.admin_notices' );
	}

	/**
	 * Add context to the app screen.
	 *
	 * @return void
	 */
	public function add_context(): void {
		if ( ! $this->is_screen() ) {
			return;
		}

		wp_advads_json_add(
			'context',
			[
				'adsenseConnected' => Utils::is_setup(),
				// What routes may require of the current user; see routes.js.
				'capabilities'     => [
					'advanced_ads_edit_ads'       => Conditional::user_can( 'advanced_ads_edit_ads' ),
					'advanced_ads_manage_options' => Conditional::user_can( 'advanced_ads_manage_options' ),
				],
			]
		);
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
	 * Overrides WordPress text in Footer
	 *
	 * @param string $text The footer text.
	 *
	 * @return string
	 */
	public function admin_footer_text( $text ): string {
		if ( $this->is_screen() ) {
			return '<span class="text-sm leading-relaxed text-gray-500">' . sprintf(
				/* translators: %1$s is the URL to add a new review */
				__( 'Help other publishers find us &ndash; leave a &#9733;&#9733;&#9733;&#9733;&#9733; review on <a href="%1$s" target="_blank" class="text-primary underline">WordPress.org</a> or <a href="%2$s" target="_blank" class="text-primary underline">send feedback</a>.', 'advanced-ads' ),
				'https://wordpress.org/support/plugin/advanced-ads/reviews/#new-post',
				admin_url( 'admin.php?page=advanced-ads-app&path=/support' )
			);
		}

		return (string) $text;
	}

	/**
	 * Overrides WordPress text in Update Footer
	 *
	 * @param string $text The update footer text.
	 *
	 * @return string
	 */
	public function update_footer( $text ): string {
		if ( $this->is_screen() ) {
			return '<span class="ml-auto leading-relaxed text-sm text-gray-400">' . sprintf(
				/* translators: %1$s is the URL to add a new review */
				__( 'Version %1$s', 'advanced-ads' ),
				ADVADS_VERSION
			) . '</span>';
		}

		return (string) $text;
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
			__( 'Settings', 'advanced-ads' ),
			Conditional::user_cap( 'advanced_ads_manage_options' ),
			$base_url . '/settings',
			__( 'Settings', 'advanced-ads' ),
			$button_class,
		];

		$submenu['advanced-ads'][] = [
			__( 'Licenses', 'advanced-ads' ),
			Conditional::user_cap( 'advanced_ads_manage_options' ),
			$base_url . '/license',
			__( 'Licenses', 'advanced-ads' ),
			$button_class,
		];

		$submenu['advanced-ads'][] = [
			__( 'Support', 'advanced-ads' ),
			Conditional::user_cap( 'advanced_ads_manage_options' ),
			$base_url . '/support',
			__( 'Support', 'advanced-ads' ),
			$button_class,
		];
	}
}
