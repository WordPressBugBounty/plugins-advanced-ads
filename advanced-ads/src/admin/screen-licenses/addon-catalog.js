/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Display metadata for license add-on ids (matches PHP ADDON_PLUGIN_FILES keys).
 *
 * @type {Record<string, { title: string, description: string, learnMore: string, manualUrl: string, iconId: string }>}
 */
export const LICENSE_ADDON_CATALOG = {
	pro: {
		title: __( 'Advanced Ads Pro', 'advanced-ads' ),
		description: __(
			'Take the monetization of your website to the next level.',
			'advanced-ads'
		),
		learnMore:
			'https://wpadvancedads.com/add-ons/advanced-ads-pro/?utm_source=advancedads&utm_medium=in-plugin&utm_campaign=a2-in_plugin-licenses_addons-pro_addon_learn_more',
		manualUrl:
			'https://wpadvancedads.com/manual/?utm_source=advanced-ads&utm_medium=link&utm_campaign=licenses-add-ons-manual',
		iconId: 'pro',
	},
	responsive: {
		title: __( 'AMP Ads', 'advanced-ads' ),
		description: __(
			'Integrate your ads on AMP (Accelerated Mobile Pages) and auto-convert your Google AdSense ad units for enhanced mobile performance.',
			'advanced-ads'
		),
		learnMore:
			'https://wpadvancedads.com/add-ons/responsive-ads/?utm_source=advanced-ads&utm_medium=link&utm_campaign=licenses-add-ons',
		manualUrl:
			'https://wpadvancedads.com/manual/ads-on-amp-pages/?utm_source=advanced-ads&utm_medium=link&utm_campaign=licenses-add-ons-manual',
		iconId: 'ampads',
	},
	gam: {
		title: __( 'Google Ad Manager Integration', 'advanced-ads' ),
		description: __(
			'Simplify the process of implementing ad units from Google Ad Manager swiftly and without errors.',
			'advanced-ads'
		),
		learnMore:
			'https://wpadvancedads.com/add-ons/google-ad-manager/?utm_source=advanced-ads&utm_medium=link&utm_campaign=licenses-add-ons',
		manualUrl:
			'https://wpadvancedads.com/manual/google-ad-manager-integration-manual/?utm_source=advanced-ads&utm_medium=link&utm_campaign=licenses-add-ons-manual',
		iconId: 'gam',
	},
	layer: {
		title: __( 'PopUp and Layer Ads', 'advanced-ads' ),
		description: __(
			'Capture attention with customizable pop-ups that ensure your ads and messages get noticed.',
			'advanced-ads'
		),
		learnMore:
			'https://wpadvancedads.com/add-ons/popup-and-layer-ads/?utm_source=advanced-ads&utm_medium=link&utm_campaign=licenses-add-ons',
		manualUrl:
			'https://wpadvancedads.com/manual/popup-and-layer-ads-documentation/?utm_source=advanced-ads&utm_medium=link&utm_campaign=licenses-add-ons-manual',
		iconId: 'popuplayer',
	},
	selling: {
		title: __( 'Selling Ads', 'advanced-ads' ),
		description: __(
			"Earn more money by enabling advertisers to buy ad space directly on your site's frontend.",
			'advanced-ads'
		),
		learnMore:
			'https://wpadvancedads.com/add-ons/selling-ads/?utm_source=advanced-ads&utm_medium=link&utm_campaign=licenses-add-ons',
		manualUrl:
			'https://wpadvancedads.com/manual/selling-ads/?utm_source=advanced-ads&utm_medium=link&utm_campaign=licenses-add-ons-manual',
		iconId: 'sellingads',
	},
	sticky: {
		title: __( 'Sticky Ads', 'advanced-ads' ),
		description: __(
			'Increase click rates by anchoring ads in sticky positions above, alongside, or below your website.',
			'advanced-ads'
		),
		learnMore:
			'https://wpadvancedads.com/add-ons/sticky-ads/?utm_source=advanced-ads&utm_medium=link&utm_campaign=licenses-add-ons',
		manualUrl:
			'https://wpadvancedads.com/manual/sticky-ads-documentation/?utm_source=advanced-ads&utm_medium=link&utm_campaign=licenses-add-ons-manual',
		iconId: 'stickyads',
	},
	tracking: {
		title: __( 'Tracking', 'advanced-ads' ),
		description: __(
			'Monitor your ad performance to maximize your revenue.',
			'advanced-ads'
		),
		learnMore:
			'https://wpadvancedads.com/add-ons/tracking/?utm_source=advanced-ads&utm_medium=link&utm_campaign=licenses-add-ons',
		manualUrl:
			'https://wpadvancedads.com/manual/tracking-documentation/?utm_source=advanced-ads&utm_medium=link&utm_campaign=licenses-add-ons-manual',
		iconId: 'tracking',
	},
};

