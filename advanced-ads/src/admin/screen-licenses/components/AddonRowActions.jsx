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
	extractApiErrorMessage,
} from '../hooks/licenses-api';
import { getAddonInstallState, getAddonRowStatus } from '../utils';
import { publishLicenseWarningNotice } from './LicenseNotices';

function BusyStatus( { label } ) {
	return (
		<span className="inline-flex items-center gap-2 text-sm text-gray-600">
			<Loader2
				className="size-4 shrink-0"
				strokeWidth={ 2 }
				aria-hidden
			/>
			{ label }
		</span>
	);
}

function ActivateButton( { onClick, disabled, isBusy } ) {
	return (
		<button
			type="button"
			className="button advads-button-neutral is-small"
			onClick={ onClick }
			disabled={ disabled }
			aria-busy={ isBusy }
		>
			{ __( 'Activate', 'advanced-ads' ) }
		</button>
	);
}

function ActiveWithDeactivate( { onDeactivate, isBusy, isDeactivating } ) {
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
				onClick={ onDeactivate }
				disabled={ isBusy }
				aria-busy={ isDeactivating }
			>
				{ __( 'Deactivate', 'advanced-ads' ) }
			</button>
		</div>
	);
}

export function AddonRowActions( {
	addonId,
	licenseKey,
	licenses,
	downloadUrl = '',
	isApplied = false,
	isActivated = false,
	disabled = false,
	managedByOtherLicense = false,
	addonInstallStates = {},
	noticesContext,
	isInstalling = false,
	installFailed = false,
	installFailedMessage = '',
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
	const isActiveUnderAllAccess = rowStatus === 'installed' && isActivated;
	const isBusy = isInstalling || isWorking || isDeactivating;
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
			showAddonErrorNotice(
				extractApiErrorMessage( err ) ||
					__( 'Activation failed.', 'advanced-ads' )
			);
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
				extractApiErrorMessage( err ) ||
				__( 'Installation failed.', 'advanced-ads' );
			showAddonErrorNotice( message );
			onInstallEnd?.( addonId, true, message );
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
			showAddonErrorNotice(
				extractApiErrorMessage( err ) ||
					__( 'Deactivation failed.', 'advanced-ads' )
			);
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
		return <BusyStatus label={ __( 'Deactivating…', 'advanced-ads' ) } />;
	}

	if ( isActiveUnderAllAccess ) {
		return (
			<ActiveWithDeactivate
				onDeactivate={ handleDeactivate }
				isBusy={ isBusy }
				isDeactivating={ isDeactivating }
			/>
		);
	}

	if ( isInstalling || isWorking ) {
		return (
			<BusyStatus
				label={
					isWorking && takeOverFromOtherLicense
						? __( 'Activating…', 'advanced-ads' )
						: __( 'Installing…', 'advanced-ads' )
				}
			/>
		);
	}

	if ( takeOverFromOtherLicense ) {
		return (
			<div className="inline-flex flex-wrap items-center gap-3">
				<ActivateButton
					onClick={ handleActivateUnderAllAccess }
					disabled={ installActionsDisabled || isBusy }
					isBusy={ isWorking }
				/>
			</div>
		);
	}

	if ( rowStatus === 'running' && isActivated ) {
		return (
			<ActiveWithDeactivate
				onDeactivate={ handleDeactivate }
				isBusy={ isBusy }
				isDeactivating={ isDeactivating }
			/>
		);
	}

	if ( rowStatus === 'ready' ) {
		return (
			<ActivateButton
				onClick={ handleActivateUnderAllAccess }
				disabled={ installActionsDisabled || isBusy }
				isBusy={ isWorking }
			/>
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
					{ installFailedMessage ||
						__( 'Installation failed', 'advanced-ads' ) }
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
