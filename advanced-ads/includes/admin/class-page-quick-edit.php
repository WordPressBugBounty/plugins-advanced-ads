<?php
/**
 * Admin Page Quick Edit.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.50.0
 */

namespace AdvancedAds\Admin;

use AdvancedAds\Framework\Interfaces\Integration_Interface;
use AdvancedAds\Framework\Utilities\Formatting;
use AdvancedAds\Framework\Utilities\Params;

defined( 'ABSPATH' ) || exit;

/**
 * Admin Page Quick Edit.
 */
class Page_Quick_Edit implements Integration_Interface {

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'quick_edit_custom_box', [ $this, 'add_quick_edit_fields' ], 10, 2 );
		add_action( 'bulk_edit_custom_box', [ $this, 'add_bulk_edit_fields' ], 10, 2 );
		add_action( 'save_post', [ $this, 'save_quick_edit_fields' ] );
		add_action( 'save_post', [ $this, 'save_bulk_edit_fields' ] );
	}

	/**
	 * Save bulk changes
	 *
	 * @return void
	 */
	public function save_bulk_edit_fields() {
		// Not bulk edit, not post/page or not enough permissions.
		if (
			! wp_verify_nonce( sanitize_key( Params::request( '_wpnonce', '', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ), 'bulk-posts' )
			|| ! in_array( sanitize_key( Params::request( 'post_type' ) ), [ 'post', 'page' ], true )
			|| ! current_user_can( 'edit_posts' )
		) {
			return;
		}
		$disable_ads         = Params::request( 'advads_disable_ads', '', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$disable_the_content = Params::request( 'advads_disable_the_content', '', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( empty( $disable_ads ) && empty( $disable_the_content ) ) {
			return;
		}

		$ids = Params::request( 'post', [], FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );

		$this->update_bulk_ad_settings_for_posts( $ids, $disable_ads, $disable_the_content );
	}

	/**
	 * Update ad settings meta for bulk-edited posts.
	 *
	 * @param array<int|string> $ids                 Post IDs from the bulk edit form.
	 * @param string            $disable_ads         Disable ads field value.
	 * @param string            $disable_the_content Disable the content field value.
	 *
	 * @return void
	 */
	private function update_bulk_ad_settings_for_posts( array $ids, string $disable_ads, string $disable_the_content ): void {
		foreach ( $ids as $id ) {
			$post_id = (int) $id;
			if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}

			$post = get_post( $post_id );
			if ( ! $post || ! in_array( $post->post_type, [ 'post', 'page' ], true ) ) {
				continue;
			}

			$meta = get_post_meta( $post_id, '_advads_ad_settings', true );
			if ( ! is_array( $meta ) ) {
				$meta = [];
			}
			if ( ! empty( $disable_ads ) ) {
				$meta['disable_ads'] = Formatting::string_to_bool( $disable_ads ) ? 1 : 0;
			}
			if ( ! empty( $disable_the_content ) ) {
				$meta['disable_the_content'] = Formatting::string_to_bool( $disable_the_content ) ? 1 : 0;
			}
			update_post_meta( $post_id, '_advads_ad_settings', $meta );
		}
	}

	/**
	 * Print bulk edit fields
	 *
	 * @param string $column_name the column name.
	 * @param string $post_type   current post type.
	 *
	 * @return void
	 */
	public function add_bulk_edit_fields( $column_name, $post_type ) {
		if ( ! in_array( $post_type, [ 'post', 'page' ], true ) ) {
			return;
		}
		require ADVADS_ABSPATH . 'views/admin/page-bulk-edit.php';
	}

	/**
	 * Save quick edit changes
	 *
	 * @param int $post_id the post id.
	 *
	 * @return void
	 */
	public function save_quick_edit_fields( $post_id ) {
		// Not inline edit, or no permission.
		if (
			! wp_verify_nonce( sanitize_key( Params::post( '_inline_edit' ) ), 'inlineeditnonce' ) ||
			! current_user_can( 'edit_post', $post_id )
		) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		$meta = [ 'disable_ads' => Params::post( 'advads-disable-ads', 0, FILTER_VALIDATE_INT ) ];
		if ( defined( 'AAP_VERSION' ) ) {
			$meta['disable_the_content'] = Params::post( 'advads-disable-the-content', 0 );
		}

		update_post_meta( $post_id, '_advads_ad_settings', $meta );
	}

	/**
	 * Print quick edit fields.
	 *
	 * @param string $column    the column name.
	 * @param string $post_type current post type.
	 *
	 * @return void
	 */
	public function add_quick_edit_fields( $column, $post_type ) {
		if ( ! in_array( $post_type, [ 'post', 'page' ], true ) ) {
			return;
		}

		if ( 'ad-status' === $column ) {
			require ADVADS_ABSPATH . 'views/admin/page-quick-edit.php';
		}
	}
}
