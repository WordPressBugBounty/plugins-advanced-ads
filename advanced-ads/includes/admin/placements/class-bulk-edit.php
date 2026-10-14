<?php
/**
 * Bulk edit for placement
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   2.0
 */

namespace AdvancedAds\Admin\Placements;

use AdvancedAds\Abstracts\Placement;
use AdvancedAds\Constants;
use AdvancedAds\Framework\Interfaces\Integration_Interface;
use AdvancedAds\Framework\Utilities\Params;
use AdvancedAds\Utilities\Conditional;

defined( 'ABSPATH' ) || exit;

/**
 * Placement Bulk Edit.
 */
class Bulk_Edit implements Integration_Interface {

	/**
	 * Whether bulk edit already ran this request — core bulk edit fires save_post once per selected post; without this guard the full selection would be re-saved on every call (N² writes).
	 *
	 * @var bool
	 */
	private static $bulk_edit_ran = false;

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'bulk_edit_custom_box', [ $this, 'add_bulk_edit_fields' ], 10, 2 );
		add_action( 'save_post', [ $this, 'save_bulk_edit' ], 100 );
	}

	/**
	 * Add the bulk edit inputs
	 *
	 * @param string $column_name the current column.
	 * @param string $post_type   the current post type.
	 *
	 * @return void
	 */
	public function add_bulk_edit_fields( $column_name, $post_type ) {
		// Early bail!!
		if ( Constants::POST_TYPE_PLACEMENT !== $post_type || 'type' !== $column_name ) {
			return;
		}

		include ADVADS_ABSPATH . 'views/admin/placements/bulk-edit.php';

		/**
		 * Allow add-ons to add more fields.
		 */
		do_action( 'advanced-ads-placement-bulk-edit-fields' );
	}

	/**
	 * Save changes made during bulk edit
	 *
	 * @return void
	 */
	public function save_bulk_edit() {
		// not placement or not enough permissions.
		if ( Constants::POST_TYPE_PLACEMENT !== sanitize_key( Params::get( 'post_type' ) ) || ! Conditional::user_can( 'advanced_ads_manage_placements' )
		) {
			return;
		}

		check_admin_referer( 'bulk-posts' );

		$ad_label = sanitize_text_field( wp_unslash( (string) Params::get( 'ad_label', '' ) ) );
		if ( ! in_array( $ad_label, [ 'default', 'enabled', 'disabled' ], true ) ) {
			$ad_label = '';
		}

		$has_change = '' !== $ad_label;

		/**
		 * Filter to determine if there are changes to be saved during bulk edit.
		 *
		 * @param bool $has_change Indicates if there are changes to be saved.
		 */
		$has_change = apply_filters( 'advanced-ads-placement-bulk-edit-has-change', $has_change );

		// No changes, bail out.
		if ( ! $has_change ) {
			return;
		}

		$placements = array_map(
			static function ( $placement ) {
				return wp_advads_get_placement( absint( $placement ) );
			},
			wp_unslash( Params::get( 'post', [], FILTER_DEFAULT, FILTER_REQUIRE_ARRAY ) )
		);

		$this->persist_bulk_edit( $placements, $ad_label );
	}

	/**
	 * Persist bulk-edit field changes once per request.
	 *
	 * @param array  $placements Placement instances.
	 * @param string $ad_label   Whitelisted ad label value from the bulk form.
	 *
	 * @return void
	 */
	private function persist_bulk_edit( array $placements, string $ad_label ): void {
		if ( self::$bulk_edit_ran ) {
			return;
		}
		self::$bulk_edit_ran = true;

		foreach ( $placements as $placement ) {
			if ( ! $placement ) {
				continue;
			}

			if ( '' !== $ad_label ) {
				$placement->set_prop( 'ad_label', $ad_label );
			}

			/**
			 * Allow add-on to bulk save placements.
			 *
			 * @param Placement $placement current placement being saved.
			 */
			$placement = apply_filters( 'advanced-ads-placement-bulk-edit-save', $placement );

			$placement->save();
		}
	}
}
