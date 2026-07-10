/**
 * External Dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

import { ALL_ACCESS_ADDON_IDS, LICENSE_ADDON_CATALOG } from './addon-catalog';
import { endpoints } from '@advancedAds';

export const LICENSE_PATH = '/license';

const MS_PER_DAY = 24 * 60 * 60 * 1000;

/** @type {Array<[string, string[]]>} Addon id => normalized product name labels (PHP manifest + shop aliases). */
const LICENSE_PRODUCT_ADDON_LABELS = [
	[ 'pro', [ 'pro' ] ],
	[ 'tracking', [ 'tracking' ] ],
	[ 'responsive', [ 'responsive', 'amp ads' ] ],
	[ 'gam', [ 'gam', 'google ad manager integration' ] ],
	[ 'layer', [ 'layer', 'popup and layer ads' ] ],
	[ 'selling', [ 'selling', 'selling ads' ] ],
	[ 'sticky', [ 'sticky', 'sticky ads' ] ],
];

function matchesLicenseProductLabel( normalized, label ) {
	return (
		normalized === label ||
		normalized === `advanced ads ${ label }` ||
		normalized.endsWith( ` ${ label }` )
	);
}

function normalizeLicenseStatus( status ) {
	return String( status ?? '' ).toLowerCase();
}

function toLicenseKey( licenseKey ) {
	return String( licenseKey ?? '' );
}

function getLicenseKeyFromRow( license ) {
	return toLicenseKey( license?.licenseKey );
}

function getAddonMapKey( appliedAddonKeyMap, addonId ) {
	return String( appliedAddonKeyMap?.[ addonId ] ?? '' );
}

function hasFutureExpiry( expiryDate ) {
	const parsed = parseLicenseExpiryDate( expiryDate );
	return parsed !== null && parsed.getTime() > Date.now();
}

function getAllAccessSiteContext(
	allLicenses,
	currentHostname,
	appliedAddonKeyMap
) {
	return {
		aaOnSite: isAllAccessActiveOnThisSite( allLicenses, currentHostname ),
		aaGoverns: isAllAccessGoverningAppliedMap(
			appliedAddonKeyMap,
			allLicenses
		),
	};
}

function buildShopSsoUrl( intent, params = {} ) {
	const url = new URL( `${ endpoints.shopUrl }/sso-login` );
	url.searchParams.set( 'site', endpoints.siteUrl );
	url.searchParams.set( 'intent', intent );

	for ( const [ key, value ] of Object.entries( params ) ) {
		if ( value === undefined || value === null || value === '' ) {
			continue;
		}
		if ( key === 'pricing_id' && Number( value ) <= 0 ) {
			continue;
		}
		url.searchParams.set( key, String( value ) );
	}

	return url.toString();
}

/**
 * Hostname from a site URL (no scheme or path).
 *
 * @param {string} [maybeUrl] Full or partial site URL.
 * @return {string} Hostname, or empty string.
 */
