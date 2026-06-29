<?php
/**
 * Crons Ads using Action Scheduler.
 *
 * @package AdvancedAds
 */

namespace AdvancedAds\Crons;

use DateTimeImmutable;
use AdvancedAds\Constants;
use AdvancedAds\Abstracts\Ad;
use AdvancedAds\Framework\Interfaces\Integration_Interface;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Crons Ads.
 */
class Ads implements Integration_Interface {

	/**
	 * Post ID allowed to bypass caps during cron expiration update.
	 *
	 * @var int
	 */
	private $bypass_cap_post_id = 0;

	/**
	 * Hook into WordPress
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'advanced-ads-ad-pre-save', [ $this, 'save_expiration_date' ] );
		add_action( Constants::CRON_JOB_AD_EXPIRATION, [ $this, 'update_ad_status' ] );
	}

	/**
	 * Create Action Scheduler job and save into independent meta
	 *
	 * @param Ad $ad Ad instance.
	 *
	 * @return void
	 */
	public function save_expiration_date( Ad $ad ): void {
		$post_id = $ad->get_id();
		if ( ! $post_id || ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		$args   = [ $post_id ];
		$group  = 'advanced_ads';
		$expiry = (int) $ad->get_expiry_date( 'edit' );

		as_unschedule_all_actions(
			Constants::CRON_JOB_AD_EXPIRATION,
			$args,
			$group
		);

		if ( $expiry <= 0 ) {
			delete_post_meta( $post_id, Constants::AD_META_EXPIRATION_TIME );
			return;
		}

		$datetime = ( new DateTimeImmutable() )->setTimestamp( $expiry );

		update_post_meta(
			$post_id,
			Constants::AD_META_EXPIRATION_TIME,
			$datetime->format( 'Y-m-d H:i:s' )
		);

		if ( $expiry <= time() ) {
			$this->update_ad_status( $post_id );
			return;
		}

		as_schedule_single_action(
			$expiry,
			Constants::CRON_JOB_AD_EXPIRATION,
			$args,
			$group,
			true
		);
	}

	/**
	 * Update post status to expired
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	public function update_ad_status( $post_id ): void {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || Constants::POST_TYPE_AD !== $post->post_type ) {
			return;
		}

		if ( Constants::AD_STATUS_EXPIRED === $post->post_status || 'trash' === $post->post_status ) {
			return;
		}

		$this->bypass_cap_post_id = $post_id;
		add_filter( 'user_has_cap', [ $this, 'grant_edit_cap_for_expiration' ], 10, 3 );

		kses_remove_filters();

		wp_update_post(
			[
				'ID'          => $post_id,
				'post_status' => Constants::AD_STATUS_EXPIRED,
			]
		);

		kses_init_filters();

		remove_filter( 'user_has_cap', [ $this, 'grant_edit_cap_for_expiration' ], 10 );
		$this->bypass_cap_post_id = 0;
	}

	/**
	 * Allow Action Scheduler to expire ads when no user is logged in.
	 *
	 * @param array<string, bool> $allcaps All capabilities.
	 * @param array<int, string>  $caps    Required capabilities.
	 * @param array<int, mixed>   $args    Capability check args.
	 *
	 * @return array<string, bool>
	 */
	public function grant_edit_cap_for_expiration( array $allcaps, array $caps, array $args ): array {
		if ( ! $this->bypass_cap_post_id || empty( $args[0] ) || 'edit_post' !== $args[0] ) {
			return $allcaps;
		}

		if ( isset( $args[2] ) && (int) $args[2] === $this->bypass_cap_post_id ) {
			$allcaps['advanced_ads_edit_ads'] = true;
		}

		return $allcaps;
	}
}
