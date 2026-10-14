<?php
/**
 * General tab settings fields.
 *
 * @package AdvancedAds
 * @var \AdvancedAds\Settings\Registry $registry
 */

defined( 'ABSPATH' ) || exit;

use AdvancedAds\Constants;
use AdvancedAds\Framework\Utilities\Formatting;
use AdvancedAds\Utilities\Data;
use AdvancedAds\Utilities\Sanitize;

$role_options = [];
global $wp_roles;
foreach ( $wp_roles->get_names() as $slug => $label ) {
	$role_options[] = [
		'value' => $slug,
		'label' => translate_user_role( $label ),
	];
}

$post_type_options = array_map(
	static fn( $post_type ) => [
		'value' => $post_type->name,
		'label' => $post_type->label,
	],
	array_values( get_post_types( [ 'public' => true ], 'objects' ) )
);

$checkbox_grid_map = static function ( array $options, $raw ): array {
	$selected = (array) ( $raw ?? [] );
	$map      = [];

	foreach ( $options as $option ) {
		$map[ $option['value'] ] = in_array( $option['value'], $selected, true );
	}

	return $map;
};

$checkbox_grid_sanitize = static fn( $value ) => array_keys( array_filter( (array) $value ) );

$registry->store->register(
	'main',
	ADVADS_SLUG,
	[
		'content-injection-priority' => 100,
		'front-prefix'               => wp_advads()->get_frontend_prefix(),
		'target-blank'               => 0,
		'disabled-ads.feed'          => true,
	]
);

