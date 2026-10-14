<?php
/**
 * Modal template.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   2.0.0
 *
 * @var array{id: string, title: string, description?: string, content: string, show_footer?: bool, wrap_class?: string, close_label?: string, save_label?: string} $args Modal arguments.
 */

use AdvancedAds\Framework\Utilities\HTML;

$wrap = HTML::classnames( 'advads-dialog', $args['wrap_class'] ?? '' );
?>
<dialog id="<?php echo esc_attr( $args['id'] ); ?>" aria-labelledby="<?php echo esc_attr( $args['id'] ); ?>-title" class="<?php echo esc_attr( $wrap ); ?>">
	<form method="post" class="advads-dialog-wrap" novalidate>
		<div class="advads-dialog-frame" tabindex="-1">
			<div class="advads-dialog-header">
				<h3 id="<?php echo esc_attr( $args['id'] ); ?>-title"><?php echo esc_html( $args['title'] ); ?></h3>
				<?php if ( $args['description'] ) : ?>
				<p class="advads-dialog-description"><?php echo esc_html( $args['description'] ); ?></p>
				<?php endif; ?>
				<a href="#" data-dialog-close class="advads-dialog-button-close" aria-label="<?php esc_attr_e( 'Close', 'advanced-ads' ); ?>">
					<span class="dashicons dashicons-no-alt"></span>
				</a>
			</div>
			<div class="advads-dialog-body">
				<?php echo $args['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- modal content may contain any kind of custom html ?>
			</div>
			<?php if ( $args['show_footer'] ) : ?>
			<div class="advads-dialog-footer">
				<button type="button" data-dialog-close class="button button-secondary"><?php echo esc_html( $args['close_label'] ); ?></button>
				<button type="submit" class="button button-primary"><?php echo esc_html( $args['save_label'] ); ?></button>
			</div>
			<?php endif; ?>
		</div>
	</form>
</dialog>
