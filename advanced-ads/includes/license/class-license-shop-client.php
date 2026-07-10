<?php
/**
 * HTTP client for license shop REST (activate, deactivate, validate).
 *
 * @package AdvancedAds
 * @since   2.0.9
 */

namespace AdvancedAds\License;

use AdvancedAds\Constants;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Shop REST calls and local/development HTTP bypass for `.test` hosts.
 */
final class License_Shop_Client {

	/**
	 * Shop REST activate endpoint URL.
	 *
	 * @return string
	 */
	public static function get_activate_endpoint(): string {
		$base = defined( 'AA_SHOP_URL' ) ? AA_SHOP_URL : 'https://wpadvancedads.com';

		return untrailingslashit( $base ) . '/wp-json/advanced-ads/v2/license/activate';
	}

	/**
	 * Shop REST deactivate endpoint URL.
	 *
	 * @return string
	 */
	public static function get_deactivate_endpoint(): string {
		$base = defined( 'AA_SHOP_URL' ) ? AA_SHOP_URL : 'https://wpadvancedads.com';

		return untrailingslashit( $base ) . '/wp-json/advanced-ads/v2/license/deactivate';
	}

	/**
	 * Shop REST validate endpoint URL (fresh package download URLs for this site).
	 *
	 * @return string
	 */
	public static function get_validate_endpoint(): string {
		$base = defined( 'AA_SHOP_URL' ) ? AA_SHOP_URL : 'https://wpadvancedads.com';

		return untrailingslashit( $base ) . '/wp-json/advanced-ads/v2/license/validate';
	}

	/**
	 * Whether the configured shop/API host is a local or development environment.
	 *
	 * Laragon, Valet, and similar setups use `.test` / `.local` hosts that resolve to
	 * 127.0.0.1. WordPress then rejects outbound requests via {@see wp_http_validate_url()}
	 * unless the host is treated as external.
	 *
	 * @return bool
	 */
	public static function uses_local_development_host(): bool {
		if ( defined( 'AA_SHOP_URL' ) && ! License_Utils::should_verify_ssl_for_url( AA_SHOP_URL ) ) {
			return true;
		}

		if ( ! License_Utils::should_verify_ssl_for_url( site_url() ) ) {
			return true;
		}

		return ! License_Utils::should_verify_ssl_for_url( Constants::API_ENDPOINT );
	}

	/**
	 * Mark the configured shop host as external for local/development HTTP requests.
	 *
	 * @param bool   $is_external Whether WordPress considers the host external.
	 * @param string $host        Request host.
	 * @param string $url         Request URL.
	 * @return bool
	 */
	public static function allow_local_development_http_request( $is_external, string $host, string $url ) {
		foreach ( self::get_configured_api_hosts() as $shop_host ) {
			if ( $host === $shop_host ) {
				return true;
			}
		}

		if ( self::uses_local_development_host() && ! License_Utils::should_verify_ssl_for_url( 'https://' . $host ) ) {
			return true;
		}

		return $is_external;
	}

	/**
	 * Register the local/development shop HTTP bypass for admin add-on updates.
	 *
	 * @return void
	 */
	public static function register_local_development_http_filters(): void {
		self::add_local_development_http_filter();
	}

	/**
	 * Shop hosts from {@see AA_SHOP_URL} and {@see Constants::API_ENDPOINT}.
	 *
	 * @return string[]
	 */
	public static function get_configured_api_hosts(): array {
		$hosts = [];

		if ( defined( 'AA_SHOP_URL' ) ) {
			$shop_host = wp_parse_url( AA_SHOP_URL, PHP_URL_HOST );
			if ( is_string( $shop_host ) && '' !== $shop_host ) {
				$hosts[] = $shop_host;
			}
		}

		$api_host = wp_parse_url( Constants::API_ENDPOINT, PHP_URL_HOST );
		if ( is_string( $api_host ) && '' !== $api_host ) {
			$hosts[] = $api_host;
		}

		return array_values( array_unique( $hosts ) );
	}

	/**
	 * HTTP timeout (seconds) for shop REST calls.
	 *
	 * @return int
	 */
	public static function http_timeout(): int {
		return 1200;
	}

	/**
	 * HTTP timeout (seconds) for signed package download URLs.
	 *
	 * @param string $download_url Package URL.
	 * @return int
	 */
	public static function package_download_timeout( string $download_url = '' ): int {
		unset( $download_url );

		return 1200;
	}

