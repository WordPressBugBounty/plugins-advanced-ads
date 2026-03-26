<?php
/**
 * Groups page.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 *
 * @var WP_List_Table|false $wp_list_table The groups list table
 */

?>
<span class="wp-header-end"></span>
<div class="wrap">
	<div id="ajax-response"></div>

	<div id="advads-ad-group-list">
		<?php $wp_list_table->display(); ?>
	</div>
</div>
