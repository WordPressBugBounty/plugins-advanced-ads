<?php
/**
 * Ad Blocker counter scripts
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

namespace AdvancedAds\Modules\AdBlocker;

use AdvancedAds\Framework\Interfaces\Integration_Interface;
use AdvancedAds\Modules\Privacy\Utils as Privacy_Utils;
use AdvancedAds\Utilities\Conditional;

defined( 'ABSPATH' ) || exit;

/**
 * Counter class.
 */
class Counter implements Integration_Interface {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'wp_footer', [ $this, 'print_adblock_check_js' ], 9 );
	}

	/**
	 * Print the appropriate script into wp_footer.
	 *
	 * Don't print anything on AMP pages.
	 * Print minimal script if Advanced Ads Pro module "Ads for ad blockers" is active.
	 *
	 * @return void
	 */
	public function print_adblock_check_js() {
		if ( Conditional::is_amp() ) {
			return;
		}

		$minified = ! defined( 'SCRIPT_DEBUG' ) || ! SCRIPT_DEBUG;

		// if ad blocker counter is active.
		$ga_uid = wp_advads_get_setting( 'ga-UID' );
		if ( ! empty( $ga_uid ) && $this->can_print_ga() ) {
			printf(
				'<script>(function(){var advanced_ads_ga_UID="%s",advanced_ads_ga_anonymIP=!!%d;%s})();</script>',
				esc_js( $ga_uid ),
				(int) (
					! defined( 'ADVANCED_ADS_DISABLE_ANALYTICS_ANONYMIZE_IP' ) ||
					! ADVANCED_ADS_DISABLE_ANALYTICS_ANONYMIZE_IP
				),
				// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- escaping could break the script and we're getting the contents of a local file
				$this->get_script( 'adblocker-enabled', $minified ) . $this->get_script( 'adblocker-ga-counter', $minified )
				// phpcs:enable
			);
		} elseif ( defined( 'AAP_SLUG' ) && $this->is_adblocker_enabled() ) {
			// if Advanced Ads Pro module "Ads for ad blockers" is active but no tracking.
			// or if method is not "nothing".
			printf(
				'<script>%s</script>',
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- escaping could break the script and we're getting the contents of a local file
				$this->get_script( 'adblocker-enabled', $minified )
			);
		}
	}

	/**
	 * Whether the GA counter may load gtag.js.
	 *
	 * @return bool
	 */
	private function can_print_ga(): bool {
		if ( ! wp_advads_is_module_active( 'privacy' ) ) {
			return true;
		}

		$method = wp_advads_get_setting( 'consent-method' ) ?? '';
		if ( 'custom' === $method ) {
			return in_array( Privacy_Utils::parse_state(), [ 'accepted', 'not_needed' ], true );
		}

		return 'iab_tcf_20' !== $method;
	}

	/**
	 * Check if ad blocker is enabled.
	 *
	 * @return bool
	 */
	private function is_adblocker_enabled(): bool {
		if ( wp_advads_get_setting( 'ads-for-adblockers.enabled', 'adblocker' ) ) {
			return true;
		}

		$method = wp_advads_get_setting( 'method', 'adblocker' );

		return is_string( $method ) && '' !== $method && 'nothing' !== $method;
	}

	/**
	 * Get the script.
	 *
	 * @param string $name Name of the script.
	 * @param bool   $minified Whether to use the minified version.
	 * @return string
	 */
	private function get_script( string $name, $minified ): string {
		$folder = $minified ? ADVADS_ABSPATH . 'assets/dist/' : __DIR__ . '/src/';
		$path   = $folder . $name . '.js';

		if ( ! is_readable( $path ) ) {
			return '';
		}

		return file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file.
	}
}
