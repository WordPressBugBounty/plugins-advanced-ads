<?php
/**
 * AdSense module utils
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

namespace AdvancedAds\Modules\AdSense;

use AdvancedAds\Abstracts\Ad;
use AdvancedAds\Constants;
use AdvancedAds\Modules\AdSense\Types\Ad_Adsense;

/**
 * Class Utils.
 */
class Utils {
	/**
	 * Get adsense id
	 *
	 * @param Ad|null $ad Ad instance.
	 *
	 * @return string
	 */
	public static function get_adsense_id( $ad = null ) {
		if ( ! empty( $ad ) && $ad->is_type( 'adsense' ) ) {
			$ad_content = json_decode( $ad->get_content() );
			// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			if ( $ad_content && isset( $ad_content->pubId ) && ! empty( $ad_content->pubId ) ) {
				return $ad_content->pubId;
			}
			// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}

		$adsense_id = wp_advads_get_setting( 'adsense-id', 'adsense' );
		return $adsense_id ? trim( $adsense_id ) : '';
	}

	/**
	 * Is page level enabled
	 *
	 * @return boolean
	 */
	public static function is_page_level_enabled(): bool {
		return (bool) wp_advads_get_setting( 'adsense-page-level' );
	}

	/**
	 * Is setup
	 *
	 * @return boolean
	 */
	public static function is_setup(): bool {
		$adsense_id = self::get_adsense_id();
		if ( $adsense_id ) {
			return \Advanced_Ads_AdSense_MAPI::has_token( $adsense_id );
		}

		return false;
	}

	/**
	 * Can show AdSense stats metabox.
	 *
	 * @return bool
	 */
	public static function can_show_stats(): bool {
		global $post;

		// Early bail!!
		if ( ! self::is_setup() ) {
			return false;
		}

		if ( $post instanceof \WP_Post && Constants::POST_TYPE_AD === $post->post_type ) {
			$the_ad = \wp_advads_get_ad( $post->ID );
			if ( ! $the_ad || ! $the_ad->is_type( 'adsense' ) ) {
				return false;
			}
		}

		return ! wp_advads_get_setting( 'adsense-hide-stats', 'adsense' );
	}

	/**
	 * Get Auto Ads messages.
	 *
	 * @return array<string, string>
	 */
	public static function get_auto_ads_messages(): array {
		$url = admin_url( 'admin.php?page=advanced-ads-app&path=/settings&tab=adsense' );

		return [
			'enabled'  => sprintf(
				/* translators: %s is a URL. */
				__( 'The AdSense verification and Auto ads code is already activated in the <a href="%s">AdSense settings</a>.', 'advanced-ads' ),
				$url
			)
			. ' ' . __( 'No need to add the code manually here, unless you want to include it into certain pages only.', 'advanced-ads' ),
			'disabled' => sprintf(
				'%s <button id="adsense_enable_pla" type="button" class="button">%s</button>',
				sprintf(
					/* translators: %s is a URL. */
					__( 'The AdSense verification and Auto ads code should be set up in the <a href="%s">AdSense settings</a>. Click on the following button to enable it now.', 'advanced-ads' ),
					$url
				),
				esc_attr__( 'Activate', 'advanced-ads' )
			),
		];
	}

	/**
	 * Get the ad selector markup
	 *
	 * @param bool $hide_idle_ads Whether to hide idle ads.
	 *
	 * @return void
	 */
	public static function get_mapi_ad_selector( $hide_idle_ads = true ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- kept for existing callers.
		global $closeable, $use_dashicons, $network, $ad_units, $display_slot_id;

		$closeable       = true;
		$use_dashicons   = false;
		$network         = \Advanced_Ads_Network_Adsense::get_instance();
		$ad_units        = $network->get_external_ad_units();
		$display_slot_id = true;
		$pub_id          = self::get_adsense_id();

		require_once ADVADS_ABSPATH . 'modules/gadsense/views/external-ads-list.php';
	}
}
