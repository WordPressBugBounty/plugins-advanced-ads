<?php
/**
 * Renders the AdSense ad parameters metabox on the ad edit page.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 *
 * @var string $unit_type The type of the AdSense unit, e.g. 'responsive'.
 * @var string $unit_resize The value of the resize option.
 * @var string $unit_id The client ID.
 * @var string $unit_code The slot ID.
 * @var string $unit_pubid The publisher ID which differs if there comes a different it from the content.
 * @var string $layout The in-feed layout.
 * @var string $layout_key The in-feed layout key.
 * @var string $json_content The content as JSON.
 * @var string $pub_id The publisher ID.
 * @var Ad_Adsense $ad The AdSense Ad type.
 * @var string|false $pub_id_errors A string with error messages, or false.
 * @var string $content The content.
 * @var array{default_width: string, default_height: string, at_media: array<int|string, mixed>} $extra_params Filterable extra params that can be passed.
 */

use AdvancedAds\Modules\AdSense\Utils;
use AdvancedAds\Modules\AdSense\Types\Ad_Adsense;

if ( ! defined( 'WPINC' ) ) {
	die();
}

$adsense_id   = Utils::get_adsense_id();
$has_token    = \Advanced_Ads_AdSense_MAPI::has_token( $adsense_id );
$sizing_array = apply_filters( 'advanced-ads-gadsense-responsive-sizing', [ 'auto' => __( 'Auto', 'advanced-ads' ) ] );

$use_paste_code = apply_filters( 'advanced-ads-gadsense-use-pastecode', true );

$is_responsive = 'responsive' === $unit_type;
$is_in_feed    = 'in-feed' === $unit_type;
$is_link_unit  = in_array( $unit_type, [ 'link', 'link-responsive' ], true );

// Units that AdSense sizes itself have no use for the fixed size fields.
if ( in_array( $unit_type, [ 'responsive', 'link-responsive', 'matched-content' ], true ) ) {
	echo '<style> #advanced-ads-ad-parameters-size {display: none;}	</style>';
}

/*
 * Visibility of the conditional field rows. Resolved once here instead of repeating the same
 * expression inside every label and wrapper below.
 */
$hidden        = ' style="display: none;"';
$hide_resize   = ( ! $is_responsive || 2 > count( $sizing_array ) ) ? $hidden : '';
$hide_in_feed  = $is_in_feed ? '' : $hidden;
$hide_clearfix = $is_responsive ? '' : $hidden;

// Warn when the slot ID looks like it contains the publisher ID.
$slot_holds_pub_id = '' !== $pub_id
	&& 0 === strpos( $pub_id, 'pub-' )
	&& false !== strpos( $unit_code, substr( $pub_id, 4 ) );

$settings_url = admin_url( 'admin.php?page=advanced-ads-app&path=/settings&tab=adsense' );
?>
<?php if ( $has_token ) : ?>
	<?php
	$mapi_options = \Advanced_Ads_AdSense_MAPI::get_option();
	$quota        = \Advanced_Ads_AdSense_MAPI::get_instance()->get_quota();
	?>
	<script type="text/javascript">
		if ( 'undefined' == typeof window.AdsenseMAPI ) {
			var AdsenseMAPI = {};
		}
		AdsenseMAPI.hasToken         = true;
		AdsenseMAPI.nonce            = '<?php echo esc_js( wp_create_nonce( 'advads-mapi' ) ); ?>';
		AdsenseMAPI.quota            = <?php echo wp_json_encode( $quota ); ?>;
		AdsenseMAPI.pubId            = '<?php echo esc_js( $pub_id ); ?>';
		AdsenseMAPI.adStatus         = '<?php echo esc_js( $ad->get_status() ); ?>';
		AdsenseMAPI.unsupportedUnits = <?php echo wp_json_encode( $mapi_options['unsupported_units'] ?? [] ); ?>;
	</script>
<?php endif; ?>

