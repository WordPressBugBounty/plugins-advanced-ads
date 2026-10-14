<?php
/**
 * HTTP client for license shop REST (activate, validate, exchange).
 *
 * @package AdvancedAds
 * @since   2.0.9
 */

namespace AdvancedAds\License;

use AdvancedAds\Constants;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Shop REST calls.
 */
final class License_Shop_Client {

	/**
	 * Absolute shop origin (no trailing slash) — `AA_SHOP_URL` in wp-config.php points it at another shop for development.
	 *
	 * @return string
	 */
	public static function shop_base_url(): string {
		$base = defined( 'AA_SHOP_URL' ) ? (string) AA_SHOP_URL : 'https://wpadvancedads.com';

		return untrailingslashit( $base );
	}

	/**
	 * Shop REST license endpoint URL for an action (activate|deactivate|validate|exchange).
	 *
	 * @param string $action Endpoint action segment.
	 * @return string
	 */
	public static function endpoint( string $action ): string {
		return self::shop_base_url() . '/wp-json/advanced-ads/v2/license/' . ltrim( $action, '/' );
	}

	/**
	 * Shop hosts from {@see self::shop_base_url()} and {@see Constants::API_ENDPOINT}.
	 *
	 * @return string[]
	 */
	public static function get_configured_api_hosts(): array {
		return array_values(
			array_unique(
				array_filter(
					[
						wp_parse_url( self::shop_base_url(), PHP_URL_HOST ),
						wp_parse_url( Constants::API_ENDPOINT, PHP_URL_HOST ),
					]
				)
			)
		);
	}

	/**
	 * HTTP args for server-side shop REST calls.
	 *
	 * @param array<string, mixed> $args Request args to merge.
	 * @return array<string, mixed>
	 */
	public static function http_request_args( array $args = [] ): array {
		return array_merge(
			[
				'timeout' => 15,
				'headers' => [ 'Content-Type' => 'application/json; charset=utf-8' ],
			],
			$args
		);
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

		$response = self::post_json( self::endpoint( 'activate' ), $payload );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			$body     = json_decode( wp_remote_retrieve_body( $response ), true );
			$api_code = is_array( $body ) ? (string) ( $body['code'] ?? '' ) : '';
			if ( $license_id > 0 && 403 === $code && 'identity_mismatch' === $api_code ) {
				return self::request_activate( $license_key, $site, 0 );
			}

			return self::http_error_from_response(
				$response,
				$code,
				'advanced_ads_license_activate_http',
				__( 'Failed to activate license.', 'advanced-ads' ),
				false
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

		$response = self::post_json(
			self::endpoint( 'validate' ),
			[
				'license' => $license_key,
				'site'    => License::get_site_hostname(),
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return self::http_error_from_response(
				$response,
				$code,
				'advanced_ads_license_validate_http',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Could not refresh license download URLs from the shop (HTTP %d).', 'advanced-ads' ),
					$code
				),
				true
			);
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return new WP_Error(
				'advanced_ads_license_validate_parse',
				__( 'Invalid license validate response from the shop.', 'advanced-ads' )
			);
		}

		$rows = isset( $data[0] ) ? $data : ( isset( $data['licenses'] ) && is_array( $data['licenses'] ) ? $data['licenses'] : $data );

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
	 * POST JSON to a shop URL.
	 *
	 * @param string                $url            Request URL.
	 * @param array<string, mixed>  $payload        Request body.
	 * @param array<string, string> $extra_headers  Optional headers merged onto Content-Type.
	 * @return array<string, mixed>|\WP_Error wp_remote_post result.
	 */
	public static function post_json( string $url, array $payload, array $extra_headers = [] ) {
		return wp_remote_post(
			$url,
			self::http_request_args(
				[
					'headers' => array_merge(
						[ 'Content-Type' => 'application/json; charset=utf-8' ],
						$extra_headers
					),
					'body'    => wp_json_encode( $payload ),
				]
			)
		);
	}

	/**
	 * Exchange a legacy license key or one-time shop token for rich license rows.
	 *
	 * @param array<string, mixed> $payload Must include `license` or `token`; `site` defaults to current hostname.
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	public static function exchange( array $payload ) {
		$license = trim( (string) ( $payload['license'] ?? '' ) );
		$token   = sanitize_text_field( (string) ( $payload['token'] ?? '' ) );

		if ( '' === $license && '' === $token ) {
			return new WP_Error(
				isset( $payload['token'] ) ? 'advanced_ads_license_exchange_token' : 'advanced_ads_license_exchange_empty',
				isset( $payload['token'] )
					? __( 'Missing exchange token.', 'advanced-ads' )
					: __( 'No license keys to exchange.', 'advanced-ads' )
			);
		}

		$body = [
			'site' => trim( (string) ( $payload['site'] ?? License::get_site_hostname() ) ),
		];
		if ( '' !== $token ) {
			$body['token'] = $token;
		} else {
			$body['license'] = $license;
		}

		return self::parse_exchange_response(
			self::post_json( self::endpoint( 'exchange' ), $body )
		);
	}

	/**
	 * Decode exchange HTTP response into license rows or WP_Error.
	 *
	 * @param array<string, mixed>|\WP_Error $response wp_remote_post result.
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	private static function parse_exchange_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return self::http_error_from_response(
				$response,
				$code,
				'advanced_ads_license_exchange_http',
				__( 'License exchange request failed.', 'advanced-ads' ),
				true
			);
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return new WP_Error(
				'advanced_ads_license_exchange_parse',
				__( 'Invalid license exchange response.', 'advanced-ads' )
			);
		}

		return $data;
	}

	/**
	 * Build WP_Error from a non-2xx shop HTTP response body.
	 *
	 * @param array<string, mixed>|\WP_Error $response HTTP response.
	 * @param int                            $code HTTP status code.
	 * @param string                         $fallback_error_code WP_Error code when body has no code.
	 * @param string                         $fallback_message Fallback message when body has none.
	 * @param bool                           $prefer_api_code When true, use body.code as WP_Error code.
	 * @return WP_Error
	 */
	private static function http_error_from_response( $response, int $code, string $fallback_error_code, string $fallback_message, bool $prefer_api_code = false ): WP_Error {
		$body       = json_decode( wp_remote_retrieve_body( $response ), true );
		$message    = is_array( $body ) ? (string) ( $body['message'] ?? ( $prefer_api_code ? '' : ( $body['data']['message'] ?? '' ) ) ) : '';
		$error_code = $fallback_error_code;

		if ( $prefer_api_code && is_array( $body ) && ! empty( $body['code'] ) ) {
			$error_code = sanitize_key( (string) $body['code'] );
		}

		return new WP_Error(
			$error_code,
			'' !== $message ? $message : $fallback_message,
			[ 'status' => $code ]
		);
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
}
