/**
 * External Dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { ChevronDown } from 'lucide-react';
import { useId, useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';

/**
 * Internal Dependencies
 */
import { getIncludedAddonsForLicense } from '../addon-catalog';
import {
	getBulkAddonActionPlan,
	getLicenseNameForKey,
	isAddonLicensedByKey,
	isAddonManagedByOtherLicense,
	isAllAccessBundleName,
} from '../utils';
import { STORE_NAME } from '@admin/store';
import {
	activateAddonFromLicense,
	deactivateAddonFromLicense,
	extractApiErrorMessage,
} from '../hooks/licenses-api';
import { AddonRowActions } from './AddonRowActions';
import { publishLicenseWarningNotice } from './LicenseNotices';

function AddonItem( {
	addon,
	licenseKey,
	licenses,
	isApplied,
	isActivated = false,
	canUseAddonActions,
	managedByOtherLicense,
	managingLicenseName,
	noticesContext,
	addonInstallStates,
	installingAddonId,
	failedAddonIds,
	failedAddonMessages,
	onInstallStart,
	onInstallEnd,
} ) {
	const { id, title, description, icon, learnMore, manualUrl, downloadUrl } =
		addon;

	return (
		<div className="relative border border-border bg-white p-3 rounded-lg flex flex-wrap flex-row gap-3 md:items-center">
			<div className="flex items-center justify-center p-2 size-8 bg-gray-200 rounded-lg grow-0">
				<img src={ icon } alt="" className="size-4" />
			</div>
			<div className="shrink-0 flex-1">
				<strong>{ title }</strong>
				<p className="flex flex-wrap items-center gap-x-1 m-0 mt-1 text-gray-500">
					{ description }
					<a
						href={ learnMore }
						target="_blank"
						rel="noopener noreferrer"
						className="no-underline"
					>
						{ __( 'Learn more', 'advanced-ads' ) }
					</a>
					<span>|</span>
					<a
						href={ manualUrl }
						target="_blank"
						rel="noopener noreferrer"
						className="no-underline"
					>
						{ __( 'Manual', 'advanced-ads' ) }
					</a>
				</p>
			</div>
			<div className="basis-full sm:basis-auto sm:ml-auto">
				<AddonRowActions
					addonId={ id }
					licenseKey={ licenseKey }
					licenses={ licenses }
					downloadUrl={ downloadUrl }
					isApplied={ isApplied }
					isActivated={ isActivated }
					disabled={ ! canUseAddonActions }
					managedByOtherLicense={ managedByOtherLicense }
					managingLicenseName={ managingLicenseName }
					addonInstallStates={ addonInstallStates }
					noticesContext={ noticesContext }
					isInstalling={ installingAddonId === id }
					installFailed={ failedAddonIds.has( id ) }
					installFailedMessage={ failedAddonMessages[ id ] || '' }
					onInstallStart={ onInstallStart }
					onInstallEnd={ onInstallEnd }
				/>
			</div>
		</div>
	);
}

