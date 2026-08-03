<?php
/**
 * Ad block finder module configuration.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

return [
	'classmap' => [
		'Advanced_Ads_Adblock_Finder'       => __DIR__ . '/public/public.php',
		'Advanced_Ads_Adblock_Finder_Admin' => __DIR__ . '/admin/admin.php',
	],
	'textdomain' => null,
];
