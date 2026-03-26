<?php
/**
 * Render the view navigation items on the Placement screen.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.48.0
 *
 * @var array $views List of views.
 */

use AdvancedAds\Framework\Utilities\Str;

?>
<ul class="flex gap-x-2 float-left clear-both mt-2.5 mb-5">
	<?php
	foreach ( $views as $index => $view ) :
		$view  = str_replace( [ ')', '(' ], '', $view );
		$class = [ 'no-underline advads-button button' ];
		if ( Str::contains( 'current', $view ) || ( $is_all && 'all' === $index ) ) {
			$class[] = 'button-primary';
			$view = str_replace( 'class="current"', '" ', $view );
		} else {
			$class[] = 'button-secondary';
		}

		$view = str_replace( '<a ', '<a class="' . esc_attr( join( ' ', $class ) ) . '" ', $view );
		?>
		<li>
			<?php
			echo wp_kses(
				$view,
				[
					'a'    => [ 'href' => [], 'class' => [], 'aria-current' => [] ],
					'span' => [ 'class' => [] ],
				]
			);
			?>
		</li>
	<?php endforeach; ?>
</ul>
