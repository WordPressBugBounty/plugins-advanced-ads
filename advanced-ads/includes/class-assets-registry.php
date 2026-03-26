<?php
/**
 * Assets registry handles the registration of stylesheets and scripts required for plugin functionality.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.47.0
 */

namespace AdvancedAds;

use AdvancedAds\Framework;

defined( 'ABSPATH' ) || exit;

/**
 * Assets Registry.
 */
class Assets_Registry extends Framework\Assets_Registry {

	/**
	 * Version for plugin local assets.
	 *
	 * @return string
	 */
	public function get_version(): string {
		return ADVADS_VERSION;
	}

	/**
	 * Prefix to use in handle to make it unique.
	 *
	 * @return string
	 */
	public function get_prefix(): string {
		return ADVADS_SLUG;
	}

	/**
	 * Base URL for plugin local assets.
	 *
	 * @return string
	 */
	public function get_base_url(): string {
		return ADVADS_BASE_URL;
	}

	/**
	 * Base path for plugin local assets.
	 *
	 * @return string
	 */
	public function get_base_path(): string {
		return ADVADS_ABSPATH;
	}

	/**
	 * Register styles
	 *
	 * @return void
	 */
	public function register_styles(): void {
		$this->register_style( 'ui', 'admin/assets/css/ui.css' );
		$this->register_style( 'admin', 'admin/assets/css/admin.css' );
		$this->register_style( 'ad-positioning', 'modules/ad-positioning/assets/css/ad-positioning.css', [ self::prefix_it( 'admin' ) ] );

		// New CSS files.
		$this->register_style( 'common', 'assets/css/admin/common.css' );
		$this->register_style( 'screen-onboarding', 'assets/css/admin/screen-onboarding.css' );

		// 2026.
		$this->register_style( 'admin-common', 'assets/dist/admin-common.css' );
		$this->register_style( 'notifications', 'assets/dist/notifications.css' );
		$this->register_style( 'screen-ads-editing', 'assets/dist/screen-ads-editing.css', [ self::prefix_it( 'common' ) ] );
		$this->register_style( 'screen-ads-listing', 'assets/dist/screen-ads-listing.css' );
		$this->register_style( 'screen-dashboard', 'assets/dist/screen-dashboard.css', [ self::prefix_it( 'common' ), 'wp-components' ] );
		$this->register_style( 'screen-groups-listing', 'assets/dist/screen-groups-listing.css' );
		$this->register_style( 'screen-placements-listing', 'assets/dist/screen-placements-listing.css' );
		$this->register_style( 'screen-settings', 'assets/dist/screen-settings.css', [ self::prefix_it( 'common' ) ] );
		$this->register_style( 'screen-support', 'assets/dist/screen-support.css' );
		$this->register_style( 'screen-tools', 'assets/dist/screen-tools.css' );
		$this->register_style( 'wp-dashboard', 'assets/dist/wp-dashboard.css', [ self::prefix_it( 'common' ) ] );
	}

	/**
	 * Register scripts
	 *
	 * @return void
	 */
	public function register_scripts(): void {
		$this->register_public_scripts();
		$this->register_admin_scripts();
	}

	/**
	 * Register public scripts
	 *
	 * @return void
	 */
	public function register_public_scripts(): void {
		$this->register_script( 'advanced-js', 'assets/dist/advanced.js', null, false, [ 'in_footer' => false, '@asset' => true ] ); // phpcs:ignore
	}

