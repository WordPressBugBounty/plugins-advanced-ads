<?php
/**
 * Admin Addon Updater.
 *
 * License keys and validity come from {@see \AdvancedAds\License\License}:
 * - {@see License::get_addon_key_map()} — rich app-licenses + site activation list
 * - {@see License::get_mirror_status_for_options_slug()} — EDD-compatible status
 * - {@see License::get_mirror_expires_for_options_slug()} — expiry from rich rows
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.50.0
 */

namespace AdvancedAds\Admin;

use AdvancedAds\Constants;
use AdvancedAds\License\License;
use AdvancedAds\License\License_Utils;
use AdvancedAds\Utilities\Data;
use AdvancedAds\Framework\Interfaces\Integration_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Admin Addon Updater.
 */
class Addon_Updater implements Integration_Interface {

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function hooks(): void {
		// Local/dev: WordPress blocks .test shop hosts (127.0.0.1) without this filter.
		License::register_local_development_shop_http_filters();

		if ( ! wp_doing_ajax() ) {
			add_action( 'load-plugins.php', [ $this, 'plugin_licenses_warning' ] );
		}

		// Register on every admin request (including AJAX plugin updates).
		add_action( 'admin_init', [ $this, 'add_on_updater' ], 1 );
	}

	/**
	 * Register the EDD updater for each add-on that has a license key on this site.
	 *
	 * @return void
	 */
	public function add_on_updater(): void {
		if ( ( is_multisite() && ! is_main_site() ) || ! apply_filters( 'advanced-ads-add-ons-updater', true ) ) {
			return;
		}

		$addon_keys = License::get_addon_key_map();

		foreach ( Data::get_addons() as $_add_on ) {
			$addon_id     = (string) ( $_add_on['id'] ?? '' );
			$options_slug = (string) ( $_add_on['options_slug'] ?? '' );
			$license_key  = trim( (string) ( $addon_keys[ $addon_id ] ?? '' ) );

			if ( '' === $license_key ) {
				continue;
			}

			new EDD_Updater(
				Constants::API_ENDPOINT,
				$_add_on['path'],
				[
					'version' => $_add_on['version'],
					'license' => $license_key,
					'item_id' => Constants::ADDON_SLUGS_ID[ $options_slug ] ?? false,
					'author'  => 'Advanced Ads',
				]
			);
		}
	}

	/**
	 * Show a license warning below add-ons with an invalid license on the plugins list.
	 *
	 * @since 1.7.12
	 *
	 * @return void
	 */
	public function plugin_licenses_warning(): void {
		if ( is_multisite() ) {
			return;
		}

		foreach ( Data::get_addons() as $_add_on ) {
			if ( 'slider-ads' === $_add_on['id'] ) {
				continue;
			}

			if ( 'valid' !== License::get_mirror_status_for_options_slug( (string) $_add_on['options_slug'] ) ) {
				$plugin_file = plugin_basename( $_add_on['path'] );
				add_action( 'after_plugin_row_' . $plugin_file, [ $this, 'add_plugin_list_license_notice' ], 10, 2 );
			}
		}
	}

	/**
	 * Add a row below add-ons with an invalid license on the plugin list
	 *
	 * @param string $plugin_file Path to the plugin file, relative to the plugins directory.
	 * @param array  $plugin_data An array of plugin data.
	 *
	 * @since 1.7.12
	 * @todo  make this work on multisite as well
	 *
	 * @return void
	 */
	public function add_plugin_list_license_notice( $plugin_file, $plugin_data ): void {
		static $cols;
		if ( null === $cols ) {
			$cols = count( _get_list_table( 'WP_Plugins_List_Table' )->get_columns() );
		}

		printf(
			'<tr class="advads-plugin-update-tr plugin-update-tr active"><td class="plugin-update colspanchange" colspan="%d"><div class="update-message notice inline notice-warning notice-alt"><p>%s</p></div></td></tr>',
			esc_attr( $cols ),
			wp_kses_post(
				sprintf(
					/* Translators: 1: add-on name 2: admin URL to license page */
					__( 'There might be a new version of %1$s. Please <strong>provide a valid license key</strong> in order to receive updates and support <a href="%2$s">on this page</a>.', 'advanced-ads' ),
					$plugin_data['Title'],
					License_Utils::admin_screen_url()
				)
			)
		);
	}
}
