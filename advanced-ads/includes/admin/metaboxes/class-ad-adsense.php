<?php
/**
 * Ad Adsense.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.48.2
 */

namespace AdvancedAds\Admin\Metaboxes;

use Advanced_Ads_Network_Adsense;
use AdvancedAds\Abstracts\Ad;
use AdvancedAds\Constants;
use AdvancedAds\Modules\AdSense\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Ad Adsense.
 */
class Ad_Adsense {

	/**
	 * Get metabox id
	 *
	 * @return string
	 */
	public function get_box_id(): string {
		return 'advads-gadsense-box';
	}

	/**
	 * Hook into WordPress. TODO: move to module to its right place.
	 *
	 * @param \AdvancedAds\Admin\Metabox_Ad $manager Manager instance.
	 *
	 * @return void
	 */
	public function register( $manager ): void {
		global $post;

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		if ( $post->ID && Utils::can_show_stats() ) {
			$ad_unit = Advanced_Ads_Network_Adsense::get_instance()->get_ad_unit( $post->ID );

			if ( $ad_unit ) {
				add_meta_box(
					$this->get_box_id(),
					sprintf(
						/* translators: 1: Name of ad unit */
						esc_html__( 'Earnings of %1$s', 'advanced-ads' ),
						esc_html( $ad_unit->name )
					),
					[ $manager, 'display' ],
					Constants::POST_TYPE_AD,
					'normal',
					'high'
				);
			}
		}
	}

	/**
	 * Variables required by the AdSense dashboard view.
	 *
	 * @param Ad $ad Ad instance.
	 *
	 * @return array{report_type: string, report_filter: string|null}
	 */
	public function prepare_view_vars( $ad ): array {
		$unit_code = null;
		if ( $ad->is_type( 'adsense' ) && ! empty( $ad->get_content() ) ) {
			$json_content = json_decode( $ad->get_content() );
			// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- the name AdSense uses in the stored ad code.
			if ( is_object( $json_content ) && isset( $json_content->slotId ) && is_scalar( $json_content->slotId ) ) {
				$unit_code = (string) $json_content->slotId;
			}
			// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}

		return [
			'report_type'   => 'unit',
			'report_filter' => $unit_code,
		];
	}

	/**
	 * Get metaboxe view file
	 *
	 * @param Ad|null $ad Ad instance.
	 *
	 * @return string
	 */
	public function get_view( $ad = null ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		return ADVADS_ABSPATH . 'views/admin/metaboxes/ads/ad-gadsense-dashboard.php';
	}

	/**
	 * Return manual link
	 *
	 * @return array<string, mixed>|string
	 */
	public function get_handle_link() {
		return '<a href="' . esc_url( admin_url( 'admin.php?page=advanced-ads-app&path=/settings&tab=adsense' ) ) . '" target="_blank">' . __( 'Disable', 'advanced-ads' ) . '</a>';
	}
}
