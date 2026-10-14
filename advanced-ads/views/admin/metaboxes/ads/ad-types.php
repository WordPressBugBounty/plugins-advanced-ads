<?php
/**
 * Render the types meta box on the ad edit screen
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.48.2
 *
 * @var Ad $ad Ad instance.
 */

use AdvancedAds\Admin\Upsell;
use AdvancedAds\Utilities\Conditional;
use AdvancedAds\Abstracts\Ad;

$gam_missing      = 'gam' === $ad->get_type() && ! defined( 'AAGAM_VERSION' );
$adsense_disabled = 'adsense' === $ad->get_type() && ! wp_advads_is_module_active( 'adsense' );
if ( $adsense_disabled && ! wp_advads_has_ad_type( 'adsense' ) ) {
	if ( ! defined( 'GADSENSE_OPT_NAME' ) ) {
		define( 'GADSENSE_OPT_NAME', ADVADS_SLUG . '-adsense' );
	}
	wp_advads_register_ad_type( \AdvancedAds\Modules\AdSense\Types\Adsense::class );
}
$types = wp_advads_get_ad_types();

if ( empty( $types ) ) : ?>
	<p>
		<?php esc_html_e( 'No ad types defined', 'advanced-ads' ); ?>
	</p>
	<?php
	return;
endif;
?>

<ul id="advanced-ad-type">
	<?php foreach ( $types as $ad_type ) : ?>
	<li class="advanced-ads-type-list-<?php echo esc_attr( $ad_type->get_id() ); ?>">
		<input
			type="radio"
			name="advanced_ad[type]"
			id="advanced-ad-type-<?php echo esc_attr( $ad_type->get_id() ); ?>"
			value="<?php echo esc_attr( $ad_type->get_id() ); ?>"
			<?php checked( $ad->get_type(), $ad_type->get_id() ); ?>
			<?php disabled( $ad_type->is_premium() ); ?>
		/>
		<label for="advanced-ad-type-<?php echo esc_attr( $ad_type->get_id() ); ?>"><?php echo esc_html( $ad_type->get_title() ); ?></label>
		<?php if ( ! empty( $ad_type->get_description() ) ) : ?>
		<span class="advads-help">
			<span class="advads-tooltip"><?php echo esc_html( $ad_type->get_description() ); ?></span>
		</span>
		<?php endif; ?>
		<?php
		if ( $ad_type->get_upgrade_url() ) {
			echo ' ';
			Upsell::upgrade_link(
				__( 'Manual', 'advanced-ads' ),
				$ad_type->get_upgrade_url(),
				'upgrade-ad-type-' . $ad_type->get_id()
			);
		}
		?>
	</li>
	<?php endforeach; ?>
</ul>

<?php
if ( $adsense_disabled ) :
	?>
	<div class="notice notice-warning inline mt-1">
		<p>
			<?php
			printf(
				/* translators: %s: settings URL */
				wp_kses_post( __( 'This ad uses the AdSense type, but the AdSense module is disabled. <a href="%s">Enable AdSense</a> under Settings → Modules for full features and frontend output.', 'advanced-ads' ) ),
				esc_url( admin_url( 'admin.php?page=advanced-ads-app&path=/settings' ) )
			);
			?>
		</p>
	</div>
	<?php
elseif ( $gam_missing ) :
	?>
	<div class="notice notice-warning inline mt-1">
		<p>
			<?php
			printf(
				/* translators: %s: GAM add-on URL */
				wp_kses_post( __( 'This ad uses the Google Ad Manager type, but the GAM Integration add-on is not installed. <a href="%s" target="_blank" rel="noopener noreferrer">Get Google Ad Manager Integration</a> for full features and frontend output.', 'advanced-ads' ) ),
				esc_url( 'https://wpadvancedads.com/add-ons/google-ad-manager/?utm_source=advanced-ads&utm_medium=link&utm_campaign=upgrade-ad-type-gam' )
			);
			?>
		</p>
	</div>
	<?php
else :
	$_notice     = 'nl_adsense';
	$box_classes = 'mt-1 advads-notice-adsense' . ( 'adsense' !== $ad->get_type() ? ' !hidden' : '' );

	if ( Advanced_Ads_Admin_Notices::get_instance()->can_display( $_notice ) && Conditional::user_can_subscribe( 'nl_first_steps' ) ) {
		$text = sprintf(
			__( 'Subscribe to our free email course for Google AdSense, receive our newsletter for periodic tutorials, and get %s for Advanced Ads.', 'advanced-ads' ),
			'<strong>' . __( '2 free add-ons', 'advanced-ads' ) . '</strong>'
		);
		include ADVADS_ABSPATH . '/admin/views/notices/inline.php';
	}
endif;
