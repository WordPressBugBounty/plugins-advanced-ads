<?php
/**
 * Render column.
 *
 * @package AdvancedAds
 * @var int   $ad_id Ad ID.
 * @var array $groups Groups the ad is used in.
 * @var array $placements Placements the ad is used in.
 */

if ( $groups ) :
	?>
	<strong><?php echo esc_html__( 'Groups', 'advanced-ads' ) . ':'; ?></strong>
	<div>
		<?php
		$group_links = [];
		foreach ( $groups as $group ) {
			$group_links[] = '<a href="' . esc_attr( $group['edit_link'] ) . '" target="_blank">'
				. esc_html( $group['title'] ) . '</a>';
		}
		echo implode( ', ', $group_links ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $group_links is HTML.
		?>
	</div>
	<?php
endif;

if ( $groups && $placements ) {
	echo '<br>';
}

if ( $placements ) :
	?>
	<strong><?php echo esc_html__( 'Placements', 'advanced-ads' ) . ':'; ?></strong>
	<div>
		<?php
		$placement_links = [];
		foreach ( $placements as $placement ) {
			$placement_links[] = '<a href="' . esc_attr( $placement['edit_link'] ) . '" target="_blank">'
				. esc_html( $placement['title'] ) . '</a>';
		}
		echo implode( ', ', $placement_links ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $placement_links is HTML.
		?>
	</div>
	<?php
endif;
