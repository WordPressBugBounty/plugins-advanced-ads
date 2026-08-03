<?php
/**
 * Support callout.
 *
 * @package Advanced_Ads
 * @since   1.0.0
 */

use AdvancedAds\Utilities\Data;
?>

<div id="advads-support-callout">
	<p class="advads-notice-inline advads-idea">
	<a href="
			<?php
				echo esc_url(
					add_query_arg(
						[
							'page'         => 'advanced-ads-app',
							'path'         => '/support',
							'utm_source'   => 'advancedads',
							'utm_medium'   => 'in-plugin',
							'utm_campaign' => 'a2-in_plugin-support',
						],
						admin_url( 'admin.php' )
					)
				);
				?>
		"
		target="_blank"><strong><?php esc_html_e( 'Problems or questions?', 'advanced-ads' ); ?></strong>
		<?php esc_html_e( 'Save time and get personal support.', 'advanced-ads' ); ?>&nbsp;<strong style="text-decoration: underline;"><?php esc_html_e( 'Ask your question!', 'advanced-ads' ); ?></strong>
	</a>
	</p>
</div>
