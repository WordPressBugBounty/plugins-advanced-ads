<?php
/**
 * Cached websites FAQ.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

?>
<p>
	<?php
	echo wp_kses_post(
		sprintf(
			/* translators: 1: link to cache busting page, 2: closing anchor tag */
			__( 'Yes, it does. However, if you plan to deliver ads dynamically based on specific conditions or rotate multiple ads within the same placement, you’ll need to enable %1$sCache Busting%2$s. You can enable this option by going to: Advanced Ads → Settings → Pro → Cache Busting', 'advanced-ads' ),
			'<a href="https://wpadvancedads.com/manual/cache-busting/?utm_source=advanced-ads&utm_medium=link&utm_campaign=plugin_support_faqs_getting_started" target="_blank" rel="noopener noreferrer">',
			'</a>'
		)
	);
	?>
</p>