$tab = $registry->tab( 'general', __( 'General', 'advanced-ads' ), [ 'icon' => 'globe', 'order' => 10 ] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- local to the method that includes this file.

$admin = $tab->group( 'admin', [ 'label' => __( 'Admin', 'advanced-ads' ) ] );
$admin
	->toggle(
		[
			'id'          => 'disable-notices',
			'label'       => __( 'Disable Ad Health and other notices', 'advanced-ads' ),
			'description' => __( 'Disable Ad Health in frontend and backend, warnings and internal notices like tips, tutorials, email newsletters and update notices.', 'advanced-ads' ),
			'manual_url'  => 'https://wpadvancedads.com/manual/ad-health/?utm_source=advanced-ads&utm_medium=link&utm_campaign=settings-ad-health',
			'store'       => 'main',
		]
	)
	->toggle(
		[
			'id'          => 'editors-manage-ads',
			'label'       => __( 'Allow editors to manage ads', 'advanced-ads' ),
			'description' => __( 'Allow editors to also manage and publish ads.', 'advanced-ads' ),
			'store'       => 'main',
		]
	)
	->toggle(
		[
			'id'          => 'uninstall-delete-data',
			'label'       => __( 'Delete data on uninstall', 'advanced-ads' ),
			'description' => __( 'Clean up all data related to Advanced Ads when removing the plugin.', 'advanced-ads' ),
			'store'       => 'main',
		]
	);

if ( is_multisite() && ( current_user_can( 'unfiltered_html' ) || is_super_admin() ) ) {
	$unfiltered_html_options = [];
	foreach ( Data::get_filtered_roles_by_cap() as $role_slug => $role_object ) {
		$unfiltered_html_options[] = [
			'value' => $role_slug,
			'label' => translate_user_role( $role_object->name ),
		];
	}

	if ( ! empty( $unfiltered_html_options ) ) {
		$admin->checkbox_grid(
			[
				'id'                => 'allow-unfiltered-html',
				'layout'            => 'block',
				/* translators: %s: the "unfiltered_html" capability name. */
				'label'             => sprintf( __( 'Add the %s capability to user roles on multisite', 'advanced-ads' ), 'unfiltered_html' ),
				'description'       => __( 'Enabling this option for untrusted users may cause them to publish malicious or poorly formatted code in their ads and can be a potential security risk. You should carefully consider which user roles you grant this capability.', 'advanced-ads' ),
				'options'           => $unfiltered_html_options,
				'store'             => 'main',
				'value_callback'    => static fn( $raw ) => $checkbox_grid_map( $unfiltered_html_options, $raw ),
				'sanitize_callback' => $checkbox_grid_sanitize,
			]
		);
	}
}

$tab
	->group( 'disable-ads', [ 'label' => __( 'Disable ads', 'advanced-ads' ) ] )
	->checkbox_grid(
		[
			'id'          => 'disable-ads',
			'layout'      => 'block',
			'label'       => __( 'Disable ads', 'advanced-ads' ),
			'description' => __( 'Choose where ads should never be shown.', 'advanced-ads' ),
			'cols'        => 2,
			'options'     => [
				[
					'value' => 'all',
					'label' => __( 'Disable all ads in frontend', 'advanced-ads' ),
				],
				[
					'value' => '404',
					'label' => __( 'Disable ads on 404 error pages', 'advanced-ads' ),
				],
				[
					'value' => 'archives',
					'label' => __( 'Disable ads on non-singular pages', 'advanced-ads' ),
				],
				[
					'value' => 'secondary',
					'label' => __( 'Disable ads on secondary queries', 'advanced-ads' ),
				],
				[
					'value' => 'feed',
					'label' => __( 'Disable ads in RSS Feed', 'advanced-ads' ),
				],
				[
					'value' => 'rest-api',
					'label' => __( 'Disable ads in REST API', 'advanced-ads' ),
				],
			],
			'store'       => 'main',
			'key'         => 'disabled-ads',
		]
	)
	->checkbox_grid(
		[
			'id'                => 'hide-for-user-role',
			'layout'            => 'block',
			'label'             => __( 'Hide ads for user roles', 'advanced-ads' ),
			'description'       => __( 'Choose the roles a user must have in order to not see any ads.', 'advanced-ads' ),
			'cols'              => 3,
			'options'           => $role_options,
			'store'             => 'main',
			'value_callback'    => static fn( $raw ) => $checkbox_grid_map(
				$role_options,
				Advanced_Ads_Utils::maybe_translate_cap_to_role( (array) ( $raw ?? [] ) )
			),
			'sanitize_callback' => $checkbox_grid_sanitize,
		]
	);

if ( ! defined( 'AAP_VERSION' ) ) {
	$tab
		->group( 'disable-ads' )
		->checkbox_grid(
			[
				'id'                => 'disable-by-post-types',
				'layout'            => 'block',
				'label'             => __( 'Disable ads for post types', 'advanced-ads' ),
				'description'       => __( 'The free version provides the post type display condition on the ad edit page.', 'advanced-ads' ),
				'cols'              => 3,
				'options'           => $post_type_options,
				'store'             => 'pro',
				'key'               => 'general.disable-by-post-types',
				'locked'            => true,
				'lock_message'      => __( 'Post type targeting is a Pro feature.', 'advanced-ads' ),
				'upgrade_url'       => 'https://wpadvancedads.com/add-ons/advanced-ads-pro/?utm_source=advanced-ads&utm_medium=link&utm_campaign=settings-disable-post-types',
				'sanitize_callback' => $checkbox_grid_sanitize,
				'value_callback'    => static fn( $raw ) => $checkbox_grid_map( $post_type_options, $raw ),
			]
		);
}

	$tab
	->group( 'disable-ads' )
	->toggle(
		[
			'id'             => 'hide-for-ip-address',
			'label'          => __( 'Hide ads for IP addresses', 'advanced-ads' ),
			'description'    => __( 'Activate module', 'advanced-ads' ),
			'store'          => 'main',
			'key'            => 'hide-for-ip-address.enabled',
			'value_callback' => static fn( $raw ) => Formatting::string_to_bool( $raw ),
		]
	)
	->textarea(
		[
			'id'           => 'hide-for-ip-address-ips',
			'layout'       => 'block',
			'placeholder'  => __( 'Enter one IP address per line for which no ads are displayed.', 'advanced-ads' ),
			'store'        => 'main',
			'key'          => 'hide-for-ip-address.ips',
			'dependencies' => [
				[
					'source'   => 'field',
					'field'    => 'hide-for-ip-address',
					'operator' => 'equals',
					'value'    => true,
				],
			],
			'effect'       => 'hide',
		]
	)
	->toggle(
		[
			'id'          => 'block-bots',
			'label'       => __( 'Hide ads from bots', 'advanced-ads' ),
			'description' => __( 'Hide ads from crawlers, bots and empty user agents.', 'advanced-ads' ),
			'note'        => sprintf(
				'<a href="%s" target="_blank">%s</a>',
				'https://wpadvancedads.com/hide-ads-from-bots/?utm_source=advanced-ads&utm_medium=link&utm_campaign=settings',
				esc_html__( 'Read this first', 'advanced-ads' )
			),
			'store'       => 'main',
		]
	);

	$layout = $tab->group( 'layout', [ 'label' => __( 'Layout / Output', 'advanced-ads' ) ] );
	$layout->text(
		[
			'id'                => 'front-prefix',
			'label'             => __( 'ID prefix', 'advanced-ads' ),
			'description'       => __( 'Prefix of class and id attributes for elements created in the frontend.', 'advanced-ads' ),
			'store'             => 'main',
			'sanitize_callback' => static fn( $value ) => Sanitize::frontend_prefix( $value, Constants::DEFAULT_FRONTEND_PREFIX ),
		]
	);

	$layout
	->group(
		'ad-label',
		[
			'label'       => __( 'Ad label', 'advanced-ads' ),
			'layout'      => 'block',
			'description' => __( 'Displayed above ads.', 'advanced-ads' ),
			'manual_url'  => 'https://wpadvancedads.com/manual/advertisement-label/?utm_source=advanced-ads&utm_medium=link&utm_campaign=settings-advertisement-label',
		]
	)
	->toggle(
		[
			'id'             => 'ad-label-enabled',
			'store'          => 'main',
			'key'            => 'custom-label.enabled',
			'value_callback' => static fn( $raw ) => Formatting::string_to_bool( $raw ),
		]
	)
	->text(
		[
			'id'                => 'ad-label-text',
			'store'             => 'main',
			'key'               => 'custom-label.text',
			'placeholder'       => _x( 'Advertisements', 'label before ads', 'advanced-ads' ),
			'sanitize_callback' => static fn( $value ) => Sanitize::ad_label( $value ),
			'dependencies'      => [
				[
					'source'   => 'field',
					'field'    => 'ad-label-enabled',
					'operator' => 'equals',
					'value'    => true,
				],
			],
			'effect'            => 'hide',
		]
	)
	->checkbox(
		[
			'id'           => 'ad-label-html',
			'label'        => __( 'Enable HTML for the field', 'advanced-ads' ),
			'store'        => 'main',
			'key'          => 'custom-label.html_enabled',
			'dependencies' => [
				[
					'source'   => 'field',
					'field'    => 'ad-label-enabled',
					'operator' => 'equals',
					'value'    => true,
				],
			],
			'effect'       => 'hide',
		]
	);

	$layout
	->toggle(
		[
			'id'          => 'link-target',
			'label'       => __( 'Open links in a new window', 'advanced-ads' ),
			'description' => __( 'Open programmatically created links in a new window.', 'advanced-ads' ),
			'manual_url'  => 'https://wpadvancedads.com/open-ad-in-new-window/?utm_source=advanced-ads&utm_medium=link&utm_campaign=open-new-window',
			'store'       => 'main',
			'key'         => 'target-blank',
		]
	)
	->toggle(
		[
			'id'          => 'advanced-js',
			'label'       => __( 'Use advanced JavaScript', 'advanced-ads' ),
			'description' => __( 'Enable advanced JavaScript functions. Some features and add-ons might override this setting if they need features from this file.', 'advanced-ads' ),
			'store'       => 'main',
		]
	);

	$tab
	->group( 'content-injection', [ 'label' => __( 'Content injection', 'advanced-ads' ) ] )
	->toggle(
		[
			'id'                => 'content-injection-enabled',
			'label'             => __( 'Content placement in post lists', 'advanced-ads' ),
			'description'       => __( 'By default, ads are not injected into excerpts or the full content of posts on archive pages. Enable this to override that restriction.', 'advanced-ads' ),
			'store'             => 'main',
			'sanitize_callback' => static fn( $value ) => $value ? 'on' : 'off',
		]
	)
	->select(
		[
			'id'                => 'content-injection-everywhere',
			'label'             => __( 'Limit to the first n posts', 'advanced-ads' ),
			'description'       => __( 'Set a limit on the number of posts where ads will be injected, or lift it entirely. If you want to insert ads between post listing items instead, use the Post list placement.', 'advanced-ads' ),
			'manual_url'        => 'https://wpadvancedads.com/manual/placement-post-lists/?utm_source=advanced-ads&utm_medium=link&utm_campaign=settings-content-injection',
			'options'           => array_merge(
				array_map(
					static fn( $n ) => [
						'value' => $n,
						'label' => (string) $n,
					],
					range( 1, 10 )
				),
				[
					[
						'value' => -1,
						'label' => __( 'all', 'advanced-ads' ),
					],
				]
			),
			'store'             => 'main',
			'value_callback'    => static fn( $raw ) => 'true' === $raw ? -1 : ( null === $raw ? 1 : (int) $raw ),
			'sanitize_callback' => static fn( $value ) => (int) $value <= -1 ? 'true' : (string) absint( $value ),
			'dependencies'      => [
				[
					'source'   => 'field',
					'field'    => 'content-injection-enabled',
					'operator' => 'equals',
					'value'    => true,
				],
			],
			'effect'            => 'hide',
		]
	)
	->number(
		[
			'id'          => 'content-injection-priority',
			'label'       => __( 'Priority of content injection filter', 'advanced-ads' ),
			'description' => __( 'Play with this value in order to change the priority of the injected ads compared to other auto injected elements in the post content.', 'advanced-ads' ),
			'store'       => 'main',
		]
	)
	->toggle(
		[
			'id'          => 'content-injection-level-limitation',
			'label'       => __( 'Disable level limitation', 'advanced-ads' ),
			'description' => __( 'Ignore paragraphs and other elements in containers when injecting ads into the post content.', 'advanced-ads' ),
			'store'       => 'main',
			'key'         => 'content-injection-level-disabled',
		]
	);
