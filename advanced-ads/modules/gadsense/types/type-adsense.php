<?php
/**
 * This class represents the "Adsense" ad type.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   2.4.0
 */

namespace AdvancedAds\Modules\AdSense\Types;

use Advanced_Ads_AdSense_MAPI;
use AdvancedAds\Abstracts\Ad;
use AdvancedAds\Interfaces\Ad_Type;
use AdvancedAds\Modules\AdSense\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Type Adsense.
 */
class Adsense implements Ad_Type {
	/**
	 * Unit types that are sized by AdSense itself rather than by fixed width/height.
	 */
	private const FLUID_UNIT_TYPES = [ 'responsive', 'link-responsive', 'matched-content' ];

	/**
	 * Default values for the parameters passed to the ad parameters template.
	 */
	private const UNIT_DEFAULTS = [
		'unit_id'      => '',
		'unit_pubid'   => '',
		'unit_code'    => '',
		'unit_type'    => 'responsive',
		'unit_width'   => 0,
		'unit_height'  => 0,
		'unit_resize'  => '',
		'layout'       => '',
		'layout_key'   => '',
		'extra_params' => [
			'default_width'  => '',
			'default_height' => '',
			'at_media'       => [],
		],
	];

	/**
	 * Get the unique identifier (ID) of the ad type.
	 *
	 * @return string The unique ID of the ad type.
	 */
	public function get_id(): string {
		return 'adsense';
	}

	/**
	 * Get the class name of the object as a string.
	 *
	 * @return string
	 */
	public function get_classname(): string {
		return Ad_Adsense::class;
	}

	/**
	 * Get the title or name of the ad type.
	 *
	 * @return string The title of the ad type.
	 */
	public function get_title(): string {
		return __( 'AdSense ad', 'advanced-ads' );
	}

	/**
	 * Get a description of the ad type.
	 *
	 * @return string The description of the ad type.
	 */
	public function get_description(): string {
		return __( 'Use ads from your Google AdSense account', 'advanced-ads' );
	}

	/**
	 * Check if this ad type requires premium.
	 *
	 * @return bool True if premium is required; otherwise, false.
	 */
	public function is_premium(): bool {
		return false;
	}

	/**
	 * Get the URL for upgrading to this ad type.
	 *
	 * @return string The upgrade URL for the ad type.
	 */
	public function get_upgrade_url(): string {
		return '';
	}

	/**
	 * Get the URL for upgrading to this ad type.
	 *
	 * @return string The upgrade URL for the ad type.
	 */
	public function get_image(): string {
		return ADVADS_BASE_URL . 'modules/gadsense/assets/img/ad-types/adsense.svg';
	}

	/**
	 * Check if this ad type has size parameters.
	 *
	 * @return bool True if has size parameters; otherwise, false.
	 */
	public function has_size(): bool {
		return true;
	}

