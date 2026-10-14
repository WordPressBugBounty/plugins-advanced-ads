<?php
/**
 * This class is responsible for handling the UTM parameters.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.50.0
 */

namespace AdvancedAds\Enums;

defined( 'ABSPATH' ) || exit;

/**
 * UTM class.
 */
class UTM {
	/**
	 * The source of the UTM parameter.
	 */
	const SOURCE_PLUGIN = 'advanced-ads';

	/**
	 * The medium of the UTM parameter.
	 */
	const MEDIUM_LINK      = 'link';
	const MEDIUM_IN_PLUGIN = 'in-plugin';

	/**
	 * Build a UTM URL using the plugin as the source, and provided medium and campaign.
	 *
	 * @param string $url          Base URL.
	 * @param string $utm_medium   UTM medium.
	 * @param string $utm_campaign UTM campaign.
	 *
	 * @return string
	 */
	public static function with_plugin( string $url, string $utm_medium, string $utm_campaign ): string {
		return self::build( $url, self::SOURCE_PLUGIN, $utm_medium, $utm_campaign );
	}

	/**
	 * Build a UTM URL using the plugin as the source and link as the medium, and the provided campaign.
	 *
	 * @param string $url          Base URL.
	 * @param string $utm_campaign UTM campaign.
	 *
	 * @return string
	 */
	public static function with_plugin_link( string $url, string $utm_campaign ): string {
		return self::build( $url, self::SOURCE_PLUGIN, self::MEDIUM_LINK, $utm_campaign );
	}

	/**
	 * Build a UTM URL using the plugin as the source and in-plugin as the medium, and the provided campaign.
	 *
	 * @param string $url          Base URL.
	 * @param string $utm_campaign UTM campaign.
	 *
	 * @return string
	 */
	public static function with_plugin_in_plugin( string $url, string $utm_campaign ): string {
		return self::build( $url, self::SOURCE_PLUGIN, self::MEDIUM_IN_PLUGIN, $utm_campaign );
	}

	/**
	 * Merge params onto a URL (override same keys).
	 *
	 * @param string $url        Base URL.
	 * @param string $utm_source UTM source.
	 * @param string $utm_medium UTM medium.
	 * @param string $utm_campaign UTM campaign.
	 *
	 * @return string
	 */
	public static function build( string $url, string $utm_source, string $utm_medium, string $utm_campaign ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- read through compact().
		return add_query_arg( compact( 'utm_source', 'utm_medium', 'utm_campaign' ), $url );
	}
}
