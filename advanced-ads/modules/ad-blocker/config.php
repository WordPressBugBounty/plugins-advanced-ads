<?php
/**
 * Ad blocker module configuration.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

return [
	'classmap' => [
		'Advanced_Ads_Ad_Blocker'       => __DIR__ . '/classes/plugin.php',
		'Advanced_Ads_Ad_Blocker_Admin' => __DIR__ . '/admin/admin.php',
	],
	'textdomain' => null,
];
