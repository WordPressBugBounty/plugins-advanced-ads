<?php
/**
 * Conditional functions.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   2.0.0
 */

use AdvancedAds\Framework\Utilities\Params;

/**
 * Check if the current request is an AMP request.
 *
 * @return bool
 */
function advads_is_amp_request(): bool {
	$is_amp_endpoint = function_exists( 'is_amp_endpoint' ) && \is_amp_endpoint();
	$is_wp_amp       = function_exists( 'is_wp_amp' ) && \is_wp_amp();
	$is_ampforwp     = function_exists( 'ampforwp_is_amp_endpoint' ) && \ampforwp_is_amp_endpoint();
	$is_penci_amp    = function_exists( 'is_penci_amp' ) && \is_penci_amp();

	return $is_amp_endpoint || $is_wp_amp || $is_ampforwp || $is_penci_amp || Params::get( 'wpamp' );
}

/**
 * Are we currently on an AMP URL? Will always return `false` and show PHP Notice if called before the `wp` hook.
 *
 * @return bool true if amp url, false otherwise
 */
function advads_is_amp() {
	global $pagenow;

	$is_auth_page   = isset( $pagenow ) && in_array( $pagenow, [ 'wp-login.php', 'wp-signup.php', 'wp-activate.php' ], true );
	$is_api_request = ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST );

	if (
		is_admin() ||
		is_embed() ||
		is_feed() ||
		$is_auth_page ||
		$is_api_request
	) {
		return false;
	}

	if ( ! did_action( 'wp' ) ) {
		return false;
	}

	return advads_is_amp_request();
}

