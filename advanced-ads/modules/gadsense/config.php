<?php
/**
 * Google AdSense module configuration.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

return [
	'classmap'   => [
		'Advanced_Ads_Ad_Type_Adsense'         => __DIR__ . '/includes/class-ad-type-adsense.php',
		'Advanced_Ads_AdSense_Data'            => __DIR__ . '/includes/class-gadsense-data.php',
		'Advanced_Ads_AdSense_MAPI'            => __DIR__ . '/includes/class-mapi.php',
		'Advanced_Ads_AdSense_Admin'           => __DIR__ . '/admin/admin.php',
		'Advanced_Ads_AdSense_Public'          => __DIR__ . '/public/public.php',
		'Advanced_Ads_AdSense_Report'          => __DIR__ . '/includes/class-adsense-report.php',
		'AdSense_Report_Data'                  => __DIR__ . '/includes/class-adsense-report-data.php',
		'Advanced_Ads_AdSense_Report_Api'      => __DIR__ . '/includes/adsense-report-api.php',
		'Advanced_Ads_Network_Adsense'         => __DIR__ . '/includes/class-network-adsense.php',
		'AdvancedAds\\Adsense\\Types\\Adsense' => __DIR__ . '/includes/types/type-adsense.php',
	],
	'textdomain' => null,
];
