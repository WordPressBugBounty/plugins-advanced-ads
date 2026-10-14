<?php
/**
 * AdSense module settings fields.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

namespace AdvancedAds\Modules\AdSense;

use AdvancedAds\Enums\UTM;
use AdvancedAds\Settings\Registry;
use AdvancedAds\Utilities\Conditional;
use AdvancedAds\Utilities\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Settings class.
 */
class Settings {

	/**
	 * Register settings.
	 *
	 * @param Registry $registry The registry instance.
	 * @return void
	 */
	public function register( Registry $registry ): void {
		$registry->store->register(
			'adsense',
			ADVADS_SLUG . '-adsense',
			[
				'adsense-id'         => '',
				'page-level-enabled' => false,
				'fullwidth-ads'      => 'default',
			]
		);

		$tab = $registry->tab(
			'adsense',
			__( 'AdSense', 'advanced-ads' ),
			[
				'icon'  => 'dollar-sign',
				'order' => 20,
			]
		);

		$has_mapi = class_exists( 'Advanced_Ads_AdSense_MAPI' );

		$adsense_id = Utils::get_adsense_id();
		$has_token  = ( $has_mapi && $adsense_id ) ? \Advanced_Ads_AdSense_MAPI::has_token( $adsense_id ) : false;

		$registry->register_context(
			'adsenseHasToken',
			static fn() => $has_token
		);

		$group = $tab->group(
			'adsense-account',
			[
				'className' => ( $has_token || $adsense_id ) ? 'm-0!' : 'adsense-account-settings hidden',
			]
		);
		$group
			->text(
				[
					'id'          => 'adsense-id',
					'label'       => __( 'AdSense account', 'advanced-ads' ),
					'store'       => 'adsense',
					'key'         => 'adsense-id',
					'placeholder' => 'pub-1234567891234567',
					'disabled'    => $has_token,
				]
			);

		if ( $adsense_id ) {
			$group->raw(
				[
					'id'             => 'adsense-account',
					'store'          => 'adsense',
					'value_callback' => static fn() => WordPress::get_file_content( __DIR__ . '/views/adsense-account-field.php' ),
				]
			);
		}
			$group->toggle(
				[
					'id'          => 'adsense-page-level',
					'label'       => __( 'Auto ads', 'advanced-ads' ),
					'description' => __( 'Insert the AdSense header code to enable Auto ads and verify your website.', 'advanced-ads' ),
					'store'       => 'adsense',
					'key'         => 'page-level-enabled',
					'note'        => sprintf(
						'<a href="%1$s" target="_blank">%2$s</a><br><a href="%3$s" target="_blank">%4$s</a><br><a href="%5$s" target="_blank">%6$s</a>',
						esc_url(
							UTM::with_plugin_link(
								'https://wpadvancedads.com/adsense-auto-ads-wordpress/#Display_AdSense_Auto_Ads_only_on_specific_pages',
								'settings-adsense-specific-pages'
							)
						),
						esc_html__( 'Display Auto ads only on specific pages', 'advanced-ads' ),
						esc_url(
							UTM::with_plugin_link(
								'https://wpadvancedads.com/adsense-in-random-positions-auto-ads/',
								'backend-autoads-ads'
							)
						),
						esc_html__( 'Why are ads appearing in random positions?', 'advanced-ads' ),
						esc_url( admin_url( 'admin.php?page=advanced-ads-app&path=/settings&tab=adsense&oauth=1' ) ),
						esc_html__( 'Adjust Auto ads options', 'advanced-ads' )
					),
				]
			);

		// TODO: Remove this once AMP is fully supported.
		if (
			! defined( 'AAR_VERSION' )
			|| version_compare( AAR_VERSION, '1.10.0', '>=' )
		) {
			$group->toggle(
				[
					'id'    => 'adsense-amp-auto-ads',
					'label' => __( 'Enable AMP Auto ads', 'advanced-ads' ),
					'store' => 'adsense',
				]
			);
		}

		$group
			->toggle(
				[
					'id'          => 'adsense-top-anchor-ad',
					'label'       => __( 'Disable top anchor ad', 'advanced-ads' ),
					'description' => __( 'Enable this box if you don’t want Google Auto ads to place anchor ads at the top of your page.', 'advanced-ads' ),
					'store'       => 'adsense',
				]
			)
			->toggle(
				[
					'id'          => 'adsense-hide-stats',
					'label'       => __( 'Disable stats', 'advanced-ads' ),
					'description' => __( 'Enable this option to stop loading stats from AdSense into your WordPress backend.', 'advanced-ads' ),
					'store'       => 'adsense',
				]
			)
			->toggle(
				[
					'id'          => 'adsense-warnings-disable',
					'label'       => __( 'Disable violation warnings', 'advanced-ads' ),
					'description' => __( 'Disable warnings about potential violations of the AdSense terms.', 'advanced-ads' ),
					'store'       => 'adsense',
					'note'        => sprintf(
						'<p>Our <a href="%1$s" target="_blank">%2$s</a>%3$s</p>',
						esc_url( 'https://wpadvancedads.com/adsense-auto-ads-wordpress/?utm_source=advanced-ads&utm_medium=link&utm_campaign=settings-adsense-specific-pages#Display_AdSense_Auto_Ads_only_on_specific_pages' ),
						esc_html__( 'Ad Health', 'advanced-ads' ),
						esc_html__( ' feature monitors if AdSense is implemented correctly on your site. It also considers ads not managed with Advanced Ads. Enable this option to remove these checks', 'advanced-ads' )
					),

				]
			)
			->toggle(
				[
					'id'          => 'adsense-wp-widget',
					'label'       => __( 'Show AdSense Earnings', 'advanced-ads' ),
					'description' => __( 'Show Earnings widget on the WordPress dashboard.', 'advanced-ads' ),
					'store'       => 'adsense',
				]
			)
			->toggle(
				[
					'id'          => 'adsense-background',
					'label'       => __( 'Transparent background', 'advanced-ads' ),
					'description' => __( 'Enable this option in case your theme adds an unfortunate background color to AdSense ads.', 'advanced-ads' ),
					'store'       => 'adsense',
				]
			)
			->select(
				[
					'id'          => 'adsense-fullwidth',
					'label'       => __( 'Full width responsive ads on mobile', 'advanced-ads' ),
					'description' => __( 'Whether your responsive ad unit may expand to use the full width of your visitor\'s mobile device screen.', 'advanced-ads' ),
					'store'       => 'adsense',
					'options'     => [
						[
							'value' => 'default',
							'label' => __( 'default', 'advanced-ads' ),
						],
						[
							'value' => 'enable',
							'label' => __( 'enable', 'advanced-ads' ),
						],
						[
							'value' => 'disable',
							'label' => __( 'disable', 'advanced-ads' ),
						],
					],
				]
			);

		$tab
			->group( 'adsense-account-overview-group', [ 'layout' => 'bare' ] )
			->raw(
				[
					'id'             => 'adsense-account-overview',
					'store'          => 'adsense',
					'layout'         => 'block',
					'value_callback' => [ $this, 'account_overview_data' ],
				]
			);
	}