<script type="text/javascript">
if ( 'undefined' === typeof gadsenseData ) {
	window.gadsenseData = {};
}
gadsenseData['pubId'] = '<?php echo esc_js( $adsense_id ); ?>';
gadsenseData['msg']   = {
	unknownAd:     '<?php echo esc_js( __( "The ad details couldn't be retrieved from the ad code", 'advanced-ads' ) ); ?>',
	pubIdMismatch: '<?php echo esc_js( __( 'Warning: The AdSense account from this code does not match the one set in the Advanced Ads options.', 'advanced-ads' ) ); ?>'
};
</script>

<input type="hidden" id="advads-ad-content-adsense" name="advanced_ad[content]" value="<?php echo esc_attr( $json_content ); ?>"/>
<input type="hidden" name="unit_id" id="unit_id" value="<?php echo esc_attr( $unit_id ); ?>"/>
<?php if ( $use_paste_code ) : ?>
	<div class="advads-adsense-code" style="display: none;">
		<p class="description"><?php echo wp_kses_post( __( 'Copy the ad code from your AdSense account, paste it into the area below and click on <em>Get details</em>.', 'advanced-ads' ) ); ?></p>
		<textarea rows="10" cols="40" id="advads-adsense-content" class="advads-adsense-content"></textarea>
		<button class="button button-primary advads-adsense-submit-code"><?php esc_html_e( 'Get details', 'advanced-ads' ); ?></button>&nbsp;&nbsp;
		<button class="button button-secondary advads-adsense-close-code"><?php esc_html_e( 'cancel', 'advanced-ads' ); ?></button>&nbsp;&nbsp;
		<?php if ( ! $has_token ) : ?>
			<a style="vertical-align:sub;font-weight:600;font-style:italic;" href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'connect to your AdSense account', 'advanced-ads' ); ?></a>
		<?php endif; ?>
		<div id="pastecode-msg"></div>
	</div>
	<?php
	if ( $has_token && Advanced_Ads_Checks::php_version_minimum() ) {
		Utils::get_mapi_ad_selector();
	}

	// The $network variable needs to be set for the view to work!
	$network = Advanced_Ads_Network_Adsense::get_instance();
	include_once ADVADS_ABSPATH . '/modules/gadsense/views/external-ads-links.php';
	?>
<?php endif; ?>
<p id="adsense-ad-param-error"></p>
<?php ob_start(); ?>
<label for="unit-code" class="label"><?php esc_html_e( 'Ad Slot ID', 'advanced-ads' ); ?></label>
<div>
	<input type="text" name="unit-code" id="unit-code" value="<?php echo esc_attr( $unit_code ); ?>"/>
	<input type="hidden" name="advanced_ad[output][adsense-pub-id]" id="advads-adsense-pub-id" value="<?php echo esc_attr( $unit_pubid ); ?>"/>
	<?php if ( $unit_pubid ) : ?>
		<?php
		/* translators: %s is the publisher ID. */
		printf( esc_html__( 'Publisher ID: %s', 'advanced-ads' ), esc_html( $unit_pubid ) );
		?>
	<?php endif; ?>
	<p id="advads-pubid-in-slot" class="advads-notice-inline advads-error"<?php echo $slot_holds_pub_id ? '' : $hidden; ?>><?php esc_html_e( 'The ad slot ID is either a number or empty and not the same as the publisher ID.', 'advanced-ads' ); ?></p>
</div>
<hr/>
<?php
$unit_code_markup = ob_get_clean();
echo apply_filters( 'advanced-ads-gadsense-unit-code-markup', $unit_code_markup, $unit_code );

if ( $pub_id_errors ) :
	?>
	<p>
		<span class="advads-notice-inline advads-error">
			<?php echo esc_html( $pub_id_errors ); ?>
		</span>
		<?php
		/* translators: %s the setting page link */
		printf( wp_kses_post( __( 'Please <a href="%s" target="_blank">change it here</a>.', 'advanced-ads' ) ), esc_url( $settings_url ) );
		?>
	</p>
