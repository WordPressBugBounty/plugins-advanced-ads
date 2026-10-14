<?php
/**
 * Privacy module frontend.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

namespace AdvancedAds\Modules\Privacy;

use AdvancedAds\Abstracts\Ad;
use AdvancedAds\Framework\Interfaces\Integration_Interface;
use AdvancedAds\Utilities\Conditional;

defined( 'ABSPATH' ) || exit;

/**
 * Frontend class.
 */
class Frontend implements Integration_Interface {

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_filter( 'advanced-ads-activate-advanced-js', '__return_true' );
		add_filter( 'advanced-ads-can-display-ad', [ $this, 'can_display_by_consent' ], 10, 3 );
		add_filter( 'advanced-ads-ad-output', [ $this, 'ad_output' ], 10, 2 );
	}

	/**
	 * Check if ad can be displayed based on user's consent.
	 *
	 * @param bool                 $can_display   Whether to display this ad.
	 * @param Ad                   $ad            Ad instance.
	 * @param array<string, mixed> $check_options Additional options passed to can_display.
	 *
	 * @return bool
	 */
	public function can_display_by_consent( $can_display, Ad $ad, $check_options ) {
		// Early bail!!
		if ( ! $can_display ) {
			return $can_display;
		}

		if (
			$check_options['passive_cache_busting'] ||
			! empty( $ad->get_prop( 'privacy.ignore-consent' ) )
		) {
			return true;
		}

		$consent_method = wp_advads_get_setting( 'consent-method' ) ?? '';

		// If the consent method is set to cookie and the ad type does not need consent.
		if ( 'custom' === $consent_method && ! Utils::ad_type_needs_consent( $ad->get_type() ) ) {
			return true;
		}

		// If method is iab_tcf_20, always set to true, JS needs to decide whether to display ad or not.
		if ( 'iab_tcf_20' === $consent_method ) {
			return true;
		}

		// Either personalized or non-personalized ad will be shown.
		if ( $ad->is_type( 'adsense' ) && wp_advads_get_setting( 'show-non-personalized-adsense' ) ) {
			return true;
		}

		return Utils::parse_state() !== 'unknown';
	}

	/**
	 * If this ad is not image or dummy base64_encode the text.
	 *
	 * @param string $output The output string.
	 * @param Ad     $ad     Ad instance.
	 *
	 * @return string
	 */
	public function ad_output( $output, Ad $ad ) {
		$consent_method = wp_advads_get_setting( 'consent-method' ) ?? '';
		if ( 'iab_tcf_20' !== $consent_method ) {
			return $output;
		}

		if (
			Conditional::is_amp() ||
			! Utils::ad_type_needs_consent( $ad->get_type() ) ||
			( ! $ad->is_type( 'adsense' ) && $ad->get_prop( 'privacy.ignore-consent' ) )
		) {
			return $output;
		}

		return Utils::encode_ad( $output, $ad );
	}
}
