<?php
/**
 * AdSense module frontend
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

namespace AdvancedAds\Modules\AdSense;

use AdvancedAds\Framework\Interfaces\Integration_Interface;
use AdvancedAds\Modules\Privacy\Utils as PrivacyUtils;
use AdvancedAds\Utilities\Conditional;

/**
 * Class Frontend.
 */
class Frontend implements Integration_Interface {
	/**
	 * AdSense script URL.
	 */
	private const ADSENSE_SCRIPT = 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js';

	/**
	 * AMP auto ads component URL.
	 */
	private const AMP_AUTO_ADS_SCRIPT = 'https://cdn.ampproject.org/v0/amp-auto-ads-0.1.js';

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'wp_head', [ $this, 'inject_header' ], 20 );
		// Fires before cache-busting frontend is initialized and tracking method is set.
		add_action( 'wp', [ $this, 'inject_amp_code' ], 20 );
	}

	/**
	 * Print data in the head tag on the front end.
	 *
	 * @return void
	 */
	public function inject_header(): void {
		// Inject CSS to make AdSense background transparent.
		// Some themes not only get the background wrong, but also add some padding to ins element.
		if ( ! empty( wp_advads_get_setting( 'adsense-background' ) ) ) {
			echo '<style>ins.adsbygoogle { background-color: transparent; padding: 0; }</style>';
		}

		if ( Conditional::is_ad_disabled() || Conditional::is_amp() ) {
			return;
		}

		$this->print_non_personalized_ads_script();

		if ( ! apply_filters( 'advanced-ads-can-display-ads-in-header', true ) ) {
			return;
		}

		// Bail on the cheap option check before touching the AdSense ID.
		if ( empty( wp_advads_get_setting( 'adsense-page-level' ) ) ) {
			return;
		}

		$pub_id = Utils::get_adsense_id();
		if ( '' === $pub_id ) {
			return;
		}

		$this->print_page_level_code( $pub_id );
	}

	/**
	 * Handle AdSense AMP code.
	 *
	 * @return void
	 */
	public function inject_amp_code(): void {
		// Early bails, cheapest first.
		if (
			wp_doing_ajax()
			|| ! function_exists( 'is_amp_endpoint' )
			|| ! Conditional::is_amp()
			|| ( defined( 'AAR_VERSION' ) && 1 === version_compare( '1.10.0', AAR_VERSION ) )
			|| empty( wp_advads_get_setting( 'adsense-amp-auto-ads' ) )
		) {
			return;
		}

		// For non-AMP pages this happens on `template_redirect`, which has not fired yet.
		wp_advads()->frontend->run_checks();

		if ( Conditional::is_ad_disabled() ) {
			return;
		}

		// Adds the AdSense Auto ads AMP code to the page (head) in "Reader" mode.
		add_filter( 'amp_post_template_data', [ $this, 'add_auto_ads_amp_head_script' ] );

		// SmartMag theme (http://theme-sphere.com/smart-mag/documentation/).
		add_action( 'bunyad_amp_pre_main', [ $this, 'add_auto_ads_amp_body_script' ] );

		/**
		 * Add AMP Auto ads body code to the footer for the `AMP` plugin ( https://wordpress.org/plugins/amp/ ).
		 *
		 * The body tag is added on `wp_footer` because there is no WordPress hook right after `body`.
		 * The head code is added via `amp_post_template_data` above.
		 *
		 * `wp_footer` covers Transitional and Standard mode, `amp_post_template_footer` covers Reader mode.
		 */
		add_action( 'wp_footer', [ $this, 'add_auto_ads_amp_body_script' ] );
		add_action( 'amp_post_template_footer', [ $this, 'add_auto_ads_amp_body_script' ] );
	}

	/**
	 * Add AdSense AMP Auto ads code to the header.
	 *
	 * @param array<string, mixed> $data AMP components.
	 *
	 * @return array<string, mixed>
	 */
	public function add_auto_ads_amp_head_script( $data ) {
		$data['amp_component_scripts']['amp-auto-ads'] = self::AMP_AUTO_ADS_SCRIPT;

		return $data;
	}

	/**
	 * Add AdSense Auto ads body script.
	 *
	 * @return void
	 */
	public function add_auto_ads_amp_body_script(): void {
		$pub_id = Utils::get_adsense_id();
		if ( '' === $pub_id ) {
			return;
		}

		printf( '<amp-auto-ads type="adsense" data-ad-client="ca-%s"></amp-auto-ads>', esc_attr( $pub_id ) );
	}

	/**
	 * Print the non-personalized ads script when consent has not been given.
	 *
	 * @return void
	 */
	private function print_non_personalized_ads_script(): void {
		$privacy_state = $this->get_non_personalized_ads_state();
		if ( null === $privacy_state ) {
			return;
		}

		echo '<script>';

		// If the page is not from a cache.
		if ( 'unknown' === $privacy_state ) {
			echo '(adsbygoogle=window.adsbygoogle||[]).requestNonPersonalizedAds=1;';
		}

		// If the page is from a cache, wait until 'advads.privacy' is available. Execute before cache-busting.
		echo '( window.advanced_ads_ready || jQuery( document ).ready ).call( null, function() {
				var state = ( advads.privacy ) ? advads.privacy.get_state() : "";
				var use_npa = ( state === "unknown" ) ? 1 : 0;
				(adsbygoogle=window.adsbygoogle||[]).requestNonPersonalizedAds=use_npa;
			} )';

		echo '</script>';
	}

	/**
	 * Get the privacy state when non-personalized AdSense ads should be requested.
	 *
	 * Checks are ordered cheapest first so the privacy state is only parsed when the feature is actually in use.
	 *
	 * @return string|null Privacy state, or null when the script should not be printed.
	 */
	private function get_non_personalized_ads_state(): ?string {
		if ( ! wp_advads_is_module_active( 'privacy' ) ) {
			return null;
		}

		if ( 'custom' !== ( wp_advads_get_setting( 'consent-method' ) ?? '' ) ) {
			return null;
		}

		if ( ! wp_advads_get_setting( 'show-non-personalized-adsense' ) ) {
			return null;
		}

		$privacy_state = PrivacyUtils::parse_state();

		return 'not_needed' === $privacy_state ? null : $privacy_state;
	}

	/**
	 * Print the page-level (Auto ads) header code.
	 *
	 * @param string $pub_id Trimmed AdSense publisher ID.
	 *
	 * @return void
	 */
	private function print_page_level_code( string $pub_id ): void {
		$client_id       = 'ca-' . $pub_id;
		$top_anchor      = ! empty( wp_advads_get_setting( 'adsense-top-anchor-ad' ) );
		$top_anchor_code = sprintf(
			'(adsbygoogle = window.adsbygoogle || []).push({
				google_ad_client: "%s",
				enable_page_level_ads: true,
				overlays: {bottom: true}
			});',
			esc_attr( $client_id )
		);

		/**
		 * Filter the output of the publisher ID appended to the AdSense JavaScript Code.
		 *
		 * @param bool $add_publisher_id Whether to append the publisher ID.
		 */
		$add_publisher_id = apply_filters( 'advanced-ads-adsense-publisher-id', true );

		$script_src = add_query_arg(
			[ 'client' => $add_publisher_id ? esc_attr( $client_id ) : false ],
			self::ADSENSE_SCRIPT
		);

		/**
		 * Allows to override the page-level code.
		 *
		 * The Pro add-on uses this filter to inject a drop-in replacement for the page-level header code.
		 *
		 * @param string $code Existing page level code.
		 * @param array{client_id: string, top_anchor: bool, top_anchor_code: string, script_src: string} $parameters {
		 *    Parameters of the AdSense code.
		 *
		 *    @type string $client_id       The Google AdSense client ID.
		 *    @type bool   $top_anchor      AdSense anchor ad on top of pages.
		 *    @type string $top_anchor_code The code for top anchor ads.
		 *    @type string $script_src      AdSense script url.
		 * }
		 */
		$custom_code = apply_filters(
			'advanced-ads-gadsense-page-level-code',
			'',
			compact( 'client_id', 'top_anchor', 'top_anchor_code', 'script_src' )
		);

		if ( $custom_code ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the snippet has already been escaped.
			echo $custom_code;
			return;
		}

		// Inject page-level header code. The template reads the variables defined above.
		include_once 'views/page-level.php';
	}
}
