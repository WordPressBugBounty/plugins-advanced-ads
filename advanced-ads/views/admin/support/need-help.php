<?php
/**
 * Need help card.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

?>
<div class="advads-card bg-gray-100">
	<h3 class="mt-0 mb-2"><?php esc_html_e( 'Need more help?', 'advanced-ads' ); ?></h3>

	<p>
		<?php esc_html_e( 'On the free plan, support is available via guides and community forums.', 'advanced-ads' ); ?>
	</p>

	<p>
		<?php echo wp_kses_post( __( 'Upgrade to <strong>A2 Pro</strong> to get:', 'advanced-ads' ) ); ?>
	</p>

	<ul class="list-disc list-inside">
		<li>
			<?php esc_html_e( 'Priority support from our team', 'advanced-ads' ); ?>
		</li>
		<li>
			<?php esc_html_e( 'Help directly inside the plugin', 'advanced-ads' ); ?>
		</li>
		<li>
			<?php esc_html_e( 'Faster issue resolution', 'advanced-ads' ); ?>
		</li>
	</ul>

	<a href="https://wpadvancedads.com/checkout/?edd_action=add_to_cart&download_id=95170&utm_source=advanced-ads&utm_medium=link&utm_campaign=plugin_support_need_help_upgrade" class="button button-block mt-6">
		<?php esc_html_e( 'Upgrade to Premium', 'advanced-ads' ); ?>
	</a>
</div>
