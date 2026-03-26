<?php
/**
 * Header on admin pages
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 *
 * @var string    $title               Page title.
 * @var string    $breadcrumb_title    Breadcrumb title.
 * @var string    $manual_url          Manual URL.
 * @var bool      $breadcrumb          Whether to show breadcrumb or not.
 * @var Screen    $current_screen      Current screen.
 */

?>
<div id="advads-header" class="relative z-10">
	<div class="inner-wrap flex items-center -ml-5 gap-x-4 p-5 bg-white shadow-md">
		<div class="flex items-center gap-x-4">
			<svg class="inline size-8" xmlns="http://www.w3.org/2000/svg" x="0" y="0" height="30" width="30" viewBox="0 0 351.7 352" xml:space="preserve"><path d="M252.2 149.6v125.1h-174.9v-174.9H202.4c-5.2-11.8-8-24.7-8-38.5s3-26.7 8-38.5h-37.7H0v267.9l8.8 8.8 -8.8-8.8C0 324.5 27.5 352 61.3 352l0 0h103.4 164.5V149.3c-11.8 5.2-25 8.3-38.8 8.3C276.9 157.6 264 154.6 252.2 149.6z" fill="#1C1B3A"/><circle cx="290.4" cy="61.3" r="61.3" fill="#0E75A4"/></svg>
			<h1 class="m-0 font-light"><?php echo esc_html( $title ); ?></h1>
			<?php $current_screen->header_actions(); ?>
		</div>

		<div class="flex items-center gap-x-4 justify-end ml-auto">
			<?php if ( ! defined( 'AAP_VERSION' ) ) : ?>
			<a href="https://wpadvancedads.com/add-ons/?utm_source=advanced-ads&utm_medium=link&utm_campaign=header-upgrade-<?php echo esc_attr( $screen->id ); ?>" target="_blank" class="button button-primary advads-button">
				<span><?php esc_html_e( 'See all Add-ons', 'advanced-ads' ); ?></span>
				<span class="dashicons dashicons-star-filled"></span>
			</a>
			<?php endif; ?>
			<a href="<?php echo esc_url( $manual_url ); ?>" target="_blank" class="advads-header-manual-link">
				<span class="dashicons dashicons-editor-help"></span>
			</a>
		</div>
	</div>

	<?php if ( $breadcrumb ) : ?>
	<div class="text-[11px] text-gray-500 mt-3 mb-6 flex items-center gap-x-2 uppercase font-medium">
		<a class="no-underline text-gray-500 hover:underline" href="<?php echo esc_url( admin_url( 'admin.php?page=advanced-ads' ) ); ?>"><?php esc_html_e( 'Dashboard', 'advanced-ads' ); ?></a>
		<span>/</span>
		<span><?php echo esc_html( $breadcrumb_title ); ?></span>
	</div>
	<?php else : ?>
	<div class="mb-6"></div>
	<?php endif; ?>
</div>
