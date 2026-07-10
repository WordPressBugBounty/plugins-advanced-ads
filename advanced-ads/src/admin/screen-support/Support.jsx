/**
 * External Dependencies
 */
import { __ } from '@wordpress/i18n';
import { BookOpen, File, Folder } from 'lucide-react';

/**
 * Internal Dependencies
 */
import { siteInfo } from '@advancedAds';
import { SearchBox } from './components/SearchBox';
import { CategoryCard } from './components/CategoryCard';
import { FAQs } from './components/FAQs';
import { Videos } from './components/Videos';
import { PaidSupport } from './components/PaidSupport';
import { SupportForum } from './components/SupportForum';

export function Support() {
	const endpoint = '/advanced-ads/v1/support-links';
	const { hasAnyValidLicense } = siteInfo;

	return (
		<div className="advads-support-wrap">
			<SearchBox />
			<div className="grid sm:grid-cols-2 xl:grid-cols-4 gap-5 mt-8">
				<CategoryCard
					icon={ <BookOpen /> }
					endpoint={ endpoint }
					title={ __( 'Getting started', 'advanced-ads' ) }
					utms="?utm_source=advanced-ads&utm_medium=link&utm_campaign=plugin_support_getting_started"
					viewAll={ {
						label: __( 'View all articles', 'advanced-ads' ),
						link: 'https://wpadvancedads.com/manual/?utm_source=advanced-ads&utm_medium=link&utm_campaign=plugin_support_getting_started_view_all',
					} }
					extraArgs={ {
						cache_key: 'advads_support_getting_started_links',
					} }
				/>
				<CategoryCard
					icon={ <File /> }
					endpoint={ endpoint }
					title={ __( 'Latest tutorials', 'advanced-ads' ) }
					utms="?utm_source=advanced-ads&utm_medium=link&utm_campaign=plugin_support_latest_tutorials"
					viewAll={ {
						label: __( 'View all tutorials', 'advanced-ads' ),
						link: 'https://wpadvancedads.com/category/tutorials/?utm_source=advanced-ads&utm_medium=link&utm_campaign=plugin_support_latest_tutorials_view_all',
					} }
					extraArgs={ {
						cache_key: 'advads_support_latest_tutorials_links',
					} }
				/>
				<CategoryCard
					icon={ <Folder /> }
					endpoint={ endpoint }
					title={ __( 'Articles', 'advanced-ads' ) }
					utms="?utm_source=advanced-ads&utm_medium=link&utm_campaign=plugin_support_articles"
					viewAll={ {
						label: __( 'View all articles', 'advanced-ads' ),
						link: 'https://wpadvancedads.com/manual/?utm_source=advanced-ads&utm_medium=link&utm_campaign=plugin_support_articles_view_all',
					} }
					extraArgs={ {
						cache_key: 'advads_support_articles_links',
					} }
				/>
				{ hasAnyValidLicense ? <PaidSupport /> : <SupportForum /> }
			</div>

			<FAQs />
			<Videos />
		</div>
	);
}
