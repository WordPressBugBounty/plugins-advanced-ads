<?php
/**
 * The placement repository.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.50.0
 */

namespace AdvancedAds\Placements;

use AdvancedAds\Abstracts\Placement;
use AdvancedAds\Constants;
use AdvancedAds\Cache_Invalidator;
use AdvancedAds\Utilities\Cache;
use AdvancedAds\Utilities\WordPress;
use Exception;
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Placements Repository.
 */
class Placement_Repository {

	/* CRUD Methods ------------------- */

	/**
	 * Create a new placement in the database.
	 *
	 * @param Placement $placement Placement object.
	 *
	 * @return Placement
	 */
	public function create( &$placement ): Placement {
		$id = wp_insert_post(
			apply_filters(
				'advanced-ads-new-placement-data',
				[
					'post_type'      => Constants::POST_TYPE_PLACEMENT,
					'post_name'      => $placement->get_slug(),
					'post_status'    => $placement->get_status() ? $placement->get_status() : 'publish',
					'post_author'    => get_current_user_id(),
					'post_title'     => $placement->get_title() ? $placement->get_title() : __( 'New Placement', 'advanced-ads' ),
					'post_content'   => $placement->get_content() ? $placement->get_content() : __( 'New placement content goes here', 'advanced-ads' ),
					'comment_status' => 'closed',
					'ping_status'    => 'closed',
				]
			),
			true
		);

		if ( $id && ! is_wp_error( $id ) ) {
			$placement->set_id( $id );
			$this->update_post_meta( $placement );
			$placement->apply_changes();

			Cache_Invalidator::invalidate_placements();
		}

		return $placement;
	}

	/**
	 * Read a placement from the database.
	 *
	 * @param Placement $placement Placement object.
	 *
	 * @throws Exception If invalid placement.
	 *
	 * @return void
	 */
	public function read( &$placement ): void {
		$placement->set_defaults();
		$post_object = get_post( $placement->get_id() );

		if ( ! $placement->get_id() || ! $post_object || Constants::POST_TYPE_PLACEMENT !== $post_object->post_type ) {
			throw new Exception( esc_html__( 'Invalid placement.', 'advanced-ads' ) );
		}

		$placement->set_props(
			[
				'title'   => $post_object->post_title,
				'slug'    => $post_object->post_name,
				'status'  => $post_object->post_status,
				'content' => $post_object->post_content,
			]
		);

		$this->read_placement_data( $placement );
		$placement->set_object_read( true );
	}

	/**
	 * Update an existing placement in the database.
	 *
	 * @param Placement $placement Placement object.
	 *
	 * @return void
	 */
	public function update( &$placement ): void {
		global $wpdb;

		$changes = $placement->get_changes();

		// Only update the post when the post data changes.
		if ( array_intersect( [ 'title', 'status', 'content' ], array_keys( $changes ) ) ) {
			$post_data = [
				'post_title'   => $placement->get_title( 'edit' ),
				'post_status'  => $placement->get_status( 'edit' ) ? $placement->get_status( 'edit' ) : 'publish',
				'post_type'    => Constants::POST_TYPE_PLACEMENT,
				'post_content' => wp_unslash(
					apply_filters(
						'content_save_pre',
						$placement->get_content( 'edit' )
					)
				),
			];

			/**
			 * When updating this object, to prevent infinite loops, use $wpdb
			 * to update data, since wp_update_post spawns more calls to the
			 * save_post action.
			 *
			 * This ensures hooks are fired by either WP itself (admin screen save),
			 * or an update purely from CRUD.
			 */
			if ( doing_action( 'save_post' ) ) {
				$GLOBALS['wpdb']->update( $GLOBALS['wpdb']->posts, $post_data, [ 'ID' => $placement->get_id() ] );
				clean_post_cache( $placement->get_id() );
			} else {
				wp_update_post( array_merge( [ 'ID' => $placement->get_id() ], $post_data ) );
			}
		} else { // Only update post modified time to record this save event.
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->posts,
				[
					'post_modified'     => current_time( 'mysql' ),
					'post_modified_gmt' => current_time( 'mysql', 1 ),
				],
				[
					'ID' => $placement->get_id(),
				]
			);
			clean_post_cache( $placement->get_id() );
		}

		$this->update_post_meta( $placement );

		$placement->apply_changes();

