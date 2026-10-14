<?php
/**
 * Locked/upsell placeholder modules (`is_upgradeable`, no `entry`). Add-ons replace a card by unregistering it on `advanced-ads-register-modules`. Required from Registry::hooks() with $registry in scope.
 *
 * @package AdvancedAds
 * @var \AdvancedAds\Modules\Registry $registry
 */

defined( 'ABSPATH' ) || exit;

use AdvancedAds\Modules\Module;

$pro_upgrade_url = 'https://wpadvancedads.com/add-ons/advanced-ads-pro/?utm_source=advanced-ads&utm_medium=link&utm_campaign=settings-modules';

$registry->register(
	new Module(
		[
			'id'             => 'module-ad-server',
			'label'          => static fn() => __( 'Ad Server', 'advanced-ads' ),
			'description'    => static fn() => __( 'Serve ads to other websites from your own WordPress install.', 'advanced-ads' ),
			'icon'           => 'server',
			'is_upgradeable' => true,
			'upgrade_url'    => $pro_upgrade_url,
		]
	)
);

$registry->register(
	new Module(
		[
			'id'             => 'module-visitor-conditions',
			'label'          => static fn() => __( 'Advanced visitor conditions', 'advanced-ads' ),
			'description'    => static fn() => __( 'Target ads by device, browser, referrer, cookies and more.', 'advanced-ads' ),
			'icon'           => 'user-check',
			'is_upgradeable' => true,
			'upgrade_url'    => $pro_upgrade_url,
		]
	)
);

$registry->register(
	new Module(
		[
			'id'             => 'module-cache-busting',
			'label'          => static fn() => __( 'Cache Busting', 'advanced-ads' ),
			'description'    => static fn() => __( "Load ads via JavaScript so caching plugins can't freeze them.", 'advanced-ads' ),
			'icon'           => 'zap',
			'is_upgradeable' => true,
			'upgrade_url'    => $pro_upgrade_url,
		]
	)
);

$registry->register(
	new Module(
		[
			'id'             => 'module-click-fraud',
			'label'          => static fn() => __( 'Click Fraud Protection', 'advanced-ads' ),
			'description'    => static fn() => __( 'Block visitors who click your ads suspiciously often.', 'advanced-ads' ),
			'icon'           => 'shield-alert',
			'is_upgradeable' => true,
			'upgrade_url'    => $pro_upgrade_url,
		]
	)
);

$registry->register(
	new Module(
		[
			'id'             => 'module-lazy-loading',
			'label'          => static fn() => __( 'Lazy Loading', 'advanced-ads' ),
			'description'    => static fn() => __( "Delay ad loading until they're about to enter the viewport.", 'advanced-ads' ),
			'icon'           => 'hourglass',
			'is_upgradeable' => true,
			'upgrade_url'    => $pro_upgrade_url,
		]
	)
);

$registry->register(
	new Module(
		[
			'id'             => 'module-rest-api',
			'label'          => static fn() => __( 'REST API', 'advanced-ads' ),
			'description'    => static fn() => __( 'Query and deliver ads through the WordPress REST API.', 'advanced-ads' ),
			'icon'           => 'code-2',
			'is_upgradeable' => true,
			'upgrade_url'    => $pro_upgrade_url,
		]
	)
);

$registry->register(
	new Module(
		[
			'id'             => 'module-tracking',
			'label'          => static fn() => __( 'Tracking', 'advanced-ads' ),
			'description'    => static fn() => __( 'Record impressions and clicks, and review them in reports.', 'advanced-ads' ),
			'icon'           => 'activity',
			'is_upgradeable' => true,
			'upgrade_url'    => 'https://wpadvancedads.com/add-ons/tracking/?utm_source=advanced-ads&utm_medium=link&utm_campaign=settings-modules',
		]
	)
);

$registry->register(
	new Module(
		[
			'id'             => 'module-geo-targeting',
			'label'          => static fn() => __( 'Geo Targeting', 'advanced-ads' ),
			'description'    => static fn() => __( "Show different ads based on the visitor's country or region.", 'advanced-ads' ),
			'icon'           => 'globe-2',
			'is_upgradeable' => true,
			'upgrade_url'    => 'https://wpadvancedads.com/add-ons/geo-targeting/?utm_source=advanced-ads&utm_medium=link&utm_campaign=settings-modules',
		]
	)
);

$registry->register(
	new Module(
		[
			'id'             => 'module-sticky-ads',
			'label'          => static fn() => __( 'Sticky Ads', 'advanced-ads' ),
			'description'    => static fn() => __( 'Keep ads in view while the visitor scrolls the page.', 'advanced-ads' ),
			'icon'           => 'pin',
			'is_upgradeable' => true,
			'upgrade_url'    => 'https://wpadvancedads.com/add-ons/sticky-ads/?utm_source=advanced-ads&utm_medium=link&utm_campaign=settings-modules',
		]
	)
);

$registry->register(
	new Module(
		[
			'id'             => 'module-popup-ads',
			'label'          => static fn() => __( 'Popup & Layer Ads', 'advanced-ads' ),
			'description'    => static fn() => __( 'Display ads in a lightbox, layer or timed popup.', 'advanced-ads' ),
			'icon'           => 'layers',
			'is_upgradeable' => true,
			'upgrade_url'    => 'https://wpadvancedads.com/add-ons/popup-and-layer-ads/?utm_source=advanced-ads&utm_medium=link&utm_campaign=settings-modules',
		]
	)
);

$registry->register(
	new Module(
		[
			'id'             => 'module-slider',
			'label'          => static fn() => __( 'Slider', 'advanced-ads' ),
			'description'    => static fn() => __( 'Rotate multiple ads inside a single animated slider.', 'advanced-ads' ),
			'icon'           => 'gallery-horizontal',
			'is_upgradeable' => true,
			'upgrade_url'    => 'https://wpadvancedads.com/add-ons/slider/?utm_source=advanced-ads&utm_medium=link&utm_campaign=settings-modules',
		]
	)
);
