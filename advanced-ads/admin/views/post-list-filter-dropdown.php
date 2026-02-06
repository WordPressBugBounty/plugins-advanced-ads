<?php
/**
 * Filter for the ad visibility on post and page list pages.
 *
 * @since   2.0
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 *
 * @var string $viewability Selected ad viewability by which posts are filtered.
 */

?>
<select name="ad-viewability">
	<option value="">
		<?php esc_html_e( 'All ad states', 'advanced-ads' ); ?>
	</option>
	<option value="disable_ads" <?php selected( 'disable_ads', $viewability ); ?>>
		<?php esc_html_e( 'All ads disabled', 'advanced-ads' ); ?>
	</option>
	<option
		value="disable_the_content"
		<?php selected( 'disable_the_content', $viewability ); ?>
		<?php disabled( defined( 'AAP_VERSION' ), false ); ?>>
		<?php
			esc_html_e( 'Ads in content disabled', 'advanced-ads' );
			echo ! defined( 'AAP_VERSION' ) ? ' (Pro)' : '';
		?>
	</option>
</select>
