<?php // phpcs:ignoreFile
/**
 * Handle add-on licenses
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.x.x
 */

use AdvancedAds\Constants;
use AdvancedAds\Utilities\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Handle add-on licenses
 */
class Advanced_Ads_Admin_Licenses {
	/**
	 * Advanced_Ads_Admin_Licenses constructor.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', [ $this, 'wp_plugins_loaded' ] );

		// todo: check if this is loaded late enough and all add-ons are registered already.
		add_filter( 'upgrader_pre_download', [ $this, 'addon_upgrade_filter' ], 10, 3 );
	}

	/**
	 * Actions and filter available after all plugins are initialized
	 */
	public function wp_plugins_loaded() {
		add_action( 'http_api_debug', [ $this, 'update_license_after_version_info' ], 10, 5 );
	}

	/**
	 * Return an instance of this class.
	 *
	 * @return self   object    A single instance of this class.
	 */
	public static function get_instance() {
		static $instance;

		// If the single instance hasn't been set, set it now.
		if ( null === $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Get license keys for all add-ons
	 *
	 * @return string[]
	 */
	public function get_licenses() {
		return \AdvancedAds\License\License::get_addon_key_map();
	}

	/**
	 * Deactivate license key
	 *
	 * @deprecated 2.0.21 Use {@see \AdvancedAds\License\License::deactivate_license_for_addon()} instead.
	 *
	 * @param string $addon        Add-on identifier.
	 * @param string $plugin_name  Name of the add-on.
	 * @param string $options_slug Slug of the option in the database.
	 *
	 * @return true|int|string
	 * @since 1.6.11
	 */
	public function deactivate_license( $addon = '', $plugin_name = '', $options_slug = '' ) {
		_deprecated_function(
			__METHOD__,
			'2.0.21',
			'\AdvancedAds\License\License::deactivate_license_for_addon()'
		);

		unset( $plugin_name, $options_slug );

		return \AdvancedAds\License\License::deactivate_license_for_addon( (string) $addon );
	}

	/**
	 * Save license keys for all add-ons
	 *
	 * @param array $licenses licenses.
	 */
	public function save_licenses( $licenses = [] ) {
		if ( ! is_array( $licenses ) ) {
			$licenses = [];
		}
		\AdvancedAds\License\License::persist_addon_key_map( $licenses );
	}

	/**
	 * Get license status of an add-on
	 *
	 * @param string $slug slug of the add-on.
	 *
	 * @return string|false license status, "valid", "invalid" or false if option doesn't exist.
	 */
	public function get_license_status( $slug = '' ) {
		return \AdvancedAds\License\License::get_mirror_status_for_options_slug( $slug );
	}

	/**
	 * If two or more add-ons use the same valid license this is probably an all-access customer
	 *
	 * @return bool
	 */
	public function get_probably_all_access() {
		$valid = array_filter(
			$this->get_licenses(),
			function ( $key ) {
				return $this->get_license_status( ADVADS_SLUG . '-' . $key );
			},
			ARRAY_FILTER_USE_KEY
		);

		return [] !== $valid && max( array_count_values( $valid ) ) > 1;
	}

	/**
	 * Return the licence expiry time if it is equal for more than one add-on. That indicates it is likely an All Access license
	 *
	 * @return string|null
	 */
	public function get_probably_all_access_expiry() {
		/**
		 * Get expiry dates of all add-ons.
		 *
		 * @param string $key Add-on key.
		 *
		 * @return string|false the expiration date or false.
		 */
		$expiry_counts = array_count_values(
			array_map(
				function ( $key ) {
					return $this->get_license_expires( ADVADS_SLUG . '-' . $key );
				},
				array_keys( array_filter( $this->get_licenses() ) )
			)
		);

		/**
		 * Remove all licenses that are used only once.
		 *
		 * @param int $count the count from array_count_values_above
		 *
		 * @return bool whether the count is greater 1
		 */
		$all_access = array_filter(
			$expiry_counts,
			function ( $count ) {
				return $count > 1;
			}
		);

		// if there is an item in $all_access we can assume this is from All Access and return the expiry date.
		return empty( $all_access ) ? null : key( $all_access );
	}

	/**
	 * Get license expired value of an add-on
	 *
	 * @param string $slug slug of the add-on.
	 *
	 * @return string $date expiry date of an add-on, empty string if no option exists
	 */
	public function get_license_expires( $slug = '' ) {
		return \AdvancedAds\License\License::get_mirror_expires_for_options_slug( $slug );
	}

	/**
	 * Add custom messages to plugin updater
	 *
	 * @param bool        $reply   Whether to bail without returning the package. Default false.
	 * @param string      $package The package file name.
	 * @param WP_Upgrader $updater The WP_Upgrader instance.
	 *
	 * @return string
	 *
	 * @todo check if this is still working.
	 */
	public function addon_upgrade_filter( $reply, $package, $updater ) {
		$key   = null;
		$value = null;

		if ( isset( $updater->skin->plugin ) ) {
			$key   = 'path';
			$value = $updater->skin->plugin;
		} elseif ( isset( $updater->skin->plugin_info['Name'] ) ) {
			$key   = 'name';
			$value = $updater->skin->plugin_info['Name'];
		}

		$add_on = $this->get_installed_add_on_by_key( $key, $value );
		if ( ! $add_on || ! isset( $add_on['path'] ) ) {
			return $reply;
		}

		$plugin_file = plugin_basename( $add_on['path'] );
		if ( wp_doing_ajax() ) {
			$update_link = wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' ) . $plugin_file, 'upgrade-plugin_' . $plugin_file );
			/* translators: %s plugin update link */
			$updater->strings['download_failed'] = sprintf( __( 'Download failed. <a href="%s">Click here to try another method</a>.', 'advanced-ads' ), $update_link );
		} else {
			/* translators: %s download failed knowledgebase link */
			$updater->strings['download_failed'] = sprintf( __( 'Download failed. <a href="%s" target="_blank">Click here to learn why</a>.', 'advanced-ads' ), 'https://wpadvancedads.com/manual/download-failed-updating-add-ons/#utm_source=advanced-ads&utm_medium=link&utm_campaign=download-failed' );
		}

		return $reply;
	}

	/**
	 * Search if a name is in the add-on array and return the add-on data of it
	 *
	 * @param string $key   key to search for.
	 * @param string $value value to search for.
	 *
	 * @return  array    array with the add-on data
	 */
	private function get_installed_add_on_by_key( $key, $value ) {
		// Early bail!!
		if ( empty( $key ) || empty( $value ) ) {
			return null;
		}

		$add_ons = Data::get_addons();
		if ( is_array( $add_ons ) ) {
			foreach ( $add_ons as $add_on ) {
				if ( $add_on[ $key ] === $value ) {
					return $add_on;
				}
			}
		}

		return null;
	}

	/**
	 * Check if any license is valid
	 * can be used to display information for any Pro user only, like link to direct support
	 */
	public static function any_license_valid() {
		$add_ons = Data::get_addons();
		if ( [] === $add_ons ) {
			return false;
		}

		foreach ( $add_ons as $_add_on ) {
			if ( 'slider-ads' === $_add_on['id'] ) {
				continue;
			}

			$status = self::get_instance()->get_license_status( $_add_on['options_slug'] );

			// check expiry date.
			$expiry_date = self::get_instance()->get_license_expires( $_add_on['options_slug'] );

			if (
				( $expiry_date && strtotime( $expiry_date ) > time() )
				|| 'valid' === $status || 'lifetime' === $expiry_date
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Update the license status based on information retrieved from the version info check
	 *
	 * @param array|WP_Error $response    HTTP response or WP_Error object.
	 * @param string         $context     Context under which the hook is fired.
	 * @param string         $http        HTTP transport used.
	 * @param array          $parsed_args HTTP request arguments.
	 * @param string         $url         The request URL.
	 * @return array|WP_Error
	 */
	public function update_license_after_version_info( $response, $context, $http, $parsed_args, $url ) {
		// Early bail!!
		if (
			Constants::API_ENDPOINT !== $url
			|| (
				empty( $parsed_args['body']['edd_action'] )
				|| 'get_version' !== $parsed_args['body']['edd_action']
			)
			|| is_wp_error( $response )
		) {
			return $response;
		}

		$params = json_decode( wp_remote_retrieve_body( $response ) );
		if ( empty( $params->name ) ) {
			return $response;
		}

		$new_license_status = null;
		$new_expiry_date    = null;

		// Some of the conditions could happen at the same time,
		// though due to different conditions in EDD we are safer to have multiple checks.
		if ( isset( $params->valid_until ) ) {
			if ( 'invalid' === $params->valid_until ) {
				$new_license_status = 'invalid';
			}
			if ( 'lifetime' === $params->valid_until ) {
				$new_license_status = 'valid';
				$new_expiry_date    = 'lifetime';
			}

			if ( is_int( $params->valid_until ) ) {
				$new_expiry_date = (int) $params->valid_until;
				if ( time() < $params->valid_until ) {
					$new_license_status = 'valid';
				}
			}
		} elseif ( empty( $params->download_link ) || empty( $params->package ) || isset( $params->msg ) ) {
			// If either of these two parameters is missing then the user does not have a valid license according to our store
			// If there is a "msg" parameter then the license did also not work for another reason.
			$new_license_status = 'invalid';
		}

		if ( ! $new_license_status && ! $new_expiry_date ) {
			return $response;
		}

		if ( \AdvancedAds\License\License::is_flat_map_retired() ) {
			return $response;
		}

		$add_ons = Data::get_addons();

		// Look for the add-on with the appropriate license key.
		foreach ( $add_ons as $_add_on ) {
			if ( ! isset( $_add_on['name'] ) || $params->name !== $_add_on['name'] ) {
				continue;
			}

			$options_slug = $_add_on['options_slug'];

			if ( $new_license_status ) {
				update_option( $options_slug . '-license-status', $new_license_status, false );
			}

			if ( $new_expiry_date ) {
				if ( 'lifetime' !== $new_expiry_date ) {
					$new_expiry_date = gmdate( 'Y-m-d 23:59:49', $new_expiry_date );
				}
				update_option( $options_slug . '-license-expires', $new_expiry_date, false );
			}

			// Return with the first match since there should only be one plugin per name.
			return $response;
		}

		return $response;
	}
}
