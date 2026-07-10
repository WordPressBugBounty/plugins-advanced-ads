<?php
// phpcs:ignoreFile

/**
 * Alternative version installer.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.50.0
 */

namespace AdvancedAds\Admin;

use AdvancedAds\License\License_Shop_Client;
use stdClass;
use WP_Error;
use Plugin_Upgrader;
use Automatic_Upgrader_Skin;

defined( 'ABSPATH' ) || exit;

/**
 * Alternate plugin version installer
 */
class Plugin_Installer {
	/**
	 * The version to install
	 *
	 * @var string
	 */
	private $version;

	/**
	 * URL to the .zip archive for the desired version
	 *
	 * @var string
	 */
	private $package_url;

	/**
	 * The plugin name
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * The plugin slug
	 *
	 * @var string
	 */
	private $plugin_slug;

	/**
	 * Whether to replace an existing plugin directory on local zip install.
	 *
	 * @var bool
	 */
	private $replace_existing;

	/**
	 * Constructor
	 *
	 * @param string    $version          The version to install.
	 * @param string    $package_url      The url or local path to the .zip archive.
	 * @param bool|null $replace_existing Replace an existing plugin directory for local zip installs.
	 */
	public function __construct( $version, $package_url, $replace_existing = null ) {
		$this->version          = $version;
		$this->package_url      = $package_url;
		$this->plugin_name      = ADVADS_PLUGIN_BASENAME;
		$this->plugin_slug      = basename( ADVADS_FILE ) . '.php';
		$this->replace_existing = null !== $replace_existing ? (bool) $replace_existing : is_file( $package_url );
	}

	/**
	 * Apply package.
	 *
	 * Change the plugin data when WordPress checks for updates. This method
	 * modifies package data to update the plugin from a specific URL containing
	 * the version package.
	 */
	protected function apply_package() {
		$update_plugins = get_site_transient( 'update_plugins' );
		if ( ! is_object( $update_plugins ) ) {
			$update_plugins = new stdClass();
		}

		$plugin_info              = new stdClass();
		$plugin_info->new_version = $this->version;
		$plugin_info->slug        = $this->plugin_slug;
		$plugin_info->package     = $this->package_url;

		$update_plugins->response[ $this->plugin_name ] = $plugin_info;

		set_site_transient( 'update_plugins', $update_plugins );
	}

	/**
	 * Do the plugin update process
	 *
	 * @return array|bool|WP_Error
	 */
	public function install() {
		self::require_upgrader_dependencies();

		$this->apply_package();

		$upgrader_args = [
			'url'    => 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( $this->plugin_name ),
			'plugin' => $this->plugin_name,
			'nonce'  => 'upgrade-plugin_' . $this->plugin_name,
			'title'  => esc_html__( 'Rollback to Previous Version', 'advanced-ads' ),
		];

		$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin( $upgrader_args ) );

		return $upgrader->upgrade( $this->plugin_name );
	}

	/**
	 * Install a plugin from a remote or local package URL.
	 *
	 * @param string $package_url      Package URL or local zip path.
	 * @param bool   $replace_existing Overwrite an existing plugin directory.
	 * @return bool|array|WP_Error|null
	 */
	public static function install_from_url( string $package_url, bool $replace_existing = false ) {
		self::require_upgrader_dependencies();

		License_Shop_Client::add_local_development_http_filter();
		if ( ! License_Shop_Client::should_verify_ssl() ) {
			add_filter( 'https_ssl_verify', '__return_false' );
		}

		$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
		$result   = $upgrader->install(
			$package_url,
			$replace_existing ? [ 'overwrite_package' => true ] : []
		);

		if ( ! License_Shop_Client::should_verify_ssl() ) {
			remove_filter( 'https_ssl_verify', '__return_false' );
		}
		License_Shop_Client::remove_local_development_http_filter();

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
