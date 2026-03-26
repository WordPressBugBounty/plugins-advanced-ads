<?php
/**
 * Subscription not working FAQ.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

?>
<p>
	<?php
	echo wp_kses_post(
		sprintf(
			/* translators: 1: link to purchase licenses page, 2: closing anchor tag */
			__( 'This is likely because you haven’t installed the additional add-ons. Please log in to your Advanced Ads account, download the necessary add-ons, and upload the downloaded zip files to your WordPress site. Install and activate them as you would any other WordPress plugin. %1$sHow to install an Add-On%2$s', 'advanced-ads' ),
			'<a href="https://wpadvancedads.com/manual/how-to-install-an-add-on/?utm_source=advanced-ads&utm_medium=link&utm_campaign=plugin_support_faqs_getting_started" target="_blank" rel="noopener noreferrer">',
			'</a>'
		)
	);
	?>
</p>
