<?php
/**
 * Support page.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

?>
<div class="hidden">
	<span class="wp-header-end"></span>
</div>
<div class="wrap advads-wrap advads-support-wrap">
	<?php require_once 'searchbox.php'; ?>

	<div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-5 mt-8">
		<?php require_once 'getting-started.php'; ?>
		<?php require_once 'latest-tutorials.php'; ?>
		<?php require_once 'articles.php'; ?>
		<?php require_once \Advanced_Ads_Admin_Licenses::get_instance()->any_license_valid() ? 'need-help-paid.php' : 'need-help.php'; ?>
	</div>

	<?php require_once 'faqs.php'; ?>
	<?php require_once 'videos.php'; ?>

	<?php
	advads_modal(
		[
			'id'          => 'advads-create-ticket',
			'title'       => __( 'Get help from our support team', 'advanced-ads' ),
			'description' => __( 'Describe your issue and our support team will review it as soon as possible. You’ll receive a confirmation email after submitting your request.', 'advanced-ads' ),
			'save_label'  => __( 'Submit', 'advanced-ads' ),
			'close_label' => __( 'Cancel', 'advanced-ads' ),
			'file_path'   => ADVADS_ABSPATH . 'views/admin/support/create-ticket.php',
			'wrap_class'  => 'advsads-dialog-sm advads-dialog-create-ticket manual',
		]
	);
	?>

</div>
