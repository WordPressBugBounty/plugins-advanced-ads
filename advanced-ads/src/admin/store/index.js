import { createReduxStore, register } from '@wordpress/data';

const STORE_NAME = 'advanced-ads/store';

const DEFAULT_STATE = {
	licenses: [],
	appliedAddonKeyMap: {},
	autoUpdateStates: {},
	addonInstallStates: {},
	lastSyncAt: 0,
	expiryNoticeFlags: {},
};

const actions = {
	setLicenses(
		licenses,
		appliedAddonKeyMap = {},
		autoUpdateStates = {},
		addonInstallStates = {},
		lastSyncAt = 0,
		expiryNoticeFlags = {}
	) {
		return {
			type: 'SET_LICENSES',
			licenses,
			appliedAddonKeyMap,
			autoUpdateStates,
			addonInstallStates,
			lastSyncAt,
			expiryNoticeFlags,
		};
	},
	setAutoUpdateStates( autoUpdateStates ) {
		return {
			type: 'SET_AUTO_UPDATE_STATES',
			autoUpdateStates,
		};
	},
	addLicense( license ) {
		return {
			type: 'ADD_LICENSE',
			license,
		};
	},
	removeLicense( licenseId ) {
		return {
			type: 'REMOVE_LICENSE',
			licenseId,
		};
	},
};

function reducer( state = DEFAULT_STATE, action ) {
	switch ( action.type ) {
		case 'SET_LICENSES':
			return {
				...state,
				licenses: action.licenses,
				appliedAddonKeyMap:
					action.appliedAddonKeyMap ?? state.appliedAddonKeyMap,
				autoUpdateStates:
					action.autoUpdateStates ?? state.autoUpdateStates,
				addonInstallStates:
					action.addonInstallStates ?? state.addonInstallStates,
				lastSyncAt: action.lastSyncAt ?? state.lastSyncAt,
				expiryNoticeFlags:
					action.expiryNoticeFlags ?? state.expiryNoticeFlags,
			};
		case 'SET_AUTO_UPDATE_STATES':
			return {
				...state,
				autoUpdateStates: action.autoUpdateStates ?? {},
			};
		case 'ADD_LICENSE':
			return {
				...state,
				licenses: [ ...state.licenses, action.license ],
			};
		case 'REMOVE_LICENSE':
			return {
				...state,
				licenses: state.licenses.filter(
					( license ) => license.licenseId !== action.licenseId
				),
			};
		default:
			return state;
	}
}

const selectors = {
	getLicenses( state ) {
		return state.licenses;
	},
	getAppliedAddonKeyMap( state ) {
		return state.appliedAddonKeyMap;
	},
	hasLicenses( state ) {
		return state.licenses.length > 0;
	},
	getAutoUpdateStates( state ) {
		return state.autoUpdateStates ?? {};
	},
	getAddonInstallStates( state ) {
		return state.addonInstallStates ?? {};
	},
	getLastSyncAt( state ) {
		return state.lastSyncAt ?? 0;
	},
	getExpiryNoticeFlags( state ) {
		return state.expiryNoticeFlags ?? {};
	},
	getLicenseById( state, licenseId ) {
		return state.licenses.find(
			( license ) => license.licenseId === licenseId
		);
	},
};

export const store = createReduxStore( STORE_NAME, {
	reducer,
	actions,
	selectors,
} );

register( store );

export { STORE_NAME };
