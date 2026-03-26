<?php
/**
 * Groups screen.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.47.0
 */

namespace AdvancedAds\Admin\Pages;

use AdvancedAds\Constants;
use AdvancedAds\Abstracts\Screen;
use AdvancedAds\Admin\Groups\Create_Modal;
use AdvancedAds\Utilities\Conditional;
use AdvancedAds\Admin\Groups\List_Table;

defined( 'ABSPATH' ) || exit;

/**
 * Groups.
 */
class Groups extends Screen {

	/**
	 * Hold table object.
	 *
	 * @var null|List_Table
	 */
	private $list_table = null;

	/**
	 * Screen unique id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'groups';
	}

	/**
	 * Register screen into WordPress admin area.
	 *
	 * @return void
	 */
	public function register_screen(): void {
		$hook = add_submenu_page(
			ADVADS_SLUG,
			__( 'Ad Groups & Rotations', 'advanced-ads' ),
			__( 'Groups & Rotation', 'advanced-ads' ),
			Conditional::user_cap( 'advanced_ads_edit_ads' ),
			ADVADS_SLUG . '-groups',
			[ $this, 'display' ]
		);

		$this->set_hook( $hook );
		add_action( 'current_screen', [ $this, 'add_screen_options' ], 5 );
		add_action( 'current_screen', [ $this, 'get_list_table' ] );
	}

	/**
	 * Enqueue assets
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		wp_advads()->registry->enqueue_style( 'screen-groups-listing' );
		wp_advads()->registry->enqueue_script( 'screen-groups-listing' );

		// Localize texts.
		$i18n = [
			'groups'           => [
				'save'    => __( 'Save', 'advanced-ads' ),
				'saveNew' => __( 'Save New Group', 'advanced-ads' ),
				'updated' => __( 'Group updated', 'advanced-ads' ),
				'deleted' => __( 'Group deleted', 'advanced-ads' ),
				/* translators: an ad group title. */
				'confirmation' => __( 'You are about to permanently delete %s', 'advanced-ads' ),
			],
		];
		wp_advads_json_add( 'i18n', $i18n );
	}

	/**
	 * Display screen content.
	 *
	 * @return void
	 */
	public function display(): void {
		global $wp_list_table;

		$wp_list_table = $this->get_list_table();
		( new Create_Modal() )->hooks();

		include_once ADVADS_ABSPATH . 'views/admin/groups/page.php';
	}

	/**
	 * Get list table object
	 *
	 * @return null|Groups_List_Table
	 */
	public function get_list_table() {
		$is_screen = wp_advads()->screens->is_screen( $this->get_id() );
		$wp_screen = get_current_screen();
		if ( $is_screen && null === $this->list_table ) {
			wp_advads()->registry->enqueue_script( 'groups' );
			$wp_screen->taxonomy  = Constants::TAXONOMY_GROUP;
			$wp_screen->post_type = Constants::POST_TYPE_AD;
			$this->list_table  = new List_Table();
		}

		return $this->list_table;
	}

	/**
	 * Add screen options.
	 *
	 * @return void
	 */
	public function add_screen_options(): void {
		// Early bail!!
		$is_screen = wp_advads()->screens->is_screen( $this->get_id() );
		if ( ! $is_screen ) {
			return;
		}

		add_screen_option(
			'per_page',
			[
				'default' => 20,
				'option'  => 'edit_' . Constants::TAXONOMY_GROUP . '_per_page',
			]
		);
	}

	/**
	 * Get page header arguments
	 *
	 * @return array
	 */
	public function define_header_args(): array {
		return [
			'title'            => __( 'Your Groups', 'advanced-ads' ),
			'breadcrumb_title' => __( 'Groups', 'advanced-ads' ),
			'manual_url'       => 'https://wpadvancedads.com/manual/ad-groups/',
		];
	}

	/**
	 * Add actions to the header
	 *
	 * @return void
	 */
	public function header_actions(): void {
		?>
		<a href="#modal-group-new" data-dialog="modal-group-new" class="button button-primary advads-button">
			<span class="dashicons dashicons-plus -ml-1.5 leading-6"></span>
			<span><?php esc_html_e( 'New Ad Group', 'advanced-ads' ); ?></span>
		</a>
		<?php
	}
}