	/**
	 * Whether outbound HTTPS to the shop should verify certificates.
	 *
	 * @return bool
	 */
	public static function should_verify_ssl(): bool {
		if ( ! defined( 'AA_SHOP_URL' ) ) {
			return true;
		}

		return License_Utils::should_verify_ssl_for_url( AA_SHOP_URL );
	}

	/**
	 * Whether a package download URL targets the configured shop host.
	 *
	 * @param string $download_url Package download URL from the shop.
	 * @return bool
	 */
	public static function is_shop_download_url( string $download_url ): bool {
		$host = wp_parse_url( $download_url, PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) {
			return false;
		}

		foreach ( self::get_configured_api_hosts() as $shop_host ) {
			if ( $host === $shop_host ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Temporarily allow shop HTTP during a single license API or download request.
	 *
	 * @return void
	 */
	public static function add_local_development_http_filter(): void {
		if ( ! self::uses_local_development_host() ) {
			return;
		}

		add_filter( 'http_request_host_is_external', [ self::class, 'allow_local_development_http_request' ], 10, 3 );
	}

	/**
	 * Remove the temporary local/development shop HTTP bypass.
	 *
	 * @return void
	 */
	public static function remove_local_development_http_filter(): void {
		remove_filter( 'http_request_host_is_external', [ self::class, 'allow_local_development_http_request' ], 10 );
	}

	/**
	 * HTTP args for server-side shop REST calls.
	 *
	 * @param array<string, mixed> $args Request args to merge.
	 * @return array<string, mixed>
	 */
	public static function http_request_args( array $args = [] ): array {
		$merged = array_merge(
			[
				'timeout' => self::http_timeout(),
				'headers' => [ 'Content-Type' => 'application/json; charset=utf-8' ],
			],
			$args
		);

		if ( ! self::should_verify_ssl() ) {
			$merged['sslverify'] = false;
		}

		return $merged;
	}

	/**
	 * Deactivate a site on the shop REST (/license/deactivate).
	 *
	 * @param string $license_key License key string.
	 * @param string $site        Site hostname.
	 * @return array<int, array<string, mixed>>|WP_Error Rich list when shop returns one, else [].
	 */
	public static function request_deactivate( string $license_key, string $site ) {
		$license_key = trim( $license_key );
		$site        = trim( $site );

		if ( '' === $license_key || '' === $site ) {
			return new WP_Error(
				'advanced_ads_license_deactivate_invalid',
				__( 'Provide license and site.', 'advanced-ads' )
			);
		}

		self::add_local_development_http_filter();

		$response = wp_remote_post(
			self::get_deactivate_endpoint(),
			self::http_request_args(
				[
					'body' => wp_json_encode(
						[
							'license' => $license_key,
							'site'    => $site,
						]
					),
				]
			)
		);

		self::remove_local_development_http_filter();

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			$body    = json_decode( wp_remote_retrieve_body( $response ), true );
			$message = is_array( $body ) ? (string) ( $body['message'] ?? $body['data']['message'] ?? '' ) : '';

			return new WP_Error(
				'advanced_ads_license_deactivate_http',
				'' !== $message ? $message : __( 'Failed to deactivate license.', 'advanced-ads' ),
				[ 'status' => $code ]
			);
		}

		return self::parse_rich_response( wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Activate a site on the shop REST (/license/activate).
	 *
	 * @param string $license_key License key string.
	 * @param string $site        Site hostname.
	 * @param int    $license_id  Optional EDD SL license post ID.
	 * @return array<int, array<string, mixed>>|WP_Error Rich list when shop returns one, else [].
	 */
	public static function request_activate( string $license_key, string $site, int $license_id = 0 ) {
		$license_key = trim( $license_key );
		$site        = trim( $site );

		if ( '' === $license_key || '' === $site ) {
			return new WP_Error(
				'advanced_ads_license_activate_invalid',
				__( 'Provide license and site.', 'advanced-ads' )
			);
		}

		$payload = [
			'license' => $license_key,
			'site'    => $site,
		];
		if ( $license_id > 0 ) {
			$payload['license_id'] = $license_id;
		}

		self::add_local_development_http_filter();

		$response = wp_remote_post(
			self::get_activate_endpoint(),
			self::http_request_args(
				[
					'body' => wp_json_encode( $payload ),
				]
			)
		);

		self::remove_local_development_http_filter();

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			$body     = json_decode( wp_remote_retrieve_body( $response ), true );
			$api_code = is_array( $body ) ? (string) ( $body['code'] ?? '' ) : '';
			if ( $license_id > 0 && 403 === (int) $code && 'identity_mismatch' === $api_code ) {
				return self::request_activate( $license_key, $site, 0 );
			}

			$message = is_array( $body ) ? (string) ( $body['message'] ?? $body['data']['message'] ?? '' ) : '';

			return new WP_Error(
				'advanced_ads_license_activate_http',
				'' !== $message ? $message : __( 'Failed to activate license.', 'advanced-ads' ),
				[ 'status' => $code ]
			);
		}

		return self::parse_rich_response( wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Fetch one license row from the shop validate endpoint.
	 *
	 * @param array<string, mixed> $row License row containing licenseKey.
	 * @return array<string, mixed>|WP_Error Updated row or error.
	 */
	public static function fetch_license_row( array $row ) {
		$license_key = trim( (string) ( $row['licenseKey'] ?? '' ) );
		if ( '' === $license_key ) {
			return $row;
		}

		self::add_local_development_http_filter();

		$response = wp_remote_post(
			self::get_validate_endpoint(),
			self::http_request_args(
				[
					'body' => wp_json_encode(
						[
							'license' => $license_key,
							'site'    => site_url(),
						]
					),
				]
			)
		);

		self::remove_local_development_http_filter();

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return self::validate_http_error( $response, $code );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return new WP_Error(
				'advanced_ads_license_validate_parse',
				__( 'Invalid license validate response from the shop.', 'advanced-ads' )
			);
		}

		$rows = isset( $data[0] ) ? $data : ( isset( $data['licenses'] ) && is_array( $data['licenses'] ) ? $data['licenses'] : $data );
		if ( ! is_array( $rows ) ) {
			return new WP_Error(
				'advanced_ads_license_validate_parse',
				__( 'Invalid license validate response from the shop.', 'advanced-ads' )
			);
		}

		foreach ( $rows as $fresh ) {
			if ( ! is_array( $fresh ) ) {
				continue;
			}
			if ( (string) ( $fresh['licenseKey'] ?? '' ) === $license_key ) {
				return $fresh;
			}
		}

		if ( isset( $rows['licenseKey'] ) ) {
			return $rows;
		}

		return $row;
	}

	/**
	 * Whether a shop validate error means the license key no longer exists.
	 *
	 * @param WP_Error $error Error from {@see self::fetch_license_row()}.
	 * @return bool
	 */
	public static function is_validate_not_found_error( WP_Error $error ): bool {
		if ( 'not_found' === $error->get_error_code() ) {
			return true;
		}

		$data = $error->get_error_data();
		if ( is_array( $data ) && 404 === (int) ( $data['status'] ?? 0 ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Parse shop JSON body into a normalized rich license list.
	 *
	 * @param string $body Response body.
	 * @return array<int, array<string, mixed>>
	 */
	private static function parse_rich_response( string $body ): array {
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			return [];
		}

		if ( isset( $data[0] ) && is_array( $data[0] ) ) {
			return License::normalize_list( $data );
		}

		if ( isset( $data['licenseKey'] ) ) {
			return License::normalize_list( [ $data ] );
		}

		return [];
	}

	/**
	 * Build WP_Error from a non-200 shop validate HTTP response.
	 *
	 * @param array<string, mixed>|\WP_HTTP_Requests_Response $response HTTP response.
	 * @param int                                                $code     HTTP status code.
	 * @return WP_Error
	 */
	private static function validate_http_error( $response, int $code ): WP_Error {
		$body       = json_decode( wp_remote_retrieve_body( $response ), true );
		$message    = is_array( $body ) ? (string) ( $body['message'] ?? '' ) : '';
		$error_code = is_array( $body ) && ! empty( $body['code'] )
			? sanitize_key( (string) $body['code'] )
			: 'advanced_ads_license_validate_http';

		if ( '' === $message ) {
			$message = sprintf(
				/* translators: %d: HTTP status code */
				__( 'Could not refresh license download URLs from the shop (HTTP %d).', 'advanced-ads' ),
				$code
			);
		}

		return new WP_Error(
			$error_code,
			$message,
			[ 'status' => $code ]
		);
	}
}
