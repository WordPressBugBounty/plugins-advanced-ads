<?php
/**
 * AdSense account field markup (connected extras + revoke).
 *
 * @package AdvancedAds
 */

use AdvancedAds\Modules\AdSense\Utils;

defined( 'ABSPATH' ) || exit;

$adsense_id = Utils::get_adsense_id();

if ( ! $adsense_id ) {
	return;
}

$has_token = class_exists( 'Advanced_Ads_AdSense_MAPI' )
	? \Advanced_Ads_AdSense_MAPI::has_token( $adsense_id )
	: false;

$mapi         = \Advanced_Ads_AdSense_MAPI::get_option();
$account_name = $mapi['accounts'][ $adsense_id ]['details']['name'] ?? '';
$manual_url   = 'https://wpadvancedads.com/adsense-ads/?utm_source=advanced-ads&utm_medium=link&utm_campaign=adsense-manual-check';
$forum_url    = 'https://wordpress.org/support/plugin/advanced-ads/#new-post';
$networks_url = 'https://wpadvancedads.com/recommended-ad-networks/?utm_source=advanced-ads&utm_medium=link&utm_campaign=recommendations';
?>
<div class="w-full items-start justify-between gap-6">
	<div class="flex shrink-0 flex-col items-start mb-4">
		<?php if ( $adsense_id ) : ?>
			<button
				type="button"
				class="button advads-button-primary"
				data-command="ajax"
				data-action="advads-mapi-revoke-token"
				data-adsense-id="<?php echo esc_attr( $adsense_id ); ?>"
				data-nonce="<?php echo esc_attr( wp_create_nonce( 'advads-mapi' ) ); ?>"
				data-reload="1"
			>
				<?php esc_html_e( 'Revoke API access', 'advanced-ads' ); ?>
			</button>
		<?php endif; ?>
	</div>
	<div class="max-w-xl">
		<?php if ( $account_name ) : ?>
			<p class="m-0 text-sm text-gray-500">
				<?php esc_html_e( 'Account holder name', 'advanced-ads' ); ?>:
				<strong><?php echo esc_html( $account_name ); ?></strong>
			</p>
		<?php endif; ?>

		<p class="m-0 mt-1 text-sm text-gray-500">
			<?php
			printf(
				wp_kses(
					__( 'Problems with AdSense? Check out the %1$s or %2$s.', 'advanced-ads' ),
					[
						'a' => [
							'href'   => true,
							'target' => true,
							'rel'    => true,
							'class'  => true,
						],
					]
				),
				'<a href="' . esc_url( $manual_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'manual', 'advanced-ads' ) . '</a>',
				'<a href="' . esc_url( $forum_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'ask here', 'advanced-ads' ) . '</a>'
			);
			?>
		</p>

		<p class="m-0 mt-1 text-sm text-gray-500">
			<?php
			printf(
				wp_kses(
					/* translators: %s: link to recommended ad networks. */
					__( 'See all %s.', 'advanced-ads' ),
					[
						'a' => [
							'href'   => true,
							'target' => true,
							'rel'    => true,
							'class'  => true,
						],
					]
				),
				'<a href="' . esc_url( $networks_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'recommended ad networks', 'advanced-ads' ) . '</a>'
			);
			?>
		</p>
	</div>
</div>