	/**
	 * Register admin scripts
	 *
	 * @return void
	 */
	public function register_admin_scripts(): void {
		$this->register_script( 'admin-global', 'admin/assets/js/admin-global.js', [ 'jquery' ], false, true );

		$this->register_script( 'ui', 'admin/assets/js/ui.js', [ 'jquery' ] );
		$this->register_script( 'conditions', 'admin/assets/js/conditions.js', [ 'jquery', self::prefix_it( 'ui' ) ] );
		$this->register_script( 'inline-edit-group-ads', 'admin/assets/js/inline-edit-group-ads.js', [ 'jquery' ], false, false );
		$this->register_script( 'ad-positioning', '/modules/ad-positioning/assets/js/ad-positioning.js', [], false, true );
		$this->register_script( 'groups', 'admin/assets/js/groups.js', [ 'jquery' ], false, true );
		$this->register_script( 'adblocker-image-data', 'admin/assets/js/adblocker-image-data.js', [ 'jquery' ] );

		$this->register_script( 'termination', 'admin/assets/js/termination.js', [ 'jquery' ], false, false );
		$this->register_script( 'modal', 'admin/assets/js/dialog-advads-modal.js', [ 'jquery' ], false, false );
		$this->register_script( 'admin', 'admin/assets/js/admin.js', [ 'jquery', self::prefix_it( 'termination' ), self::prefix_it( 'modal' ), self::prefix_it( 'ui' ), 'jquery-ui-autocomplete', 'wp-util' ], false, false );

		// New JS files.
		$onboarding_deps = [
			'jquery',
			'lodash',
			'moment',
			'wp-data',
			'wp-compose',
			'wp-components',
			'wp-api-fetch',
		];
		$this->register_script( 'screen-onboarding', 'assets/js/screen-onboarding.js', $onboarding_deps, false, true );

		// OneClick.
		$deps = [
			'jquery',
			'wp-dom-ready',
			'wp-components',
			'wp-notices',
			'wp-element',
			'wp-html-entities',
		];
		$this->register_script( 'oneclick-onboarding', 'assets/js/admin/oneclick-onboarding.js', $deps, false, true );

		// 2026.
		$this->register_script( 'find-adblocker', 'admin/assets/js/advertisement.js' );

		// phpcs:disable
		$use_assets = [ 'in_footer' => true, '@asset' => true ];

	    $this->register_script( 'commands', 'assets/dist/commands.js', null, false, $use_assets );
		$this->register_script( 'admin-common', 'assets/dist/admin-common.js', [ self::prefix_it( 'find-adblocker' ) ], false, $use_assets );
		$this->register_script( 'notifications-center', 'assets/dist/notifications.js', null, false, $use_assets );
		$this->register_script( 'page-quick-edit', 'assets/dist/post-quick-edit.js', null, false, $use_assets );
		$this->register_script( 'screen-ads-editing', 'assets/dist/screen-ads-editing.js', [ self::prefix_it( 'find-adblocker' ), self::prefix_it( 'admin-common' ) ], false, $use_assets );
		$this->register_script( 'screen-ads-listing', 'assets/dist/screen-ads-listing.js', [ 'inline-edit-post', 'wp-util', 'wp-api-fetch', self::prefix_it( 'admin-common' ), self::prefix_it( 'find-adblocker' ) ], false, $use_assets );
		$this->register_script( 'screen-dashboard', 'assets/dist/screen-dashboard.js', [ self::prefix_it( 'admin-common' ) ], false, $use_assets );
		$this->register_script( 'screen-groups-listing', 'assets/dist/screen-groups-listing.js', [ self::prefix_it( 'find-adblocker' ), self::prefix_it( 'admin-common' ) ], false, $use_assets );
		$this->register_script( 'screen-placements-listing', 'assets/dist/screen-placements-listing.js', [ 'wp-util', self::prefix_it( 'find-adblocker' ), self::prefix_it( 'admin-global' ), self::prefix_it( 'admin-common' ) ], false, $use_assets );
		$this->register_script( 'screen-settings', 'assets/dist/screen-settings.js', null, false, $use_assets );
		$this->register_script( 'screen-support', 'assets/dist/screen-support.js', null, false, $use_assets );
		$this->register_script( 'screen-tools', 'assets/dist/screen-tools.js', [ self::prefix_it( 'find-adblocker' ) ], false, $use_assets );
		$this->register_script( 'wp-dashboard', 'assets/dist/wp-dashboard.js', null, false, $use_assets );
		// phpcs:enable
	}
}
