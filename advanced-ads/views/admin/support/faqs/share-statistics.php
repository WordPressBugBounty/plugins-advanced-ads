<?php
/**
 * Share statistics FAQ.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

?>
<p>
	<?php
	echo wp_kses_post(
		sprintf(
			/* translators: 1: link to shareable link page, 2: closing anchor tag */
			__( '%1$sAs shown here%2$s, the Tracking add-on allows you to generate non-indexed public statistics pages for each ad. You can share these links directly with your advertisers so they can view performance data, including impressions, clicks, a visual graph, and customizable date range filters.', 'advanced-ads' ),
			'<a href="https://wpadvancedads.com/manual/tracking-documentation/#Public_Stats/?utm_source=advanced-ads&utm_medium=link&utm_campaign=plugin_support_faqs_getting_started" target="_blank" rel="noopener noreferrer">',
			'</a>'
		)
	);
	?>
</p>
