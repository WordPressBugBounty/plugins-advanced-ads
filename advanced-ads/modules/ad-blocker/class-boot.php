<?php
/**
 * Ad Blocker module boot
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

namespace AdvancedAds\Modules\AdBlocker;

use AdvancedAds\Modules\Bootable_Interface;
use AdvancedAds\Utilities\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Boot class.
 */
class Boot implements Bootable_Interface {

	/**
	 * Boot the Ad Blocker module.
	 *
	 * @return void
	 */
	public function boot(): void {
		wp_advads()->register_integration( Scripts::class );
		wp_advads()->register_integration( Ajax::class );
		add_action( 'advanced-ads-register-settings', [ new Settings(), 'register' ] );

		if ( ! is_admin() ) {
			wp_advads()->register_integration( Counter::class );
			return;
		}

		add_action( 'upgrader_process_complete', [ $this, 'rebuild_after_plugin_update' ], 10, 2 );
	}

	/**
	 * Incrementally recopy disguised assets after this plugin or an advanced-ads* add-on updates.
	 *
	 * @param \WP_Upgrader $upgrader   Upgrader instance.
	 * @param array        $hook_extra Upgrade context from WordPress.
	 *
	 * @return void
	 */
	public function rebuild_after_plugin_update( $upgrader, $hook_extra ): void {
		if ( ! $this->is_advanced_ads_plugin_update( $hook_extra ) ) {
			return;
		}

		if ( ! wp_advads_get_setting( 'use-adblocker', 'adblocker' ) ) {
			return;
		}

		$folder_name = wp_advads_get_setting( 'folder_name', 'adblocker' );
		if ( ! $folder_name ) {
			return;
		}

		( new Builder() )->build( false );
	}

	/**
	 * Whether $hook_extra is a plugin update for this plugin or an advanced-ads* add-on.
	 *
	 * @param mixed $hook_extra Upgrade context from WordPress.
	 *
	 * @return bool
	 */
	private function is_advanced_ads_plugin_update( $hook_extra ): bool {
		if ( ! is_array( $hook_extra ) ) {
			return false;
		}

		if ( 'plugin' !== ( $hook_extra['type'] ?? '' ) || 'update' !== ( $hook_extra['action'] ?? '' ) ) {
			return false;
		}

		$plugins = [];
		if ( ! empty( $hook_extra['plugins'] ) && is_array( $hook_extra['plugins'] ) ) {
			$plugins = $hook_extra['plugins'];
		} elseif ( ! empty( $hook_extra['plugin'] ) && is_string( $hook_extra['plugin'] ) ) {
			$plugins = [ $hook_extra['plugin'] ];
		}

		foreach ( $plugins as $plugin ) {
			if ( str_starts_with( dirname( (string) $plugin ), 'advanced-ads' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Uninstall the Ad Blocker module.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		$wp_filesystem = WordPress::get_filesystem();

		$folder_name = wp_advads_get_setting( 'folder_name', 'adblocker' );

		if ( ! $folder_name || ! $wp_filesystem || ! preg_match( '/^[A-Za-z0-9]+$/', $folder_name ) ) {
			return;
		}

		$path = trailingslashit( wp_upload_dir()['basedir'] ) . trailingslashit( $folder_name );
		$wp_filesystem->rmdir( $path, true );
	}
}