		Cache_Invalidator::invalidate_placements();
	}

	/**
	 * Delete an placement from the database.
	 *
	 * @param Placement $placement    Placement object or id.
	 * @param bool      $force_delete Whether to bypass Trash and force deletion. Default false.
	 *
	 * @return void
	 */
	public function delete( &$placement, $force_delete = false ): void {
		// Early bail!!
		if ( ! $placement || ! $placement->get_id() ) {
			return;
		}

		if ( $force_delete ) {
			wp_delete_post( $placement->get_id(), true );
			$placement->set_id( 0 );
		} else {
			wp_trash_post( $placement->get_id() );
			$placement->set_status( 'trash' );
		}

		Cache_Invalidator::invalidate_placements();
	}

	/* Finder Methods ------------------- */

	/**
	 * Find placement by item id.
	 *
	 * Example: Find all placements that are connected to a specific ad.
	 *          In this case $item_id = "ad_1234"
	 *
	 * Example: Find all placements that are connected to a specific group.
	 *          In this case $item_id = "group_1234"
	 *
	 * @param string $item_id Item id to search for.
	 *
	 * @return Placement[]
	 */
	public function find_by_item_id( $item_id ): array {
		if ( ! is_string( $item_id ) || '' === $item_id ) {
			return [];
		}

		$placement_ids = [];

		foreach ( $this->get_placement_summaries() as $id => $summary ) {
			if ( $item_id === $summary['item'] ) {
				$placement_ids[] = (int) $id;
			}
		}

		return $this->hydrate_placements( $placement_ids );
	}

	/**
	 * Retrieves placements by type.
	 *
	 * Filters cached placement summaries by type and hydrates matches.
	 *
	 * @param string|array $types          Placement types to query.
	 * @param string       $output         The required return type. One of OBJECT or ids,
	 *                                     which correspond to an Placement object or an array containing post ids respectively.
	 * @param bool         $published_only Whether to return only published placements.
	 *
	 * @return array An associative array of placement IDs as keys and their corresponding placement objects as values.
	 */
	public function find_by_types( $types, $output = OBJECT, $published_only = false ): array {
		$types = array_values( array_filter( array_map( 'sanitize_key', (array) $types ) ) );

		if ( empty( $types ) ) {
			return [];
		}

		$placement_ids = [];

		foreach ( $this->get_placement_summaries() as $id => $summary ) {
			if ( $published_only && 'publish' !== $summary['status'] ) {
				continue;
			}

			if ( in_array( $summary['type'], $types, true ) ) {
				$placement_ids[] = (int) $id;
			}
		}

		if ( 'ids' === $output ) {
			return $placement_ids;
		}

		return $this->hydrate_placements( $placement_ids );
	}

	/**
	 * Get array of all placements.
	 *
	 * Prefer get_placement_summaries() for admin lists that only need id, title, type,
	 * status, or author — avoids constructing a full Placement instance per row.
	 *
	 * @return Placement[]
	 */
	public function get_all_placements(): array {
		return $this->hydrate_placements( array_keys( $this->get_placement_summaries() ) );
	}

	/**
	 * Get array of all published placements.
	 *
	 * @return Placement[]
	 */
	public function get_all_published(): array {
		return $this->hydrate_placements( $this->get_published_ids() );
	}

	/**
	 * Get lightweight placement summaries for list UIs (cached cross-request).
	 *
	 * @return array<int, array{id: int, title: string, type: string, status: string, author_id: int, item: string}>
	 */
	public function get_placement_summaries(): array {
		$summaries = Cache::get( Cache::PREFIX_PLACEMENTS, Cache::KEY_SUMMARIES );

		if ( null === $summaries ) {
			$summaries = $this->query_placement_summaries();
			Cache::set( Cache::PREFIX_PLACEMENTS, Cache::KEY_SUMMARIES, $summaries );
		}

		return $summaries;
	}

	/**
	 * Get all placement as ID => title, derived from cached summaries.
	 *
	 * @return array<int, string>
	 */
	public function get_placements_dropdown(): array {
		$summaries = $this->get_placement_summaries();

		if ( empty( $summaries ) ) {
			return [];
		}

		return wp_list_pluck( $summaries, 'title', 'id' );
	}

	/**
	 * Get array of all published placement IDs, derived from cached summaries.
	 *
	 * @return int[]
	 */
	public function get_published_ids(): array {
		$published_ids = [];

		foreach ( $this->get_placement_summaries() as $id => $summary ) {
			if ( 'publish' === $summary['status'] ) {
				$published_ids[] = (int) $id;
			}
		}

		return $published_ids;
	}

	/**
	 * Query lightweight placement summaries without hydrating Placement objects.
	 *
	 * @return array<int, array{id: int, title: string, type: string, status: string, author_id: int, item: string}>
	 */
	private function query_placement_summaries(): array {
		$query = new WP_Query(
			WordPress::improve_wp_query(
				[
					'post_type'        => Constants::POST_TYPE_PLACEMENT,
					'posts_per_page'   => -1,
					'post_status'      => 'any',
					'orderby'          => 'title',
					'order'            => 'ASC',
					'suppress_filters' => defined( 'ICL_SITEPRESS_VERSION' ) ? true : false, // Suppress filters if WPML is present.
				]
			)
		);

		if ( ! $query->have_posts() ) {
			return [];
		}

		$post_ids = wp_list_pluck( $query->posts, 'ID' );
		_prime_post_caches( $post_ids, false, true );
		update_meta_cache( 'post', $post_ids );

		$summaries = [];
		foreach ( $query->posts as $post ) {
			$type = get_post_meta( $post->ID, 'type', true );
			$item = get_post_meta( $post->ID, 'item', true );

			$summaries[ (int) $post->ID ] = [
				'id'        => (int) $post->ID,
				'title'     => $post->post_title,
				'type'      => $type ? $type : 'default',
				'status'    => $post->post_status,
				'author_id' => (int) $post->post_author,
				'item'      => is_string( $item ) ? $item : '',
			];
		}

		return $summaries;
	}

	/**
	 * Hydrate placement objects for the given post IDs.
	 *
	 * @param int[] $post_ids Placement post IDs.
	 *
	 * @return Placement[]
	 */
	private function hydrate_placements( array $post_ids ): array {
		$post_ids = array_values( array_filter( array_map( 'absint', $post_ids ) ) );

		if ( ! empty( $post_ids ) ) {
			_prime_post_caches( $post_ids, false, true );
		}

		$placements = [];
		foreach ( $post_ids as $post_id ) {
			$placement_object = wp_advads_get_placement( $post_id );
			if ( false !== $placement_object ) {
				$placements[ $post_id ] = $placement_object;
			}
		}

		return $placements;
	}

	/* Additional Methods ------------------- */

	/**
	 * Read placement data. Can be overridden by child classes to load other props.
	 *
	 * @param Ad $placement Ad object.
	 *
	 * @return void
	 */
	private function read_placement_data( &$placement ): void {
		$item    = get_post_meta( $placement->get_id(), 'item', true );
		$options = get_post_meta( $placement->get_id(), 'options', true );
		$type    = get_post_meta( $placement->get_id(), 'type', true );

		if ( empty( $options ) || ! is_array( $options ) ) {
			$options = [];
		}

		$display_conditions = $options['display'] ?? [];
		$visitor_conditions = $options['visitors'] ?? [];
		unset( $options['display'], $options['visitors'] );

		$placement->set_item( $item );
		$placement->set_type( $type ?? 'default' );
		$placement->set_props( $options );
		$placement->set_display_conditions( $display_conditions );
		$placement->set_visitor_conditions( $visitor_conditions );
	}

	/**
	 * Update placement data. Can be overridden by child classes to load other props.
	 *
	 * @param Placement $placement Placement object.
	 *
	 * @return void
	 */
	private function update_post_meta( &$placement ): void {
		do_action( 'advanced-ads-placement-pre-save', $placement );

		$meta_keys = $placement->get_data_keys();
		$meta_keys = array_combine( $meta_keys, $meta_keys );

		$meta_values = [];
		foreach ( $meta_keys as $meta_key => $prop ) {
			$value = method_exists( $placement, "get_$prop" )
				? $placement->{"get_$prop"}( 'edit' )
				: $placement->get_prop( $prop, 'edit' );

			$value = is_string( $value ) ? wp_slash( $value ) : $value;

			switch ( $prop ) {
				case 'display':
				case 'visitors':
					$value = WordPress::sanitize_conditions( $value );
					break;
			}

			$meta_values[ $meta_key ] = $value;
		}

		$meta_values = array_diff_key(
			$meta_values,
			[
				'type' => true,
				'slug' => true,
				'item' => true,
			]
		);

		update_post_meta( $placement->get_id(), 'item', $placement->get_item() );
		update_post_meta( $placement->get_id(), 'type', $placement->get_type() );
		update_post_meta( $placement->get_id(), 'options', $meta_values );
	}
}
