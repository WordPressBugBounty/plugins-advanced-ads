<?php
/**
 * Dashboard screen.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.47.0
 */

namespace AdvancedAds\Admin\Pages;

use AdvancedAds\Abstracts\Screen;
use AdvancedAds\Utilities\Conditional;
use AdvancedAds\Utilities\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Dashboard.
 */
class Dashboard extends Screen {

	/**
	 * Screen unique id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'dashboard';
	}

	/**
	 * Register screen into WordPress admin area.
	 *
	 * @return void
	 */
	public function register_screen(): void {
		$has_ads = WordPress::get_count_ads();

		add_menu_page(
			__( 'Dashboard', 'advanced-ads' ),
			'Advanced Ads',
			Conditional::user_cap( 'advanced_ads_see_interface' ),
			ADVADS_SLUG,
			[ $this, 'display' ],
			$this->get_icon_svg(),
			'58.74'
		);

		// When ads exist, the submenu reuses the top-level slug without its own
		// callback so empty-state users still see the dashboard welcome UI.
		$hook = add_submenu_page(
			ADVADS_SLUG,
			__( 'Dashboard', 'advanced-ads' ),
			__( 'Dashboard', 'advanced-ads' ),
			Conditional::user_cap( $has_ads ? 'advanced_ads_edit_ads' : 'advanced_ads_see_interface' ),
			ADVADS_SLUG,
			$has_ads ? '' : [ $this, 'display' ]
		);

		$this->set_hook( $hook );
	}

	/**
	 * Enqueue assets
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		wp_advads()->registry->enqueue_style( 'screen-dashboard' );
		wp_advads()->registry->enqueue_script( 'screen-dashboard' );
	}

	/**
	 * Get page header arguments
	 *
	 * @return array<string, mixed>
	 */
	public function define_header_args(): array {
		return [
			'breadcrumb' => false,
		];
	}

	/**
	 * Display screen content.
	 *
	 * @return void
	 */
	public function display(): void {
		include ADVADS_ABSPATH . 'views/admin/screens/dashboard.php';
	}

	/**
	 * Advanced Ads logo as a data URI for the WP Admin menu icon.
	 *
	 * @return string
	 */
	private function get_icon_svg(): string {
		$svg = WordPress::get_svg( 'admin-menu-icon.svg' );

		return '' !== $svg ? 'data:image/svg+xml;base64,' . base64_encode( $svg ) : ''; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- the menu icon as a data URI.
	}
}
