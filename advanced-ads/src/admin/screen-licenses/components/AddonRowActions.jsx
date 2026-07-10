/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';

/**
 * External Dependencies
 */
import { Check, AlertCircle, Loader2 } from 'lucide-react';

/**
 * Internal Dependencies
 */
import { STORE_NAME } from '@admin/store';
import { getManualInstallGuideUrl } from '../addon-catalog';
import {
	activateAddonFromLicense,
	deactivateAddonFromLicense,
} from '../hooks/licenses-api';
import { getAddonInstallState, getAddonRowStatus } from '../utils';
import { publishLicenseWarningNotice } from './LicenseNotices';

export function AddonRowActions( {
	addonId,
	licenseKey,
	licenses,
	downloadUrl = '',
	isApplied = false,
	disabled = false,
	managedByOtherLicense = false,
	addonInstallStates = {},
	noticesContext,
	isInstalling = false,
	installFailed = false,
	onInstallStart,
	onInstallEnd,
} ) {
	const [ isWorking, setIsWorking ] = useState( false );
	const [ isDeactivating, setIsDeactivating ] = useState( false );
	const { createErrorNotice, removeNotice } = useDispatch( noticesStore );
	const licensesFromStore = useSelect(
		( select ) => select( STORE_NAME ).getLicenses(),
		[]
	);
	const licenseList =
		licensesFromStore.length > 0 ? licensesFromStore : licenses;

	const rowStatus = getAddonRowStatus(
		addonId,
		addonInstallStates,
		isApplied
	);
	const { installed } = getAddonInstallState( addonId, addonInstallStates );
	// Active + Deactivate only when this All Access key owns the add-on (isApplied), not when the plugin is merely running.
	const isActiveUnderAllAccess = rowStatus === 'installed';
	const isBusy = isInstalling || isWorking || isDeactivating;
	// Install/download requires AA entitled; Activate/Deactivate on a row stay available when entitled.
	const installActionsDisabled = disabled;
	const takeOverFromOtherLicense =
		managedByOtherLicense && ! isApplied && installed;

	function showAddonErrorNotice( message ) {
		const noticeId = `advanced-ads/addon-error-${ addonId }`;

		removeNotice( noticeId, noticesContext );
		publishLicenseWarningNotice( createErrorNotice, {
			id: noticeId,
			context: noticesContext,
			message,
		} );
	}

	async function handleActivateUnderAllAccess() {
		if ( isBusy || ! licenseKey || ! addonId ) {
			return;
		}

		setIsWorking( true );

		try {
			await activateAddonFromLicense( licenseKey, addonId, licenseList );
		} catch ( err ) {
			const message =
				err instanceof Error
					? err.message
					: __( 'Activation failed.', 'advanced-ads' );
			showAddonErrorNotice( message );
		} finally {
			setIsWorking( false );
		}
	}

	async function handleDownloadAndInstall() {
		if ( isBusy || installActionsDisabled || ! licenseKey || ! addonId ) {
			return;
		}

		onInstallStart?.( addonId );
		setIsWorking( true );

		try {
			await activateAddonFromLicense( licenseKey, addonId, licenseList );
			onInstallEnd?.( addonId, false );
		} catch ( err ) {
			const message =
				err instanceof Error
					? err.message
					: __( 'Installation failed.', 'advanced-ads' );
			showAddonErrorNotice( message );
			onInstallEnd?.( addonId, true );
		} finally {
			setIsWorking( false );
		}
	}

	async function handleDeactivate() {
		if ( isBusy || ! addonId ) {
			return;
		}

		setIsDeactivating( true );

		try {
			await deactivateAddonFromLicense( addonId, licenseList );
		} catch ( err ) {
			const message =
				err instanceof Error
					? err.message
					: __( 'Deactivation failed.', 'advanced-ads' );
			showAddonErrorNotice( message );
		} finally {
			setIsDeactivating( false );
		}
	}

	function handleManualInstall() {
		const packageUrl = String( downloadUrl ?? '' ).trim();
		const guideUrl = getManualInstallGuideUrl( addonId );

		if ( packageUrl ) {
			window.open( packageUrl, '_blank', 'noopener,noreferrer' );
		}

		window.open( guideUrl, '_blank', 'noopener,noreferrer' );
	}

	if ( isDeactivating ) {
		return (
			<span className="inline-flex items-center gap-2 text-sm text-gray-600">
				<Loader2
					className="size-4 shrink-0"
					strokeWidth={ 2 }
					aria-hidden
				/>
				{ __( 'Deactivating…', 'advanced-ads' ) }
			</span>
		);
	}

	if ( isActiveUnderAllAccess ) {
		return (
			<div className="inline-flex flex-wrap items-center gap-3">
				<span className="inline-flex items-center gap-2 text-sm text-green-700">
					<Check
						className="size-4 shrink-0"
						strokeWidth={ 2 }
						aria-hidden
					/>
					{ __( 'Active', 'advanced-ads' ) }
				</span>
				<button
					type="button"
					className="button advads-button-secondary is-small"
					onClick={ handleDeactivate }
					disabled={ isBusy }
					aria-busy={ isDeactivating }
				>
					{ __( 'Deactivate', 'advanced-ads' ) }
				</button>
			</div>
		);
	}

	if ( isInstalling || isWorking ) {
		return (
			<span className="inline-flex items-center gap-2 text-sm text-gray-600">
				<Loader2
					className="size-4 shrink-0"
					strokeWidth={ 2 }
					aria-hidden
				/>
				{ isWorking && takeOverFromOtherLicense
					? __( 'Activating…', 'advanced-ads' )
					: __( 'Installing…', 'advanced-ads' ) }
			</span>
		);
	}

	if ( takeOverFromOtherLicense ) {
		return (
			<div className="inline-flex flex-wrap items-center gap-3">
				<button
					type="button"
					className="button advads-button-neutral is-small"
					onClick={ handleActivateUnderAllAccess }
					disabled={ isBusy }
					aria-busy={ isWorking }
				>
					{ __( 'Activate', 'advanced-ads' ) }
				</button>
			</div>
		);
	}

	if ( rowStatus === 'running' ) {
		return (
			<div className="inline-flex flex-wrap items-center gap-3">
				<span className="inline-flex items-center gap-2 text-sm text-gray-600">
					<Check
						className="size-4 shrink-0 text-green-700"
						strokeWidth={ 2 }
						aria-hidden
					/>
					{ __( 'Plugin active', 'advanced-ads' ) }
				</span>
				<button
					type="button"
					className="button advads-button-neutral is-small"
					onClick={ handleActivateUnderAllAccess }
					disabled={ isBusy }
					aria-busy={ isWorking }
				>
					{ __( 'Activate', 'advanced-ads' ) }
				</button>
			</div>
		);
	}

	if ( rowStatus === 'ready' ) {
		return (
			<button
				type="button"
				className="button advads-button-neutral is-small"
				onClick={ handleActivateUnderAllAccess }
				disabled={ isBusy }
				aria-busy={ isWorking }
			>
				{ __( 'Activate', 'advanced-ads' ) }
			</button>
		);
	}

	return (
		<div className="inline-flex flex-wrap items-center gap-3">
			{ installFailed ? (
				<span className="inline-flex items-center gap-2 text-sm text-red-700">
					<AlertCircle
						className="size-4 shrink-0"
						strokeWidth={ 2 }
						aria-hidden
					/>
					{ __( 'Installation failed', 'advanced-ads' ) }
				</span>
			) : null }
			<button
				type="button"
				className="button advads-button-neutral is-small whitespace-nowrap"
				onClick={ handleDownloadAndInstall }
				disabled={ installActionsDisabled || isBusy }
				aria-busy={ isBusy }
			>
				{ __( 'Download and install', 'advanced-ads' ) }
			</button>
			{ installFailed ? (
				<button
					type="button"
					className="button advads-button-secondary is-small"
					onClick={ handleManualInstall }
					disabled={ installActionsDisabled || isBusy }
				>
					{ __( 'Manual install', 'advanced-ads' ) }
				</button>
			) : null }
		</div>
	);
}
