<?php
/**
 * HTTP client for license exchange API.
 *
 * @package AdvancedAds
 * @since   2.0.9
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

namespace AdvancedAds\License;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Exchange legacy keys or one-time tokens for rich license records.
 */
final class License_Exchange {

	/**
	 * POST legacy map; return rich list or WP_Error.
	 *
	 * @param array<string, string> $addon_id_to_key Legacy map (addon id => license key).
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	public static function request( array $addon_id_to_key ) {
		$license_key = is_array( $addon_id_to_key ) ? ( array_values( $addon_id_to_key )[0] ?? null ) : null;

		if ( null === $license_key || '' === trim( (string) $license_key ) ) {
			return new WP_Error(
				'advanced_ads_license_exchange_empty',
				__( 'No license keys to exchange.', 'advanced-ads' )
			);
		}

		License_Shop_Client::add_local_development_http_filter();

		$response = wp_remote_post(
			self::exchange_endpoint(),
			License_Shop_Client::http_request_args(
				[
					'body' => wp_json_encode(
						[
							'site'    => home_url(),
							'license' => $license_key,
						]
					),
				]
			)
		);

		License_Shop_Client::remove_local_development_http_filter();

		return self::parse_exchange_response( $response );
	}

	/**
	 * Exchange a one-time shop token for rich license rows.
	 *
	 * @param string $token One-time token from shop redirect.
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	public static function request_by_token( string $token ) {
		$token = sanitize_text_field( $token );
		if ( '' === $token ) {
			return new WP_Error(
				'advanced_ads_license_exchange_token',
				__( 'Missing exchange token.', 'advanced-ads' )
			);
		}

		License_Shop_Client::add_local_development_http_filter();

		$response = wp_remote_post(
			self::exchange_endpoint(),
			License_Shop_Client::http_request_args(
				[
					'body' => wp_json_encode(
						[
							'token' => $token,
							'site'  => site_url(),
						]
					),
				]
			)
		);

		License_Shop_Client::remove_local_development_http_filter();

		return self::parse_exchange_response( $response );
	}

	/**
	 * Activate a license on the shop for this site.
	 *
	 * @param string $license_key License key.
	 * @param int    $license_id  EDD SL license post ID.
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	public static function request_activate( string $license_key, int $license_id = 0 ) {
		return License::request_shop_activate( $license_key, $license_id );
	}

	/**
	 * Shop REST exchange endpoint URL.
	 *
	 * @return string
	 */
	private static function exchange_endpoint(): string {
		$base_url = defined( 'AA_SHOP_URL' ) ? AA_SHOP_URL : 'https://wpadvancedads.com';

		return untrailingslashit( esc_url_raw( $base_url ) ) . '/wp-json/advanced-ads/v2/license/exchange';
	}

	/**
	 * Decode exchange HTTP response into license rows or WP_Error.
	 *
	 * @param array|\WP_Error $response wp_remote_post result.
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	private static function parse_exchange_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			$body     = json_decode( wp_remote_retrieve_body( $response ), true );
			$message  = is_array( $body ) ? (string) ( $body['message'] ?? '' ) : '';
			$api_code = is_array( $body ) ? (string) ( $body['code'] ?? '' ) : '';

			return new WP_Error(
				'' !== $api_code ? $api_code : 'advanced_ads_license_exchange_http',
				'' !== $message ? $message : __( 'License exchange request failed.', 'advanced-ads' ),
				[ 'status' => $code ]
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
}
