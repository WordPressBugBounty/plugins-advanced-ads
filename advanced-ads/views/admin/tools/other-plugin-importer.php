<?php
/**
 * Render Importers
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.50.0
 */

use AdvancedAds\Framework\Utilities\Params;

$importers = wp_advads()->importers;

$count  = 0;
$links  = [];
$inputs = [];

foreach ( $importers->get_importers() as $importer ) {
	if ( ! $importer->is_detected() ) {
		continue;
	}

	$tab_id = esc_attr( 'importer-' . $importer->get_id() );

	$inputs[] = sprintf(
		'<input type="radio" id="%1$s" name="other-plugin-importers"%2$s>',
		$tab_id,
		checked( 0 === $count++, true, false )
	);

	$links[] = sprintf(
		'<label for="%1$s">%2$s<span>%3$s</span></label>',
		$tab_id,
		$importer->get_icon(),
		esc_html( $importer->get_title() )
	);
}
?>
<div class="advads-other-plugin-importer mt-8">
	<header>
		<h2 class="advads-h2"><?php esc_html_e( 'Other Plugins', 'advanced-ads' ); ?></h2>
		<p class="text-sm"><?php esc_html_e( 'To make things even easier, we are working on new ways to import your settings and data from other plugins.', 'advanced-ads' ); ?></p>
	</header>
	<div class="advads-tabs-wrap">
		<?php echo join( '', $inputs ); ?>

		<div class="advads-tab-nav">
			<?php echo join( '', $links ); ?>
		</div>
		<?php
		foreach ( $importers->get_importers() as $importer ) :
			if ( ! $importer->is_detected() ) {
				continue;
			}
			?>
		<form
			id="<?php echo esc_attr( $importer->get_id() ); ?>"
			class="advads-tab-content"
			method="post"
			action="<?php echo esc_url( Params::server( 'REQUEST_URI' ) . '#' . $importer->get_id() ); ?>"
		>
			<?php wp_nonce_field( 'advads_import' ); ?>
			<input type="hidden" name="action" value="advads_import">
			<input type="hidden" name="importer" value="<?php echo esc_attr( $importer->get_id() ); ?>">

			<div class="advads-tab-content-body">
				<?php if ( $importer->get_description() ) : ?>
				<p class="text-base"><?php echo esc_html( $importer->get_description() ); ?></p>
				<?php endif; ?>
				<div class="pt-2">
					<?php $importer->render_form(); ?>
				</div>
			</div>

			<?php if ( $importer->show_button() ) : ?>
			<div class="advads-tab-content-footer">
				<button class="button button-primary button-large" type="submit">
					<?php esc_html_e( 'Start Importing', 'advanced-ads' ); ?>&nbsp;<?php echo esc_html( $importer->get_title() ); ?>
				</button>
			</div>
			<?php endif; ?>
		</form>
		<?php endforeach; ?>
	</div>
</div>
