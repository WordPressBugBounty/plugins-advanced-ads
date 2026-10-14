<?php
/**
 * Ads.txt module settings fields.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

namespace AdvancedAds\Modules\AdsTxt;

use AdvancedAds\Settings\Registry;
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
		$registry->store->register( 'ads-txt', 'advanced_ads_ads_txt' );
		$group = $registry
			->tab( 'ads-txt', __( 'Ads.txt', 'advanced-ads' ), [ 'icon' => 'file-text', 'order' => 40 ] )
			->group( 'ads-txt', [ 'label' => __( 'Create and manage the ads.txt file', 'advanced-ads' ) ] );
		$group->textarea(
			[
				'id'          => 'ads-txt-custom',
				'layout'      => 'block',
				'label'       => __( 'Additional records', 'advanced-ads' ),
				'description' => __( 'Added to the file, one record per line.', 'advanced-ads' ),
				'store'       => 'ads-txt',
				'key'         => 'custom',
			]
		);

		$adsense_id = wp_advads_get_setting( 'adsense-id' );
		if ( $adsense_id ) {
			$group->group(
				'adsense-block',
				[
					'label'  => __( 'AdSense account found', 'advanced-ads' ),
					'layout' => 'block',
				]
			)
			->raw(
				[
					'id'             => 'ads-txt-adsense-account-found',
					'store'          => 'main',
					'value_callback' => static fn() => WordPress::get_file_content( __DIR__ . '/views/adsense-account-found.php' ),
				]
			)
			->toggle(
				[
					'id'          => 'ads-txt-adsense-toggle',
					'label'       => __( 'Remove from ads.txt', 'advanced-ads' ),
					'description' => __( 'The AdSense record will be removed from the ads.txt file.', 'advanced-ads' ),
					'store'       => 'main',
					'key'         => 'ads-txt.adsense-toggle',
				]
			);
		}
	}
}
