<?php
/**
 * Abstracts Screen.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.47.0
 */

namespace AdvancedAds\Abstracts;

use AdvancedAds\Framework\Utilities\Str;
use AdvancedAds\Framework\Utilities\Params;

defined( 'ABSPATH' ) || exit;

/**
 * Abstracts Screen.
 */
abstract class Screen {

	/**
	 * The hook assigned to the screen.
	 *
	 * @var string
	 */
	private $hook = '';

	/**
	 * List of tabs for the screen.
	 *
	 * @var array
	 */
	private $tabs = [];

	/* Page Hook Api --------------- */

	/**
	 * Get the hook.
	 *
	 * @return string
	 */
	public function get_hook(): string {
		return $this->hook;
	}

	/**
	 * Set the hook.
	 *
	 * @param string $hook Hook to set.
	 *
	 * @return void
	 */
	public function set_hook( string $hook ): void {
		$this->hook = $hook;
	}

	/* Screen API ------------------ */

	/**
	 * Screen unique id.
	 *
	 * @return string
	 */
	abstract public function get_id(): string;

	/**
	 * Register screen into WordPress admin area.
	 *
	 * @return void
	 */
	abstract public function register_screen(): void;

	/**
	 * Enqueue assets
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {}

	/**
	 * Display screen content.
	 *
	 * @return void
	 */
	public function display(): void {}

	/**
	 * Get the order value.
	 *
	 * @return int The order value, which is 10.
	 */
	public function get_order(): int {
		return 10;
	}

	/* Tabs API -------------------- */

	/**
	 * Get the tabs.
	 *
	 * @return array
	 */
	public function get_tabs(): array {
		return $this->tabs;
	}

	/**
	 * Set the tabs.
	 *
	 * @param array $tabs Array of screen tabs.
	 *
	 * @return void
	 */
	public function set_tabs( array $tabs ): void {
		$this->tabs = apply_filters( 'advanced-ads-screen-tabs-' . $this->get_id(), $tabs );
	}

	/**
	 * Get current tab id.
	 *
	 * @return string
	 */
	public function get_current_tab_id(): string {
		$first = current( array_keys( $this->tabs ) );

		return Params::get( 'sub_page', $first );
	}

	/**
	 * Render tabs menu
	 *
	 * @param array $args Arguments to be used in the template.
	 *
	 * @return void
	 */
	public function get_tabs_menu( array $args = [] ): void { // phpcs:ignore
		$tabs   = $this->tabs;
		$active = $this->get_current_tab_id();

		include ADVADS_ABSPATH . 'views/admin/header-tabs.php';
	}

	/**
	 * Render tabs content
	 *
	 * @param array $args Arguments to be used in the template.
	 *
	 * @return void
	 */
	public function get_tab_content( array $args = [] ): void { // phpcs:ignore
		$active = $this->get_current_tab_id();
		$tab    = $this->tabs[ $active ] ?? null;

		if ( ! $tab ) {
			return;
		}

		echo '<div class="advads-tab-content">';
		if ( isset( $tab['callback'] ) ) {
			call_user_func( $tab['callback'] );
		} elseif ( isset( $tab['filename'] ) ) {
			include $tab['filename'];
		}

		echo '</div>';
	}

	/* Header API ------------------ */

	/**
	 * Get page header arguments
	 *
	 * @return array
	 */
	public function define_header_args(): array {
		return [];
	}

	/**
	 * Add actions to the header
	 *
	 * @return void
	 */
	public function header_actions(): void {}

	/**
	 * Get admin page header
	 *
	 * @param array $args Arguments to be used in the template.
	 *
	 * @return void
	 */
	public function get_header( array $args = [] ): void {
		$args = wp_parse_args( $args, $this->get_header_args() );

		extract( $args, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract

		include ADVADS_ABSPATH . 'views/admin/layout/header.php';
	}

	/**
	 * Get page header arguments
	 *
	 * @return array
	 */
	public function get_header_args(): array {
		$wp_screen = get_current_screen();
		return wp_parse_args(
			$this->define_header_args(),
			[
				'title'            => get_admin_page_title(),
				'breadcrumb_title' => get_admin_page_title(),
				'breadcrumb'       => true,
				'manual_url'       => $this->get_manual_url( '' ),
				'screen'           => $wp_screen,
			]
		);
	}

	/**
	 * Get manual url
	 *
	 * @param string $path Path to the manual page.
	 *
	 * @return string
	 */
	public function get_manual_url( string $path ): string {
		$domain = 'https://wpadvancedads.com/manual/';

		if ( Str::starts_with( 'http', $path ) ) {
			$domain = '';
		}

		return add_query_arg(
			[
				'utm_source'   => 'advanced-ads',
				'utm_medium'   => 'link',
				'utm_campaign' => 'header-manual-' . $this->get_id(),
			],
			$domain . ltrim( $path, '/' )
		);
	}
}
