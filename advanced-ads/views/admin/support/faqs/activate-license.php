<?php
/**
 * Activate license FAQ.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

?>
<p>
	<?php esc_html_e( 'Many customers maintain a local, non-public site for testing, or troubleshooting purposes. This setup is particularly helpful for staging complex changes and verifying their expected behaviour before releasing it to the public.', 'advanced-ads' ); ?>
</p>

<p>
	<?php
	echo wp_kses_post(
		sprintf(
			/* translators: 1: link to purchase licenses page, 2: closing anchor tag */
			__( 'Testing sites do not count towards your Advanced Ads site limit. We automatically detect testing sites based on the URL patterns in %1$sthis list%2$s.', 'advanced-ads' ),
			'<a href="https://wpadvancedads.com/manual/purchase-licenses/#Test_sites?utm_source=advanced-ads&utm_medium=link&utm_campaign=plugin_support_faqs_getting_started" target="_blank" rel="noopener noreferrer">',
			'</a>'
		)
	)
	?>
</p>

<p>
	<?php
	echo wp_kses_post(
		sprintf(
			/* translators: 1: link to purchase licenses page, 2: closing anchor tag */
			__( 'If you are running the plugin on one of those URLs, the activation works even if you hit your limit. You can always manage license activations in %1$syour account%2$s.', 'advanced-ads' ),
			'<a href="https://wpadvancedads.com/account/?utm_source=advanced-ads&utm_medium=link&utm_campaign=plugin_support_faqs_getting_started" target="_blank" rel="noopener noreferrer">',
			'</a>'
		)
	)
	?>
</p>
