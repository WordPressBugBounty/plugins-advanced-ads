/**
 * External Dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

export function ManageAdsAcrossMultipleInstallations() {
	const p1 = sprintf(
		/* translators: 1: link to manage ads page, 2: closing anchor tag */
		__(
			'You can do this by using the <strong>Ad Server placement</strong>. It allows you to embed the generated JavaScript code or iframe on your other WordPress installations to deliver ads from your main site. Please review the feature’s limitations before implementation to ensure it fits your needs. %1$sYou can find more details here%2$s.',
			'advanced-ads'
		),
		'<a href="https://wpadvancedads.com/ad-server-wordpress/?utm_source=advanced-ads&utm_medium=link&utm_campaign=plugin_support_faqs_getting_started" target="_blank" rel="noopener noreferrer">',
		'</a>'
	);

	return <p dangerouslySetInnerHTML={ { __html: p1 } } />;
}
