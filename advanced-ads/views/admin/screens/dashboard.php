<?php
/**
 * Dashboard page.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

use AdvancedAds\Admin\Welcome;

?>
<div class="wrap">
	<span class="wp-header-end"></span>
	<?php do_action( 'advanced-ads-admin-overview-before' ); ?>
	<div id="advads-overview">
		<?php Welcome::get()->display(); ?>
		<?php Advanced_Ads_Overview_Widgets_Callbacks::setup_overview_widgets(); ?>
	</div>
	<?php do_action( 'advanced-ads-admin-overview-after' ); ?>
</div>
