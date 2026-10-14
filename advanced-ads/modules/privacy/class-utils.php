<?php
/**
 * Privacy module utils
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

namespace AdvancedAds\Modules\Privacy;

use AdvancedAds\Abstracts\Ad;
use AdvancedAds\Framework\Utilities\Params;
use AdvancedAds\Framework\Utilities\Str;
use AdvancedAds\Utilities\Conditional;

defined( 'ABSPATH' ) || exit;

/**
 * Utils class.
 */
class Utils {
	/**
	 * Check whether this ad_type needs consent.
	 *
	 * @param string $type The ad type.
	 *
	 * @return bool
	 */
	public static function ad_type_needs_consent( $type ) {
		return ! in_array( $type, [ 'image', 'dummy', 'group' ], true );
	}

	/**
	 * Check if the current ad output is encoded.
	 *
	 * @param string $output The ad output.
	 *
	 * @return bool
	 */
	public static function is_ad_output_encoded( $output ) {
		return (bool) strpos( $output, 'data-tcf="waiting-for-consent"' );
	}

	/**
	 * Used by get_state() to parse the state of privacy/consent.
	 *
	 * @return string
	 *     'not_needed' - consent is not needed.
	 *     'accepted' - consent was given.
	 *     'unknown' - consent was not given yet.
	 */
	public static function parse_state() {
		$consent_method = wp_advads_get_setting( 'consent-method' ) ?? '';
		if ( Conditional::is_amp() || '' === $consent_method ) {
			return 'not_needed';
		}

		if ( 'custom' !== $consent_method ) {
			return 'unknown';
		}

		$name = wp_advads_get_setting( 'custom-cookie-name' ) ?? '';
		if ( empty( $name ) ) {
			return 'not_needed';
		}

		if ( ! Params::cookie( $name ) ) {
			return 'unknown';
		}

		$value = wp_advads_get_setting( 'custom-cookie-value' ) ?? '';
		if (
			( '' === $value && '' === Params::cookie( $name ) ) ||
			Str::contains( $value, Params::cookie( $name ) )
		) {
			return 'accepted';
		}

		return 'unknown';
	}

	/**
	 * Encode the ad output.
	 *
	 * @param string $output The output string.
	 * @param Ad     $ad     Ad intance.
	 *
	 * @return string
	 */
	public static function encode_ad( $output, Ad $ad ) {
		$data_attributes = [
			'id'  => $ad->get_id(),
			'bid' => get_current_blog_id(),
		];

		if ( $ad->is_parent_placement() ) {
			$data_attributes['placement'] = $ad->get_parent()->get_id();
		}

		/**
		 * Filter the data attributes and allow removing/adding attributes.
		 * All attributes will be prefix with `data-` on output.
		 *
		 * @param array<string, int|string> $data_attributes The default data attributes.
		 * @param Ad    $ad              Ad instance.
		 */
		$data_attributes = (array) apply_filters( 'advanced-ads-privacy-output-attributes', $data_attributes, $ad );

		// Convert the data-attributes array into a string.
		$attributes_string = '';
		array_walk(
			$data_attributes,
			function ( $value, $key ) use ( &$attributes_string ) {
				$attributes_string .= sprintf( ' data-%s="%s"', sanitize_key( $key ), esc_attr( $value ) );
			}
		);

		return sprintf(
			'<script type="text/plain" data-tcf="waiting-for-consent"%s>%s</script>',
			$attributes_string,
			// phpcs:ignore  WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- we need to obfuscate the html (and maybe decode it in JS).
			base64_encode( $output )
		);
	}
}
