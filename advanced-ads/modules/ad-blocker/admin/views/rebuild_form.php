<?php // phpcs:disable WordPress.Files.FileName.NotHyphenatedLowercase
/**
 * Ad blocker disguise rebuild template
 *
 * @package       AdvancedAds\Pro
 * @var array     $upload_dir      wp_upload_dir response
 * @var string    $message         Response message
 * @var bool|null $success         Whether request was successful
 * @var bool      $button_disabled If button should have disabled attribute.
 */

?>

<?php if ( ! empty( $message ) && isset( $success ) ) : ?>
	<div class="<?php echo $success ? 'advads-check' : 'advads-error'; ?> advads-notice-inline is-dismissible">
		<p><?php echo esc_html( $message ); ?></p>
	</div>
<?php endif; ?>

<?php if ( ! empty( $upload_dir['error'] ) ) : ?>
	<p class="advads-notice-inline advads-error"><?php esc_html_e( 'Upload folder is not writable', 'advanced-ads' ); ?></p>
	<?php
	return;
endif;
?>

<div id="advanced-ads-rebuild-assets-form">
	<?php if ( ! empty( $options['folder_name'] ) && ! empty( $options['module_can_work'] ) ) : ?>
		<table class="form-table" role="presentation">
			<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Asset path', 'advanced-ads' ); ?></th>
				<td><?php echo esc_html( trailingslashit( $upload_dir['basedir'] ) . $options['folder_name'] ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Asset URL', 'advanced-ads' ); ?></th>
				<td><?php echo esc_html( trailingslashit( $upload_dir['baseurl'] ) . $options['folder_name'] ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Rename assets', 'advanced-ads' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="advads_ab_assign_new_folder">
						<?php echo esc_html__( 'Check if you want to change the names of the assets', 'advanced-ads' ) . '.'; ?>
						<span class="advads-help">
							<span class="advads-tooltip" style="position: fixed; left: 693px; top: 387px;">
								<?php esc_html_e( 'This feature relocates potentially blocked scripts to a new, randomly named folder to help bypass ad blockers. The folder receives updates during plugin updates. Occasional rebuilding of the asset folder prevents browsers from caching outdated versions. If you\'re already using a plugin that renames scripts, like Autoptimize or WP Rocket, turn off this feature to avoid conflicts.', 'advanced-ads' ); ?>
							</span>
						</span>
					</label>
				</td>
			</tr>
			</tbody>
		</table>
	<?php else : ?>
		<p>
			<?php
			$folder = ! empty( $options['folder_name'] )
				? trailingslashit( $upload_dir['basedir'] ) . $options['folder_name']
				: $upload_dir['basedir'];
			printf(
			/* translators: placeholder is path to folder in uploads dir */
				esc_html__( 'Please, rebuild the asset folder. All assets will be located in %s', 'advanced-ads' ),
				sprintf( '<strong>%s</strong>', esc_attr( $folder ) )
			);
			?>
		</p>
	<?php endif; ?>

	<p class="submit">
		<button type="button" class="button button-primary" id="advads-adblocker-rebuild" <?php echo( $button_disabled ? 'disabled' : '' ); ?>>
			<?php esc_html_e( 'Rebuild asset folder', 'advanced-ads' ); ?>
		</button>
	</p>
</div>