	/**
	 * Output for the ad parameters metabox
	 *
	 * @since 1.4
	 * @param Ad $ad Ad instance.
	 *
	 * @return void
	 */
	public function render_parameters( $ad ): void {
		$this->print_ad_importer_script();

		$pub_id       = Utils::get_adsense_id();
		$raw_content  = (string) ( $ad->get_content() ?? '' );
		$json_content = '';
		$content      = $raw_content;

		if ( '' !== trim( $raw_content ) ) {
			$json_content = stripslashes( $raw_content );
			$content      = json_decode( $json_content );

			if ( isset( $content->unitType ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$content->json = $json_content;
			}
		}

		$params = $this->get_unit_parameters( $content, $pub_id, $ad );

		/*
		 * The variables below are part of the contract with the template required at the end of this
		 * method. Keep them assigned explicitly so the template's dependencies stay visible here.
		 */
		$options      = $ad->get_data();
		$unit_id      = $params['unit_id'];
		$unit_pubid   = $params['unit_pubid'];
		$unit_code    = $params['unit_code'];
		$unit_type    = $params['unit_type'];
		$unit_width   = $params['unit_width'];
		$unit_height  = $params['unit_height'];
		$unit_resize  = $params['unit_resize'];
		$layout       = $params['layout'];
		$layout_key   = $params['layout_key'];
		$extra_params = $params['extra_params'];

		$pub_id_errors = $this->get_pub_id_error( $pub_id, $unit_code );

		global $external_ad_unit_id, $use_dashicons, $closeable;
		$closeable           = true;
		$use_dashicons       = false;
		$external_ad_unit_id = $unit_id;

		$default_template = ADVADS_ABSPATH . 'modules/gadsense/views/adsense-ad-parameters.php';

		/**
		 * Inclusion of other UI template is done here. The content is passed in order to allow the inclusion of different
		 * templates file, depending of the ad. It's up to the developer to verify that $content is not an empty
		 * variable (which is the case for a new ad).
		 *
		 * Inclusion of .js and .css files for the ad creation/editon page are done by another hook. See
		 * 'advanced-ads-gadsense-ad-param-script' and 'advanced-ads-gadsense-ad-param-style' in "../admin/class-gadsense-admin.php".
		 */
		$template = apply_filters( 'advanced-ads-gadsense-ad-param-template', $default_template, $content );

		require_once $template;
	}

	/**
	 * Print the inline script that boots the AdSense ad importer.
	 *
	 * @return void
	 */
	private function print_ad_importer_script(): void {
		$mapi_options  = Advanced_Ads_AdSense_MAPI::get_option();
		$json_ad_codes = wp_json_encode( $mapi_options['ad_codes'] ?? [] );

		if ( false === $json_ad_codes ) {
			$json_ad_codes = '[]';
		}
		?>
		<script>
			jQuery( function () {
				const adsense = new AdvancedAdsNetworkAdsense(<?php echo $json_ad_codes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- encoded by wp_json_encode. ?>)
				AdvancedAdsAdmin.AdImporter.setup( adsense )
			} )
		</script>
		<?php
	}

	/**
	 * Build the ad unit parameters used by the ad parameters template.
	 *
	 * @param mixed  $content Decoded ad content, or the raw content when it could not be decoded.
	 * @param string $pub_id  AdSense publisher ID, used as a fallback for the unit publisher ID.
	 * @param Ad     $ad      Ad instance.
	 *
	 * @return array<string, mixed>
	 */
	private function get_unit_parameters( $content, string $pub_id, $ad ): array {
		$params = self::UNIT_DEFAULTS;

		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- AdSense uses camelCase keys.
		if ( ! isset( $content->unitType ) ) {
			return $params;
		}

		$unit_type = $content->unitType;

		$params['unit_type']  = $unit_type;
		$params['unit_code']  = $content->slotId ?? '';
		$params['unit_pubid'] = ! empty( $content->pubId ) ? $content->pubId : $pub_id;
		$params['layout']     = $content->layout ?? '';
		$params['layout_key'] = $content->layout_key ?? '';

		if ( in_array( $unit_type, self::FLUID_UNIT_TYPES, true ) ) {
			// Responsive && multiplex ads.
			$params['unit_resize'] = $content->resize ?? 'auto';

			if ( 'auto' !== $params['unit_resize'] ) {
				$params['extra_params'] = apply_filters(
					'advanced-ads-gadsense-ad-param-data',
					self::UNIT_DEFAULTS['extra_params'],
					$content,
					$ad
				);
			}
		} else {
			// Normal ad unit.
			$params['unit_width']  = $ad->get_width();
			$params['unit_height'] = $ad->get_height();
		}
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		if ( ! empty( $params['unit_pubid'] ) ) {
			$params['unit_id'] = 'ca-' . $params['unit_pubid'] . ':' . $params['unit_code'];
		}

		return $params;
	}

	/**
	 * Get the publisher ID error message, if any.
	 *
	 * @param string $pub_id    Trimmed AdSense publisher ID.
	 * @param string $unit_code Ad unit slot ID.
	 *
	 * @return string|false Error message, or false when the publisher ID is fine.
	 */
	private function get_pub_id_error( string $pub_id, string $unit_code ) {
		if ( '' === $pub_id ) {
			return '' !== trim( $unit_code )
				? __( 'Your AdSense Publisher ID is missing.', 'advanced-ads' )
				: false;
		}

		if ( 0 !== strpos( $pub_id, 'pub-' ) ) {
			return __( 'The Publisher ID has an incorrect format. (must start with "pub-")', 'advanced-ads' );
		}

		return false;
	}
}
