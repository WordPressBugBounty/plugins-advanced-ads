<?php
/**
 * Video ads FAQ.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

?>
<p>
<?php
echo wp_kses_post(
	__( 'In general, <strong>video ads tend to generate higher engagement rates</strong> compared to static image ads, primarily because motion naturally draws user attention, especially on content-heavy pages where users are scrolling quickly. That moving part will make users stop scrolling and check.', 'advanced-ads' )
);
?>
</p>

<p>
	<?php esc_html_e( 'Performance is highly dependent on several factors:', 'advanced-ads' ); ?>
</p>

<ul class="list-disc list-inside">
	<li><?php esc_html_e( 'Your audience and niche', 'advanced-ads' ); ?></li>
	<li><?php esc_html_e( 'Placement position', 'advanced-ads' ); ?></li>
	<li><?php esc_html_e( 'Page load performance', 'advanced-ads' ); ?></li>
	<li><?php esc_html_e( 'The quality and relevance of the creative', 'advanced-ads' ); ?></li>
	<li><?php esc_html_e( 'How intrusive the format is', 'advanced-ads' ); ?></li>
</ul>

<p>
	<?php esc_html_e( 'While video ads often deliver stronger engagement metrics (CTR, viewability, time on ad), they can also negatively impact user experience and performance if not implemented carefully.', 'advanced-ads' ); ?>
</p>

<p>
	<?php
	echo wp_kses_post(
		sprintf(
			/* translators: 1: link to video ads guide, 2: link to video ads configuration guide, 3: closing anchor tag */
			__( '%1$sRead more%3$s about video ads, and %2$slearn how to configure them%3$s using Advanced Ads.', 'advanced-ads' ),
			'<a href="https://wpadvancedads.com/beginners-guide-video-ads/?utm_source=advanced-ads&utm_medium=link&utm_campaign=plugin_support_faqs_video_ads" target="_blank" rel="noopener noreferrer">',
			'<a href="https://wpadvancedads.com/video-ads/?utm_source=advanced-ads&utm_medium=link&utm_campaign=plugin_support_faqs_video_ads" target="_blank" rel="noopener noreferrer">',
			'</a>'
		)
	);
	?>
</p>
