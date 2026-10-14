<?php
/**
 * Plugin package installer — installs a package from a URL.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.50.0
 */

namespace AdvancedAds\Updater;

use WP_Error;
use Plugin_Upgrader;
use Automatic_Upgrader_Skin;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin package installer.
 */
class Installer {

	/**
	 * Install a plugin from a remote or local package URL.
	 *
	 * @param string $package_url      Package URL or local zip path.
	 * @param bool   $replace_existing Overwrite an existing plugin directory.
	 *
	 * @return bool|array<string, mixed>|\WP_Error|null
	 */
	public static function install_from_url( string $package_url, bool $replace_existing = false ) {
		// WordPress denies this capability when file changes are disabled and, on multisite, to everyone but super admins.
		if ( ! current_user_can( 'install_plugins' ) ) {
			return new WP_Error( 'advanced_ads_install_not_allowed', __( 'You are not allowed to install plugins on this site.', 'advanced-ads' ) );
		}

		self::require_upgrader_dependencies();

		$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
		$result   = $upgrader->install(
			$package_url,
			$replace_existing ? [ 'overwrite_package' => true ] : []
		);

		return $result;
	}

	/**
	 * Admin includes required by Plugin_Upgrader (not loaded during REST requests).
	 *
	 * @return void
	 */
	private static function require_upgrader_dependencies(): void {
		if ( ! function_exists( 'request_filesystem_credentials' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	}
}