	/**
	 * Data for the AdSense account card
	 *
	 * @return string JSON-encoded payload.
	 */
	public function account_overview_data(): string {
		$has_mapi = class_exists( 'Advanced_Ads_AdSense_MAPI' );

		$adsense_id = Utils::get_adsense_id();
		$has_token  = ( $has_mapi && $adsense_id ) ? \Advanced_Ads_AdSense_MAPI::has_token( $adsense_id ) : false;

		$account_name = '';
		$warnings     = [];
		$auth_url     = '#';
		$nonce        = wp_create_nonce( 'advads-mapi' );

		if ( $has_mapi ) {
			$cid = ( \Advanced_Ads_AdSense_MAPI::use_user_app() && defined( 'ADVANCED_ADS_MAPI_CID' ) )
				? ADVANCED_ADS_MAPI_CID
				: \Advanced_Ads_AdSense_MAPI::CID;

			$auth_url = add_query_arg(
				[
					'scope'                  => 'https://www.googleapis.com/auth/adsense.readonly',
					'client_id'              => $cid,
					'redirect_uri'           => \Advanced_Ads_AdSense_MAPI::REDIRECT_URI,
					'state'                  => base64_encode( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- OAuth state payload, not obfuscation.
						wp_json_encode(
							[
								'api'        => 'adsense',
								'nonce'      => $nonce,
								'return_url' => admin_url( 'admin.php?page=advanced-ads-app&path=/settings&tab=adsense&oauth=1' ),
							]
						)
					),
					'access_type'            => 'offline',
					'include_granted_scopes' => 'true',
					'prompt'                 => 'consent',
					'response_type'          => 'code',
				],
				'https://accounts.google.com/o/oauth2/v2/auth'
			);

			if ( $has_token ) {
				$mapi_options   = \Advanced_Ads_AdSense_MAPI::get_option();
				$account_name   = $mapi_options['accounts'][ $adsense_id ]['details']['name'] ?? '';
				$alerts         = \Advanced_Ads_AdSense_MAPI::get_stored_account_alerts( $adsense_id );
				$alert_messages = \Advanced_Ads_AdSense_MAPI::get_adsense_alert_messages();

				if ( is_array( $alerts ) && ! empty( $alerts['items'] ) && is_array( $alerts['items'] ) ) {
					foreach ( $alerts['items'] as $alert_id => $alert ) {
						$internal_id = $alert['id'] ?? str_replace( '-', '_', strtoupper( $alert['type'] ?? '' ) );
						$warnings[]  = [
							'id'   => (string) $alert_id,
							'html' => wp_kses(
								$alert_messages[ $internal_id ] ?? ( $alert['message'] ?? '' ),
								[ 'a' => [ 'href' => true, 'target' => true, 'class' => true ] ]
							),
						];
					}
				}
			}
		}

		$show_subscribe = class_exists( 'Advanced_Ads_Admin_Notices' )
			&& Conditional::user_can_subscribe( 'nl_first_steps' )
			&& \Advanced_Ads_Admin_Notices::get_instance()->can_display( 'nl_adsense' );

		return wp_json_encode(
			[
				'adsenseId'     => $adsense_id,
				'hasToken'      => $has_token,
				'accountName'   => $account_name,
				'invalidFormat' => '' !== $adsense_id && 0 !== strpos( $adsense_id, 'pub-' ),
				'warnings'      => $warnings,
				'authUrl'       => $auth_url,
				'nonce'         => $nonce,
				'showSubscribe' => $show_subscribe,
				'links'         => [
					'manual'    => 'https://wpadvancedads.com/adsense-ads/?utm_source=advanced-ads&utm_medium=link&utm_campaign=adsense-manual-check',
					'forum'     => 'https://wordpress.org/support/plugin/advanced-ads/#new-post',
					'networks'  => 'https://wpadvancedads.com/recommended-ad-networks/?utm_source=advanced-ads&utm_medium=link&utm_campaign=recommendations',
					'signup'    => 'https://www.google.com/adsense/start/?utm_source=AdvancedAdsPlugIn&utm_medium=partnerships&utm_campaign=AdvancedAdsPartner1',
					'video'     => 'https://vimeo.com/381874350',
					'positions' => 'https://wpadvancedads.com/place-adsense-ad-unit-manually/?utm_source=advanced-ads&utm_medium=link&utm_campaign=adsense-manually',
				],
			]
		);
	}
}
