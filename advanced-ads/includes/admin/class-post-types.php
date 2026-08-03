<?php
/**
 * The class is responsible for handling the edit posts views and some functionality on the edit post screen.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.48.2
 */

namespace AdvancedAds\Admin;

use AdvancedAds\Constants;
use AdvancedAds\Framework\Interfaces\Integration_Interface;
use AdvancedAds\Framework\Utilities\Params;
use stdClass;

defined( 'ABSPATH' ) || exit;

/**
 * Post Types.
 */
class Post_Types implements Integration_Interface {

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'before_delete_post', [ $this, 'before_delete_ad' ], 10, 2 );

		add_filter( 'post_updated_messages', [ $this, 'post_updated_messages' ] );
		add_filter( 'bulk_post_updated_messages', [ $this, 'bulk_post_updated_messages' ], 10, 2 );

		add_filter( 'wp_count_posts', [ $this, 'update_count_posts' ], 10, 2 );
		add_filter( 'get_edit_post_link', [ $this, 'get_edit_post_link' ], 10, 2 );
	}

	/**
	 * Prepare the ad groups for ad deletion
	 *
	 * @param int     $post_id id of the post.
	 * @param WP_Post $post    Post object.
	 *
	 * @return void
	 */
	public function before_delete_ad( $post_id, $post ): void {
		global $wpdb;

		if (
			! current_user_can( 'delete_posts' )
			|| $post_id <= 0
			|| Constants::POST_TYPE_AD !== $post->post_type
		) {
			return;
		}

		/**
		 * Images uploaded to an image ad type get the `_advanced-ads_parent_id` meta key from WordPress automatically
		 * the following SQL query removes that meta data from any attachment when the ad is removed.
		 */
		$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = %d", '_advanced-ads_parent_id', $post_id ) ); // phpcs:ignore

		foreach ( wp_advads_get_groups_by_ad_id( $post_id ) as $group ) {
			$ad_weights = $group->get_ad_weights();
			unset( $ad_weights[ $post_id ] );
			$group->set_ad_weights( $ad_weights );
			$group->save();
		}
	}

	/**
	 * Update post counts to have expiring ads.
	 *
	 * @param stdClass $counts An object containing the current post_type's post
	 *                         counts by status.
	 * @param string   $type   Post type.
	 *
	 * @return stdClass
	 */
	public function update_count_posts( $counts, $type ): stdClass {
		if ( Constants::POST_TYPE_AD !== $type ) {
			return $counts;
		}

		$now      = time();
		$expiring = 0;

		foreach ( wp_advads_get_ad_summaries() as $summary ) {
			if ( ! empty( $summary['expiry_date'] ) && $summary['expiry_date'] > $now ) {
				++$expiring;
			}
		}

		$counts->{Constants::AD_STATUS_EXPIRING} = $expiring;

		return $counts;
	}

	/**
	 * Change messages when a post type is updated.
	 *
	 * @since 1.4.7
	 *
	 * @param array<string, array<int, string>> $messages Existing post update messages.
	 *
	 * @return array<string, array<int, string>>
	 */
	public function post_updated_messages( $messages = [] ): array {
		global $post;

		// Added to fix error message array caused by third party code that uses post_updated_messages filter wrong.
		if ( ! is_array( $messages ) ) {
			$messages = [];
		}

		$revision = Params::get( 'revision', 0, FILTER_VALIDATE_INT );

		$messages[ Constants::POST_TYPE_AD ] = [
			0  => '', // Unused. Messages start at index 1.
			1  => __( 'Ad updated.', 'advanced-ads' ),
			4  => __( 'Ad updated.', 'advanced-ads' ),
			5  => $revision
				/* translators: %s: date and time of the revision */
				? sprintf( __( 'Ad restored to revision from %s', 'advanced-ads' ), wp_post_revision_title( $revision, false ) )
				: false,
			6  => __( 'Ad saved.', 'advanced-ads' ),
			7  => __( 'Ad saved.', 'advanced-ads' ),
			8  => __( 'Ad submitted.', 'advanced-ads' ),
			9  => sprintf(
				/* translators: %s: date */
				__( 'Ad scheduled for: <strong>%1$s</strong>.', 'advanced-ads' ),
				'<strong>' . date_i18n( __( 'M j, Y @ G:i', 'advanced-ads' ), strtotime( $post->post_date ) ) . '</strong>'
			),
			10 => __( 'Ad draft updated.', 'advanced-ads' ),
		];

		return $messages;
	}

	/**
	 * Edit ad bulk update messages
	 *
	 * @param array<string, array<string, string>> $messages Existing bulk update messages.
	 * @param array<string, int>                   $counts   Numbers of updated ads.
	 *
	 * @return array<string, array<string, string>>
	 */
	public function bulk_post_updated_messages( array $messages, array $counts ): array {
		$messages[ Constants::POST_TYPE_AD ] = [
			/* translators: %s: ad count */
			'updated'   => _n( '%s ad updated.', '%s ads updated.', $counts['updated'], 'advanced-ads' ),
			/* translators: %s: ad count */
			'locked'    => _n( '%s ad not updated, somebody is editing it.', '%s ads not updated, somebody is editing them.', $counts['locked'], 'advanced-ads' ),
			/* translators: %s: ad count */
			'deleted'   => _n( '%s ad permanently deleted.', '%s ads permanently deleted.', $counts['deleted'], 'advanced-ads' ),
			/* translators: %s: ad count */
			'trashed'   => _n( '%s ad moved to the Trash.', '%s ads moved to the Trash.', $counts['trashed'], 'advanced-ads' ),
			/* translators: %s: ad count */
			'untrashed' => _n( '%s ad restored from the Trash.', '%s ads restored from the Trash.', $counts['untrashed'], 'advanced-ads' ),
		];

		$messages[ Constants::POST_TYPE_PLACEMENT ] = [
			/* translators: %s: placement count */
			'updated'   => _n( '%s placement updated.', '%s placements updated.', $counts['updated'], 'advanced-ads' ),
			/* translators: %s: placement count */
			'locked'    => _n( '%s placement not updated, somebody is editing it.', '%s placements not updated, somebody is editing them.', $counts['locked'], 'advanced-ads' ),
			/* translators: %s: placement count */
			'deleted'   => _n( '%s placement permanently deleted.', '%s placements permanently deleted.', $counts['deleted'], 'advanced-ads' ),
			/* translators: %s: placement count */
			'trashed'   => _n( '%s placement moved to the Trash.', '%s placements moved to the Trash.', $counts['trashed'], 'advanced-ads' ),
			/* translators: %s: placement count */
			'untrashed' => _n( '%s placement restored from the Trash.', '%s placements restored from the Trash.', $counts['untrashed'], 'advanced-ads' ),
		];

		return $messages;
	}

	/**
	 * Replace the edit link with a link to the modal to edit the placement.
	 *
	 * @param string $link    The previous link.
	 * @param int    $post_id The \WP_Post::$ID for the current item.
	 *
	 * @return string
	 */
	public function get_edit_post_link( string $link, int $post_id ): string {
		if ( Constants::POST_TYPE_PLACEMENT === get_post_type( $post_id ) ) {
			return admin_url( 'edit.php?post_type=' . Constants::POST_TYPE_PLACEMENT . '#modal-placement-edit-' . $post_id );
		}

		return $link;
	}
}
