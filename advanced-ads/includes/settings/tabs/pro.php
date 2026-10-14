<?php
/**
 * Pro tab shell.
 *
 * @package AdvancedAds
 * @var \AdvancedAds\Settings\Registry $registry
 */

defined( 'ABSPATH' ) || exit;

use AdvancedAds\Enums\UTM;

$settings_tab = $registry->tab( 'pro', __( 'Pro', 'advanced-ads' ), [ 'icon' => 'gem', 'order' => 50 ] );

$registry
	->register_context( 'is_pro_active', static fn() => defined( 'AAP_VERSION' ) )
	->register_context(
		'pro_upgrade_url',
		static fn() => UTM::with_plugin_link( 'https://wpadvancedads.com/add-ons/advanced-ads-pro/', 'pitch-pro' )
	)
	->register_context(
		'all_access_url',
		static fn() => add_query_arg(
			[
				'page'         => 'advanced-ads-app',
				'path'         => '/license',
				'utm_source'   => 'advancedads',
				'utm_medium'   => 'in-plugin',
				'utm_campaign' => 'a2-in_plugin-pricing_table-all_access_plan',
			],
			admin_url( 'admin.php' )
		)
	);


if ( defined( 'AAP_VERSION' ) && version_compare( AAP_VERSION, '3.0.15', '<' ) ) {
	$settings_tab->group( 'pro-update-notice', [ 'layout' => 'block' ] )
		->raw(
			[
				'id'             => 'pro-status-notice',
				'store'          => 'pro',
				'value_callback' => static function (): string {
					$url = admin_url( 'plugins.php' );
					return sprintf(
						'<p>%s</p><p><a class="button advads-button-primary" href="%s">%s</a></p>',
						sprintf(
							/* translators: 1: opening <strong> tag, 2: closing </strong> tag, 3: installed Pro version */
							esc_html__( 'Please update %1$sAdvanced Ads Pro%2$s to newer version. You are running version %3$s.', 'advanced-ads' ),
							'<strong>',
							'</strong>',
							'<b>' . esc_html( AAP_VERSION ) . '</b>'
						),
						esc_url( $url ),
						esc_html__( 'Go to Plugins', 'advanced-ads' )
					);
				},
			]
		);
}
