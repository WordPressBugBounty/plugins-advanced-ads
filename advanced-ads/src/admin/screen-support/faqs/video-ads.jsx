/**
 * External Dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

export function VideoAds() {
	const p1 = __(
		'In general, <strong>video ads tend to generate higher engagement rates</strong> compared to static image ads, primarily because motion naturally draws user attention, especially on content-heavy pages where users are scrolling quickly. That moving part will make users stop scrolling and check.',
		'advanced-ads'
	);
	const p3 = sprintf(
		/* translators: 1: link to video ads guide, 2: link to video ads configuration guide, 3: closing anchor tag */
		__(
			'%1$sRead more%3$s about video ads, and %2$slearn how to configure them%3$s using Advanced Ads.',
			'advanced-ads'
		),
		'<a href="https://wpadvancedads.com/beginners-guide-video-ads/?utm_source=advanced-ads&utm_medium=link&utm_campaign=plugin_support_faqs_video_ads" target="_blank" rel="noopener noreferrer">',
		'<a href="https://wpadvancedads.com/video-ads/?utm_source=advanced-ads&utm_medium=link&utm_campaign=plugin_support_faqs_video_ads" target="_blank" rel="noopener noreferrer">',
		'</a>'
	);

	return (
		<>
			<p dangerouslySetInnerHTML={ { __html: p1 } } />
			<p>
				{ __(
					'Performance is highly dependent on several factors:',
					'advanced-ads'
				) }
			</p>
			<ul className="list-disc list-inside">
				<li>{ __( 'Your audience and niche', 'advanced-ads' ) }</li>
				<li>{ __( 'Placement position', 'advanced-ads' ) }</li>
				<li>{ __( 'Page load performance', 'advanced-ads' ) }</li>
				<li>
					{ __(
						'The quality and relevance of the creative',
						'advanced-ads'
					) }
				</li>
				<li>{ __( 'How intrusive the format is', 'advanced-ads' ) }</li>
			</ul>

			<p>
				{ __(
					'While video ads often deliver stronger engagement metrics (CTR, viewability, time on ad), they can also negatively impact user experience and performance if not implemented carefully.',
					'advanced-ads'
				) }
			</p>

			<p dangerouslySetInnerHTML={ { __html: p3 } } />
		</>
	);
}
