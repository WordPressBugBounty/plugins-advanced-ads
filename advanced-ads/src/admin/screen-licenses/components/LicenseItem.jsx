/**
 * External Dependencies
 */
import { ChevronDown } from 'lucide-react';
import { useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';

/**
 * WordPress Dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { DropdownMenu } from '@wordpress/components';

/**
 * Internal Dependencies
 */
import AdvAdsLogo from '@assets/img/logo.svg';
import { useModalState } from '@admin/hooks/useModalState';
import { STORE_NAME } from '@admin/store';

import {
	resolveAddonIdForLicense,
	formatLicenseDate,
	canUpgradeLicensePlan,
	getAutoUpdateAddonIdsForLicense,
	getAutoUpdateDisplayLabel,
	getAutoUpdateScopeLabel,
	getAddonInstallState,
	getDisplayLicenseStatus,
	getHostnameFromUrl,
	getLicenseSitesUsedCount,
	getTypeLabel,
	isAllAccessBundleName,
	isLicenseAppliedOnThisSite,
	isPluginAutoUpdateEnabled,
	isLicenseExpiredForDisplay,
	isLicenseExpiringSoon,
	isRichLicenseEntitled,
	resolvePlanLabelForLicense,
	resolvePlanIdForLicense,
	startShopRenewalForLicense,
	startShopUpgradeForPlan,
} from '../utils';
import { LICENSE_ADDON_CATALOG } from '../addon-catalog';
import { togglePluginAutoUpdate } from '../hooks/licenses-api';
import { AddonsList } from './AddonsList';
import { AllAccessAddonsSummary } from './AllAccessAddonsSummary';
import { LicenseStatus } from './LicenseStatus';
import { LicenseField } from './LicenseField';
import { AutoUpdateModal } from './AutoUpdateModal';
import { PricingModal } from './PricingModal';
import { PLANS } from './PricingTable';
import { SitesModal } from './SitesModal';
import { DownloadActivateButton } from './DownloadActivateButton';

export function LicenseItem( {
	activationCount = 0,
	availableSites = 0,
	licenseId,
	licenseKey,
	purchaseDate,
	expiryDate,
	status,
	name,
	autoRenew = false,
	paymentStatus,
	sitesActivated,
	download_url: downloadUrl,
	addons,
	noticesContext,
	allLicenses = [],
	appliedAddonKeyMap = {},
} ) {
	const hasAutoRenew = Boolean( autoRenew );
	const autoUpdateStates = useSelect(
		( select ) => select( STORE_NAME ).getAutoUpdateStates(),
		[]
	);
	const addonInstallStates = useSelect(
		( select ) => select( STORE_NAME ).getAddonInstallStates(),
		[]
	);
	const [ savingAddonId, setSavingAddonId ] = useState( '' );
	const isAllAccess = isAllAccessBundleName( name );

	const {
		isOpen: isAutoUpdateOpen,
		open: handleAutoUpdateOpen,
		close: handleAutoUpdateClose,
	} = useModalState();
	const {
		isOpen: isSitesOpen,
		open: openSitesModal,
		close: handleSitesClose,
	} = useModalState();
	const {
		isOpen: isPricingOpen,
		open: handlePricingOpen,
		close: handlePricingClose,
	} = useModalState();

	const handleSitesOpen = () => {
		const utmUrl = `${ advancedAds.endpoints.shopUrl }/account/?utm_source=advancedads&utm_medium=in-plugin&utm_campaign=a2-in_plugin-licenses_addons-view_all_sites`;
		fetch( utmUrl, { mode: 'no-cors', keepalive: true } );
		openSitesModal();
	};

	const handleSelectPlan = ( planId ) => {
		if ( licenseId ) {
			startShopUpgradeForPlan( planId, licenseId, PLANS );
		}
	};

	const canRenew =
		isLicenseExpiredForDisplay( status, expiryDate ) ||
		isLicenseExpiringSoon( expiryDate, 30 );

	const handleRenew = () => {
		startShopRenewalForLicense( licenseId );
	};

	const licenseTypeLabel = getTypeLabel( name, availableSites );
	const currentHostname = getHostnameFromUrl(
		advancedAds?.endpoints?.siteUrl
	);
	const licenseRow = {
		name,
		status,
		sitesActivated,
		expiryDate,
		licenseKey,
		download_url: downloadUrl,
		addons,
		availableSites,
	};
	const currentPlanId = resolvePlanIdForLicense( licenseRow );
	const showUpgradePlan = canUpgradeLicensePlan( currentPlanId );
	let displayStatus = getDisplayLicenseStatus(
		licenseRow,
		allLicenses,
		currentHostname,
		appliedAddonKeyMap,
		addonInstallStates
	);

	displayStatus =
		paymentStatus === 'failed' ? 'Payment Failed' : displayStatus;
	const isActivated = isLicenseAppliedOnThisSite(
		licenseRow,
		allLicenses,
		currentHostname,
		appliedAddonKeyMap,
		addonInstallStates
	);
	const isLicenseEntitled = isRichLicenseEntitled( status, expiryDate );
	const productAddonId = resolveAddonIdForLicense(
		licenseRow,
		appliedAddonKeyMap
	);
	const mappedAddonKey = productAddonId
		? String( appliedAddonKeyMap?.[ productAddonId ] ?? '' )
		: '';
	const pluginActive = productAddonId
		? getAddonInstallState( productAddonId, addonInstallStates ).active
		: true;
	const licensedUnderThisKey =
		productAddonId && licenseKey && mappedAddonKey === String( licenseKey );
	const canUseDownloadActivate =
		isLicenseEntitled && ( ! licensedUnderThisKey || ! pluginActive );
	const sitesUsed = getLicenseSitesUsedCount(
		sitesActivated,
		activationCount
	);
	const canUseAllAccessAddons =
		isAllAccess && isRichLicenseEntitled( status, expiryDate );
	const allAccessAddonIds = getAutoUpdateAddonIdsForLicense( name );

	const autoUpdateLabel = getAutoUpdateDisplayLabel( name, autoUpdateStates );
	const autoUpdateScope = getAutoUpdateScopeLabel( name );

	const autoUpdateToggles = getAutoUpdateAddonIdsForLicense( name ).map(
		( addonId ) => ( {
			addonId,
			label:
				LICENSE_ADDON_CATALOG[ addonId ]?.title ??
				( addonId === 'main'
					? __( 'Advanced Ads', 'advanced-ads' )
					: addonId ),
			enabled: isPluginAutoUpdateEnabled( addonId, autoUpdateStates ),
		} )
	);

	const handleAutoUpdateToggle = async ( addonId, enabled ) => {
		setSavingAddonId( addonId );
		try {
			await togglePluginAutoUpdate( addonId, enabled ? 'on' : 'off' );
		} catch {
			// Store keeps previous state on failure.
		} finally {
			setSavingAddonId( '' );
		}
	};

	return (
		<div className="rounded-lg p-6 border border-gray-300 bg-gray-100">
			<div className="grid grid-cols-3 gap-8 xl:grid-cols-[88px_minmax(0,1fr)_300px]">
				<div className="order-1 xl:col-span-1">
					<div className="inline-flex grow-0 bg-gray-200 p-6 items-center justify-center rounded-lg">
						<AdvAdsLogo className="size-10" />
					</div>
				</div>

				<div className="space-y-5 col-span-3 order-3 xl:order-2 xl:col-span-1">
					<div className="grid grid-cols-1 gap-x-8 gap-y-5 sm:grid-cols-2 xl:grid-cols-4">
						<LicenseField
							label={ __( 'License type:', 'advanced-ads' ) }
							value={ licenseTypeLabel }
						/>
						<LicenseField
							label={ __( 'License status:', 'advanced-ads' ) }
							value={
								<LicenseStatus
									status={ displayStatus }
									showRenew={ canRenew }
									onRenew={ handleRenew }
								/>
							}
						/>
						<LicenseField
							label={ __( 'Sites:', 'advanced-ads' ) }
							value={
								<span className="flex flex-wrap items-center gap-x-2 gap-y-1">
									<span>
										{ sprintf(
											/* translators: 1: Number of sites activated, 2: Number of sites in the license. */
											__(
												'%1$s of %2$s used',
												'advanced-ads'
											),
											String( sitesUsed ),
											String( availableSites )
										) }
									</span>
									<button
										type="button"
										className="button button-link underline! underline-offset-3"
										onClick={ handleSitesOpen }
									>
										{ __( 'View', 'advanced-ads' ) }
									</button>
								</span>
							}
						/>
						<LicenseField
							label={ __( 'License key:', 'advanced-ads' ) }
							value={
								<code className="break-all text-sm font-normal text-gray-800 p-1">
									{ licenseKey }
								</code>
							}
						/>
						<LicenseField
							label={ __( 'Purchase date:', 'advanced-ads' ) }
							value={ formatLicenseDate( purchaseDate ) }
						/>
						<LicenseField
							label={
								hasAutoRenew
									? __( 'Renews:', 'advanced-ads' )
									: __(
											'License expiration:',
											'advanced-ads'
									  )
							}
							value={ formatLicenseDate( expiryDate ) }
						/>
						<LicenseField
							label={ __( 'Auto-updates:', 'advanced-ads' ) }
							value={
								<span className="flex flex-wrap items-center gap-x-2 gap-y-1">
									<span>
										{ autoUpdateLabel }
										<span className="text-gray-500">
											{ ' · ' }
											{ autoUpdateScope }
										</span>
									</span>
									<button
										type="button"
										className="button button-link underline! underline-offset-3"
										onClick={ handleAutoUpdateOpen }
									>
										{ __( 'Edit', 'advanced-ads' ) }
									</button>
								</span>
							}
						/>
						{ isAllAccess && isActivated ? (
							<LicenseField
								label={ __( 'Add-ons:', 'advanced-ads' ) }
								value={
									<AllAccessAddonsSummary
										addonIds={ allAccessAddonIds }
										addonInstallStates={
											addonInstallStates
										}
										appliedAddonKeyMap={
											appliedAddonKeyMap
										}
										licenseKey={ licenseKey }
									/>
								}
							/>
						) : null }
					</div>
				</div>

				<div className="flex flex-col text-right shrink-0 gap-2 col-span-2 order-2 md:flex-row md:justify-end xl:order-3 xl:col-span-1">
					{ ! isAllAccess ? (
						<div>
							<DownloadActivateButton
								licenseKey={ licenseKey }
								isActivated={ isActivated }
								canActivate={ canUseDownloadActivate }
								noticesContext={ noticesContext }
								downloadUrl={ downloadUrl }
								addonId={ productAddonId ?? '' }
							/>
						</div>
					) : null }
					<div>
						<DropdownMenu
							toggleProps={ {
								className:
									'button advads-button-secondary inline-flex items-center justify-center gap-2 whitespace-nowrap px-4 py-2',
							} }
							icon={
								<>
									{ __( 'Manage', 'advanced-ads' ) }
									<ChevronDown
										className="h-4 w-4 shrink-0 opacity-90"
										strokeWidth={ 2 }
										aria-hidden
									/>
								</>
							}
							controls={ [
								...( showUpgradePlan
									? [
											{
												title: __(
													'Upgrade plan',
													'advanced-ads'
												),
												onClick: handlePricingOpen,
											},
									  ]
									: [] ),
								...( canRenew
									? [
											{
												title: __(
													'Renew license',
													'advanced-ads'
												),
												onClick: handleRenew,
											},
									  ]
									: [] ),
							] }
						/>
					</div>
				</div>
			</div>
			<AddonsList
				license={ licenseRow }
				allLicenses={ allLicenses }
				appliedAddonKeyMap={ appliedAddonKeyMap }
				currentHostname={ currentHostname }
				noticesContext={ noticesContext }
				canUseAddonActions={ canUseAllAccessAddons }
			/>

			{ isAutoUpdateOpen && (
				<AutoUpdateModal
					toggles={ autoUpdateToggles }
					onToggle={ handleAutoUpdateToggle }
					savingId={ savingAddonId }
					onClose={ handleAutoUpdateClose }
				/>
			) }
			{ isSitesOpen && (
				<SitesModal
					title={ licenseTypeLabel }
					description={ sprintf(
						/* translators: 1: Number of sites activated, 2: Number of sites in the license. */
						__(
							'Activated on %1$s out of %2$s sites.',
							'advanced-ads'
						),
						String( sitesUsed ),
						String( availableSites )
					) }
					sites={ sitesActivated ?? [] }
					onClose={ handleSitesClose }
				/>
			) }
			{ isPricingOpen && (
				<PricingModal
					onClose={ handlePricingClose }
					onSelectPlan={ handleSelectPlan }
					currentPlanLabel={ resolvePlanLabelForLicense(
						licenseRow
					) }
					currentPlanId={ currentPlanId }
					currentActionLabel={ __( 'Upgrade plan', 'advanced-ads' ) }
				/>
			) }
		</div>
	);
}
