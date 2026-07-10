/**
 * External Dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { ChevronDown } from 'lucide-react';
import { useId, useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';

/**
 * Internal Dependencies
 */
import { getIncludedAddonsForLicense } from '../addon-catalog';
import {
	getLicenseNameForKey,
	isAddonLicensedByKey,
	isAddonManagedByOtherLicense,
	isAllAccessBundleName,
	isLicenseAppliedOnThisSite,
	isRichLicenseEntitled,
} from '../utils';
import { STORE_NAME } from '@admin/store';
import { AddonRowActions } from './AddonRowActions';

function AddonItem( {
	addon,
	licenseKey,
	licenses,
	isApplied,
	canUseAddonActions,
	managedByOtherLicense,
	managingLicenseName,
	licenseAppliedOnSite,
	noticesContext,
	addonInstallStates,
	installingAddonId,
	failedAddonIds,
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
					disabled={ ! canUseAddonActions }
					managedByOtherLicense={ managedByOtherLicense }
					managingLicenseName={ managingLicenseName }
					licenseAppliedOnSite={ licenseAppliedOnSite }
					addonInstallStates={ addonInstallStates }
					noticesContext={ noticesContext }
					isInstalling={ installingAddonId === id }
					installFailed={ failedAddonIds.has( id ) }
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
	currentHostname = '',
	noticesContext,
	canUseAddonActions = false,
} ) {
	const addonsId = useId();
	const [ addOnsOpen, setAddOnsOpen ] = useState( true );
	const [ installingAddonId, setInstallingAddonId ] = useState( '' );
	const [ failedAddonIds, setFailedAddonIds ] = useState( () => new Set() );

	const addonInstallStates = useSelect(
		( select ) => select( STORE_NAME ).getAddonInstallStates(),
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
	const licenseAppliedOnSite = isLicenseAppliedOnThisSite(
		license,
		allLicenses,
		currentHostname,
		appliedAddonKeyMap,
		addonInstallStates
	);

	function handleInstallStart( addonId ) {
		setInstallingAddonId( addonId );
	}

	function handleInstallEnd( addonId, failed ) {
		setInstallingAddonId( '' );
		if ( failed ) {
			setFailedAddonIds( ( prev ) => new Set( prev ).add( addonId ) );
		} else {
			setFailedAddonIds( ( prev ) => {
				const next = new Set( prev );
				next.delete( addonId );
				return next;
			} );
		}
	}

	return (
		<div className="mt-6 xl:ml-30">
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
							? String(
									appliedAddonKeyMap?.[ addon.id ] ?? ''
							  )
							: '';

						return (
							<AddonItem
								key={ addon.id }
								addon={ addon }
								licenseKey={ licenseKey }
								licenses={ allLicenses }
								isApplied={ isLicensedByThisKey }
								canUseAddonActions={ canUseAddonActions }
								managedByOtherLicense={ managedByOther }
								managingLicenseName={ getLicenseNameForKey(
									managingKey,
									allLicenses
								) }
								licenseAppliedOnSite={ licenseAppliedOnSite }
								noticesContext={ noticesContext }
								addonInstallStates={ addonInstallStates }
								installingAddonId={ installingAddonId }
								failedAddonIds={ failedAddonIds }
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
