<?php
/**
 * Render a row with add-on information on the Advanced Ads overview page
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 *
 * @var array $_addon add-on information.
 */

?>
<tr<?php echo isset( $_addon['class'] ) ? ' class="' . esc_attr( $_addon['class'] ) . '"' : ''; ?>>
	<th>
		<?php echo $_addon['title']; // phpcs:ignore ?>
	</th>
	<td>
		<?php echo $_addon['desc']; // phpcs:ignore ?>
	</td>
	<td>
		<?php if ( isset( $_addon['link'] ) && $_addon['link'] ) : ?>
			<a class="button <?php echo ( isset( $_addon['link_primary'] ) ) ? 'button-primary' : 'button-secondary'; ?>" href="<?php echo esc_url( $_addon['link'] ); ?>" target="_blank">
				<?php echo esc_html( $link_title ); ?>
			</a>
		<?php endif; ?>
	</td>
</tr>
