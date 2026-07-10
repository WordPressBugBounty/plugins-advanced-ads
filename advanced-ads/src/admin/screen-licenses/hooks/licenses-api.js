import apiFetch from '@wordpress/api-fetch';
import { dispatch, select } from '@wordpress/data';

import { STORE_NAME } from '@admin/store';

const API_PATH = '/advanced-ads/v1/licenses';
const AUTOUPDATE_API_PATH = '/advanced-ads/v1/plugin-autoupdate';
const API_URL = `${ advancedAds.endpoints.siteUrl }/wp-json${ API_PATH }`;

export function extractApiMessage( payload ) {
	if (
		! payload ||
		typeof payload !== 'object' ||
		Array.isArray( payload )
	) {
		return null;
	}

	const message = payload.message ?? payload.data?.message;

	if ( message === undefined || message === null ) {
		return null;
	}

	const text = String( message ).trim();
	if ( text === '' ) {
		return null;
	}

	return text;
}

/**
 * Pull a user-facing message from a failed apiFetch / REST error.
 *
 * @param {unknown} err Error from apiFetch.
 * @return {string|null} Message text or null when none is available.
 */
export function extractApiErrorMessage( err ) {
	if ( ! err || typeof err !== 'object' ) {
		return null;
	}

	const dataMessage = err.data?.message;
	if ( dataMessage !== undefined && dataMessage !== null ) {
		const text = String( dataMessage ).trim();
		if ( text !== '' ) {
			return text;
		}
	}

	if ( err instanceof Error ) {
		const text = String( err.message ?? '' ).trim();
		if ( text !== '' ) {
			return text;
		}
	}

	const message = err.message;
	if ( message !== undefined && message !== null ) {
		const text = String( message ).trim();
		if ( text !== '' ) {
			return text;
		}
	}

	return null;
}

function safeObject( val ) {
	return val && typeof val === 'object' ? val : {};
}

/**
 * Normalize GET/POST licenses REST payload (array legacy or object with map).
 *
 * @param {Array|Object} payload REST licenses payload.
 * @return {{ licenses: Array, appliedAddonKeyMap: Object, autoUpdateStates: Object, addonInstallStates: Object }} Normalized licenses response object.
 */
export function normalizeLicensesResponse( payload ) {
	if ( Array.isArray( payload ) ) {
		return {
			licenses: payload,
			appliedAddonKeyMap: {},
			autoUpdateStates: {},
			addonInstallStates: {},
			lastSyncAt: 0,
			expiryNoticeFlags: {},
		};
	}

	return {
		licenses: Array.isArray( payload?.licenses ) ? payload.licenses : [],
		appliedAddonKeyMap: safeObject( payload?.appliedAddonKeyMap ),
		autoUpdateStates: safeObject( payload?.autoUpdateStates ),
		addonInstallStates: safeObject( payload?.addonInstallStates ),
		lastSyncAt: payload?.lastSyncAt ?? 0,
		expiryNoticeFlags: safeObject( payload?.expiryNoticeFlags ),
	};
}

function applyLicensesPayloadToStore( payload ) {
	const {
		licenses,
		appliedAddonKeyMap,
		autoUpdateStates,
		addonInstallStates,
		lastSyncAt,
		expiryNoticeFlags,
	} = normalizeLicensesResponse( payload );
	dispatch( STORE_NAME ).setLicenses(
		licenses,
		appliedAddonKeyMap,
		autoUpdateStates,
		addonInstallStates,
		lastSyncAt,
		expiryNoticeFlags
	);
	return licenses;
}

/**
 * Toggle per-plugin auto-update (advanced-ads-{addon}-autoupdate).
 *
 * @param {string} addonId Short id (pro, tracking, …) or "main" for base plugin.
 * @param {string} state   "on" or "off".
 */
export async function togglePluginAutoUpdate( addonId, state ) {
	const response = await apiFetch( {
		path: AUTOUPDATE_API_PATH,
		method: 'POST',
		data: {
			addonId: addonId || 'main',
			state,
		},
	} );

	if (
		response?.autoUpdateStates &&
		typeof response.autoUpdateStates === 'object'
	) {
		dispatch( STORE_NAME ).setAutoUpdateStates( response.autoUpdateStates );
	}

	return response;
}

