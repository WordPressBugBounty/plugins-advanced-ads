<?php
/**
 * Core module registrations. Visible (Modules-tab card + toggle): AdSense, Ad Blocker, Ads.txt (default on), Privacy (default off). Hidden (`is_hidden`, always boots): OneClick, Gutenberg. Required from Registry::hooks() with $registry in scope.
 *
 * @package AdvancedAds
 * @var \AdvancedAds\Modules\Registry $registry
 */

defined( 'ABSPATH' ) || exit;

use AdvancedAds\Modules\Module;

$registry->register(
	new Module(
		[
			'id'          => 'adsense',
			'label'       => static fn() => __( 'AdSense', 'advanced-ads' ),
			'description' => static fn() => __( 'Connect and manage Google AdSense ads.', 'advanced-ads' ),
			'icon'        => 'dollar-sign',
			'entry'       => \AdvancedAds\Modules\AdSense\Boot::class,
		]
	)
);

$registry->register(
	new Module(
		[
			'id'             => 'ad-blocker',
			'label'          => static fn() => __( 'Ad Blocker', 'advanced-ads' ),
			'description'    => static fn() => __( 'Disguise plugin assets from ad blockers and optionally track blocked visits in Google Analytics.', 'advanced-ads' ),
			'icon'           => 'shield-off',
			'default_active' => true,
			'entry'          => \AdvancedAds\Modules\AdBlocker\Boot::class,
		]
	)
);

$registry->register(
	new Module(
		[
			'id'          => 'ads-txt',
			'label'       => static fn() => __( 'Ads.txt', 'advanced-ads' ),
			'description' => static fn() => __( 'Create and manage the ads.txt file.', 'advanced-ads' ),
			'icon'        => 'file-text',
			'entry'       => \AdvancedAds\Modules\AdsTxt\Boot::class,
			'manual_url'  => 'https://wpadvancedads.com/manual/ads-txt/#utm_source=advanced-ads&utm_medium=link&utm_campaign=ads-txt-manual',
		]
	)
);

$registry->register(
	new Module(
		[
			'id'             => 'privacy',
			'label'          => static fn() => __( 'Privacy', 'advanced-ads' ),
			'description'    => static fn() => __( 'Show ads only to users who give their permission to cookies and ads.', 'advanced-ads' ),
			'icon'           => 'shield',
			'default_active' => false,
			'entry'          => \AdvancedAds\Modules\Privacy\Boot::class,
		]
	)
);

$registry->register(
	new Module(
		[
			'id'          => 'one-click',
			'label'       => static fn() => __( 'OneClick Connect', 'advanced-ads' ),
			'description' => static fn() => __( 'One-click ad network connection workflow.', 'advanced-ads' ),
			'icon'        => 'zap',
			'is_hidden'   => true,
			'entry'       => \AdvancedAds\Modules\OneClick\Boot::class,
		]
	)
);

$registry->register(
	new Module(
		[
			'id'          => 'gutenberg',
			'label'       => static fn() => __( 'Gutenberg', 'advanced-ads' ),
			'description' => static fn() => __( 'Block editor integration.', 'advanced-ads' ),
			'icon'        => 'blocks',
			'is_hidden'   => true,
			'entry'       => \AdvancedAds\Modules\Gutenberg\Boot::class,
		]
	)
);