export function AddonsList( {
	license,
	allLicenses = [],
	appliedAddonKeyMap = {},
	isActivated = false,
	noticesContext,
	canUseAddonActions = false,
} ) {
	const addonsId = useId();
	const [ addOnsOpen, setAddOnsOpen ] = useState( true );
	const [ installingAddonId, setInstallingAddonId ] = useState( '' );
	const [ failedAddonIds, setFailedAddonIds ] = useState( () => new Set() );
	const [ failedAddonMessages, setFailedAddonMessages ] = useState( {} );
	const [ bulkModeBusy, setBulkModeBusy ] = useState( '' );
	const { createErrorNotice, removeNotice } = useDispatch( noticesStore );

	const addonInstallStates = useSelect(
		( select ) => select( STORE_NAME ).getAddonInstallStates(),
		[]
	);
	const licensesFromStore = useSelect(
		( select ) => select( STORE_NAME ).getLicenses(),
		[]
	);

	if ( ! isAllAccessBundleName( license?.name ) ) {
		return null;
	}

	const includedAddons = getIncludedAddonsForLicense( license );
	if ( ! includedAddons.length ) {
		return null;
	}

	const licenseKey = String( license?.licenseKey ?? '' );
	const licenseList =
		licensesFromStore.length > 0 ? licensesFromStore : allLicenses;
	const addonIds = includedAddons.map( ( addon ) => addon.id );
	const bulkPlan = getBulkAddonActionPlan( {
		addonIds,
		addonInstallStates,
		appliedAddonKeyMap,
		licenseKey,
		isLicenseActivatedOnSite: isActivated,
		allLicenses: licenseList,
	} );

	let bulkLabel;

	if ( bulkModeBusy === 'activate' ) {
		bulkLabel = __( 'Activating…', 'advanced-ads' );
	} else if ( bulkModeBusy === 'deactivate' ) {
		bulkLabel = __( 'Deactivating…', 'advanced-ads' );
	} else if ( bulkPlan.mode === 'deactivate' ) {
		bulkLabel = __( 'Deactivate all', 'advanced-ads' );
	} else {
		bulkLabel = __( 'Activate all', 'advanced-ads' );
	}

	const bulkDisabled =
		! canUseAddonActions ||
		bulkModeBusy !== '' ||
		installingAddonId !== '' ||
		bulkPlan.mode === 'none';

	function handleInstallStart( addonId ) {
		setInstallingAddonId( addonId );
	}

	function handleInstallEnd( addonId, failed, message = '' ) {
		setInstallingAddonId( '' );
		if ( failed ) {
			setFailedAddonIds( ( prev ) => new Set( prev ).add( addonId ) );
			if ( message ) {
				setFailedAddonMessages( ( prev ) => ( {
					...prev,
					[ addonId ]: message,
				} ) );
			}
		} else {
			setFailedAddonIds( ( prev ) => {
				const next = new Set( prev );
				next.delete( addonId );
				return next;
			} );
			setFailedAddonMessages( ( prev ) => {
				if ( ! prev[ addonId ] ) {
					return prev;
				}
				const next = { ...prev };
				delete next[ addonId ];
				return next;
			} );
		}
	}

	async function handleBulkAddonAction() {
		if ( bulkDisabled || bulkPlan.mode === 'none' ) {
			return;
		}

		const mode = bulkPlan.mode;
		const targets = [ ...bulkPlan.targetIds ];
		setBulkModeBusy( mode );

		for ( const addonId of targets ) {
			if ( mode === 'activate' ) {
				handleInstallStart( addonId );
				try {
					await activateAddonFromLicense(
						licenseKey,
						addonId,
						licenseList
					);
					handleInstallEnd( addonId, false );
				} catch ( err ) {
					const message =
						extractApiErrorMessage( err ) ||
						__( 'Activation failed.', 'advanced-ads' );
					const noticeId = `advanced-ads/addon-error-${ addonId }`;
					removeNotice( noticeId, noticesContext );
					publishLicenseWarningNotice( createErrorNotice, {
						id: noticeId,
						context: noticesContext,
						message,
					} );
					handleInstallEnd( addonId, true, message );
				}
			} else {
				try {
					await deactivateAddonFromLicense( addonId, licenseList );
				} catch ( err ) {
					const message =
						extractApiErrorMessage( err ) ||
						__( 'Deactivation failed.', 'advanced-ads' );
					const noticeId = `advanced-ads/addon-error-${ addonId }`;
					removeNotice( noticeId, noticesContext );
					publishLicenseWarningNotice( createErrorNotice, {
						id: noticeId,
						context: noticesContext,
						message,
					} );
				}
			}
		}

		setBulkModeBusy( '' );
	}

	return (
		<div className="mt-6 xl:ml-30">
			<div className="flex flex-wrap items-center justify-between gap-3">
				<button
					type="button"
					id={ addonsId }
					className="is-ghost"
					onClick={ () => setAddOnsOpen( ( open ) => ! open ) }
					aria-expanded={ addOnsOpen }
				>
					{ sprintf(
						/* translators: %d: Number of included add-ons. */
						__( 'Included add-ons (%d)', 'advanced-ads' ),
						includedAddons.length
					) }
					<ChevronDown
						className={ `h-4 w-4 shrink-0 transition-transform ${
							addOnsOpen ? 'rotate-180' : ''
						}` }
						strokeWidth={ 2 }
						aria-hidden
					/>
				</button>
				{ addOnsOpen ? (
					<button
						type="button"
						className={ `button ${
							bulkLabel !== 'Deactivate all'
								? 'advads-button-neutral'
								: 'advads-button-secondary'
						} whitespace-nowrap px-4 py-2 ` }
						onClick={ handleBulkAddonAction }
						disabled={ bulkDisabled }
						aria-busy={ bulkModeBusy !== '' }
					>
						{ bulkLabel }
					</button>
				) : (
					''
				) }
			</div>
			{ addOnsOpen ? (
				<section
					className="mt-6 space-y-6"
					aria-labelledby={ addonsId }
				>
					{ includedAddons.map( ( addon ) => {
						const isLicensedByThisKey = isAddonLicensedByKey(
							addon.id,
							licenseKey,
							appliedAddonKeyMap
						);
						const managedByOther = isAddonManagedByOtherLicense(
							addon.id,
							licenseKey,
							appliedAddonKeyMap
						);
						const managingKey = managedByOther
							? String( appliedAddonKeyMap?.[ addon.id ] ?? '' )
							: '';

						return (
							<AddonItem
								key={ addon.id }
								addon={ addon }
								licenseKey={ licenseKey }
								licenses={ allLicenses }
								isApplied={ isLicensedByThisKey }
								isActivated={ isActivated }
								canUseAddonActions={ canUseAddonActions }
								managedByOtherLicense={ managedByOther }
								managingLicenseName={ getLicenseNameForKey(
									managingKey,
									allLicenses
								) }
								noticesContext={ noticesContext }
								addonInstallStates={ addonInstallStates }
								installingAddonId={ installingAddonId }
								failedAddonIds={ failedAddonIds }
								failedAddonMessages={ failedAddonMessages }
								onInstallStart={ handleInstallStart }
								onInstallEnd={ handleInstallEnd }
							/>
						);
					} ) }
				</section>
			) : null }
		</div>
	);
}
