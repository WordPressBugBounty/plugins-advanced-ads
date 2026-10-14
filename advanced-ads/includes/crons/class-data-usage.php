<?php
/**
 * Anonymous usage-data cron via Action Scheduler.
 *
 * @package AdvancedAds
 */

namespace AdvancedAds\Crons;

use AdvancedAds\Constants;
use AdvancedAds\Framework\Interfaces\Integration_Interface;
use AdvancedAds\License\License_Shop_Client;

defined( 'ABSPATH' ) || exit;

/**
 * Schedules and sends anonymous usage data to the shop REST API.
 */
class Data_Usage implements Integration_Interface {

	/**
	 * Shared-secret header (same as shop feedback REST).
	 *
	 * @var string
	 */
	const TOKEN_HEADER = 'X-AdvAds-Token';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( Constants::CRON_JOB_DATA_USAGE, [ $this, 'send' ] );
		add_action( 'update_option_' . Constants::OPTION_DATA_USAGE, [ $this, 'update_preferences' ], 10, 0 );
		add_action( 'add_option_' . Constants::OPTION_DATA_USAGE, [ $this, 'update_preferences' ], 10, 0 );
	}

	/**
	 * Reschedule when preferences change.
	 *
	 * @return void
	 */
	public function update_preferences(): void {
		self::schedule_job();
	}

	/**
	 * Unschedule all usage-data actions.
	 *
	 * @return void
	 */
	public static function unschedule_all(): void {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		as_unschedule_all_actions(
			Constants::CRON_JOB_DATA_USAGE,
			[],
			Constants::CRON_GROUP_ADVANCED_ADS
		);
	}

	/**
	 * Schedule recurring send from saved preferences.
	 *
	 * @return void
	 */
	public static function schedule_job(): void {
		if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}

		self::unschedule_all();

		$prefs = get_option( Constants::OPTION_DATA_USAGE, [] );
		if ( ! is_array( $prefs ) || empty( $prefs['enabled'] ) ) {
			return;
		}

		$freq     = ( $prefs['dataFreq'] ?? 'monthly' ) === 'weekly' ? 'weekly' : 'monthly';
		$interval = 'weekly' === $freq ? WEEK_IN_SECONDS : MONTH_IN_SECONDS;

		as_schedule_recurring_action(
			time() + MINUTE_IN_SECONDS,
			$interval,
			Constants::CRON_JOB_DATA_USAGE,
			[],
			Constants::CRON_GROUP_ADVANCED_ADS,
			true
		);
	}

	/**
	 * Cron callback: POST payload to shop.
	 *
	 * @return void
	 */
	public function send(): void {
		$prefs = get_option( Constants::OPTION_DATA_USAGE, [] );
		if ( ! is_array( $prefs ) || empty( $prefs['enabled'] ) ) {
			self::unschedule_all();
			return;
		}

		if ( ! defined( 'ADVADS_DEACTIVATION_FEEDBACK_TOKEN' ) ) {
			define( 'ADVADS_DEACTIVATION_FEEDBACK_TOKEN', 'advads-dfb-7c4e9a2b8f1d6e0c3a5b9d2e4f8a1c6b' );
		}

		$token = (string) ADVADS_DEACTIVATION_FEEDBACK_TOKEN;
		if ( '' === $token ) {
			return;
		}

		$payload = self::build_payload( $prefs );
		$url     = self::endpoint();

		$response = wp_remote_post(
			$url,
			License_Shop_Client::http_request_args(
				[
					'headers' => [
						'Content-Type'     => 'application/json; charset=utf-8',
						'user-agent'       => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
						self::TOKEN_HEADER => $token,
					],
					'body'    => wp_json_encode( $payload ),
				]
			)
		);

		if ( is_wp_error( $response ) ) {
			return;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return;
		}
	}

	/**
	 * Shop REST usage endpoint.
	 *
	 * @return string
	 */
	private static function endpoint(): string {
		return License_Shop_Client::shop_base_url() . '/wp-json/advanced-ads/v1/usage';
	}

	/**
	 * Build anonymous payload.
	 *
	 * @param array<string, mixed> $prefs Saved preferences.
	 * @return array<string, mixed>
	 */
	private static function build_payload( array $prefs ): array {
		$anonymize = ! empty( $prefs['anonymize'] );
		$site      = home_url( '/' );

		$ads = wp_count_posts( Constants::POST_TYPE_AD );
		$plc = wp_count_posts( Constants::POST_TYPE_PLACEMENT );

		$payload = [
			'site'          => $site,
			'anonymize'     => $anonymize,
			'dataFreq'      => ( $prefs['dataFreq'] ?? 'monthly' ) === 'weekly' ? 'weekly' : 'monthly',
			'pluginVersion' => defined( 'ADVADS_VERSION' ) ? ADVADS_VERSION : '',
			'counts'        => [
				'ads'        => isset( $ads->publish ) ? (int) $ads->publish : 0,
				'placements' => isset( $plc->publish ) ? (int) $plc->publish : 0,
				'groups'     => (int) wp_count_terms(
					[
						'taxonomy'   => Constants::TAXONOMY_GROUP,
						'hide_empty' => false,
					]
				),
			],
		];

		if ( ! empty( $prefs['inclEnv'] ) ) {
			$payload['env'] = [
				'wp'  => get_bloginfo( 'version' ),
				'php' => PHP_VERSION,
			];
		}

		if ( ! empty( $prefs['inclPlugins'] ) ) {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$payload['plugins'] = array_values( (array) get_option( 'active_plugins', [] ) );
			$payload['theme']   = get_stylesheet();
		}

		/**
		 * Filter usage-data payload before send.
		 *
		 * @param array $payload Payload.
		 * @param array $prefs   Preferences.
		 */
		return (array) apply_filters( 'advanced-ads-data-usage-payload', $payload, $prefs );
	}
}
