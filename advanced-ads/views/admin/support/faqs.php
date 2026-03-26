<?php
/**
 * FAQs card.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

$faqs = [
	[
		'title'   => __( 'What are best practices for getting started with Advanced Ads?', 'advanced-ads' ),
		'content' => 'faqs/getting-started.php',
	],
	[
		'title'   => __( 'Are video ads better than image ads?', 'advanced-ads' ),
		'content' => 'faqs/video-ads.php',
	],
	[
		'title'   => __( 'How do I activate my license on a test site?', 'advanced-ads' ),
		'content' => 'faqs/activate-license.php',
	],
	[
		'title'   => __( 'I purchased a subscription but can’t see the features in the backend of my website. Why?', 'advanced-ads' ),
		'content' => 'faqs/subscription-not-working.php',
	],
	[
		'title'   => __( 'Does Advanced Ads work on cached websites?', 'advanced-ads' ),
		'content' => 'faqs/cached-websites.php',
	],
	[
		'title'   => __( 'How can I share the statistics of my ads with my clients?', 'advanced-ads' ),
		'content' => 'faqs/share-statistics.php',
	],
	[
		'title'   => __( 'Can I manage ads across multiple WordPress installations from one website?', 'advanced-ads' ),
		'content' => 'faqs/manage-ads-across-multiple-installations.php',
	],
	[
		'title'   => __( 'How can I prevent click fraud on my ads?', 'advanced-ads' ),
		'content' => 'faqs/prevent-click-fraud.php',
	],
];
?>
<div class="advads-card grid sm:grid-cols-3 gap-5">
	<div>
		<div class="header-icon">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none">
				<path d="M21 15C21 15.5304 20.7893 16.0391 20.4142 16.4142C20.0391 16.7893 19.5304 17 19 17H7L3 21V5C3 4.46957 3.21071 3.96086 3.58579 3.58579C3.96086 3.21071 4.46957 3 5 3H19C19.5304 3 20.0391 3.21071 20.4142 3.58579C20.7893 3.96086 21 4.46957 21 5V15Z" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"/>
			</svg>
		</div>

		<h3><?php esc_html_e( 'FAQ\'s', 'advanced-ads' ); ?></h3>

		<p>
			<?php esc_html_e( 'Everything you need to know about the product.', 'advanced-ads' ); ?>
		</p>
		<?php if ( ! \Advanced_Ads_Admin_Licenses::get_instance()->any_license_valid() ) : ?>
			<p>
				<?php esc_html_e( 'Couldn’t find what you were looking for?', 'advanced-ads' ); ?>
				<br />
				<?php esc_html_e( 'Get help from the community.', 'advanced-ads' ); ?>
			</p>
			<p>
				<a class="advads-view-all-link" href="https://wordpress.org/support/plugin/advanced-ads/" target="_blank">
					<?php esc_html_e( 'Visit WordPress Forum', 'advanced-ads' ); ?>
					<?php include 'arrow.php'; ?>
				</a>
			</p>
		<?php endif; ?>
	</div>

	<div class="advads-accordion col-span-2">
		<?php foreach ( $faqs as $index => $faq ) : ?>
			<div class="advads-accordion-item">
				<input type="checkbox" name="accordion-1" id="rb<?php echo esc_attr( $index + 1 ); ?>"<?php checked( 0 === $index ); ?>>
				<label for="rb<?php echo esc_attr( $index + 1 ); ?>" class="accordion__header">
					<?php echo esc_html( $faq['title'] ); ?>
				</label>
				<div class="accordion__content">
					<?php require_once $faq['content']; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</div>
