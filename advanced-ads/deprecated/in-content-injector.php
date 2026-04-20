<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Backward compatibility shim for content injection.
 *
 * @deprecated 2.0.20 Use {@see \AdvancedAds\Content_Injector} instead.
 * @package AdvancedAds
 */

use AdvancedAds\Content_Injector;

/**
 * Injects ads in the content based on an XPath expression.
 *
 * @deprecated 2.0.20 Use {@see Content_Injector}.
 */
class Advanced_Ads_In_Content_Injector {

	/**
	 * Inject ads directly into the content.
	 *
	 * @deprecated 2.0.20 Use {@see Content_Injector::inject_in_content()}.
	 *
	 * @param string $placement_id   Id of the placement.
	 * @param array  $placement_opts Placement options.
	 * @param string $content        Content to inject placement into.
	 * @param array  $options        Injection options.
	 * @return string
	 */
	public static function &inject_in_content( $placement_id, $placement_opts, &$content, $options = [] ) {
		return Content_Injector::inject_in_content( $placement_id, $placement_opts, $content, $options );
	}

	/**
	 * Callback function for usort() to sort ads for placeholders.
	 *
	 * @param array $first  The first array to compare.
	 * @param array $second The second array to compare.
	 * @return int
	 */
	public static function sort_ads_for_placehoders( $first, $second ) {
		return Content_Injector::sort_ads_for_placehoders( $first, $second );
	}

	/**
	 * Add a warning to 'Ad health'.
	 *
	 * @param array $nodes Nodes.
	 * @return array
	 */
	public static function add_ad_health_node( $nodes ) {
		return Content_Injector::add_ad_health_node( $nodes );
	}
}
