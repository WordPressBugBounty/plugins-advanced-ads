<?php
/**
 * Need help card.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

?>
<div class="advads-card bg-gray-100 flex flex-col">
	<div>
		<div class="header-icon">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none">
				<path d="M22 19C22 19.5304 21.7893 20.0391 21.4142 20.4142C21.0391 20.7893 20.5304 21 20 21H4C3.46957 21 2.96086 20.7893 2.58579 20.4142C2.21071 20.0391 2 19.5304 2 19V5C2 4.46957 2.21071 3.96086 2.58579 3.58579C2.96086 3.21071 3.46957 3 4 3H9L11 6H20C20.5304 6 21.0391 6.21071 21.4142 6.58579C21.7893 6.96086 22 7.46957 22 8V19Z" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"/>
			</svg>
		</div>

		<h3><?php esc_html_e( 'Access Priority Support', 'advanced-ads' ); ?></h3>

		<p>
			<?php echo wp_kses_post( __( 'As a paid user, you receive <strong>priority, premium support from our specialists</strong>. Submit a support ticket and get fast, tailored assistance to resolve your request.', 'advanced-ads' ) ); ?>
		</p>
	</div>

	<a href="#advads-create-ticket" data-dialog="advads-create-ticket" class="button button-block mt-auto">
		<?php esc_html_e( 'Submit a ticket', 'advanced-ads' ); ?>
	</a>
</div>