/** Manual installation guide (zip upload via Plugins screen). */
export const MANUAL_INSTALL_GUIDE_URL =
	'https://wpadvancedads.com/manual/how-to-install-an-add-on/?utm_source=advanced-ads&utm_medium=link&utm_campaign=licenses-manual-install';

/**
 * @param {string} [addonId] Short add-on id for campaign tracking.
 * @return {string} Manual install documentation URL.
 */
export function getManualInstallGuideUrl( addonId = '' ) {
	if ( ! addonId ) {
		return MANUAL_INSTALL_GUIDE_URL;
	}

	const url = new URL( MANUAL_INSTALL_GUIDE_URL );
	url.searchParams.set( 'addon', addonId );
	return url.toString();
}

/** @type {string[]} Default All Access add-on ids when shop omits addons[]. */
export const ALL_ACCESS_ADDON_IDS = [
	'pro',
	'responsive',
	'gam',
	'layer',
	'selling',
	'sticky',
	'tracking',
];

/**
 * @param {string} addonId License add-on id.
 * @return {string} Icon URL under plugin assets.
 */
export function getAddonIconUrl( addonId ) {
	const catalog = LICENSE_ADDON_CATALOG[ addonId ];
	const iconId = catalog?.iconId ?? addonId;
	const base = advancedAds?.endpoints?.assetsUrl ?? '';

	return `${ base }assets/img/add-ons/aa-addons-icons-${ iconId }.svg`;
}

/**
 * Build list rows for the Included add-ons UI.
 *
 * @param {Object} license Rich license row.
 * @return {Array<object>} Array of formatted add-on rows for rendering in the UI.
 */
export function getIncludedAddonsForLicense( license ) {
	const bundleUrl = String( license?.download_url ?? '' );
	const fromApi = Array.isArray( license?.addons ) ? license.addons : [];

	const entries =
		fromApi.length > 0
			? fromApi
					.map( ( row ) => ( {
						id: String( row?.name ?? '' ).trim(),
						downloadUrl: String( row?.download_url ?? '' ).trim(),
					} ) )
					.filter( ( row ) => row.id )
			: ALL_ACCESS_ADDON_IDS.map( ( id ) => ( {
					id,
					downloadUrl: bundleUrl,
			  } ) );

	return entries.map( ( { id, downloadUrl } ) => {
		const meta = LICENSE_ADDON_CATALOG[ id ] ?? {
			title: id,
			description: '',
			learnMore:
				'https://wpadvancedads.com/add-ons/?utm_source=advanced-ads&utm_medium=link&utm_campaign=licenses-add-ons',
			manualUrl:
				'https://wpadvancedads.com/manual/?utm_source=advanced-ads&utm_medium=link&utm_campaign=licenses-add-ons-manual',
		};

		return {
			id,
			title: meta.title,
			description: meta.description,
			learnMore: meta.learnMore,
			manualUrl: meta.manualUrl,
			icon: getAddonIconUrl( id ),
			downloadUrl,
		};
	} );
}
