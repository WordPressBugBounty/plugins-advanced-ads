<?php
/**
 * Ads.txt utility helpers.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

namespace AdvancedAds\Modules\AdsTxt;

defined( 'ABSPATH' ) || exit;

/**
 * User interface for managing the 'ads.txt' file.
 */
class Utils {
	/**
	 * Last redirect location collected during HTTP requests.
	 *
	 * @var string|null
	 */
	private static $location;

	/**
	 * Get Adsense data.
	 *
	 * @return string
	 */
	public static function get_adsense_data() {
		$adsense_id = trim( wp_advads_get_setting( 'adsense-id' ) );
		if ( ! $adsense_id ) {
			return '';
		}

		return implode(
			', ',
			[
				'domain'                  => 'google.com',
				'account_id'              => $adsense_id,
				'account_type'            => 'DIRECT',
				'certification_authority' => 'f08c47fec0942fa0',
			]
		);
	}

	/**
	 * Get file info.
	 *
	 * @param string $url Url to retrieve the file.
	 * @return array{exists: bool, is_third_party: bool}|\WP_Error An array containing 'exists', 'is_third_party'.
	 *                        A WP_Error upon error.
	 */
	public static function get_file_info( $url = null ) {
		$url = $url ? $url : home_url( '/' );

		// Disable ssl verification to prevent errors on servers that are not properly configured with its https certificates.
		/** This filter is documented in wp-includes/class-wp-http-streams.php */
		$sslverify = apply_filters( 'https_local_ssl_verify', false );
		$response  = wp_remote_get(
			trailingslashit( $url ) . 'ads.txt',
			[
				'timeout'   => 3,
				'sslverify' => $sslverify,
				'headers'   => [
					'Cache-Control' => 'no-cache',
				],
			]
		);

		// Bail before reading anything off the response.
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code         = wp_remote_retrieve_response_code( $response );
		$content      = (string) wp_remote_retrieve_body( $response );
		$content_type = wp_remote_retrieve_header( $response, 'content-type' );

		// Some transports hand back every value of a repeated header.
		if ( is_array( $content_type ) ) {
			$content_type = implode( ', ', $content_type );
		}

		$file_exists   = 404 !== $code && false !== stripos( (string) $content_type, 'text/plain' );
		$header_exists = false !== strpos( $content, Frontend::TOP );

		return [
			'exists'         => $file_exists && $header_exists,
			'is_third_party' => $file_exists && ! $header_exists,
		];
	}

	/**
	 * Check if the site is in a subdirectory, for example 'http://one.two/three'.
	 *
	 * @return bool
	 */
	public static function is_multisite_subdomain() {
		if ( ! function_exists( 'is_subdomain_install' ) ) {
			return true;
		}

		return is_subdomain_install();
	}
}
