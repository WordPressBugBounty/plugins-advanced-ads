<?php
/**
 * Render list-table view navigation.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.48.0
 *
 * @var array<string, string> $views List of views.
 * @var bool  $is_all Whether no filters are applied.
 */

use AdvancedAds\Framework\Utilities\Str;

?>
<ul class="flex gap-x-2 float-left clear-both mt-2.5 mb-5">
	<?php foreach ( $views as $index => $view ) : ?>
		<?php
		$view      = str_replace( [ '(', ')' ], '', $view );
		$is_active = Str::contains( 'class="current"', $view ) || ( $is_all && 'all' === $index );
		$classes   = 'no-underline advads-button button ' . ( $is_active ? 'button-primary' : 'button-secondary' );

		if ( $is_active ) {
			$view = str_replace( 'class="current"', '', $view );
		}

		$view = str_replace( '<a ', '<a class="' . esc_attr( $classes ) . '" ', $view );
		?>
		<li>
			<?php
			echo wp_kses(
				$view,
				[
					'a'    => [
						'href'         => [],
						'class'        => [],
						'aria-current' => [],
					],
					'span' => [ 'class' => [] ],
				]
			);
			?>
		</li>
	<?php endforeach; ?>
</ul>
