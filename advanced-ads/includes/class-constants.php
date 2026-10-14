<?php
/**
 * Constants.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.48.2
 */

namespace AdvancedAds;

defined( 'ABSPATH' ) || exit;

/**
 * Constants.
 */
class Constants {
	/**
	 * Rest API base.
	 *
	 * @var string
	 */
	const REST_BASE = 'advanced-ads/v1';

	/**
	 * Prefix of selectors (id, class) in the frontend — can be changed by options.
	 *
	 * @var string
	 */
	const DEFAULT_FRONTEND_PREFIX = 'advads-';

	/**
	 * Constant representing the slug for the hidden page in the Advanced Ads plugin.
	 *
	 * @var string HIDDEN_PAGE_SLUG The slug for the hidden page.
	 */
	const HIDDEN_PAGE_SLUG = 'advanced_ads_hidden_page_slug';

	/* Entity Types ------------------- */

	/**
	 * The ad entity type.
	 *
	 * @var string
	 */
	const ENTITY_AD = 'ad';

	/**
	 * The group entity type.
	 *
	 * @var string
	 */
	const ENTITY_GROUP = 'group';

	/**
	 * The placement entity type.
	 *
	 * @var string
	 */
	const ENTITY_PLACEMENT = 'placement';

	/* Post Types and Taxonomies Slugs ------------------- */

	/**
	 * The ad post type slug.
	 *
	 * @var string
	 */
	const POST_TYPE_AD = 'advanced_ads';

	/**
	 * The placement post type slug.
	 *
	 * @var string
	 */
	const POST_TYPE_PLACEMENT = 'advanced_ads_plcmnt';

	/**
	 * The group taxonomy slug.
	 *
	 * @var string
	 */
	const TAXONOMY_GROUP = 'advanced_ads_groups';

	/* Post Types Status ------------------- */

	/**
	 * Ad post expired status
	 *
	 * @var string
	 */
	const AD_STATUS_EXPIRED = 'advanced_ads_expired';

	/**
	 * Ad post expiring status
	 *
	 * @var string
	 */
	const AD_STATUS_EXPIRING = 'advanced_ads_expiring';

	/* Cron Jobs Hooks ------------------- */

	/**
	 * Ad expiration cron job hook.
	 *
	 * @var string
	 */
	const CRON_JOB_AD_EXPIRATION = 'advanced-ads-ad-expiration';

	/**
	 * Anonymous usage-data send cron hook.
	 *
	 * @var string
	 */
	const CRON_JOB_DATA_USAGE = 'advanced-ads-data-usage';

	/**
	 * License expiry: one month before expiration.
	 *
	 * @var string
	 */
	const CRON_JOB_LICENSE_EXPIRY_ONE_MONTH = 'advanced-ads-license-expiry-one-month';

	/**
	 * License expiry: ten days before expiration.
	 *
	 * @var string
	 */
	const CRON_JOB_LICENSE_EXPIRY_TEN_DAYS = 'advanced-ads-license-expiry-ten-days';

	/**
	 * License expiry: two hours after expiration (grace).
	 *
	 * @var string
	 */
	const CRON_JOB_LICENSE_EXPIRY_GRACE = 'advanced-ads-license-expiry-grace';

	/**
	 * Action Scheduler group for Advanced Ads cron jobs.
	 *
	 * @var string
	 */
	const CRON_GROUP_ADVANCED_ADS = 'advanced_ads';

	/**
	 * Legacy daily license validate cron hook (unschedule on deactivate only).
	 *
	 * @var string
	 */
	const CRON_JOB_LICENSE_VALIDATE = 'advanced-ads-license-validate';

	/**
	 * License expiry cron hooks mapped to milestone level keys.
	 *
	 * @var array<string, string>
	 */
	const LICENSE_EXPIRY_CRONS = [
		self::CRON_JOB_LICENSE_EXPIRY_ONE_MONTH => 'month',
		self::CRON_JOB_LICENSE_EXPIRY_TEN_DAYS  => 'ten_days',
		self::CRON_JOB_LICENSE_EXPIRY_GRACE     => 'grace',
	];

	/* Meta keys ------------------- */

	/**
	 * Ad metakey for expiry time.
	 *
	 * @var string
	 */
	const AD_META_EXPIRATION_TIME = 'advanced_ads_expiration_time';

	/**
	 * Ad metakey for group ids.
	 *
	 * @var string
	 */
	const AD_META_GROUP_IDS = 'advanced_ads_ad_group_ids';

	/* User Meta Keys ------------------- */

	/**
	 * Wizard notice dismiss.
	 *
	 * @var string
	 */
	const USER_WIZARD_DISMISS = 'advanced-ads-notice-wizard-dismiss';

	/* Option keys ------------------- */

	/**
	 * Option key for the completion status of the wizard.
	 *
	 * @var string
	 */
	const OPTION_WIZARD_COMPLETED = '_advanced_ads_wizard_completed';

	/**
	 * Option key for wizard setup preference (recommended | advanced).
	 *
	 * @var string
	 */
	const OPTION_WIZARD_PREF = '_advanced_ads_wizard_pref';

	/**
	 * Option key for anonymous usage-data preferences.
	 *
	 * @var string
	 */
	const OPTION_DATA_USAGE = '_advanced_ads_data_usage';

	/**
	 * Option key for adblocker settings.
	 *
	 * @var string
	 */
	const OPTION_ADBLOCKER_SETTINGS = 'advanced-ads-adblocker';

	/* Entity: Group ------------------- */

	/**
	 * Default ad group weight
	 */
	const GROUP_AD_DEFAULT_WEIGHT = 10;

	/* Misc ---------------------------- */

	/**
	 * License API endpoint URL
	 *
	 * @const string
	 */
	const API_ENDPOINT = 'https://wpadvancedads.com/license-api/';

	/**
	 * AdSense new account link
	 *
	 * @const string
	 */
	const ADSENSE_NEW_ACCOUNT_LINK = 'https://www.google.com/adsense/start/?utm_source=AdvancedAdsPlugIn&utm_medium=partnerships&utm_campaign=AdvancedAdsPartner1';
}
