<?php
/**
 * Prevent click fraud FAQ.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

?>
<p>
	<?php
	echo wp_kses_post(
		sprintf(
			/* translators: 1: link to manage ads page, 2: closing anchor tag */
			__( 'You can protect your ads by using the Click Fraud Protection feature included in Advanced Ads Pro. This feature automatically hides an ad once a defined number of clicks from the same user is reached, helping to prevent repeated or suspicious clicks and reducing the risk of fraudulent activity. %1$sYou can find more details here%2$s.', 'advanced-ads' ),
			'<a href="https://wpadvancedads.com/manual/click-fraud-protection/?utm_source=advanced-ads&utm_medium=link&utm_campaign=plugin_support_faqs_getting_started" target="_blank" rel="noopener noreferrer">',
			'</a>'
		)
	);
	?>
</p>
