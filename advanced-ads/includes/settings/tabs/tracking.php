<?php
/**
 * Tracking tab shell.
 *
 * @package AdvancedAds
 * @var \AdvancedAds\Settings\Registry $registry
 */

defined( 'ABSPATH' ) || exit;

use AdvancedAds\Enums\UTM;

$settings_tab = $registry->tab( 'tracking', __( 'Tracking', 'advanced-ads' ), [ 'icon' => 'activity', 'order' => 60 ] );

$registry
	->register_context( 'is_tracking_active', static fn() => defined( 'AAT_VERSION' ) )
	->register_context(
		'tracking_upgrade_url',
		static fn() => UTM::with_plugin_link( 'https://wpadvancedads.com/add-ons/tracking/', 'pitch-tracking' )
	);

if ( defined( 'AAT_VERSION' ) && version_compare( AAT_VERSION, '3.0.15', '<' ) ) {
	$settings_tab->group( 'tracking-update-notice', [ 'layout' => 'block' ] )
		->raw(
			[
				'id'             => 'tracking-status-notice',
				'store'          => 'tracking',
				'value_callback' => static function (): string {
					return sprintf(
						'<p>%s</p><p><a class="button advads-button-primary" href="%s">%s</a></p>',
						sprintf(
							/* translators: 1: opening <strong> tag, 2: closing </strong> tag, 3: installed Tracking version */
							esc_html__( 'Please update %1$sAdvanced Ads Tracking%2$s to newer version. You are running version %3$s.', 'advanced-ads' ),
							'<strong>',
							'</strong>',
							'<b>' . esc_html( AAT_VERSION ) . '</b>'
						),
						esc_url( admin_url( 'plugins.php' ) ),
						esc_html__( 'Go to Plugins', 'advanced-ads' )
					);
				},
			]
		);
}
