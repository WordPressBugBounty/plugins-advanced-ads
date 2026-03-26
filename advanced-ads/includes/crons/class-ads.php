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

defined( 'ABSPATH' ) || exit;

/**
 * Crons Ads.
 */
class Ads implements Integration_Interface {

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

		$args   = [ $ad->get_id() ];
		$group  = 'advanced_ads';
		$expiry = $ad->get_expiry_date();

		as_unschedule_action(
			Constants::CRON_JOB_AD_EXPIRATION,
			$args,
			$group
		);

		if ( 0 === $expiry ) {
			delete_post_meta( $ad->get_id(), Constants::AD_META_EXPIRATION_TIME );
			return;
		}

		$datetime = ( new DateTimeImmutable() )->setTimestamp( $expiry );

		update_post_meta(
			$ad->get_id(),
			Constants::AD_META_EXPIRATION_TIME,
			$datetime->format( 'Y-m-d H:i:s' )
		);

		// Schedule new expiration action.
		as_schedule_single_action(
			$expiry,
			Constants::CRON_JOB_AD_EXPIRATION,
			$args,
			$group
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

		kses_remove_filters();

		wp_update_post(
			[
				'ID'          => $post_id,
				'post_status' => Constants::AD_STATUS_EXPIRED,
			]
		);

		kses_init_filters();
	}
}
