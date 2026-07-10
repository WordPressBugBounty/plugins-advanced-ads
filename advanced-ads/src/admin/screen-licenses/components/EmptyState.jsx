/**
 * External Dependencies
 */
import { __ } from '@wordpress/i18n';
import { useModalState } from '@admin/hooks/useModalState';
import { PLANS } from './PricingTable';
import { startShopCheckoutForPlan } from '../utils';
import { PricingModal } from './PricingModal';

export function EmptyState() {
	const {
		isOpen: isPricingOpen,
		open: openModal,
		close: handlePricingClose,
	} = useModalState();

	const handlePricingOpen = () => {
		const utmUrl = `${ advancedAds.endpoints.shopUrl }/?utm_source=advancedads&utm_medium=in-plugin&utm_campaign=a2-in_plugin-licenses_addons-buy-no_licenses`;

		// 1. Fire the UTM "ping" in the background
		// 'no-cors' prevents errors if the shop is on a different domain
		// 'keepalive' ensures the request finishes even if the modal opens/state changes
		fetch( utmUrl, { mode: 'no-cors', keepalive: true } );

		// 2. Open the modal
		openModal();
	};

	const handleSelectPlan = ( planId ) => {
		startShopCheckoutForPlan( planId, PLANS );
	};

	return (
		<div className="flex items-center justify-center w-full h-[calc(70vh)]">
			<div className="text-center">
				<h1 className="text-3xl font-semibold m-0">
					{ __( 'No active licenses there yet', 'advanced-ads' ) }
				</h1>
				<p className="m-0 mt-3 mb-8 text-gray-500 text-base">
					{ __(
						'Purchase a license to unlock premium features.',
						'advanced-ads'
					) }
					<br />
					{ __(
						'Free plugins don’t require a license and won’t appear here.',
						'advanced-ads'
					) }
				</p>
				<div className="flex items-center justify-center gap-x-4">
					<a
						href={ `${ advancedAds.endpoints.shopUrl }/sso-login?site=${ advancedAds.endpoints.siteUrl }&utm_source=advancedads&utm_medium=in-plugin&utm_campaign=a2-in_plugin-licenses_addons-connect-no_licenses` }
						className="button advads-button-neutral is-big"
					>
						{ __( 'Connect license', 'advanced-ads' ) }
					</a>

					<button
						type="button"
						className="button advads-button-secondary is-big"
						onClick={ handlePricingOpen }
					>
						{ __( 'Buy license', 'advanced-ads' ) }
					</button>
				</div>
			</div>
			{ isPricingOpen && (
				<PricingModal
					onClose={ handlePricingClose }
					currentActionLabel={ __( 'Buy plan', 'advanced-ads' ) }
					onSelectPlan={ handleSelectPlan }
				/>
			) }
		</div>
	);
}
