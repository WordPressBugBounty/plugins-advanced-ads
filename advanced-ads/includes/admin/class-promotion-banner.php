<?php
/**
 * Dated promotional banner under the admin toolbar on Advanced Ads screens.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   2.0.30
 */

declare(strict_types=1);

namespace AdvancedAds\Admin;

defined( 'ABSPATH' ) || exit;

use AdvancedAds\Framework\Interfaces\Integration_Interface;
use AdvancedAds\Utilities\Conditional;

/**
 * Early-bird / VIP list promotion banner.
 */
class Promotion_Banner implements Integration_Interface {

	/**
	 * Inclusive start date (Y-m-d). Active from 00:00:00 Europe/Berlin that day.
	 */
	public const START_DATE = '2026-11-09';

	/**
	 * Inclusive end datetime (Y-m-d H:i:s) in Europe/Berlin (CET/CEST).
	 * Campaign ends Friday Nov 12, 15:00 CET.
	 */
	public const END_DATETIME = '2026-11-12 15:00:00';

	/**
	 * CTA destination (UTM params included).
	 */
	public const CTA_URL = 'https://go.wpadvancedads.com/bfcm26-early-bird-vip-opt-in?utm_source=advanced-ads&utm_medium=in-plugin&utm_campaign=advanced-ads-in-plugin-bfcm26-vip-invite';

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ], 20 );
		add_action( 'in_admin_header', [ $this, 'render' ], 20 );
	}

	/**
	 * Load admin-common CSS when the banner may render (needed on the WP
	 * Dashboard, where AA screens do not already enqueue it).
	 *
	 * @return void
	 */
	public function enqueue(): void {
		if ( ! $this->should_display() ) {
			return;
		}

		wp_advads()->registry->enqueue_style( 'admin-common' );
	}

	/**
	 * Whether the banner should display for the current time.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		$now = $this->now();

		return $now >= $this->start_timestamp() && $now <= $this->end_timestamp();
	}

	/**
	 * Output the banner on AA screens or the WP Dashboard inside the date window.
	 *
	 * Skips the React SPA (`advanced-ads-app`): Settings, Licenses, Tracking,
	 * Tools tabs, etc. own the full #wpcontent chrome and break if HTML is
	 * injected above them via in_admin_header.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! $this->should_display() ) {
			return;
		}

		$cta_url = self::CTA_URL;

		require ADVADS_ABSPATH . 'views/admin/promotion-banner.php';
	}

	/**
	 * Whether the banner may render on the current admin screen.
	 *
	 * @return bool
	 */
	public function should_display(): bool {
		if ( ! $this->is_active() ) {
			return false;
		}

		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		// WP Dashboard (same surface AdRotate uses for admin notices).
		if ( 'dashboard' === $screen->id ) {
			return true;
		}

		if ( ! Conditional::is_screen_advanced_ads() ) {
			return false;
		}

		// React SPA screen ID: advanced-ads_page_advanced-ads-app.
		return 'advanced-ads_page_advanced-ads-app' !== $screen->id;
	}

	/**
	 * Current Unix timestamp (UTC). Overridable in tests.
	 *
	 * @return int
	 */
	protected function now(): int {
		return time();
	}

	/**
	 * Campaign timezone (CET/CEST via Europe/Berlin).
	 *
	 * @return \DateTimeZone
	 */
	private function timezone(): \DateTimeZone {
		return new \DateTimeZone( 'Europe/Berlin' );
	}

	/**
	 * Unix timestamp for START_DATE 00:00:00 Europe/Berlin.
	 *
	 * @return int
	 */
	private function start_timestamp(): int {
		return ( new \DateTimeImmutable( self::START_DATE . ' 00:00:00', $this->timezone() ) )->getTimestamp();
	}

	/**
	 * Unix timestamp for END_DATETIME Europe/Berlin.
	 *
	 * @return int
	 */
	private function end_timestamp(): int {
		return ( new \DateTimeImmutable( self::END_DATETIME, $this->timezone() ) )->getTimestamp();
	}
}
