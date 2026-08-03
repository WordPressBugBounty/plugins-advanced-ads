<?php
/**
 * Module configuration.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

return [
	'classmap'   => [
		'Advanced_Ads_Ads_Txt_Public'    => __DIR__ . '/public/class-advanced-ads-ads-txt-public.php',
		'Advanced_Ads_Ads_Txt_Strategy'  => __DIR__ . '/includes/class-advanced-ads-ads-txt-strategy.php',
		'Advanced_Ads_Ads_Txt_Admin'     => __DIR__ . '/admin/class-advanced-ads-ads-txt-admin.php',
		'Advanced_Ads_Ads_Txt_Utils'     => __DIR__ . '/includes/class-advanced-ads-ads-txt-utils.php',
		'Advanced_Ads_Ads_Txt_Real_File' => __DIR__ . '/includes/class-advanced-ads-ads-txt-real-file.php',
	],
	'textdomain' => null,
];
