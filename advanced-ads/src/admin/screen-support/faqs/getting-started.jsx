/**
 * External Dependencies
 */
import { __ } from '@wordpress/i18n';

export function GettingStarted() {
	const p1 = __(
		'<strong>Using placements for delivery is best practice</strong>, whether you are inserting a single ad or an ad group. Even if you prefer working with shortcodes, you can use a <strong>Manual Placement</strong>, which also provides a shortcode. This gives you the flexibility of shortcode insertion while still keeping centralized control.',
		'advanced-ads'
	);
	const p2 = __(
		'<strong>Here’s why that matters:</strong> If you manually insert an ad shortcode into 100 individual posts and later stop working with that advertiser, you would need to edit all 100 posts to remove or replace the shortcode (unless it’s embedded in a template). With placements, you simply replace or disable the ad (or ad group) assigned to that placement, and the change is applied everywhere instantly. This significantly reduces maintenance effort and makes your setup more scalable and future-proof.',
		'advanced-ads'
	);

	return (
		<>
			<p dangerouslySetInnerHTML={ { __html: p1 } } />
			<p dangerouslySetInnerHTML={ { __html: p2 } } />
		</>
	);
}
