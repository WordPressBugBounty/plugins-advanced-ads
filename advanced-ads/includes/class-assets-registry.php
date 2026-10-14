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

		// 2026.
		$this->register_style( 'admin-common', 'assets/dist/admin-common.css' );
		$this->register_style( 'notifications', 'assets/dist/notifications.css' );
		$this->register_style( 'screen-ads-editing', 'assets/dist/screen-ads-editing.css', [ self::prefix_it( 'common' ) ] );
		$this->register_style( 'screen-ads-listing', 'assets/dist/screen-ads-listing.css' );
		$this->register_style( 'screen-dashboard', 'assets/dist/screen-dashboard.css', [ self::prefix_it( 'common' ), 'wp-components' ] );
		$this->register_style( 'screen-groups-listing', 'assets/dist/screen-groups-listing.css' );
		$this->register_style( 'screen-placements-listing', 'assets/dist/screen-placements-listing.css' );
		$this->register_style( 'screen-plugins', 'assets/dist/screen-plugins.css' );
		$this->register_style( 'screen-tools', 'assets/dist/screen-tools.css' );
		$this->register_style( 'wp-dashboard', 'assets/dist/wp-dashboard.css', [ self::prefix_it( 'common' ) ] );

		// App.
		$this->register_style( 'app', 'assets/dist/app.css' );
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
		$this->register_script( 'adblocker-image-data', 'admin/assets/js/adblocker-image-data.js', [ 'jquery' ] );

		$this->register_script( 'termination', 'admin/assets/js/termination.js', [ 'jquery' ], false, false );
		$this->register_script( 'modal', 'admin/assets/js/dialog-advads-modal.js', [ 'jquery' ], false, false );
		$this->register_script( 'admin', 'admin/assets/js/admin.js', [ 'jquery', self::prefix_it( 'termination' ), self::prefix_it( 'modal' ), self::prefix_it( 'ui' ), 'jquery-ui-autocomplete', 'wp-util' ], false, false );

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
		$this->register_script( 'screen-plugins', 'assets/dist/screen-plugins.js', [ 'utils' ], false, $use_assets );
		$this->register_script( 'screen-tools', 'assets/dist/screen-tools.js', [ self::prefix_it( 'find-adblocker' ) ], false, $use_assets );
		$this->register_script( 'wp-dashboard', 'assets/dist/wp-dashboard.js', null, false, $use_assets );
		$this->register_script( 'oneclick-onboarding', 'assets/dist/oneclick-onboarding.js', [ 'wp-components' ], false, $use_assets );
		// phpcs:enable

		// App.
		$this->register_script( 'app-common', 'assets/dist/app-common.js', null, false, $use_assets );
		$this->register_script( 'app', 'assets/dist/app.js', [ self::prefix_it( 'app-common' ) ], false, $use_assets );

		global $wp_version;

		// Polyfill window.ReactJSXRuntime from window.React on WordPress < 6.6.
		if ( version_compare( $wp_version, '6.6', '<' ) && ! wp_script_is( 'react-jsx-runtime', 'registered' ) ) {
			$polyfill = '( function() {
					if ( window.ReactJSXRuntime ) { return; }
					var R = window.React;
					if ( ! R ) { return; }
					window.ReactJSXRuntime = {
						jsx: function ( type, props, key ) {
							return R.createElement( type, key !== undefined ? Object.assign( {}, props, { key: key } ) : props );
						},
						jsxs: function ( type, props, key ) {
							return R.createElement( type, key !== undefined ? Object.assign( {}, props, { key: key } ) : props );
						},
						Fragment: R.Fragment
					};
				} )();';

			wp_add_inline_script( self::prefix_it( 'app' ), $polyfill, 'before' );
			wp_add_inline_script( self::prefix_it( 'oneclick-onboarding' ), $polyfill, 'before' );
		}
	}

	/**
	 * Load asset metadata with backward compatibility for WP < 6.6.
	 *
	 * The `react-jsx-runtime` dependency was introduced in WordPress 6.6.
	 * When running on an older version the handle is not registered, so WordPress
	 * silently skips it – but the dep entry still prevents correct dependency
	 * ordering. Strip it here so the script queue stays clean, and rely on the
	 * inline polyfill added in register_admin_scripts() instead.
	 *
	 * @param string $relative_path Relative JS path (e.g. assets/dist/app.js).
	 * @return array{dependencies: array<string>, version: string|null}
	 */
	protected function load_asset_metadata( string $relative_path ): array {
		$asset = parent::load_asset_metadata( $relative_path );

		global $wp_version;

		if ( version_compare( $wp_version, '6.6', '<' ) && ! wp_script_is( 'react-jsx-runtime', 'registered' ) ) {
			$asset['dependencies'] = array_values(
				array_diff( $asset['dependencies'], [ 'react-jsx-runtime' ] )
			);
		}

		return $asset;
	}
}
