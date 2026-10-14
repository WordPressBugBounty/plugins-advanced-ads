<?php
/**
 * Privacy module admin.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

namespace AdvancedAds\Modules\Privacy;

use AdvancedAds\Abstracts\Ad;
use AdvancedAds\Framework\Interfaces\Integration_Interface;
use AdvancedAds\Framework\Utilities\Params;

defined( 'ABSPATH' ) || exit;

/**
 * Admin class.
 */
class Admin implements Integration_Interface {

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'advanced-ads-ad-params-after', [ $this, 'render_ad_options' ], 20 );
		add_action( 'advanced-ads-ad-pre-save', [ $this, 'save_ad_options' ], 10, 2 );
	}

	/**
	 * Add options to ad edit page
	 *
	 * @param Ad $ad Ad object.
	 *
	 * @return void
	 */
	public function render_ad_options( Ad $ad ) {
		if ( empty( $ad->get_id() ) ) {
			return;
		}

		// Don't add override option if the ad is adsense, image or dummy.
		$skip_option = $ad->is_type( 'adsense' ) || ! Utils::ad_type_needs_consent( $ad->get_type() );

		if ( (bool) apply_filters( 'advanced-ads-ad-privacy-hide-ignore-consent', $skip_option, $ad, \Advanced_Ads_Privacy::get_instance()->options() ) ) {
			return;
		}

		$ignore_consent = $ad->get_prop( 'privacy.ignore-consent' );
		?>
		<div class="advads-option-list">
			<span class="label"><?php esc_html_e( 'privacy', 'advanced-ads' ); ?></span>
			<div id="advanced-ads-ad-parameters-privacy">
				<label>
					<input name="advanced_ad[privacy][ignore-consent]" type="checkbox" <?php checked( $ignore_consent ); ?>/>
					<?php
					printf(
						/* Translators: 1: a tag with link to general privacy settings, 2: closing a tag */
						esc_html__( 'Ignore %1$sgeneral Privacy settings%2$s and display the ad even without consent.', 'advanced-ads' ),
						'<a onclick="event.stopPropagation();" href="' . esc_url( admin_url( 'admin.php?page=advanced-ads-app&path=/settings&tab=privacy' ) ) . '">',
						'</a>'
					);
					?>
				</label>
			</div>
		</div>
		<hr/>
		<?php
	}

	/**
	 * Save ad options.
	 *
	 * @param Ad                   $ad        Ad instance.
	 * @param array<string, mixed> $post_data Post data array.
	 *
	 * @return void
	 */
	public function save_ad_options( Ad $ad, $post_data ): void {
		if ( wp_verify_nonce( sanitize_key( Params::get( '_wpnonce' ) ), 'bulk-posts' ) || wp_verify_nonce( sanitize_key( Params::post( '_inline_edit' ) ), 'inlineeditnonce' ) ) {
			return;
		}

		if ( isset( $post_data['privacy'] ) ) {
			$ad->set_prop( 'privacy', wp_unslash( $post_data['privacy'] ) );
		} else {
			$ad->unset_prop( 'privacy' );
		}
	}
}
