<?php
/**
 * Ad Blocker module settings fields.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

namespace AdvancedAds\Modules\AdBlocker;

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
		$registry->store->register( 'adblocker', 'advanced-ads-adblocker' );

		$group = $registry
			->tab( 'adblocker', __( 'Ad Blocker', 'advanced-ads' ), [ 'icon' => 'shield-off', 'order' => 40 ] )
			->group( 'adblocker', [ 'label' => __( 'Ad Blocker', 'advanced-ads' ) ] );

		$group->text(
			[
				'id'          => 'ga-UID',
				'label'       => __( 'Google Analytics Tracking ID', 'advanced-ads' ),
				'description' => __( 'Enter your Google Analytics property ID (e.g. G-A12BC3D456) above to track the page views of visitors who use an ad blocker.', 'advanced-ads' ),
				'placeholder' => 'G-A12BC3D456',
				'store'       => 'adblocker',
			]
		);

		// A block-layout nested group: its own label/description carry what the
		// toggle used to show via SettingRow (block-layout children render bare,
		// skipping that wrapper — see FieldRenderer.jsx), so the raw notice can
		// live nested under the toggle instead of as a flat sibling.
		$group
			->group(
				'adblocker-disguise',
				[
					'layout' => 'block',
				]
			)
			->toggle(
				[
					'id'          => 'use-adblocker',
					'store'       => 'adblocker',
					'label'       => __( 'Ad blocker disguise', 'advanced-ads' ),
					'description' => __( 'Prevents ad blockers from breaking your website when blocking asset files (.js, .css).', 'advanced-ads' ),
					'manual_url'  => 'https://wpadvancedads.com/manual/ad-blockers/#utm_source=advanced-ads&utm_medium=link&utm_campaign=adblock-manual',
				]
			)
			->raw(
				[
					'id'             => 'adblocker-status-notice',
					'store'          => 'adblocker',
					'value_callback' => static fn() => WordPress::get_file_content( __DIR__ . '/adblocker-paths.php' ),
					'dependencies'   => [
						[
							'source'   => 'field',
							'field'    => 'use-adblocker',
							'operator' => 'equals',
							'value'    => true,
						],
					],
					'effect'         => 'hide',
				]
			);
	}
}
