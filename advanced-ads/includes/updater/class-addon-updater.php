<?php
/**
 * Add-on updater — feeds shop update data to WordPress through the `Update URI` plugin header, so core does the version compare and fills the update transient.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   x.x.x
 */

namespace AdvancedAds\Updater;

use AdvancedAds\Constants;
use AdvancedAds\License\License;
use AdvancedAds\License\License_Shop_Client;
use AdvancedAds\License\License_Utils;
use AdvancedAds\Utilities\Addons;
use AdvancedAds\Framework\Interfaces\Integration_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Add-on updater.
 */
class Addon_Updater implements Integration_Interface {

	/**
	 * Site transient prefix for the shop answer of one add-on, followed by the shop product ID.
	 *
	 * @var string
	 */
	private const CACHE_PREFIX = 'advads_addon_version_';

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function hooks(): void {
		// Core calls this for every plugin whose `Update URI` header points to the shop host.
		add_filter( 'update_plugins_' . wp_parse_url( Constants::API_ENDPOINT, PHP_URL_HOST ), [ $this, 'check_update' ], 10, 3 );
		// Bridge for add-on versions released before they got the `Update URI` header: remove it in the second release of Advanced Ads after every add-on has shipped a version with the header.
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'add_updates_without_header' ] );
		add_filter( 'plugins_api', [ $this, 'plugin_information' ], 10, 3 );
		add_action( 'load-plugins.php', [ $this, 'add_license_messages' ] );
		add_action( 'deleted_site_transient', [ $this, 'clear_cache' ] );
		add_filter( 'upgrader_pre_download', [ $this, 'download_package' ], 10, 3 );
		add_filter( 'upgrader_post_install', [ $this, 'stop_when_replaced' ], 10, 3 );
	}

	/**
	 * Stop answering once Advanced Ads itself was replaced on disk by an update or a rollback — this code is still the previous version, and classes it has not loaded yet would come from the other one.
	 *
	 * @param bool|\WP_Error       $response   Installation response.
	 * @param array<string, mixed> $hook_extra Extra arguments passed to the upgrader.
	 * @param array<string, mixed> $result     Installation result data.
	 *
	 * @return bool|\WP_Error
	 */
	public function stop_when_replaced( $response, $hook_extra, $result ) {
		if ( dirname( ADVADS_PLUGIN_BASENAME ) === ( $result['destination_name'] ?? '' ) ) {
			remove_filter( 'update_plugins_' . wp_parse_url( Constants::API_ENDPOINT, PHP_URL_HOST ), [ $this, 'check_update' ] );
			remove_filter( 'pre_set_site_transient_update_plugins', [ $this, 'add_updates_without_header' ] );
			remove_action( 'deleted_site_transient', [ $this, 'clear_cache' ] );
		}

		return $response;
	}

	/**
	 * Provide the update data of one add-on.
	 *
	 * @param array|false          $update      The plugin update data, false when none was provided yet.
	 * @param array<string, mixed> $plugin_data Plugin headers.
	 * @param string               $plugin_file Plugin file relative to the plugins directory.
	 *
	 * @return array|false
	 */
	public function check_update( $update, $plugin_data, $plugin_file ) {
		$body = $this->get_addon_version( $plugin_data, $plugin_file );

		if ( null === $body ) {
			return $update;
		}

		return [
			'slug'         => dirname( $plugin_file ),
			'version'      => $body['new_version'],
			'url'          => $body['url'] ?? '',
			'package'      => $this->get_package( $body ),
			'tested'       => $body['tested'] ?? '',
			'requires'     => $body['requires'] ?? '',
			'requires_php' => $body['requires_php'] ?? '',
			'icons'        => (array) maybe_unserialize( $body['icons'] ?? [] ),
			'banners'      => (array) maybe_unserialize( $body['banners'] ?? [] ),
		];
	}

	/**
	 * Add the update data of add-ons without the `Update URI` header, which WordPress does not ask this updater about.
	 *
	 * @param mixed $updates The plugin update data WordPress is about to store.
	 *
	 * @return mixed
	 */
	public function add_updates_without_header( $updates ) {
		if ( ! is_object( $updates ) ) {
			return $updates;
		}

		if ( ! defined( 'ADVADS_ABSPATH' ) || ! is_readable( ADVADS_ABSPATH . 'includes/utilities/class-addons.php' ) ) {
			return $updates;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( get_plugins() as $plugin_file => $plugin_data ) {
			// Only Advanced Ads add-ons, and only those WordPress does not ask this updater about.
			if ( null === Addons::addon_id_for_text_domain( (string) ( $plugin_data['TextDomain'] ?? '' ) ) || ! empty( $plugin_data['UpdateURI'] ) ) {
				continue;
			}

			$update = $this->check_update( false, $plugin_data, $plugin_file );

			if ( ! $update ) {
				continue;
			}

			// The same fields WordPress adds for add-ons with the header.
			$update              = (object) $update;
			$update->id          = Constants::API_ENDPOINT;
			$update->plugin      = $plugin_file;
			$update->new_version = $update->version;

			unset( $updates->no_update[ $plugin_file ], $updates->response[ $plugin_file ] );

			if ( version_compare( $update->new_version, $plugin_data['Version'], '>' ) ) {
				$updates->response[ $plugin_file ] = $update;
			} else {
				$updates->no_update[ $plugin_file ] = $update;
			}
		}

		return $updates;
	}

	/**
	 * Feed the "View details" popup of an add-on from the shop answer.
	 *
	 * @param false|object|array $result The result so far, false to let WordPress.org answer.
	 * @param string             $action The type of information requested.
	 * @param object             $args   Plugin API arguments.
	 *
	 * @return false|object|array
	 */
	public function plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) ) {
			return $result;
		}

		foreach ( Addons::get_plugins() as $plugin_file => $plugin_data ) {
			if ( dirname( $plugin_file ) !== $args->slug ) {
				continue;
			}

			$body = $this->get_addon_version( $plugin_data, $plugin_file );

			if ( null === $body ) {
				return $result;
			}

			return (object) [
				'name'          => $body['name'] ?? $plugin_data['Name'],
				'slug'          => $args->slug,
				'version'       => $body['new_version'],
				'author'        => $plugin_data['Author'] ?? '',
				'homepage'      => $body['homepage'] ?? '',
				'last_updated'  => $body['last_updated'] ?? '',
				'tested'        => $body['tested'] ?? '',
				'requires'      => $body['requires'] ?? '',
				'requires_php'  => $body['requires_php'] ?? '',
				'sections'      => (array) maybe_unserialize( $body['sections'] ?? [] ),
				'banners'       => (array) maybe_unserialize( $body['banners'] ?? [] ),
				'download_link' => $this->get_package( $body ),
				'external'      => true,
			];
		}

		return $result;
	}

	/**
	 * Add the license message to the update row of add-ons that have an update but no package.
	 *
	 * @return void
	 */
	public function add_license_messages(): void {
		$updates = get_site_transient( 'update_plugins' );
		$host    = wp_parse_url( Constants::API_ENDPOINT, PHP_URL_HOST );

		foreach ( $updates->response ?? [] as $plugin_file => $update ) {
			if ( empty( $update->package ) && wp_parse_url( (string) ( $update->id ?? '' ), PHP_URL_HOST ) === $host ) {
				add_action( 'in_plugin_update_message-' . $plugin_file, [ $this, 'license_message' ] );
			}
		}
	}

	/**
	 * Tell the user how to get the update when the license does not allow the download.
	 *
	 * @param array<string, mixed> $plugin_data Plugin headers.
	 *
	 * @return void
	 */
	public function license_message( $plugin_data ): void {
		$expired = 'expired' === License::get_mirror_status_for_options_slug( (string) ( $plugin_data['TextDomain'] ?? '' ) );

		printf(
			' <a href="%s">%s</a>',
			esc_url( License_Utils::admin_screen_url() ),
			$expired
				? esc_html__( 'Renew your license to receive this update.', 'advanced-ads' )
				: esc_html__( 'Activate a valid license to receive this update.', 'advanced-ads' )
		);
	}

	/**
	 * Download a package from the shop, and pass on the reason when the shop refuses it — WordPress would only report the HTTP status.
	 *
	 * @param bool|string|\WP_Error $reply    False to let WordPress download the package.
	 * @param string                $package  The package URL.
	 * @param \WP_Upgrader          $upgrader The upgrader instance.
	 *
	 * @return bool|string|\WP_Error Path of the downloaded file, or the error.
	 */
	public function download_package( $reply, $package, $upgrader ) {
		if (
			false !== $reply
			|| ! is_string( $package )
			|| ! in_array( wp_parse_url( $package, PHP_URL_HOST ), License_Shop_Client::get_configured_api_hosts(), true )
		) {
			return $reply;
		}

		$upgrader->skin->feedback( 'downloading_package', $package );

		// The shop explains a refusal at the end of an error page, beyond the part WordPress keeps by default.
		$keep_error_page = static fn() => 10 * KB_IN_BYTES;
		add_filter( 'download_url_error_max_body_size', $keep_error_page );
		$file            = download_url( $package, 300 );
		remove_filter( 'download_url_error_max_body_size', $keep_error_page );

		if ( ! is_wp_error( $file ) ) {
			return $file;
		}

		$reason = preg_match( '#<div class="wp-die-message">(.*?)</div>#s', (string) ( $file->get_error_data()['body'] ?? '' ), $matches )
			? wp_kses( $matches[1], [ 'a' => [ 'href' => [] ] ] )
			: $file->get_error_message();

		return new \WP_Error( 'download_failed', $upgrader->strings['download_failed'] ?? __( 'Download failed.', 'advanced-ads' ), $reason );
	}

	/**
	 * Get the shop answer for an installed add-on.
	 *
	 * @param array<string, mixed> $plugin_data Plugin headers.
	 * @param string               $plugin_file Plugin file relative to the plugins directory.
	 *
	 * @return array<string, mixed>|null Null when the plugin is not a shop add-on or the shop knows no version.
	 */
	private function get_addon_version( $plugin_data, $plugin_file ): ?array {
		$addon_id = Addons::addon_id_for_text_domain( (string) ( $plugin_data['TextDomain'] ?? '' ) );

		if ( null === $addon_id ) {
			return null;
		}

		// The update data is shared by the whole network, so the licenses and the site URL of the main site count, whichever site runs the check.
		$switched = is_multisite() && ! is_main_site();
		if ( $switched ) {
			switch_to_blog( get_main_site_id() );
		}

		$body = $this->get_version(
			Addons::item_id( $addon_id ),
			trim( (string) ( License::get_addon_key_map()[ $addon_id ] ?? '' ) ),
			dirname( $plugin_file ),
			(string) ( $plugin_data['Version'] ?? '' )
		);

		if ( $switched ) {
			restore_current_blog();
		}

		return empty( $body['new_version'] ) ? null : $body;
	}

	/**
	 * Get the package link from a shop answer.
	 *
	 * @param array<string, mixed> $body Shop answer.
	 *
	 * @return string Empty when the license does not allow the download.
	 */
	private function get_package( array $body ): string {
		// The shop still sends a package link for an expired or not activated license, but refuses the download.
		if ( 'invalid' === ( $body['valid_until'] ?? '' ) ) {
			return '';
		}

		return (string) ( $body['package'] ?? $body['download_link'] ?? '' );
	}

	/**
	 * Ask the shop for the latest version of one add-on.
	 *
	 * @param int    $item_id Shop product ID.
	 * @param string $license License key, empty when the add-on has none.
	 * @param string $slug    Plugin folder name.
	 * @param string $version Installed version.
	 *
	 * @return array<string, mixed>|null The shop response, null when the shop could not be reached.
	 */
	private function get_version( int $item_id, string $license, string $slug, string $version ): ?array {
		$cached = get_site_transient( self::CACHE_PREFIX . $item_id );

		// "Check again" on the Updates screen asks for fresh data.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( is_array( $cached ) && md5( $license ) === ( $cached['license'] ?? '' ) && empty( $_GET['force-check'] ) ) {
			return $cached['body'];
		}

		$body = $this->request_version( $item_id, $license, $slug, $version );

		// The shop names no version for a key it does not accept for this add-on: ask without it, so the update still shows, without a package.
		if ( '' !== $license && is_array( $body ) && empty( $body['new_version'] ) ) {
			$body = $this->request_version( $item_id, '', $slug, $version );
		}

		// Shorter than the lifetime of the shop's package links (24 hours), and a short back-off when the shop fails.
		set_site_transient(
			self::CACHE_PREFIX . $item_id,
			[
				'license' => md5( $license ),
				'body'    => $body,
			],
			null === $body ? HOUR_IN_SECONDS : 12 * HOUR_IN_SECONDS
		);

		return $body;
	}

	/**
	 * Send one version request to the shop.
	 *
	 * @param int    $item_id Shop product ID.
	 * @param string $license License key, empty when the add-on has none.
	 * @param string $slug    Plugin folder name.
	 * @param string $version Installed version.
	 *
	 * @return array<string, mixed>|null The shop response, null when the shop could not be reached.
	 */
	private function request_version( int $item_id, string $license, string $slug, string $version ): ?array {
		$request = wp_remote_post(
			License_Shop_Client::shop_base_url() . '/license-api/',
			[
				'timeout' => 15,
				'body'    => [
					'edd_action'  => 'get_version',
					'license'     => $license,
					'item_id'     => $item_id,
					'version'     => $version,
					'slug'        => $slug,
					'url'         => home_url(),
					'php_version' => phpversion(),
					'wp_version'  => get_bloginfo( 'version' ),
				],
			]
		);

		$body = is_wp_error( $request ) || 200 !== wp_remote_retrieve_response_code( $request )
			? null
			: json_decode( wp_remote_retrieve_body( $request ), true );

		return is_array( $body ) ? $body : null;
	}

	/**
	 * Forget the shop answers when WordPress drops its own update data.
	 *
	 * @param string $transient Name of the deleted site transient.
	 *
	 * @return void
	 */
	public function clear_cache( $transient ): void {
		if ( 'update_plugins' !== $transient ) {
			return;
		}

		foreach ( Addons::known_addon_ids() as $addon_id ) {
			delete_site_transient( self::CACHE_PREFIX . Addons::item_id( $addon_id ) );
		}
	}
}
