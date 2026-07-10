/**
 * External Dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal Dependencies
 */
import { GettingStarted } from './getting-started';
import { ActivateLicense } from './activate-license';
import { VideoAds } from './video-ads';
import { SubscriptionNotWorking } from './subscription-not-working';
import { CachedWebsites } from './cached-websites';
import { ShareStatistics } from './share-statistics';
import { ManageAdsAcrossMultipleInstallations } from './manage-ads-across-multiple-installations';
import { PreventClickFraud } from './prevent-click-fraud';

const FAQS = [
	{
		title: __(
			'What are best practices for getting started with Advanced Ads?',
			'advanced-ads'
		),
		content: <GettingStarted />,
	},
	{
		title: __( 'Are video ads better than image ads?', 'advanced-ads' ),
		content: <VideoAds />,
	},
	{
		title: __(
			'How do I activate my license on a test site?',
			'advanced-ads'
		),
		content: <ActivateLicense />,
	},
	{
		title: __(
			'I purchased a subscription but can’t see the features in the backend of my website. Why?',
			'advanced-ads'
		),
		content: <SubscriptionNotWorking />,
	},
	{
		title: __(
			'Does Advanced Ads work on cached websites?',
			'advanced-ads'
		),
		content: <CachedWebsites />,
	},
	{
		title: __(
			'How can I share the statistics of my ads with my clients?',
			'advanced-ads'
		),
		content: <ShareStatistics />,
	},
	{
		title: __(
			'Can I manage ads across multiple WordPress installations from one website?',
			'advanced-ads'
		),
		content: <ManageAdsAcrossMultipleInstallations />,
	},
	{
		title: __( 'How can I prevent click fraud on my ads?', 'advanced-ads' ),
		content: <PreventClickFraud />,
	},
];

export { FAQS };
