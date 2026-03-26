<?php
/**
 * Placements screen.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.47.0
 */

namespace AdvancedAds\Admin\Pages;

use WP_Screen;
use AdvancedAds\Constants;
use AdvancedAds\Abstracts\Screen;
use AdvancedAds\Utilities\Conditional;
use AdvancedAds\Admin\Placements\List_Table;
use AdvancedAds\Admin\Placements\Create_Modal;

defined( 'ABSPATH' ) || exit;

/**
 * Placements.
 */
class Placements extends Screen {

	/**
	 * Screen unique id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'placements';
	}

	/**
	 * Register screen into WordPress admin area.
	 *
	 * @return void
	 */
	public function register_screen(): void {
		add_submenu_page(
			ADVADS_SLUG,
			__( 'Ad Placements', 'advanced-ads' ),
			__( 'Placements', 'advanced-ads' ),
			Conditional::user_cap( 'advanced_ads_manage_placements' ),
			'edit.php?post_type=' . Constants::POST_TYPE_PLACEMENT
		);

		// Keep the manual placements page around, but redirect it to the custom post type.
		$old_placements_hook = add_submenu_page(
			'',
			'',
			'',
			Conditional::user_cap( 'advanced_ads_manage_placements' ),
			ADVADS_SLUG . '-placements',
			'__return_true'
		);
		$this->set_hook( 'edit-' . Constants::POST_TYPE_PLACEMENT );
		add_action( 'current_screen', [ $this, 'load_placement_ui' ] );
		add_action( 'load-' . $old_placements_hook, [ $this, 'redirect_to_post_type' ] );
	}

	/**
	 * Enqueue assets
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		wp_advads()->registry->enqueue_style( 'screen-placements-listing' );
		wp_advads()->registry->enqueue_script( 'screen-placements-listing' );

		wp_advads_json_add(
			'placements',
			[
				'updateItemNonce' => wp_create_nonce( 'placement-update-item'),
				'pickerUrl'       => $this->get_placement_picker_url(),
			]
		);

		// Localize texts.
		$i18n = [
			'placements'               => [
				'created'   => __( 'New placement created', 'advanced-ads' ),
				'updated'   => __( 'Placement updated', 'advanced-ads' ),
				'closeSave' => __( 'Close and save', 'advanced-ads' ),
				'saveNew'   => __( 'Save new placement', 'advanced-ads' ),
				'draft'     => __( 'Draft', 'advanced-ads' ),
			],
		];
		wp_advads_json_add( 'i18n', $i18n );
	}

	/**
	 * Redirect old placement page to custom post type.
	 *
	 * @return void
	 */
	public function redirect_to_post_type(): void {
		wp_safe_redirect( 'edit.php?post_type=' . Constants::POST_TYPE_PLACEMENT );
	}

	/**
	 * Load list table
	 *
	 * @param WP_Screen $screen Current screen instance.
	 *
	 * @return void
	 */
	public function load_placement_ui( WP_Screen $screen ): void {
		if ( 'edit-' . Constants::POST_TYPE_PLACEMENT === $screen->id ) {
			( new List_Table() )->hooks();
			( new Create_Modal() )->hooks();
		}
	}

	/**
	 * Get page header arguments
	 *
	 * @return array
	 */
	public function define_header_args(): array {
		return [
			'title'            => __( 'Your Placements', 'advanced-ads' ),
			'breadcrumb_title' => __( 'Placements', 'advanced-ads' ),
			'manual_url'       => 'https://wpadvancedads.com/manual/placements/',
		];
	}

	/**
	 * Add actions to the header
	 *
	 * @return void
	 */
	public function header_actions(): void {
		?>
		<a href="#modal-placement-new" data-dialog="modal-placement-new" class="button button-primary advads-button">
			<span class="dashicons dashicons-plus -ml-1.5 leading-6"></span>
			<span><?php esc_html_e( 'New Placement', 'advanced-ads' ); ?></span>
		</a>
		<?php
	}

	/**
	 * Get the URL where the user is redirected after activating the frontend picker for a "Content" placement.
	 *
	 * @return string
	 */
	private function get_placement_picker_url() {
		$location = false;

		if ( get_option( 'show_on_front' ) === 'posts' ) {
			$recent_posts = wp_get_recent_posts(
				[
					'numberposts' => 1,
					'post_type'   => 'post',
					'post_status' => 'publish',
				],
				'OBJECT'
			);

			if ( $recent_posts ) {
				$location = get_permalink( $recent_posts[0] );
			}
		}

		return $location ?? home_url();
	}
}