export function getHostnameFromUrl( maybeUrl ) {
	if ( ! maybeUrl || typeof maybeUrl !== 'string' ) {
		return '';
	}

	try {
		return new URL( maybeUrl ).hostname;
	} catch {
		return maybeUrl
			.replace( /^https?:\/\//i, '' )
			.replace( /\/.*$/, '' )
			.replace( /\/$/, '' );
	}
}

/**
 * Whether the license status allows activation (active/valid only).
 *
 * @param {string} [status] License status from API.
 * @return {boolean} True when the license is active or valid.
 */
export function isRichLicenseActive( status ) {
	const normalized = normalizeLicenseStatus( status );

	return normalized === 'active' || normalized === 'valid';
}

/**
 * Parse shop expiry strings (DD-MM-YYYY or ISO) for entitlement checks.
 *
 * @param {string} [raw] Expiry from API.
 * @return {Date|null} Parsed date or null when invalid.
 */
export function parseLicenseExpiryDate( raw ) {
	if ( ! raw ) {
		return null;
	}

	const value = String( raw ).trim();
	const dmy = /^(\d{1,2})-(\d{1,2})-(\d{4})$/.exec( value );
	if ( dmy ) {
		const parsed = new Date(
			Number( dmy[ 3 ] ),
			Number( dmy[ 2 ] ) - 1,
			Number( dmy[ 1 ] )
		);
		return Number.isNaN( parsed.getTime() ) ? null : parsed;
	}

	const parsed = new Date( value );
	return Number.isNaN( parsed.getTime() ) ? null : parsed;
}

/**
 * Expiry date as Unix milliseconds.
 *
 * @param {string} [raw] Expiry from API.
 * @return {number} Timestamp in ms, or 0 when invalid.
 */
export function getLicenseExpiryTimestamp( raw ) {
	const parsed = parseLicenseExpiryDate( raw );
	return parsed ? parsed.getTime() : 0;
}

/**
 * Whether expiryDate is in the past.
 *
 * @param {string} [expiryDate] Expiry from API.
 * @return {boolean} True when the expiry date is in the past.
 */
export function isLicenseDateExpired( expiryDate ) {
	const ts = getLicenseExpiryTimestamp( expiryDate );
	return ts > 0 && ts <= Date.now();
}

/**
 * Whether the license should display as expired (shop status or past date).
 *
 * @param {string} [status]     License status from API.
 * @param {string} [expiryDate] Expiry from API.
 * @return {boolean} True when shop status or expiry date indicates expired.
 */
export function isLicenseExpiredForDisplay( status, expiryDate ) {
	if ( isShopLicenseExpired( status ) ) {
		return true;
	}

	return isLicenseDateExpired( expiryDate );
}

/**
 * Whether the license expires within the given number of days.
 *
 * @param {string} [expiryDate] Expiry from API.
 * @param {number} [days]       Day threshold.
 * @return {boolean} True when the license expires within the threshold.
 */
export function isLicenseExpiringSoon( expiryDate, days = 30 ) {
	const ts = getLicenseExpiryTimestamp( expiryDate );
	const now = Date.now();
	if ( ! ts || ts <= now ) {
		return false;
	}

	return ts - now <= days * MS_PER_DAY;
}

/**
 * Whole days until expiry (0 when expired or invalid).
 *
 * @param {string} [expiryDate] Expiry from API.
 * @return {number} Whole days until expiry, or 0 when expired or invalid.
 */
export function getDaysUntilLicenseExpiry( expiryDate ) {
	const ts = getLicenseExpiryTimestamp( expiryDate );
	const now = Date.now();
	if ( ! ts || ts <= now ) {
		return 0;
	}

	return Math.ceil( ( ts - now ) / MS_PER_DAY );
}

/**
 * Whether the license grants access (active, or inactive with a valid subscription).
 *
 * @param {string} [status]     License status from API.
 * @param {string} [expiryDate] Expiry from API.
 * @return {boolean} True when the license is entitled for use.
 */
export function isRichLicenseEntitled( status, expiryDate ) {
	if ( isRichLicenseActive( status ) ) {
		return true;
	}

	const normalized = normalizeLicenseStatus( status );

	if ( normalized === 'inactive' ) {
		return ! expiryDate || hasFutureExpiry( expiryDate );
	}

	if (
		normalized === 'expired' ||
		normalized === 'invalid' ||
		normalized === 'disabled'
	) {
		return !! expiryDate && hasFutureExpiry( expiryDate );
	}

	return false;
}

/**
 * Shop row status for display (active|expired contract) before All Access / map overrides.
 *
 * @param {Object} license
 * @param {string} currentHostname
 * @return {string} Normalized shop status.
 */
export function normalizeShopRowStatus( license, currentHostname ) {
	const status = license?.status ?? '';

	if ( ! isRichLicenseEntitled( status, license?.expiryDate ) ) {
		return status;
	}

	if ( isAllAccessBundleName( license?.name ) ) {
		return isCurrentSiteActivated(
			license?.sitesActivated,
			currentHostname
		)
			? 'active'
			: status;
	}

	if ( isCurrentSiteActivatedOnLicense( license, currentHostname ) ) {
		return 'active';
	}

	return 'inactive';
}

/**
 * Whether the current site hostname appears in activated sites.
 *
 * @param {Array<{domain?: string}>} sitesActivated  Sites from license API.
 * @param {string}                   currentHostname Current site hostname.
 * @return {boolean} True when the current hostname is activated.
 */
export function isCurrentSiteActivated( sitesActivated, currentHostname ) {
	if ( ! currentHostname ) {
		return false;
	}
	return ( sitesActivated ?? [] ).some( ( row ) =>
		String( row?.domain ?? '' ).includes( currentHostname )
	);
}

/**
 * Number of sites consuming a license slot (list length or EDD activation count).
 *
 * @param {Array<{domain?: string}>} sitesActivated  Sites from license API.
 * @param {number}                   activationCount EDD activation count when provided.
 * @return {number} Sites used, from list length or EDD activation count.
 */
export function getLicenseSitesUsedCount( sitesActivated, activationCount ) {
	const listCount = ( sitesActivated ?? [] ).length;
	const count =
		typeof activationCount === 'number' && activationCount > 0
			? activationCount
			: 0;

	return Math.max( listCount, count );
}

/**
 * Whether this site is activated on this license row (active licenses only).
 *
 * @param {Object}                   license
 * @param {string}                   license.status
 * @param {Array<{domain?: string}>} license.sitesActivated
 * @param {string}                   currentHostname
 * @return {boolean} True when this license is activated on the current site.
 */
export function isCurrentSiteActivatedOnLicense( license, currentHostname ) {
	if ( ! isRichLicenseEntitled( license?.status, license?.expiryDate ) ) {
		return false;
	}

	return isCurrentSiteActivated( license?.sitesActivated, currentHostname );
}

/**
 * Whether activation is allowed for this site (slot or already on account).
 *
 * @param {Object}                   args
 * @param {string}                   args.status
 * @param {number}                   args.activationCount
 * @param {number}                   args.availableSites
 * @param {Array<{domain?: string}>} args.sitesActivated
 * @param {string}                   args.currentHostname
 * @param {string}                   [args.expiryDate]    License expiry date.
 * @return {boolean} True when activation is allowed on this site.
 */
export function canActivateOnThisSite( {
	status,
	activationCount,
	availableSites,
	sitesActivated,
	currentHostname,
	expiryDate,
} ) {
	if ( ! isRichLicenseEntitled( status, expiryDate ) ) {
		return false;
	}

	if ( isCurrentSiteActivated( sitesActivated, currentHostname ) ) {
		return true;
	}

	return (
		getLicenseSitesUsedCount( sitesActivated, activationCount ) <
		availableSites
	);
}

/**
 * Format the license date.
 *
 * @param {string} [raw] Date string from API.
 * @return {string} Formatted date, em dash if missing, or original if invalid.
 */
export function formatLicenseDate( raw ) {
	if ( ! raw ) {
		return '—';
	}
	const parsed = parseLicenseExpiryDate( raw );
	if ( ! parsed ) {
		return raw;
	}
	return parsed.toLocaleDateString( undefined, {
		year: 'numeric',
		month: 'short',
		day: 'numeric',
	} );
}

/**
 * Get the type label for the license.
 *
 * @param {string} name  License name.
 * @param {number} total Total sites allowed by the license.
 * @return {string} Display string for license type / tier.
 */
export function getTypeLabel( name, total = 0 ) {
	name = name ?? __( 'License', 'advanced-ads' );

	if ( total > 1 ) {
		return `${ name } / ${ total } ${ __( 'sites', 'advanced-ads' ) }`;
	}

	return name;
}

/**
 * Normalize a license product name for comparisons.
 *
 * @param {string} [name] License product name from API.
 * @return {string} Normalized lowercase license name.
 */
export function normalizeLicenseName( name ) {
	return String( name ?? '' )
		.toLowerCase()
		.trim()
		.replace( /\s+/g, ' ' );
}

/**
 * Whether the license name represents an All Access bundle.
 *
 * @param {string} [name] License product name from API.
 * @return {boolean} True when the name matches an All Access bundle.
 */
export function isAllAccessBundleName( name ) {
	const normalized = normalizeLicenseName( name );
	return normalized !== '' && normalized.startsWith( 'all access' );
}

/**
 * Strip site-tier suffixes from a normalized license product name (e.g. "/ 2 sites").
 *
 * @param {string} normalized Already normalized license name.
 * @return {string} Product name without tier suffix.
 */
export function stripLicenseProductTierSuffix( normalized ) {
	return String( normalized ?? '' )
		.replace( /\s*\/\s*\d+\s*sites?.*$/i, '' )
		.replace( /\s*\(\s*\d+\s*sites?\s*\).*$/i, '' )
		.trim();
}

/**
 * Map a shop license product name to a short add-on id (pro, tracking, …).
 * Aligns with PHP License_Product_Map (manifest labels + tier stripping).
 *
 * @param {string} [name] License product name from API.
 * @return {string|null} Add-on id or null when unknown / All Access row.
 */
export function addonIdForLicenseProductName( name ) {
	let normalized = normalizeLicenseName( name );
	if ( '' === normalized || normalized.startsWith( 'all access' ) ) {
		return null;
	}

	normalized = stripLicenseProductTierSuffix( normalized );

	for ( const [ id, labels ] of LICENSE_PRODUCT_ADDON_LABELS ) {
		for ( const label of labels ) {
			if ( matchesLicenseProductLabel( normalized, label ) ) {
				return id;
			}
		}
	}

	return null;
}

/**
 * Find add-on id assigned to a license key in the applied map.
 *
 * @param {string}                 licenseKey
 * @param {Object<string, string>} appliedAddonKeyMap
 * @return {string|null} Addon ID
 */
export function addonIdForLicenseKey( licenseKey, appliedAddonKeyMap ) {
	const key = toLicenseKey( licenseKey );
	if ( ! key ) {
		return null;
	}

	for ( const [ addonId, mappedKey ] of Object.entries(
		appliedAddonKeyMap ?? {}
	) ) {
		if ( String( mappedKey ) === key ) {
			return addonId;
		}
	}

	return null;
}

/**
 * Resolve add-on id for a license row (product name, then applied map).
 *
 * @param {Object}                 license
 * @param {Object<string, string>} [appliedAddonKeyMap]
 * @return {string|null} Addon ID
 */
export function resolveAddonIdForLicense( license, appliedAddonKeyMap = {} ) {
	return (
		addonIdForLicenseProductName( license?.name ) ??
		addonIdForLicenseKey( license?.licenseKey, appliedAddonKeyMap )
	);
}

/**
 * Best entitled All Access row (same rule as PHP License::find_entitled_all_access_license).
 *
 * @param {Array<{name?: string, status?: string, expiryDate?: string}>} [licenses]
 * @return {object|null} Best matching entitled All Access license or null.
 */
export function findEntitledAllAccessLicense( licenses ) {
	let best = null;

	for ( const row of licenses ?? [] ) {
		if ( ! isAllAccessBundleName( row?.name ) ) {
			continue;
		}
		if ( ! isRichLicenseEntitled( row?.status, row?.expiryDate ) ) {
			continue;
		}
		if ( ! best ) {
			best = row;
			continue;
		}
		const bestActive = isRichLicenseActive( best?.status );
		const rowActive = isRichLicenseActive( row?.status );
		if ( rowActive && ! bestActive ) {
			best = row;
		}
	}

	return best;
}

/**
 * License keys currently stored in advanced-ads-licenses (addon id => key).
 *
 * @param {Object<string, string>} [appliedAddonKeyMap]
 * @return {Set<string>} Set of applied license keys.
 */
export function getAppliedLicenseKeys( appliedAddonKeyMap ) {
	const keys = new Set();
	for ( const value of Object.values( appliedAddonKeyMap ?? {} ) ) {
		if ( value ) {
			keys.add( String( value ) );
		}
	}
	return keys;
}

/**
 * Whether the persisted map uses one All Access key for every addon (AA governs UI).
 *
 * @param {Object<string, string>} appliedAddonKeyMap Applied addon-to-license map.
 * @param {Array<object>}          allLicenses        All available licenses.
 * @return {boolean} True when a single All Access key governs all addons.
 */
export function isAllAccessGoverningAppliedMap(
	appliedAddonKeyMap,
	allLicenses
) {
	const values = [ ...getAppliedLicenseKeys( appliedAddonKeyMap ) ];
	if ( values.length < 2 ) {
		return false;
	}
	const unique = new Set( values );
	if ( unique.size !== 1 ) {
		return false;
	}
	const entitledAllAccess = findEntitledAllAccessLicense( allLicenses );
	return (
		!! entitledAllAccess &&
		String( entitledAllAccess.licenseKey ?? '' ) === values[ 0 ]
	);
}

/**
 * Whether this license key is in advanced-ads-licenses (source of truth for "active on site").
 *
 * @param {string}                 licenseKey         License key to check.
 * @param {Object<string, string>} appliedAddonKeyMap Applied addon-to-license map.
 * @return {boolean} True when the license key exists in the applied map.
 */
export function isLicenseKeyInAppliedMap( licenseKey, appliedAddonKeyMap ) {
	const key = toLicenseKey( licenseKey );
	if ( ! key ) {
		return false;
	}
	return getAppliedLicenseKeys( appliedAddonKeyMap ).has( key );
}

/**
 * Whether All Access is activated for this site in rich license data.
 *
 * @param {Array<object>} allLicenses     All available licenses.
 * @param {string}        currentHostname Current site hostname.
 * @return {boolean} True when All Access is active on the current site.
 */
export function isAllAccessActiveOnThisSite( allLicenses, currentHostname ) {
	const entitledAllAccess = findEntitledAllAccessLicense( allLicenses );
	if ( ! entitledAllAccess ) {
		return false;
	}
	return isCurrentSiteActivatedOnLicense(
		entitledAllAccess,
		currentHostname
	);
}

/**
 * Disk / plugin state for one add-on from REST addonInstallStates.
 *
 * @param {string}                 addonId
 * @param {Object<string, object>} addonInstallStates
 * @return {Object} Installation and activation state of the add-on.
 */
export function getAddonInstallState( addonId, addonInstallStates ) {
	const row = addonInstallStates?.[ addonId ];
	return {
		installed: Boolean( row?.installed ),
		active: Boolean( row?.active ),
	};
}

/**
 * Whether at least one add-on is running and assigned to this All Access key on site.
 *
 * @param {string}                 licenseKey
 * @param {Object<string, string>} appliedAddonKeyMap
 * @param {Object<string, object>} addonInstallStates
 * @return {boolean} Whether at least one assigned add-on is currently running.
 */
export function hasActiveAddonUnderAllAccessLicense(
	licenseKey,
	appliedAddonKeyMap,
	addonInstallStates
) {
	const key = toLicenseKey( licenseKey );
	if ( ! key ) {
		return false;
	}

	for ( const [ addonId, mappedKey ] of Object.entries(
		appliedAddonKeyMap ?? {}
	) ) {
		if ( String( mappedKey ) !== key ) {
			continue;
		}

		if ( getAddonInstallState( addonId, addonInstallStates ).active ) {
			return true;
		}
	}

	return false;
}

function getDisplayStatusForAllAccessLicense(
	license,
	licenseKey,
	raw,
	currentHostname,
	appliedAddonKeyMap = {},
	addonInstallStates = {}
) {
	if ( ! isRichLicenseEntitled( license?.status, license?.expiryDate ) ) {
		return raw;
	}

	const mapKeys = getAppliedLicenseKeys( appliedAddonKeyMap );
	if ( mapKeys.size === 0 ) {
		return 'inactive';
	}

	if ( ! isLicenseKeyInAppliedMap( licenseKey, appliedAddonKeyMap ) ) {
		return 'inactive';
	}

	if ( isCurrentSiteActivated( license?.sitesActivated, currentHostname ) ) {
		return 'active';
	}

	if (
		hasActiveAddonUnderAllAccessLicense(
			licenseKey,
			appliedAddonKeyMap,
			addonInstallStates
		)
	) {
		return 'active';
	}

	return 'inactive';
}

function getDisplayStatusWithAppliedMap(
	license,
	licenseKey,
	raw,
	allLicenses,
	appliedAddonKeyMap,
	addonInstallStates,
	aaOnSite
) {
	const addonId = resolveAddonIdForLicense( license, appliedAddonKeyMap );
	const inMap =
		addonId &&
		isAddonLicensedByKey( addonId, licenseKey, appliedAddonKeyMap );

	if ( ! isRichLicenseEntitled( license?.status, license?.expiryDate ) ) {
		return raw;
	}

	if ( ! inMap && isRichLicenseActive( raw ) ) {
		return 'inactive';
	}

	if (
		inMap &&
		addonId &&
		! getAddonInstallState( addonId, addonInstallStates ).active
	) {
		return 'inactive';
	}

	if (
		inMap &&
		addonId &&
		getAddonInstallState( addonId, addonInstallStates ).active &&
		raw === 'active'
	) {
		return 'active';
	}

	if (
		aaOnSite ||
		isAllAccessGoverningAppliedMap( appliedAddonKeyMap, allLicenses )
	) {
		return 'inactive';
	}

	return raw;
}

function getDisplayStatusWithoutAppliedMap(
	license,
	raw,
	currentHostname,
	appliedAddonKeyMap,
	addonInstallStates,
	aaOnSite
) {
	if ( aaOnSite && ! isAllAccessBundleName( license?.name ) ) {
		return 'inactive';
	}

	const addonId = resolveAddonIdForLicense( license, appliedAddonKeyMap );
	if (
		addonId &&
		isCurrentSiteActivatedOnLicense( license, currentHostname ) &&
		isRichLicenseActive( raw ) &&
		! getAddonInstallState( addonId, addonInstallStates ).active
	) {
		return 'inactive';
	}

	return raw;
}

/**
 * Status label for the license card (may differ from shop row when All Access governs this site).
 *
 * @param {Object}        license              License row data.
 * @param {string}        license.name         License product name.
 * @param {string}        license.status       License status.
 * @param {Array<object>} allLicenses          All available licenses.
 * @param {string}        currentHostname      Current site hostname.
 * @param {Object}        [appliedAddonKeyMap] Applied addon-to-license map.
 * @param {Object}        [addonInstallStates] Per-add-on installed/active flags from REST.
 * @return {string} Display status for the license card.
 */
export function getDisplayLicenseStatus(
	license,
	allLicenses,
	currentHostname,
	appliedAddonKeyMap = {},
	addonInstallStates = {}
) {
	const raw = normalizeShopRowStatus(
		license,
		currentHostname,
		appliedAddonKeyMap,
		addonInstallStates
	);
	const licenseKey = getLicenseKeyFromRow( license );

	if ( isAllAccessBundleName( license?.name ) ) {
		return getDisplayStatusForAllAccessLicense(
			license,
			licenseKey,
			raw,
			currentHostname,
			appliedAddonKeyMap,
			addonInstallStates
		);
	}

	const appliedKeys = getAppliedLicenseKeys( appliedAddonKeyMap );
	// eslint-disable-next-line @wordpress/no-unused-vars-before-return
	const { aaOnSite } = getAllAccessSiteContext(
		allLicenses,
		currentHostname,
		appliedAddonKeyMap
	);

	if ( appliedKeys.size === 0 ) {
		if ( ! isRichLicenseEntitled( license?.status, license?.expiryDate ) ) {
			return raw;
		}

		if ( aaOnSite && ! isAllAccessBundleName( license?.name ) ) {
			return 'inactive';
		}

		return raw;
	}

	if ( appliedKeys.size > 0 ) {
		return getDisplayStatusWithAppliedMap(
			license,
			licenseKey,
			raw,
			allLicenses,
			appliedAddonKeyMap,
			addonInstallStates,
			aaOnSite
		);
	}

	return getDisplayStatusWithoutAppliedMap(
		license,
		raw,
		currentHostname,
		appliedAddonKeyMap,
		addonInstallStates,
		aaOnSite
	);
}

export function isLicenseAppliedOnThisSite(
	license,
	allLicenses,
	currentHostname,
	appliedAddonKeyMap = {},
	addonInstallStates = {}
) {
	const licenseKey = getLicenseKeyFromRow( license );
	if (
		! licenseKey ||
		! isRichLicenseEntitled( license?.status, license?.expiryDate )
	) {
		return false;
	}

	if ( isAllAccessBundleName( license?.name ) ) {
		if ( getAppliedLicenseKeys( appliedAddonKeyMap ).size === 0 ) {
			return false;
		}
		if ( ! isLicenseKeyInAppliedMap( licenseKey, appliedAddonKeyMap ) ) {
			return false;
		}

		if ( isCurrentSiteActivatedOnLicense( license, currentHostname ) ) {
			return true;
		}

		return hasActiveAddonUnderAllAccessLicense(
			licenseKey,
			appliedAddonKeyMap,
			addonInstallStates
		);
	}

	const { aaOnSite, aaGoverns } = getAllAccessSiteContext(
		allLicenses,
		currentHostname,
		appliedAddonKeyMap
	);
	const addonId = resolveAddonIdForLicense( license, appliedAddonKeyMap );

	if ( ! addonId ) {
		return aaOnSite || aaGoverns
			? false
			: isCurrentSiteActivatedOnLicense( license, currentHostname );
	}

	const licensedByMap = isAddonLicensedByKey(
		addonId,
		licenseKey,
		appliedAddonKeyMap
	);
	const pluginRunning = getAddonInstallState(
		addonId,
		addonInstallStates
	).active;

	if ( licensedByMap ) {
		return (
			pluginRunning &&
			isCurrentSiteActivatedOnLicense( license, currentHostname )
		);
	}

	if ( aaOnSite || aaGoverns ) {
		return false;
	}

	return (
		isCurrentSiteActivatedOnLicense( license, currentHostname ) &&
		pluginRunning
	);
}

/**
 * Whether this add-on uses the given license key on this site (map only).
 *
 * @param {string}                 addonId
 * @param {string}                 licenseKey
 * @param {Object<string, string>} appliedAddonKeyMap
 * @return {boolean} Whether the add-on is assigned to the given license key.
 */
export function isAddonLicensedByKey(
	addonId,
	licenseKey,
	appliedAddonKeyMap
) {
	if ( ! addonId || ! licenseKey ) {
		return false;
	}

	return (
		getAddonMapKey( appliedAddonKeyMap, addonId ) ===
		toLicenseKey( licenseKey )
	);
}

/**
 * Whether this add-on is licensed on site by a different license than the All Access card key.
 *
 * @param {string}                 addonId
 * @param {string}                 allAccessLicenseKey
 * @param {Object<string, string>} appliedAddonKeyMap
 * @return {boolean} Whether the add-on is assigned to a different license key.
 */
export function isAddonManagedByOtherLicense(
	addonId,
	allAccessLicenseKey,
	appliedAddonKeyMap
) {
	if ( ! addonId || ! allAccessLicenseKey ) {
		return false;
	}

	const assignedKey = getAddonMapKey( appliedAddonKeyMap, addonId );
	return (
		assignedKey !== '' &&
		assignedKey !== toLicenseKey( allAccessLicenseKey )
	);
}

/**
 * Display name for the license row that owns an add-on key on this site.
 *
 * @param {string}        licenseKey
 * @param {Array<object>} allLicenses
 * @return {string} The display name of the license that owns the add-on key.
 */
export function getLicenseNameForKey( licenseKey, allLicenses ) {
	const key = toLicenseKey( licenseKey );
	if ( ! key ) {
		return '';
	}

	const row = ( allLicenses ?? [] ).find(
		( license ) => getLicenseKeyFromRow( license ) === key
	);

	return String( row?.name ?? '' );
}

/**
 * Row status for Included add-ons UI.
 *
 * @param {string}                 addonId
 * @param {Object<string, object>} addonInstallStates
 * @param {boolean}                isApplied          License key applied for this add-on.
 * @return {string} UI status of the add-on row.
 */
export function getAddonRowStatus( addonId, addonInstallStates, isApplied ) {
	const { installed, active } = getAddonInstallState(
		addonId,
		addonInstallStates
	);

	if ( active && isApplied ) {
		return 'installed';
	}

	if ( active ) {
		return 'running';
	}

	if ( installed ) {
		return 'ready';
	}

	return 'needs_install';
}

/**
 * Counts per add-on row state for the All Access license card summary.
 *
 * @param {string[]}               addonIds
 * @param {Object<string, object>} addonInstallStates
 * @param {Object<string, string>} appliedAddonKeyMap
 * @param {string}                 licenseKey
 * @return {{
 *   total: number,
 *   installed: number,
 *   ready: number,
 *   pending: number,
 *   state: 'none'|'partial'|'complete'
 * }} Summary counts and overall license state across all add-ons.
 */
export function getAllAccessAddonsInstallSummary(
	addonIds,
	addonInstallStates,
	appliedAddonKeyMap,
	licenseKey
) {
	let installed = 0;
	let ready = 0;
	let pending = 0;

	for ( const addonId of addonIds ) {
		const isLicensedByThisKey = isAddonLicensedByKey(
			addonId,
			licenseKey,
			appliedAddonKeyMap
		);
		const rowStatus = getAddonRowStatus(
			addonId,
			addonInstallStates,
			isLicensedByThisKey
		);

		if ( rowStatus === 'installed' ) {
			installed++;
		} else if ( rowStatus === 'ready' ) {
			ready++;
		} else {
			pending++;
		}
	}

	const total = addonIds.length;
	let state = 'partial';
	if ( installed === total && total > 0 ) {
		state = 'complete';
	} else if ( installed === 0 && ready === 0 ) {
		state = 'none';
	}

	return {
		total,
		installed,
		ready,
		pending,
		state,
	};
}

/**
 * Human-readable All Access add-on setup label for the license card.
 *
 * @param {{ total: number, installed: number, ready: number, pending: number, state: string }} summary
 * @return {string} Human-readable setup label describing the All Access add-on state.
 */
export function getAllAccessAddonsSummaryLabel( summary ) {
	const { total, installed, ready, state } = summary;

	if ( state === 'complete' ) {
		return __( 'All installed', 'advanced-ads' );
	}

	if ( state === 'none' ) {
		return sprintf(
			/* translators: %d: Total number of included add-ons. */
			__( 'None installed (%d add-ons)', 'advanced-ads' ),
			total
		);
	}

	if ( ready > 0 ) {
		return sprintf(
			/* translators: 1: Number installed, 2: Total add-ons, 3: Number downloaded but not finished. */
			__( '%1$d of %2$d installed (%3$d ready)', 'advanced-ads' ),
			installed,
			total,
			ready
		);
	}

	return sprintf(
		/* translators: 1: Number installed, 2: Total add-ons. */
		__( '%1$d of %2$d installed', 'advanced-ads' ),
		installed,
		total
	);
}

/**
 * Whether the user can activate this license row on the current site.
 *
 * @param {Object}        license              License row data.
 * @param {Array<object>} allLicenses          All available licenses.
 * @param {Object}        args                 Same fields as canActivateOnThisSite.
 * @param {Object}        [appliedAddonKeyMap] Applied addon-to-license map.
 * @return {boolean} True when the license can be activated on this site.
 */
export function canActivateLicenseRowOnSite(
	license,
	allLicenses,
	args,
	appliedAddonKeyMap = {}
) {
	const { status, expiryDate } = args;

	if ( ! isRichLicenseEntitled( status, expiryDate ) ) {
		return false;
	}

	const licenseKey = getLicenseKeyFromRow( license );
	const addonId = resolveAddonIdForLicense( license, appliedAddonKeyMap );

	// This license already owns its add-on on site (Deactivate path, not activate).
	if (
		addonId &&
		licenseKey &&
		isAddonLicensedByKey( addonId, licenseKey, appliedAddonKeyMap )
	) {
		return false;
	}

	if ( isAllAccessBundleName( license?.name ) ) {
		return canActivateOnThisSite( args );
	}

	// Single-product: always allow shop activate when entitled (slot limits enforced by shop).
	return true;
}

/**
 * Whether auto-updates are enabled for one add-on id (or main).
 *
 * @param {string}                 addonId          Short id or "main".
 * @param {Object<string, string>} autoUpdateStates Map from REST.
 * @return {boolean} True if auto-updates are enabled.
 */
export function isPluginAutoUpdateEnabled( addonId, autoUpdateStates ) {
	const key = addonId || 'main';
	return autoUpdateStates?.[ key ] === 'on';
}

/**
 * Human-readable auto-update summary for a license row.
 *
 * @param {string}                 licenseName      License product name.
 * @param {Object<string, string>} autoUpdateStates Map from REST.
 * @return {string} Localized On, Off, or Mixed.
 */
export function getAutoUpdateDisplayLabel( licenseName, autoUpdateStates ) {
	if ( isAllAccessBundleName( licenseName ) ) {
		const states = ALL_ACCESS_ADDON_IDS.map(
			( id ) => autoUpdateStates?.[ id ] ?? 'off'
		);
		const allOn = states.every( ( state ) => state === 'on' );
		const allOff = states.every( ( state ) => state === 'off' );

		if ( allOn ) {
			return __( 'On', 'advanced-ads' );
		}
		if ( allOff ) {
			return __( 'Off', 'advanced-ads' );
		}

		return __( 'Mixed', 'advanced-ads' );
	}

	const addonId = addonIdForLicenseProductName( licenseName );
	const key = addonId || 'main';

	return isPluginAutoUpdateEnabled( key, autoUpdateStates )
		? __( 'On', 'advanced-ads' )
		: __( 'Off', 'advanced-ads' );
}

/**
 * Add-on ids whose auto-update toggles apply to this license row.
 *
 * @param {string} licenseName License product name.
 * @return {string[]} Short add-on ids or ["main"].
 */
export function getAutoUpdateAddonIdsForLicense( licenseName ) {
	if ( isAllAccessBundleName( licenseName ) ) {
		return [ ...ALL_ACCESS_ADDON_IDS ];
	}

	const addonId = addonIdForLicenseProductName( licenseName );
	return [ addonId || 'main' ];
}

/**
 * Short label for which plugin(s) an auto-update row controls.
 *
 * @param {string} licenseName License product name.
 * @return {string} Label for the auto-update row.
 */
export function getAutoUpdateScopeLabel( licenseName ) {
	if ( isAllAccessBundleName( licenseName ) ) {
		return __( 'All add-ons', 'advanced-ads' );
	}

	const addonIds = getAutoUpdateAddonIdsForLicense( licenseName );
	const titles = addonIds.map( ( id ) => {
		if ( id === 'main' ) {
			return __( 'Advanced Ads', 'advanced-ads' );
		}
		return LICENSE_ADDON_CATALOG[ id ]?.title ?? id;
	} );

	return titles.join( ', ' );
}

/**
 * Shop checkout URL that adds a plan to cart for in-plugin purchase.
 *
 * @param {Object} params
 * @param {number} params.downloadId    EDD download post ID.
 * @param {number} params.pricingId     EDD variable price ID.
 * @param {string} [params.utmCampaign] Optional utm_campaign query param.
 * @return {string} Shop checkout add-to-cart URL with site return param.
 */
export function buildShopCheckoutUrl( { downloadId, pricingId, utmCampaign } ) {
	const url = new URL( `${ endpoints.shopUrl }/checkout/` );
	url.searchParams.set( 'edd_action', 'add_to_cart' );
	url.searchParams.set( 'download_id', String( downloadId ) );
	if ( Number( pricingId ) > 0 ) {
		url.searchParams.set( 'edd_options[price_id]', String( pricingId ) );
	}
	url.searchParams.set( 'site', endpoints.siteUrl );
	if ( utmCampaign ) {
		url.searchParams.set( 'utm_source', 'advancedads' );
		url.searchParams.set( 'utm_medium', 'in-plugin' );
		url.searchParams.set( 'utm_campaign', utmCampaign );
	}
	return url.toString();
}

/**
 * Direct EDD checkout URL for license upgrade (sl_license_upgrade).
 *
 * @param {Object} params
 * @param {number} params.licenseId     Current EDD license post ID.
 * @param {number} params.upgradeId     EDD upgrade path id (1 = AA single, 2 = AA 5-site).
 * @param {string} [params.utmCampaign] Optional utm_campaign query param.
 * @return {string} Shop checkout upgrade URL.
 */
export function buildShopEddUpgradeUrl( {
	licenseId,
	upgradeId,
	utmCampaign,
} ) {
	const url = new URL( `${ endpoints.shopUrl }/checkout/` );
	url.searchParams.set( 'edd_action', 'sl_license_upgrade' );
	url.searchParams.set( 'license_id', String( licenseId ) );
	url.searchParams.set( 'upgrade_id', String( upgradeId ) );
	url.searchParams.set( 'site', endpoints.siteUrl );

	if ( utmCampaign ) {
		url.searchParams.set( 'utm_source', 'advancedads' );
		url.searchParams.set( 'utm_medium', 'in-plugin' );
		url.searchParams.set( 'utm_campaign', utmCampaign );
	}

	return url.toString();
}

/**
 * Shop checkout bridge URL for license renewal (replaces sso-login).
 *
 * @param {Object} params
 * @param {number} params.licenseId     EDD license post ID.
 * @param {string} [params.utmCampaign] Optional utm_campaign query param.
 * @return {string} Checkout bridge URL.
 */
export function buildShopRenewalCheckoutUrl( { licenseId, utmCampaign } ) {
	const url = new URL( `${ endpoints.shopUrl }/checkout/` );
	url.searchParams.set( 'site', endpoints.siteUrl );
	url.searchParams.set( 'intent', 'renew' );
	url.searchParams.set( 'license_id', String( licenseId ) );

	if ( utmCampaign ) {
		url.searchParams.set( 'utm_source', 'advancedads' );
		url.searchParams.set( 'utm_medium', 'in-plugin' );
		url.searchParams.set( 'utm_campaign', utmCampaign );
	}

	return url.toString();
}

/**
 * Resolve checkout IDs for a modal plan key.
 *
 * @param {string}                                                     planId  Plan id from PricingTable PLANS.
 * @param {Array<{id: string, downloadId: number, pricingId: number}>} [plans] Plan list.
 * @return {{ downloadId: number, pricingId: number }|null} EDD download and price IDs, or null when unknown.
 */
export function getCheckoutIdsForPlan( planId, plans ) {
	const plan = ( plans ?? [] ).find( ( entry ) => entry.id === planId );
	if ( ! plan?.downloadId ) {
		return null;
	}
	if ( 'all-access-five' === plan.id && 2 !== plan.pricingId ) {
		return null;
	}
	return {
		downloadId: Number( plan.downloadId ),
		pricingId: Number( plan.pricingId ) || 0,
	};
}

/**
 * Navigate to shop checkout for the given plan.
 *
 * @param {string}                                                     planId Plan id from PricingTable.
 * @param {Array<{id: string, downloadId: number, pricingId: number}>} plans  Plan list from PLANS.
 * @return {boolean} False when plan IDs are missing.
 */
export function startShopCheckoutForPlan( planId, plans ) {
	const ids = getCheckoutIdsForPlan( planId, plans );
	if ( ! ids ) {
		return false;
	}
	const plan = ( plans ?? [] ).find( ( entry ) => entry.id === planId );
	globalThis.location.href = buildShopCheckoutUrl( {
		...ids,
		utmCampaign: plan?.utmCampaign ?? '',
	} );
	return true;
}

/**
 * Navigate to shop EDD upgrade checkout for the given plan and license.
 *
 * @param {string}                                  planId    Plan id from PricingTable.
 * @param {number}                                  licenseId EDD license post ID.
 * @param {Array<{id: string, upgradeId?: number}>} plans     Plan list from PLANS.
 * @return {boolean} False when plan or licenseId are missing.
 */
export function startShopUpgradeForPlan( planId, licenseId, plans ) {
	const plan = ( plans ?? [] ).find( ( entry ) => entry.id === planId );
	const upgradeId = plan?.upgradeId;
	if ( ! upgradeId || ! licenseId ) {
		return false;
	}
	globalThis.location.href = buildShopEddUpgradeUrl( {
		licenseId,
		upgradeId,
		utmCampaign: plan?.utmCampaign ?? '',
	} );
	return true;
}

/**
 * Whether the shop license row is expired (EDD renewal eligibility).
 *
 * @param {string} [status] License status from exchange payload.
 * @return {boolean} True when status is expired on the shop.
 */
export function isShopLicenseExpired( status ) {
	return normalizeLicenseStatus( status ) === 'expired';
}

/**
 * Shop /sso-login URL that starts license renewal intent for an existing license row.
 *
 * @param {Object} params
 * @param {number} params.licenseId EDD license post ID from exchange payload.
 * @return {string} Shop sso-login URL with renew intent query args.
 */
export function buildShopRenewalUrl( { licenseId } ) {
	return buildShopSsoUrl( 'renew', {
		license_id: licenseId,
	} );
}

/**
 * Navigate to shop renewal checkout for the given license.
 *
 * @param {number} licenseId EDD license post ID.
 * @return {boolean} False when licenseId is missing.
 */
export function startShopRenewalForLicense( licenseId ) {
	if ( ! licenseId ) {
		return false;
	}
	globalThis.location.href = buildShopRenewalCheckoutUrl( {
		licenseId,
		utmCampaign: 'a2-in_plugin-licenses_addons-renew',
	} );
	return true;
}

/**
 * Target plan ids allowed when upgrading from the current plan.
 *
 * @param {'pro'|'all-access-single'|'all-access-five'|null} currentPlanId
 * @return {string[]} Allowed PricingTable plan ids (empty = no upgrade).
 */
export function getAllowedUpgradePlanIds( currentPlanId ) {
	switch ( currentPlanId ) {
		case 'pro':
			return [ 'all-access-single', 'all-access-five' ];
		case 'all-access-single':
			return [ 'all-access-five' ];
		default:
			return [];
	}
}

/**
 * Whether the license row has any valid upgrade target.
 *
 * @param {'pro'|'all-access-single'|'all-access-five'|null} currentPlanId
 * @return {boolean} return plan ids
 */
export function canUpgradeLicensePlan( currentPlanId ) {
	return getAllowedUpgradePlanIds( currentPlanId ).length > 0;
}

/**
 * Display label for the current plan in the upgrade pricing modal header.
 *
 * @param {Object} license License row from exchange payload.
 * @return {string} Human-readable plan name.
 */
export function resolvePlanLabelForLicense( license ) {
	const name = license?.name ?? '';
	if ( isAllAccessBundleName( name ) ) {
		return ( license?.availableSites ?? 0 ) > 1
			? __( 'All Access (5 sites)', 'advanced-ads' )
			: __( 'All Access', 'advanced-ads' );
	}
	return name || __( 'License', 'advanced-ads' );
}

/**
 * Pricing table plan id for the license row (upgrade modal current-plan match).
 *
 * @param {Object} license License row from exchange payload.
 * @return {Object} object of license
 */
export function resolvePlanIdForLicense( license ) {
	if ( ! license ) {
		return null;
	}

	const name = license.name ?? '';

	if ( isAllAccessBundleName( name ) ) {
		return ( license.availableSites ?? 0 ) > 1
			? 'all-access-five'
			: 'all-access-single';
	}

	if ( addonIdForLicenseProductName( name ) === 'pro' ) {
		return 'pro';
	}

	return null;
}

/**
 * Normalize checkout_intent from the post-checkout return URL.
 *
 * @param {string} [queryIntent] Raw query value.
 * @return {'buy'|'upgrade'|'renew'} Normalized checkout intent.
 */
export function normalizeCheckoutIntent( queryIntent ) {
	const intent = String( queryIntent ?? '' ).toLowerCase();

	if ( intent === 'upgrade' || intent === 'renew' ) {
		return intent;
	}

	return 'buy';
}

/**
 * License row to evaluate for post-checkout setup subtitle copy.
 *
 * @param {Array<object>} [licenses]         Rich license list.
 * @param {string|number} [licenseIdFromUrl] EDD SL license post ID from return URL.
 * @return {object|null}                       Matching license row or null.
 */
export function resolvePostCheckoutLicenseRow( licenses, licenseIdFromUrl ) {
	const list = licenses ?? [];
	const licenseId = parseInt( String( licenseIdFromUrl ?? '' ), 10 );

	if ( licenseId > 0 ) {
		const match = list.find(
			( row ) => Number( row?.licenseId ) === licenseId
		);
		if ( match ) {
			return match;
		}
	}

	const allAccess = findEntitledAllAccessLicense( list );
	if ( allAccess ) {
		return allAccess;
	}

	let best = null;
	let bestId = -1;

	for ( const row of list ) {
		if ( isAllAccessBundleName( row?.name ) ) {
			continue;
		}
		if ( ! isRichLicenseEntitled( row?.status, row?.expiryDate ) ) {
			continue;
		}

		const id = Number( row?.licenseId ?? 0 );
		if ( id > bestId ) {
			bestId = id;
			best = row;
		}
	}

	return best;
}

/**
 * Post-checkout subtitle bucket (license-level setup on this site).
 *
 * @param {object|null}            row                License row from exchange.
 * @param {Array<object>}          licenses           Full license list.
 * @param {string}                 hostname           Current site hostname.
 * @param {Object<string, string>} appliedAddonKeyMap Addon id => license key.
 * @param {Object<string, object>} addonInstallStates Per-addon install flags.
 * @return {'ready'|'activate'|'download'}           Setup state for notice subtitle.
 */
export function getPostCheckoutSetupState(
	row,
	licenses,
	hostname,
	appliedAddonKeyMap = {},
	addonInstallStates = {}
) {
	if ( ! row || ! hostname ) {
		return 'download';
	}

	if ( isAllAccessBundleName( row?.name ) ) {
		return isAllAccessActiveOnThisSite( licenses, hostname )
			? 'ready'
			: 'download';
	}

	if ( isCurrentSiteActivatedOnLicense( row, hostname ) ) {
		return 'ready';
	}

	const addonId = resolveAddonIdForLicense( row, appliedAddonKeyMap );
	if ( addonId ) {
		const { installed, active } = getAddonInstallState(
			addonId,
			addonInstallStates
		);
		if ( installed && ! active ) {
			return 'activate';
		}
	}

	return 'download';
}

/**
 * Post-checkout success notice title and body (copy only).
 *
 * @param {Object}                 args
 * @param {string}                 [args.checkoutIntent]     URL checkout_intent.
 * @param {Array<object>}          [args.licenses]           Rich license list.
 * @param {string|number}          [args.licenseId]          URL license_id.
 * @param {string}                 [args.hostname]           Current site hostname.
 * @param {Object<string, string>} [args.appliedAddonKeyMap]
 * @param {Object<string, object>} [args.addonInstallStates]
 * @return {{ title: string, message: string }} Notice title and message.
 */
export function buildPostCheckoutNoticeCopy( {
	checkoutIntent,
	licenses,
	licenseId,
	hostname,
	appliedAddonKeyMap = {},
	addonInstallStates = {},
} ) {
	const intent = normalizeCheckoutIntent( checkoutIntent );
	const titles = {
		buy: __( 'License purchased successfully', 'advanced-ads' ),
		upgrade: __( 'License upgraded successfully', 'advanced-ads' ),
		renew: __( 'License renewed successfully', 'advanced-ads' ),
	};
	const messages = {
		ready: __( 'Your license is ready.', 'advanced-ads' ),
		activate: __(
			"Your license is ready. Use 'activate' to set it up on this site.",
			'advanced-ads'
		),
		download: __(
			"Your license is ready. Use 'Download and activate' to set it up on this site.",
			'advanced-ads'
		),
	};

	const row = resolvePostCheckoutLicenseRow( licenses, licenseId );
	const setupState = row
		? getPostCheckoutSetupState(
				row,
				licenses,
				hostname,
				appliedAddonKeyMap,
				addonInstallStates
		  )
		: 'download';

	return {
		title: titles[ intent ] ?? titles.buy,
		message: messages[ setupState ] ?? messages.download,
	};
}
