<?php
/**
 * Admin-post handler for shop token exchange.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

namespace AdvancedAds\Admin;

use AdvancedAds\Framework\Interfaces\Integration_Interface;
use AdvancedAds\Framework\Utilities\Params;
use AdvancedAds\License\License;
use AdvancedAds\License\License_Exchange;
use AdvancedAds\License\License_Utils;
use AdvancedAds\Utilities\Conditional;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Exchanges one-time shop tokens server-side after connect or checkout.
 */
class License_Admin_Post implements Integration_Interface {

	public const ACTION = 'advanced-ads-license';

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'admin_post_' . self::ACTION, [ $this, 'handle' ] );
	}

	/**
	 * Exchange token, optionally activate on shop, save licenses, redirect.
	 *
	 * @return void
	 */
	public function handle(): void {
		if ( ! Conditional::user_can( 'advanced_ads_manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'advanced-ads' ), '', [ 'response' => 403 ] );
		}

		$token           = sanitize_text_field( (string) Params::request( 'token', '' ) );
		$license_id      = absint( Params::request( 'license_id', 0, FILTER_VALIDATE_INT ) );
		$purchase_id     = absint( Params::request( 'purchase_id', 0, FILTER_VALIDATE_INT ) );
		$checkout_intent = sanitize_key( (string) Params::request( 'checkout_intent', '' ) );

		if ( '' === $token ) {
			$this->redirect_with_error( 'invalid_token' );
		}

		$rows = License_Exchange::request_by_token( $token );
		if ( is_wp_error( $rows ) ) {
			$this->redirect_with_error( $this->map_error_code( $rows ) );
		}
		if ( empty( $rows ) ) {
			$this->redirect_with_error( 'no_licenses' );
		}

		$activating_key = $this->resolve_checkout_activating_key( $rows, $license_id );

		$saved = License::save_licenses(
			$rows,
			'' !== $activating_key,
			$activating_key,
			'',
			false,
			'',
			'',
			true
		);

		if ( is_wp_error( $saved ) ) {
			$this->redirect_with_activation_error( $saved );
		}

		$args = [];
		if ( $purchase_id > 0 ) {
			$args['purchase_id'] = $purchase_id;
		}
		if ( in_array( $checkout_intent, [ 'buy', 'upgrade', 'renew' ], true ) ) {
			$args['checkout_intent'] = $checkout_intent;
		}
		if ( $license_id > 0 ) {
			$args['license_id'] = $license_id;
		}

		wp_safe_redirect( $this->license_admin_url( $args ) );
		exit;
	}

	/**
	 * License key to activate after checkout when this site is not on the shop row yet.
	 *
	 * @param array<int, array<string, mixed>> $rows       Exchange rows.
	 * @param int                              $license_id EDD SL license post ID from redirect.
	 * @return string License key or empty.
	 */
	private function resolve_checkout_activating_key( array $rows, int $license_id ): string {
		if ( $license_id < 1 ) {
			return '';
		}

		$row = $this->resolve_checkout_row( $rows, $license_id );
		if ( null === $row ) {
			return '';
		}

		$key = (string) ( $row['licenseKey'] ?? '' );
		if ( '' === $key || $this->is_site_active_on_row( $row ) ) {
			return '';
		}

		return $key;
	}

	/**
	 * Pick license row for post-checkout activation.
	 *
	 * @param array<int, array<string, mixed>> $rows       Exchange rows.
	 * @param int                              $license_id EDD SL license post ID.
	 * @return array<string, mixed>|null
	 */
	private function resolve_checkout_row( array $rows, int $license_id ): ?array {
		foreach ( $rows as $row ) {
			if ( (int) ( $row['licenseId'] ?? 0 ) === $license_id ) {
				return $row;
			}
		}

		return $rows[0] ?? null;
	}

	/**
	 * Whether this site is already activated on the license row.
	 *
	 * @param array<string, mixed> $row License exchange row.
	 * @return bool
	 */
	private function is_site_active_on_row( array $row ): bool {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$host = is_string( $host ) ? preg_replace( '/^www\./', '', strtolower( $host ) ) : '';
		$sites = isset( $row['sitesActivated'] ) && is_array( $row['sitesActivated'] ) ? $row['sitesActivated'] : [];

		foreach ( $sites as $site ) {
			$domain = is_array( $site ) ? (string) ( $site['domain'] ?? '' ) : '';
			$parsed = wp_parse_url( $domain, PHP_URL_HOST ) ?: $domain;
			$parsed = preg_replace( '/^www\./', '', strtolower( (string) $parsed ) );
			if ( $parsed === $host ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Map exchange WP_Error to redirect error code.
	 *
	 * @param WP_Error $error Exchange error.
	 * @return string
	 */
	private function map_error_code( WP_Error $error ): string {
		$error_code = $error->get_error_code();

		if ( 'not_found' === $error_code ) {
			return 'no_licenses';
		}

		if ( 'invalid_token' === $error_code ) {
			return 'invalid_token';
		}

		$data   = $error->get_error_data();
		$status = is_array( $data ) ? (int) ( $data['status'] ?? 0 ) : 0;

		if ( 403 === $status ) {
			$message = strtolower( $error->get_error_message() );
			if (
				false !== strpos( $message, 'no license' )
				|| false !== strpos( $message, 'any licenses' )
			) {
				return 'no_licenses';
			}

			return 'invalid_token';
		}

		return 'network';
	}

	/**
	 * Redirect to license screen with exchange error query arg.
	 *
	 * @param string $code Error code slug.
	 * @return void
	 */
	private function redirect_with_error( string $code ): void {
		wp_safe_redirect(
			$this->license_admin_url( [ 'advads_exchange_error' => $code ] )
		);
		exit;
	}

	/**
	 * Redirect to license screen when shop activation failed during save.
	 *
	 * @param WP_Error $error Save or sync activation error.
	 * @return void
	 */
	private function redirect_with_activation_error( WP_Error $error ): void {
		$data   = $error->get_error_data();
		$status = is_array( $data ) ? (int) ( $data['status'] ?? 0 ) : 0;
		$code   = 'activate_failed';

		if ( 0 === $status && in_array( $error->get_error_code(), [ 'http_request_failed' ], true ) ) {
			$code = 'network';
		}

		$args = [ 'advads_activation_error' => $code ];
		$message = sanitize_text_field( $error->get_error_message() );
		if ( '' !== $message ) {
			$args['advads_activation_message'] = substr( $message, 0, 200 );
		}

		wp_safe_redirect( $this->license_admin_url( $args ) );
		exit;
	}

	/**
	 * License admin screen URL with optional query args.
	 *
	 * @param array<string, int|string> $query_args Query arguments.
	 * @return string
	 */
	private function license_admin_url( array $query_args = [] ): string {
		return License_Utils::admin_screen_url( $query_args );
	}
}
