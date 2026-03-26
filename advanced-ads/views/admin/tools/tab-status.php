<?php
/**
 * Render System information
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.50.0
 */

use AdvancedAds\Admin\System_Info;

$system_info = new System_Info();
?>
<div class="advads-system-information">
	<h2><?php esc_html_e( 'System Information', 'advanced-ads' ); ?></h2>
	<textarea readonly onclick="this.select()"><?php echo esc_textarea( $system_info->get_info() ); ?></textarea>
</div>
