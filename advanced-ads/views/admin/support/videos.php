<?php
/**
 * Video tutorials card.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

$video_ids = [
	'RmH8YiswfIk',
	'EL29HYcCcxY',
	'jWZe020bdJc',
	'Q7QQGvCJh2I',
	'wUBa7Ht52fs',
	'Ywtj0Ui_TaI',
];
?>
<div class="advads-card">
	<div class="header-icon">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none">
			<g clip-path="url(#clip0_49_1302)">
				<path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"/>
				<path d="M10 8L16 12L10 16V8Z" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"/>
			</g>
			<defs>
				<clipPath id="clip0_49_1302">
					<rect width="24" height="24" fill="white"/>
				</clipPath>
			</defs>
		</svg>
	</div>

	<h3><?php esc_html_e( 'Video tutorials', 'advanced-ads' ); ?></h3>

	<div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-8 advads-videos-grid">
		<?php
		foreach ( $video_ids as $video_id ) :
			$this->render_youtube_video( $video_id );
		endforeach;
		?>
	</div>

	<footer class="mt-6">
		<a class="advads-view-all-link" href="https://www.youtube.com/channel/UCBBcWLiklJ-mbq9LB6TbkVQ" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e( 'View all videos tutorials on YouTube', 'advanced-ads' ); ?>
			<?php include 'arrow.php'; ?>
		</a>
	</footer>

</div>
