/**
 * External Dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal Dependencies
 */
import { Card } from '@admin/components/Card';
import { SearchComboBox } from '@admin/components/SearchComboBox';

export function SearchBox() {
	return (
		<Card className="text-center">
			<h2 className="m-0 text-2xl">
				{ __( 'How can we help?', 'advanced-ads' ) }
			</h2>
			<p>
				{ __(
					'Search for a topic or browse our documentation.',
					'advanced-ads'
				) }
			</p>
			<div className="relative mt-5 w-lg max-w-lg mx-auto">
				<SearchComboBox
					id="advads-support-search"
					placeholder={ __(
						'Search help articles, guides and FAQs',
						'advanced-ads'
					) }
					endpoint="https://wpadvancedads.com/wp-json/wp/v2/search?subtype=post,bwl_kb&search={{search}}"
					formatSuggestion={ ( s ) => (
						<span className="flex flex-col">
							<span className="font-medium">{ s.title }</span>
							<span className="text-gray-400 block text-xs italic">
								{ s.subtype === 'post'
									? 'Tutorials'
									: 'Knowledge Base' }
							</span>
						</span>
					) }
					onSelect={ ( suggestion, inputRef ) => {
						window.open(
							suggestion.url +
								'?utm_source=advanced-ads&utm_medium=link&utm_campaign=plugin_support_searchbox',
							'_blank'
						);
						inputRef.current.value = '';
					} }
				/>
			</div>
		</Card>
	);
}