<?php endif; ?>
	<label class="label" for="unit-type" id="unit-type-block"><?php esc_html_e( 'Type', 'advanced-ads' ); ?></label>
	<div>
		<select name="unit-type" id="unit-type">
			<option value="normal" <?php selected( $unit_type, 'normal' ); ?>><?php esc_html_e( 'Fixed Size', 'advanced-ads' ); ?></option>
			<option value="responsive" <?php selected( $unit_type, 'responsive' ); ?>><?php esc_html_e( 'Responsive', 'advanced-ads' ); ?></option>
			<option value="matched-content" <?php selected( $unit_type, 'matched-content' ); ?>><?php esc_html_e( 'Multiplex', 'advanced-ads' ); ?></option>
			<?php if ( 'link' === $unit_type ) : ?>
				<option value="link" selected><?php esc_html_e( 'Link ads', 'advanced-ads' ); ?></option>
			<?php endif; ?>
			<?php if ( 'link-responsive' === $unit_type ) : ?>
				<option value="link-responsive" selected><?php esc_html_e( 'Link ads (Responsive)', 'advanced-ads' ); ?></option>
			<?php endif; ?>
			<option value="in-article" <?php selected( $unit_type, 'in-article' ); ?>><?php esc_html_e( 'In-article', 'advanced-ads' ); ?></option>
			<option value="in-feed" <?php selected( $unit_type, 'in-feed' ); ?>><?php esc_html_e( 'In-feed', 'advanced-ads' ); ?></option>
		</select>
		<a href="https://wpadvancedads.com/google-adsense-ad-formats/?utm_source=advanced-ads&utm_medium=link&utm_campaign=adsense-ad-types" class="advads-manual-link" target="_blank"><?php esc_html_e( 'Manual', 'advanced-ads' ); ?></a>
	</div>
<?php if ( $is_link_unit ) : ?>
	<p class="advads-message-warning"><?php esc_html_e( 'Google AdSense deprecated Link Units. Please choose another type.', 'advanced-ads' ); ?>
		<a href="https://wpadvancedads.com/adsense-link-units/" target="_blank" rel="noopener">
			<?php esc_html_e( 'Learn more', 'advanced-ads' ); ?>
		</a>
	</p>
<?php endif; ?>
	<hr/>
	<label class="label" for="ad-resize-type" id="resize-label"<?php echo $hide_resize; ?>><?php esc_html_e( 'Resizing', 'advanced-ads' ); ?></label>
	<div<?php echo $hide_resize; ?>>
		<select name="ad-resize-type" id="ad-resize-type">
			<?php foreach ( $sizing_array as $key => $desc ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $unit_resize ); ?>><?php echo esc_html( $desc ); ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<hr>
	<label class="label advads-adsense-layout" for="ad-layout" id="advads-adsense-layout"<?php echo $hide_in_feed; ?>><?php esc_html_e( 'Layout', 'advanced-ads' ); ?></label>
	<div<?php echo $hide_in_feed; ?>>
		<input name="ad-layout" id="ad-layout" value="<?php echo esc_attr( $layout ?? '' ); ?>"/>
	</div>
	<hr>
	<label class="label advads-adsense-layout-key" for="ad-layout-key" id="advads-adsense-layout-key"<?php echo $hide_in_feed; ?>><?php esc_html_e( 'Layout-Key', 'advanced-ads' ); ?></label>
	<div<?php echo $hide_in_feed; ?>>
		<input type="text" name="ad-layout-key" id="ad-layout-key" value="<?php echo esc_attr( $layout_key ?? '' ); ?>"/>
	</div>
	<hr/>
	<label for="advanced_ad[output][clearfix_before]" class="label clearfix-before"<?php echo $hide_clearfix; ?>><?php esc_html_e( 'Clearfix', 'advanced-ads' ); ?></label>
	<div class="clearfix-before"<?php echo $hide_clearfix; ?>>
		<input type="hidden" name="advanced_ad[output][clearfix_before]" value="0"/>
		<input type="checkbox" name="advanced_ad[output][clearfix_before]" value="1" <?php checked( ! empty( $ad->get_prop( 'clearfix_before' ) ), true ); ?> />
		<p class="description inline">
			<?php esc_html_e( 'Enable this if responsive ads cover something on your site.', 'advanced-ads' ); ?>
		</p>
	</div>
	<hr class="clearfix-before"<?php echo $hide_clearfix; ?>/>
<?php
do_action( 'advanced-ads-gadsense-extra-ad-param', $extra_params, $content, $ad );
