<?php
/**
 * AdSense module admin
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

namespace AdvancedAds\Modules\AdSense;

use AdvancedAds\Abstracts\Ad;
use AdvancedAds\Constants;
use AdvancedAds\Framework\Interfaces\Integration_Interface;
use AdvancedAds\Framework\Utilities\Params;
use AdvancedAds\Modules\Privacy\Utils as PrivacyUtils;
use AdvancedAds\Utilities\Conditional;

/**
 * Class Admin.
 */
class Admin implements Integration_Interface {
	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'admin_print_scripts', [ $this, 'print_scripts' ] );
		add_filter( 'advanced-ads-ad-notices', [ $this, 'ad_notices' ], 10, 2 );
		add_action( 'advanced-ads-ad-pre-save', [ $this, 'save_ad_options' ], 10, 2 );
	}

	/**
	 * Add AdSense-related scripts.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		if ( Conditional::is_screen_advanced_ads() ) {
			if ( ! wp_script_is( 'advads/connect-adsense', 'registered' ) ) {
				wp_enqueue_script( 'advads/connect-adsense', ADVADS_BASE_URL . 'modules/gadsense/assets/js/connect-adsense.js', [ 'jquery' ], ADVADS_VERSION, false );
			}
			if ( ! has_action( 'admin_footer', [ $this, 'print_connect_adsense' ] ) ) {
				add_action( 'admin_footer', [ $this, 'print_connect_adsense' ] );
			}
		}

		if ( $this->is_on_screen() ) {
			$scripts = apply_filters( 'advanced-ads-gadsense-ad-param-script', [] );
			$styles  = apply_filters( 'advanced-ads-gadsense-ad-param-style', [] );

			$this->enqueue_handles( $scripts, 'wp_enqueue_script' );
			$this->enqueue_handles( $styles, 'wp_enqueue_style' );
		}
	}

	/**
	 * Load JavaScript needed on some pages.
	 *
	 * @return void
	 */
	public function print_scripts(): void {
		global $pagenow;

		if ( $this->is_on_screen() ) {
			?>
			<script type="text/javascript">
				if ( 'undefined' == typeof gadsenseData ) {
					window.gadsenseData = {};
				}
				// todo: check why we are using echo here.
				gadsenseData['pagenow'] = '<?php echo esc_attr( $pagenow ); ?>';
			</script>
			<?php
		}
	}

	/**
	 * Show AdSense ad specific notices in parameters box
	 *
	 * @param array<int, array>    $notices Some notices to show in the parameters box.
	 * @param array<string, mixed> $box     Meta box.
	 *
	 * @return array<int, array>
	 */
	public function ad_notices( array $notices, array $box ): array {
		if ( 'ad-parameters-box' === $box['id'] ) {
			// Add warning if this is a responsive ad unit without custom sizes and position is set to left or right.
			// Hidden by default and made visible with JS.
			$notices[] = [
				'text'  => sprintf(
					/* translators: %s is a URL. */
					__( 'Responsive AdSense ads don’t work reliably with <em>Position</em> set to left or right. Either switch the <em>Type</em> to "normal" or follow <a href="%s" target="_blank">this tutorial</a> if you want the ad to be wrapped in text.', 'advanced-ads' ),
					'https://wpadvancedads.com/adsense-responsive-custom-sizes/?utm_source=advanced-ads&utm_medium=link&utm_campaign=adsense-custom-sizes-tutorial'
				),
				'class' => 'advads-ad-notice-responsive-position advads-notice-inline advads-error hidden',
			];

			// Show hint about AdSense In-feed add-on.
			if ( ! class_exists( 'Advanced_Ads_In_Feed', false ) && ! class_exists( 'Advanced_Ads_Pro_Admin', false ) ) {
				$notices[] = [
					'text'  => sprintf(
							/* translators: %s is a URL. */
						__( '<a href="%s" target="_blank">Install the free AdSense In-feed add-on</a> in order to place ads between posts.', 'advanced-ads' ),
						wp_nonce_url(
							self_admin_url( 'update.php?action=install-plugin&plugin=advanced-ads-adsense-in-feed' ),
							'install-plugin_advanced-ads-adsense-in-feed'
						)
					),
					'class' => 'advads-ad-notice-in-feed-add-on advads-notice-inline advads-idea hidden',
				];
			}
		}

		return $notices;
	}

	/**
	 * Save ad options.
	 *
	 * @param Ad                   $ad        Ad instance.
	 * @param array<string, mixed> $post_data Post data array.
	 *
	 * @return void
	 */
	public function save_ad_options( Ad $ad, $post_data ): void {
		if ( ! $ad->is_type( 'adsense' ) ) {
			return;
		}

		// Remove ad size options for responsive AdSense ads.
		$content = json_decode( str_replace( "\n", '', wp_unslash( $post_data['content'] ) ), true );
		if ( in_array(
			$content['unitType'] ?? 'none',
			[
				'responsive',
				'link',
				'link-responsive',
				'matched-content',
				'in-article',
				'in-feed',
			],
			true
		)
		) {
			$ad->set_width( 0 );
			$ad->set_height( 0 );
		}
	}

	/**
	 * Prints AdSense connection markup.
	 *
	 * @return void
	 */
	public function print_connect_adsense(): void {
		require_once ADVADS_ABSPATH . 'modules/gadsense/views/connect-adsense.php';
	}

	/**
	 * Is on screen.
	 *
	 * @return bool
	 */
	private function is_on_screen(): bool {
		global $pagenow, $post_type;

		return ( 'post-new.php' === $pagenow && Constants::POST_TYPE_AD === $post_type ) ||
			( 'post.php' === $pagenow && Constants::POST_TYPE_AD === $post_type && 'edit' === Params::get( 'action' ) );
	}

	/**
	 * Enqueue handles.
	 *
	 * @param array  $handles Handles to enqueue.
	 * @param string $func Function to use to enqueue the handle.
	 *
	 * @return void
	 */
	private function enqueue_handles( array $handles, string $func ): void {
		foreach ( $handles as $handle => $value ) {
			if ( empty( $handle ) ) {
				continue;
			}

			if ( ! empty( $handle ) && empty( $value ) ) {
				wp_enqueue_script( $handle );
				continue;
			}

			if ( ! isset( $value['version'] ) ) {
				$value['version'] = null;
			}

			$func( $handle, $value['path'], $value['dep'], $value['version'] );
		}
	}
}
