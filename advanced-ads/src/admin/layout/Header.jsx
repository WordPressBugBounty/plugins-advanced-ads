/**
 * External Dependencies
 */
import { __ } from '@wordpress/i18n';
import { Star, CircleQuestionMark } from 'lucide-react';

/**
 * Internal Dependencies
 */
import { endpoints, siteInfo } from '@advancedAds';
import AdvAdsLogo from '@assets/img/logo.svg';
import { useArea, useCurrentRoute } from '@admin/router';

/**
 * Breadcrumb
 *
 * @param {Object} props                 Object containing the breadcrumb title
 * @param {string} props.breadcrumbTitle Breadcrumb title
 */
export default function Breadcrumb( { breadcrumbTitle = '' } ) {
	return (
		<div className="px-5 -mt-2 text-[11px] text-gray-500 flex items-center gap-x-2 uppercase font-medium">
			<a
				className="no-underline text-gray-500 hover:underline"
				href={ endpoints.dashboardUrl }
			>
				Dashboard
			</a>
			<span>/</span>
			<span>{ breadcrumbTitle }</span>
		</div>
	);
}

export function Header() {
	const header = useArea( 'header' );
	const { route } = useCurrentRoute() || {};
	const {
		title,
		headerActions,
		upgradeUrl = '',
		manualUrl = '#',
		breadcrumbTitle = '',
	} = route?.header || {};

	return (
		<div id="advads-header" className="relative z-10 space-y-6 mb-10">
			<div className="flex items-center gap-x-4 p-5 bg-white shadow-md">
				<div className="flex items-center gap-x-4">
					<AdvAdsLogo className="size-8" />
					<h1 className="m-0 font-light">{ title }</h1>
					{ headerActions }
				</div>
				<div className="flex items-center gap-x-4 justify-end ml-auto">
					{ ! siteInfo.proVersion && (
						<a
							href={ upgradeUrl }
							target="_blank"
							rel="noreferrer"
							className="button advads-button-primary px-3 py-1.5"
						>
							<span>See all Add-ons</span>
							<Star className="fill-current size-4" />
						</a>
					) }

					<a
						href={ manualUrl }
						target="_blank"
						rel="noreferrer"
						className="group text-gray-500 hover:text-primary transition-colors"
						aria-label="Open manual"
					>
						<CircleQuestionMark className="size-7 group-hover:stroke-current" />
					</a>
				</div>
			</div>
			{ breadcrumbTitle && (
				<Breadcrumb breadcrumbTitle={ breadcrumbTitle } />
			) }
			{ header }
		</div>
	);
}
