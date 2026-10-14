<?php
/**
 * Privacy module settings fields.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

namespace AdvancedAds\Modules\Privacy;

use AdvancedAds\Compatibility\Compatibility;
use AdvancedAds\Settings\Registry;
use AdvancedAds\Tracking\Helpers;
use AdvancedAds\Utilities\Conditional;

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
		$registry->store->register( 'privacy', 'advanced-ads-privacy' );

		$registry->register_context(
			'privacy_has_cache_plugin',
			static fn() => Conditional::has_cache_plugins()
		);

		$registry->register_context(
			'privacy_has_borlabs_cookie_adsense_autoads',
			static fn() => Compatibility::borlabs_cookie_adsense_auto_ads_code_exists()
		);

		$registry->register_context(
			'privacy_has_tcf_conflict',
			static fn() => method_exists( Helpers::class, 'has_tcf_conflict' ) && Helpers::has_tcf_conflict()
		);

		$custom_cookie_dependency = [
			[
				'source'   => 'field',
				'field'    => 'consent-method',
				'operator' => 'equals',
				'value'    => 'custom',
			],
		];

		$registry
			->tab( 'privacy', __( 'Privacy', 'advanced-ads' ), [ 'icon' => 'shield', 'order' => 30 ] )
			->group( 'privacy', [ 'label' => __( 'Show ads only to users who give their permission to cookies and ads.', 'advanced-ads' ) ] )
			->radio_group(
				[
					'id'      => 'consent-method',
					'layout'  => 'block',
					'label'   => __( 'Consent method', 'advanced-ads' ),
					'options' => [
						[
							'value' => '',
							'label' => __( 'Show all ads even without consent', 'advanced-ads' ),
						],
						[
							'value'      => 'custom',
							'label'      => __( 'Cookie', 'advanced-ads' ),
							'manual_url' => 'https://wpadvancedads.com/manual/ad-cookie-consent/?utm_source=advanced-ads&utm_medium=link&utm_campaign=privacy-tab',
						],
						[
							'value'      => 'iab_tcf_20',
							'label'      => __( 'IAB Transparency and Consent Framework (TCF) integration', 'advanced-ads' ),
							'manual_url' => 'https://wpadvancedads.com/manual/tcf-consent-wordpress/?utm_source=advanced-ads&utm_medium=link&utm_campaign=privacy-tab',
						],
					],
					'store'   => 'privacy',
					'effect'  => 'hide',
				]
			)
			->text(
				[
					'id'                => 'custom-cookie-name',
					'label'             => __( 'Cookie name', 'advanced-ads' ),
					'placeholder'       => __( 'Name', 'advanced-ads' ),
					'store'             => 'privacy',
					'sanitize_callback' => static fn( $value ) => trim( (string) $value ),
					'dependencies'      => $custom_cookie_dependency,
					'effect'            => 'hide',
				]
			)
			->text(
				[
					'id'                => 'custom-cookie-value',
					'label'             => __( 'contains value', 'advanced-ads' ),
					'placeholder'       => __( 'Value', 'advanced-ads' ),
					'store'             => 'privacy',
					'sanitize_callback' => static fn( $value ) => trim( (string) $value ),
					'dependencies'      => $custom_cookie_dependency,
					'effect'            => 'hide',
				]
			)
			->toggle(
				[
					'id'           => 'show-non-personalized-adsense',
					'label'        => __( 'Show non-personalized AdSense ads until consent is given.', 'advanced-ads' ),
					'store'        => 'privacy',
					'dependencies' => $custom_cookie_dependency,
					'effect'       => 'hide',
				]
			);
	}
}
