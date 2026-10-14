<?php
/**
 * Deactivation feedback — the popup on the Plugins screen and the AJAX handler that sends the feedback to the shop.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.47.0
 */

namespace AdvancedAds\Admin\Plugins;

use WP_Error;
use WP_User;
use Advanced_Ads;
use AdvancedAds\License\License_Shop_Client;
use AdvancedAds\Framework\Utilities\Params;
use AdvancedAds\Framework\Interfaces\Integration_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Deactivation feedback.
 */
class Deactivation_Feedback implements Integration_Interface {

	/**
	 * Shop REST path for deactivation feedback.
	 *
	 * @var string
	 */
	const FEEDBACK_REST_PATH = '/wp-json/advanced-ads/v1/deactivation-feedback';

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'wp_ajax_advads_send_feedback', [ $this, 'send_feedback' ] );
		add_action( 'admin_footer', [ $this, 'add_deactivation_popup' ] );
	}

	/**
	 * Display deactivation logic on plugins page
	 *
	 * @since 1.7.14
	 *
	 * @return void
	 */
	public function add_deactivation_popup(): void {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, [ 'plugins', 'plugins-network' ], true ) ) {
			return;
		}

		$from         = '';
		$email        = '';
		$current_user = wp_get_current_user();
		if ( $current_user instanceof WP_User ) {
			$from  = sprintf( '%1$s <%2$s>', $current_user->user_nicename, trim( $current_user->user_email ) );
			$email = $current_user->user_email;
		}

		include ADVADS_ABSPATH . 'views/admin/screens/feedback-disable.php';
	}

	/**
	 * Proxy deactivation feedback to the shop REST endpoint.
	 *
	 * @since 1.7.14
	 *
	 * @return void
	 */
	public function send_feedback(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
		}

		$data = Params::post( 'formdata' );
		if ( ! $data ) {
			wp_send_json_error( [ 'message' => 'Missing form data' ], 400 );
		}

		wp_parse_str( wp_unslash( $data ), $form );

		if ( ! wp_verify_nonce( $form['advanced_ads_disable_form_nonce'] ?? '', 'advanced_ads_disable_form' ) ) {
			wp_send_json_error( [ 'message' => 'Invalid nonce' ], 403 );
		}

		$email = trim( (string) ( $form['advanced_ads_disable_reply_email'] ?? '' ) );
		if ( '' === $email || ! is_email( $email ) ) {
			$current_user = wp_get_current_user();
			$email        = ( $current_user instanceof WP_User ) ? trim( (string) $current_user->user_email ) : '';
		}
		if ( '' === $email || ! is_email( $email ) ) {
			wp_send_json_error( [ 'message' => 'Invalid email' ], 400 );
		}

		$reason  = (string) ( $form['advanced_ads_disable_reason'] ?? '' );
		$message = '';
		if ( isset( $form['advanced_ads_disable_text'] ) && is_array( $form['advanced_ads_disable_text'] ) ) {
			$message = implode( "\n\r", $form['advanced_ads_disable_text'] );
		}

		if ( 'get help' === $reason ) {
			$message .= $this->get_help_suffix( $email, $form );
		}

		$result = $this->send_feedback_to_shop(
			[
				'reason'  => $reason,
				'message' => $message,
				'email'   => $email,
			]
		);

		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			wp_send_json_error(
				[ 'message' => $result->get_error_message() ],
				is_array( $data ) ? (int) ( $data['status'] ?? 500 ) : 500
			);
		}

		wp_send_json_success();
	}

	/**
	 * POST feedback to the shop REST endpoint.
	 *
	 * @param array<string, mixed> $fields Feedback fields (reason, message, email, …).
	 *
	 * @return true|WP_Error
	 */
	private function send_feedback_to_shop( array $fields ) {
		$options   = Advanced_Ads::get_instance()->internal_options();
		$installed = isset( $options['installed'] ) ? gmdate( 'd.m.Y', $options['installed'] ) : '';
		$payload   = array_merge(
			[
				'site_url'       => home_url(),
				'installed_at'   => $installed,
				'php_version'    => PHP_VERSION,
				'wp_version'     => get_bloginfo( 'version' ),
				'plugin_version' => defined( 'ADVADS_VERSION' ) ? ADVADS_VERSION : '',
				'locale'         => get_user_locale(),
			],
			$fields
		);
		$token     = defined( 'ADVADS_DEACTIVATION_FEEDBACK_TOKEN' )
			? (string) ADVADS_DEACTIVATION_FEEDBACK_TOKEN
			: '';
		$url       = License_Shop_Client::shop_base_url() . self::FEEDBACK_REST_PATH;

		$response = License_Shop_Client::post_json(
			$url,
			$payload,
			[ 'X-AdvAds-Token' => $token ]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( $code >= 200 && $code < 300 && ! empty( $body['success'] ) ) {
			return true;
		}

		$message = is_array( $body ) && ! empty( $body['message'] )
			? (string) $body['message']
			: __( 'Could not send feedback. Please try again or deactivate anyway.', 'advanced-ads' );

		return new WP_Error(
			'deactivation_feedback_failed',
			$message,
			[ 'status' => $code >= 400 ? $code : 500 ]
		);
	}

	/**
	 * German/English thank-you / help-on-the-way lines for “get help”.
	 *
	 * @param string               $email Reply email.
	 * @param array<string, mixed> $form  Parsed form.
	 *
	 * @return string
	 */
	private function get_help_suffix( string $email, array $form ): string {
		$is_german = (
			preg_match( '/\.de$/', $email ) ||
			'de_' === substr( get_locale(), 0, 3 ) ||
			'de_' === substr( get_user_locale(), 0, 3 )
		);

		if ( '' !== trim( $form['advanced_ads_disable_text'][0] ?? '' ) ) {
			return $is_german
				? "\n\n Hilfe ist auf dem Weg."
				: "\n\n Help is on its way.";
		}

		return $is_german
			? "\n\n Vielen Dank für das Feedback."
			: "\n\n Thank you for your feedback.";
	}
}