//  ----------- Internal Api call --------------- //

export function fetchLicenses() {
	return apiFetch( { url: API_URL, method: 'GET' } );
}

export function saveLicenses(
	licenses,
	{
		activate = false,
		activatingLicenseKey = '',
		activatingAddonId = '',
		installOnly = false,
		deactivatingAddonId = '',
		deactivatingLicenseKey = '',
		signal = undefined,
	} = {}
) {
	return apiFetch( {
		url: API_URL,
		method: 'POST',
		signal,
		data: {
			licenses: Array.isArray( licenses ) ? licenses : [],
			activate,
			activatingLicenseKey: activatingLicenseKey || '',
			activatingAddonId: activatingAddonId || '',
			installOnly,
			deactivatingAddonId: deactivatingAddonId || '',
			deactivatingLicenseKey: deactivatingLicenseKey || '',
		},
	} );
}

/**
 * Remove one license from the current site (Tracking, Pro, All Access, …).
 *
 * @param {string} licenseKey License key for the card being deactivated.
 * @param {Array}  licenses   Current license list for POST body.
 */
export async function deactivateLicenseOnSite( licenseKey, licenses ) {
	const saved = await saveLicenses( licenses, {
		deactivatingLicenseKey: licenseKey,
	} );
	applyLicensesPayloadToStore( saved );
	return saved;
}

/**
 * Apply license and activate one All Access add-on on this site.
 *
 * @param {string} licenseKey All Access license key.
 * @param {string} addonId    Short add-on id (pro, tracking, …).
 * @param {Array}  licenses   Current license list for POST body.
 */
export async function activateAddonFromLicense(
	licenseKey,
	addonId,
	licenses
) {
	const saved = await saveLicenses( licenses, {
		activatingLicenseKey: licenseKey,
		activatingAddonId: addonId,
	} );
	applyLicensesPayloadToStore( saved );
	return saved;
}

/**
 * Deactivate one All Access add-on plugin on this site (keeps package on disk).
 *
 * @param {string} addonId  Short add-on id (pro, tracking, …).
 * @param {Array}  licenses Current license list for POST body.
 */
export async function deactivateAddonFromLicense( addonId, licenses ) {
	const saved = await saveLicenses( licenses, {
		deactivatingAddonId: addonId,
	} );
	applyLicensesPayloadToStore( saved );
	return saved;
}

//  ----------- Store Helpers  --------------- //

export async function initLicenses() {
	const payload = await fetchLicenses();
	applyLicensesPayloadToStore( payload );
}

export async function setLicensesAndSave( licenses, options = {} ) {
	dispatch( STORE_NAME ).setLicenses( licenses );
	const saved = await saveLicenses( licenses, options );
	applyLicensesPayloadToStore( saved );
}

export async function addLicenseAndSave( license ) {
	dispatch( STORE_NAME ).addLicense( license );
	const current = select( STORE_NAME ).getLicenses();
	const saved = await saveLicenses( current );
	applyLicensesPayloadToStore( saved );
}

export async function removeLicenseAndSave( licenseId ) {
	dispatch( STORE_NAME ).removeLicense( licenseId );
	const current = select( STORE_NAME ).getLicenses();
	const saved = await saveLicenses( current );
	applyLicensesPayloadToStore( saved );
}

/**
 * Activate a license on the shop via plugin REST (shop-first on the server).
 *
 * @param {string}      licenseKey License key.
 * @param {AbortSignal} [signal]   Optional abort signal.
 * @return {Object} Licenses REST payload.
 */
export async function activateLicenseOnShop( licenseKey, signal ) {
	const licenses = select( STORE_NAME ).getLicenses();
	const saved = await saveLicenses( licenses, {
		activatingLicenseKey: licenseKey,
		signal,
	} );
	applyLicensesPayloadToStore( saved );
	return saved;
}
