<?php
/**
 * The class is responsible for adding menu and submenu pages for the plugin in the WordPress admin area.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.47.0
 */

namespace AdvancedAds\Admin;

use Advanced_Ads_Checks;
use Advanced_Ads_Ad_Health_Notices;
use AdvancedAds\Constants;
use AdvancedAds\Admin\Pages;
use AdvancedAds\Utilities\WordPress;
use AdvancedAds\Utilities\Conditional;
use AdvancedAds\Framework\Interfaces\Integration_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Admin Admin Menu.
 */
class Admin_Menu implements Integration_Interface {

	/**
	 * Registered screens.
	 *
	 * @var array
	 */
	private $screens = [];

	/**
	 * Cached screen IDs mapped to their hooks.
	 *
	 * @var array|null
	 */
	private $screen_ids = null;

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'admin_menu', [ $this, 'add_screens' ], 15 );
		add_filter( 'admin_body_class', [ $this, 'add_body_class' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ], 10, 0 );
		add_action( 'in_admin_header', [ $this, 'add_custom_header' ], 25 );
		add_action( 'admin_head', [ $this, 'highlight_menu_item' ] );
	}

	/**
	 * Add administration pages to the WordPress Dashboard menu.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function add_screens(): void {
		foreach ( $this->get_screens() as $screen ) {
			$screen->register_screen();
			$this->screen_ids[ $screen->get_hook() ] = $screen->get_id();
		}

		$this->register_forward_links();

		/**
		 * Allows extensions to insert sub menu pages.
		 *
		 * @deprecated 2.0.0 use `advanced-ads-add-screen` instead.
		 *
		 * @param string $plugin_slug      The slug slug used to add a visible page.
		 * @param string $hidden_page_slug The slug slug used to add a hidden page.
		 */
		do_action( 'advanced-ads-submenu-pages', ADVADS_SLUG, 'advanced_ads_hidden_page_slug' ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
	}

	/**
	 * Add a custom class to the body tag of plugin screens.
	 *
	 * @param string $classes Space-separated list of classes.
	 *
	 * @return string
	 */
	public function add_body_class( string $classes ): string {
		if ( $this->is_screen() ) {
			// Ensure $classes is always a string due to 3rd party plugins interfering with the filter.
			$classes  = is_string( $classes ) ? $classes : '';
			$classes .= ' advads-page';
		}

		return $classes;
	}

	/**
	 * Enqueue styles and scripts for the current screen.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		if ( $this->is_screen() ) {
			$screen = $this->get_current_screen();
			wp_advads()->registry->enqueue_style( 'admin-common' );
			wp_advads()->registry->enqueue_script( 'admin-common' );
			$screen->enqueue_assets();
			do_action( 'advanced-ads-screen-' . $screen->get_id(), $screen );
		}
	}

	/**
	 * Add a custom header to plugin screens.
	 *
	 * @return void
	 */
	public function add_custom_header(): void {
		if ( $this->is_screen() ) {
			$current_screen = $this->get_current_screen();
			if ( $current_screen ) {
				extract( $current_screen->get_header_args(), EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract
				include_once ADVADS_ABSPATH . 'views/admin/header.php';
			}
		}
	}

	/**
	 * Highlights the 'Advanced Ads->Ads' item in the menu when an ad edit page is open
	 *
	 * @see the 'parent_file' and the 'submenu_file' filters for reference
	 *
	 * @return void
	 */
	public function highlight_menu_item(): void {
		global $parent_file, $submenu_file;

		$wp_screen = get_current_screen();

		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		if ( 'post' === $wp_screen->base && Constants::POST_TYPE_AD === $wp_screen->post_type ) {
			$parent_file  = ADVADS_SLUG;
			$submenu_file = 'edit.php?post_type=' . Constants::POST_TYPE_AD;
		}
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/**
	 * Register a new screen.
	 *
	 * @param string $screen Fully qualified class name of the screen.
	 *
	 * @return void
	 */
	public function add_screen( string $screen ): void {
		$screen = new $screen();

		$this->screens[ $screen->get_id() ] = $screen;
	}

	/**
	 * Get a screen by its id
	 *
	 * @param string $id Screen id.
	 *
	 * @return Screen|null
	 */
	private function get_screen( string $id ) {
		$screens = $this->get_screens();

		return $screens[ $id ] ?? null;
	}

	/**
	 * Get the hook of a screen by its id
	 *
	 * @param string $id Screen id.
	 *
	 * @return string|null
	 */
	public function get_hook( $id ) {
		$screen = $this->get_screen( $id );

		return $screen ? $screen->get_hook() : null;
	}

	/**
	 * Check if the current screen belongs to the plugin.
	 *
	 * @param string $screen_id Optional screen id to check against.
	 *
	 * @return bool
	 */
	public function is_screen( $screen_id = '' ): bool {
		$wp_screen_id = get_current_screen()->id;

		if ( '' !== $screen_id ) {
			$hook = array_search( $screen_id, $this->screen_ids, true );
			return false !== $hook && $hook === $wp_screen_id;
		}

		return isset( $this->screen_ids[ $wp_screen_id ] );
	}

	/**
	 * Retrieve the current screen instance.
	 *
	 * @return null|Screen The current screen instance or null if not applicable.
	 */
	private function get_current_screen() {
		$screen_id = $this->screen_ids[ get_current_screen()->id ] ?? null;

		return $screen_id ? $this->screens[ $screen_id ] : null;
	}

	/**
	 * Get screens
	 *
	 * @return array
	 */
	private function get_screens(): array {
		if ( ! empty( $this->screens ) ) {
			return $this->screens;
		}

		$this->register_core_screens();

		/**
		 * Let developers add their own screens.
		 *
		 * @param Admin_Menu $this The admin menu instance.
		 */
		do_action( 'advanced-ads-add-screen', $this );

		$this->sort_screens();

		return $this->screens;
	}

	/**
	 * Register forward links
	 *
	 * @return void
	 */
	private function register_forward_links(): void {
		global $submenu;

		$has_ads      = WordPress::get_count_ads();
		$notices      = Advanced_Ads_Ad_Health_Notices::get_number_of_notices();
		$notice_alert = '&nbsp;<span class="update-plugins count-' . $notices . '"><span class="update-count">' . $notices . '</span></span>';

		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		if ( current_user_can( Conditional::user_cap( 'advanced_ads_manage_options' ) ) ) {
			if ( $has_ads ) {
				$submenu['advanced-ads'][0][0] .= $notice_alert;
			} else {
				$submenu['advanced-ads'][1][0] .= $notice_alert;
			}

			// Link to license tab if they are invalid.
			if ( Advanced_Ads_Checks::licenses_invalid() ) {
				$submenu['advanced-ads'][] = [
					__( 'Licenses', 'advanced-ads' )
						. '&nbsp;<span class="update-plugins count-1"><span class="update-count">!</span></span>',
					Conditional::user_cap( 'advanced_ads_manage_options' ),
					admin_url( 'admin.php?page=advanced-ads-settings#top#licenses' ),
					__( 'Licenses', 'advanced-ads' ),
				];
			}
		}
		// phpcs:enable
	}

	/**
	 * Register core screens.
	 *
	 * @return void
	 */
	private function register_core_screens(): void {
		$this->add_screen( Pages\Dashboard::class );
		$this->add_screen( Pages\Ads::class );
		$this->add_screen( Pages\Ads_Editing::class );
		$this->add_screen( Pages\Groups::class );
		$this->add_screen( Pages\Placements::class );
		$this->add_screen( Pages\Settings::class );
		$this->add_screen( Pages\Tools::class );
		$this->add_screen( Pages\Onboarding::class );
		$this->add_screen( Pages\Support::class );
	}

	/**
	 * Sort screens by order.
	 *
	 * @return void
	 */
	private function sort_screens(): void {
		uasort(
			$this->screens,
			static function ( $a, $b ) {
				$order_a = $a->get_order();
				$order_b = $b->get_order();

				if ( $order_a === $order_b ) {
					return 0;
				}

				return ( $order_a < $order_b ) ? -1 : 1;
			}
		);
	}
}
