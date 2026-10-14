<?php
/**
 * Upsell prompts — upgrade links, Pro feature hints and the duplicate-link teaser shown to users without the matching add-on.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   2.0.0
 */

namespace AdvancedAds\Admin;

defined( 'ABSPATH' ) || exit;

use AdvancedAds\Constants;
use AdvancedAds\Abstracts\Ad;
use AdvancedAds\Utilities\Conditional;
use AdvancedAds\Framework\Interfaces\Integration_Interface;
use WP_Post;

/**
 * Upsell prompts.
 */
class Upsell implements Integration_Interface {

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function hooks(): void {
		// Show notice in Ad Parameters when someone uses an Ad Manager ad in the plain text code field.
		add_filter( 'advanced-ads-ad-notices', [ $this, 'ad_notices' ], 10, 3 );

		// Show AMP options on ad edit page of AdSense ads.
		add_action( 'advanced-ads-gadsense-extra-ad-param', [ $this, 'adsense_type_amp_options' ] );

		// Add Duplicate.
		add_filter( 'post_row_actions', [ $this, 'render_duplicate_link' ], 10, 2 );
		add_filter( 'post_row_actions', [ $this, 'render_placement_duplicate_link' ], 10, 2 );
		add_action( 'post_submitbox_start', [ $this, 'render_duplicate_link_in_submit_box' ] );
	}

	/**
	 * Show an upgrade link
	 *
	 * @param string|null $title link text, the default one when empty.
	 * @param string      $url target URL.
	 * @param string      $utm_campaign utm_campaign value to attach to the URL.
	 *
	 * @return void
	 */
	public static function upgrade_link( $title = '', $url = '', $utm_campaign = 'upgrade' ): void {
		$title = ! empty( $title ) ? $title : __( 'Upgrade', 'advanced-ads' );
		$url   = ! empty( $url ) ? $url : 'https://wpadvancedads.com/add-ons/';

		$url = add_query_arg(
			[
				'utm_source'   => 'advanced-ads',
				'utm_medium'   => 'link',
				'utm_campaign' => $utm_campaign,
			],
			$url
		);

		include ADVADS_ABSPATH . 'admin/views/upgrades/upgrade-link.php';
	}

	/**
	 * Show an Advanced Ads Pro upsell pitch
	 *
	 * @param string $utm_campaign utm_campaign value to attach to the URL.
	 *
	 * @return void
	 */
	public static function pro_feature_link( $utm_campaign = '' ): void {
		self::upgrade_link(
			__( 'Pro Feature', 'advanced-ads' ),
			'https://wpadvancedads.com/advanced-ads-pro/',
			$utm_campaign
		);
	}

	/**
	 * Show notices in the Ad Parameters meta box
	 *
	 * @param array<int, array<string, mixed>> $notices Notices.
	 * @param array<string, mixed>             $box     Current meta box.
	 * @param Ad                               $ad post object.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function ad_notices( $notices, $box, $ad ) {
		// Show notice when someone uses an Ad Manager ad in the plain text code field.
		if ( ! defined( 'AAGAM_VERSION' ) && 'ad-parameters-box' === $box['id'] ) {
			if ( $ad->is_type( 'plain' ) && strpos( $ad->get_content(), 'div-gpt-ad-' ) ) {
				$notices[] = [
					'text' => sprintf(
						/* translators: %1$s and %2$s are opening and closing <a> tags */
						esc_html__( 'This looks like a Google Ad Manager ad. Use the %1$sGAM Integration%2$s.', 'advanced-ads' ),
						'<a href="https://wpadvancedads.com/add-ons/google-ad-manager/?utm_source=advanced-ads&utm_medium=link&utm_campaign=upgrade-ad-parameters-gam" target="_blank">',
						'</a>'
					) . ' ' . __( 'A quick and error-free way of implementing ad units from your Google Ad Manager account.', 'advanced-ads' ),
				];
			}
		}

		return $notices;
	}

	/**
	 * AMP options for AdSense ads in the Ad Parameters on the ad edit page.
	 *
	 * @return void
	 */
	public function adsense_type_amp_options() {
		if ( ! defined( 'AAR_VERSION' ) && \Advanced_Ads_Checks::active_amp_plugin() ) {
			include_once ADVADS_ABSPATH . 'admin/views/upgrades/adsense-amp.php';
		}
	}

	/**
	 * Add the link to action list for post_row_actions
	 *
	 * @param array<string, string> $actions List of existing actions.
	 * @param WP_Post               $post Post object.
	 *
	 * @return array<string, string> with actions.
	 */
	public function render_duplicate_link( $actions, $post ) {
		return $this->maybe_add_duplicate_row_action( $actions, $post, Constants::POST_TYPE_AD );
	}

	/**
	 * Add the link to action list for placements.
	 *
	 * @param array<string, string> $actions List of existing actions.
	 * @param WP_Post               $post Post object.
	 *
	 * @return array<string, string> with actions.
	 */
	public function render_placement_duplicate_link( $actions, $post ) {
		return $this->maybe_add_duplicate_row_action( $actions, $post, Constants::POST_TYPE_PLACEMENT );
	}

	/**
	 * Add the link to the submit box on the ad edit screen.
	 *
	 * @return void
	 */
	public function render_duplicate_link_in_submit_box() {
		global $post;

		if (
			$post
			&& 'edit' === $post->filter // only for already saved ads.
			&& $this->can_show_duplicate_upsell( $post, Constants::POST_TYPE_AD )
		) {
			?>
			<div>
				<?php echo wp_kses_post( $this->create_duplicate_link() ); ?>
			</div>
			<?php
		}
	}

	/**
	 * Maybe append the Duplicate upsell row action for a post type.
	 *
	 * @param array    $actions   Existing row actions.
	 * @param \WP_Post $post      Post object.
	 * @param string   $post_type Expected post type.
	 *
	 * @return array
	 */
	private function maybe_add_duplicate_row_action( $actions, $post, string $post_type ): array {
		if ( $this->can_show_duplicate_upsell( $post, $post_type ) ) {
			$actions['copy-ad'] = $this->create_duplicate_link( $post_type );
		}

		return $actions;
	}

	/**
	 * Whether to show the Pro Duplicate upsell for the given post.
	 *
	 * @param \WP_Post $post      Post object.
	 * @param string   $post_type Expected post type.
	 *
	 * @return bool
	 */
	private function can_show_duplicate_upsell( $post, string $post_type ): bool {
		return ! defined( 'AAP_VERSION' )
			&& isset( $post->post_type )
			&& $post_type === $post->post_type
			&& Conditional::user_can( 'advanced_ads_edit_ads' );
	}

	/**
	 * Generate text and upgrade link for the Duplicate function
	 *
	 * @param string $post_type post type.
	 *
	 * @return string
	 */
	public function create_duplicate_link( $post_type = Constants::POST_TYPE_AD ) {
		ob_start();

		$utm_campaign = ( Constants::POST_TYPE_PLACEMENT === $post_type ) ? 'duplicate-placement' : 'duplicate-ad';

		self::upgrade_link(
			null,
			sprintf(
				'https://wpadvancedads.com/advanced-ads-pro/?utm_source=advanced-ads&utm_medium=link&utm_campaign=%s',
				$utm_campaign
			),
			$utm_campaign
		);

		return sprintf(
			'%1$s (%2$s)',
			esc_html__( 'Duplicate', 'advanced-ads' ),
			trim( ob_get_clean() )
		);
	}
}
