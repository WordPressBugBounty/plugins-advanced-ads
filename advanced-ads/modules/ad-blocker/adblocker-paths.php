<?php
/**
 * Adblocker paths.
 *
 * @package AdvancedAds\Modules\AdBlocker
 */

defined( 'ABSPATH' ) || exit;

$folder_name = wp_advads_get_setting( 'folder_name', 'adblocker' );
$upload_dir  = wp_upload_dir();
$asset_path  = $upload_dir['basedir'] . '/' . $folder_name;
$asset_url   = $upload_dir['baseurl'] . '/' . $folder_name;
?>
<div class="grid grid-cols-[auto_1fr] gap-4 mt-2">
	<div class="w-20 font-bold">
		<?php esc_html_e( 'Asset path', 'advanced-ads' ); ?>
	</div>
	<div>
		<?php echo esc_html( $asset_path ); ?>
	</div>
	<div class="w-20 font-bold">
		<?php esc_html_e( 'Asset URL', 'advanced-ads' ); ?>
	</div>
	<div>
		<?php echo esc_html( $asset_url ); ?>
	</div>
	<div class="col-span-2 w-full">
		<label for="adblocker-rebuild-force">
			<input type="checkbox" id="adblocker-rebuild-force" name="adblocker-rebuild-force" value="1">
			<?php esc_html_e( 'Force rename of assets. This will delete the existing asset folder and create a new one.', 'advanced-ads' ); ?>
		</label>
	</div>
	<div class="col-span-2 w-full">
		<button type="button" class="button advads-button-primary" data-command="ajax" data-action="advads-adblock-rebuild-assets" data-inputs="#adblocker-rebuild-force">
			<?php esc_html_e( 'Rebuild asset folder', 'advanced-ads' ); ?>
		</button>
	</div>
</div>
