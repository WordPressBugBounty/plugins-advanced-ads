/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal Dependencies
 */
import { getCheckoutIdsForPlan } from '../utils';
import { PricingTableItem } from './PricingTableItem';

/** @type {Array<{ id: string, title: string, subtitle: string, price: string, description: string, ctaLabel: string, popular: boolean, downloadId: number, pricingId: number, upgradeId?: number, utmCampaign: string, features: string[] }>} */
export const PLANS = [
	{
		id: 'pro',
		title: __( 'Pro', 'advanced-ads' ),
		subtitle: __( 'Single site', 'advanced-ads' ),
		price: '$59',
		description: __(
			'For small businesses and bloggers who want to increase ad revenue',
			'advanced-ads'
		),
		ctaLabel: __( 'Upgrade to Pro', 'advanced-ads' ),
		popular: false,
		downloadId: 1742,
		pricingId: 0,
		utmCampaign: 'a2-in_plugin-pricing_table-pro_plan',
		features: [
			__( '1 site', 'advanced-ads' ),
			__( '1 year of support and updates', 'advanced-ads' ),
			__( '30-day money-back guarantee', 'advanced-ads' ),
			__( 'Free activation on test sites', 'advanced-ads' ),
			__( 'Advanced Ads Pro', 'advanced-ads' ),
		],
	},
	{
		id: 'all-access-single',
		title: __( 'All Access', 'advanced-ads' ),
		subtitle: __( 'Single site', 'advanced-ads' ),
		price: '$89',
		description: __(
			'For professional publishers to increase revenue and save development time',
			'advanced-ads'
		),
		ctaLabel: __( 'Get All Access', 'advanced-ads' ),
		popular: true,
		downloadId: 95170,
		pricingId: 1,
		upgradeId: 3,
		utmCampaign: 'a2-in_plugin-pricing_table-all_access_plan',
		features: [
			__( '1 site', 'advanced-ads' ),
			__( '1 year of support and updates', 'advanced-ads' ),
			__( '30-day money-back guarantee', 'advanced-ads' ),
			__( 'Free activation on test sites', 'advanced-ads' ),
			__( 'Including all add-ons', 'advanced-ads' ),
			__( 'Advanced Ads Pro', 'advanced-ads' ),
			__( 'Tracking', 'advanced-ads' ),
		],
	},
	{
		id: 'all-access-five',
		title: __( 'All Access', 'advanced-ads' ),
		subtitle: __( '5 sites', 'advanced-ads' ),
		price: '$129',
		description: __(
			'For large publishing companies and agencies managing multiple sites',
			'advanced-ads'
		),
		ctaLabel: __( 'Scale to multiple sites', 'advanced-ads' ),
		popular: false,
		downloadId: 95170,
		pricingId: 2,
		upgradeId: 1,
		utmCampaign: 'a2-in_plugin-pricing_table-all_access_5_sites_plan',
		features: [
			__( '5 sites', 'advanced-ads' ),
			__( '1 year of support and updates', 'advanced-ads' ),
			__( '30-day money-back guarantee', 'advanced-ads' ),
			__( 'Free activation on test sites', 'advanced-ads' ),
			__( 'Including all add-ons', 'advanced-ads' ),
			__( 'Advanced Ads Pro', 'advanced-ads' ),
			__( 'Tracking', 'advanced-ads' ),
		],
	},
];

/**
 * @param {Object}                   props
 * @param {'pro'|'all-access-single'|'all-access-five'|null} [props.currentPlanId]
 * @param {(planId: string) => void} [props.onSelectPlan]
 */
export function PricingTable( {
	currentPlanId = null,
	onSelectPlan,
} ) {
	return (
		<section className="w-full">
			<div
				className="grid grid-cols-1 gap-6 md:grid-cols-3 md:grid-rows-[auto_auto_auto_auto_auto] md:gap-x-6 md:gap-y-6 lg:gap-x-8"
			>
				{ PLANS.map( ( plan ) => {
					const checkoutReady =
						( 'all-access-single' !== plan.id ||
							plan.pricingId === 1 ) &&
						null !== getCheckoutIdsForPlan( plan.id, PLANS );
					const isCurrentPlan = plan.id === currentPlanId;
					const ctaDisabled = ! checkoutReady || isCurrentPlan;

					return (
						<PricingTableItem
							key={ plan.id }
							title={ plan.title }
							subtitle={ plan.subtitle }
							price={ plan.price }
							description={ plan.description }
							ctaLabel={ plan.ctaLabel }
							features={ plan.features }
							popular={ plan.popular }
							ctaDisabled={ ctaDisabled }
							onCtaClick={
								onSelectPlan && ! ctaDisabled
									? () => onSelectPlan( plan.id )
									: undefined
							}
						/>
					);
				} ) }
			</div>
		</section>
	);
}
