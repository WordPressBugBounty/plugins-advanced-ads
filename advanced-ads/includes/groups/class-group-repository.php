<?php
/**
 * Group Repository.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.48.0
 */

namespace AdvancedAds\Groups;

use AdvancedAds\Abstracts\Group;
use AdvancedAds\Constants;
use AdvancedAds\Framework\Utilities\Formatting;
use AdvancedAds\Cache_Invalidator;
use AdvancedAds\Utilities\Cache;
use Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Group Repository.
 */
class Group_Repository {

	/**
	 * Group options metakey
	 *
	 * @var string
	 */
	const OPTION_METAKEY = 'advanced_ads_group_options';

	/**
	 * Group type metakey
	 *
	 * @var string
	 */
	const TYPE_METAKEY = '_advads_group_type';

	/* CRUD Methods ------------------- */

	/**
	 * Create a new group in the database.
	 *
	 * @param Group $group Group object.
	 *
	 * @return Group
	 */
	public function create( &$group ): Group {
		apply_filters( 'advanced-ads-group-pre-save', $group );

		$ids = wp_insert_term(
			$group->get_title(),
			Constants::TAXONOMY_GROUP,
			[
				'description' => $group->get_content(),
				'slug'        => $group->get_slug(),
			]
		);

		if ( $ids && ! is_wp_error( $ids ) ) {
			$group->set_id( $ids['term_id'] );
			$this->update_term_meta( $group );
			$group->apply_changes();

			Cache_Invalidator::invalidate_groups();
		}

		return $group;
	}

	/**
	 * Read an group from the database.
	 *
	 * @param Group $group Group object.
	 * @throws Exception If invalid group.
	 *
	 * @return void
	 */
	public function read( &$group ): void {
		$group->set_defaults();
		$term_object = get_term( $group->get_id(), Constants::TAXONOMY_GROUP );

		if ( null === $term_object || is_wp_error( $term_object ) ) {
			throw new Exception( esc_html__( 'Invalid group.', 'advanced-ads' ) );
		}

		$group->set_title( $term_object->name );
		$group->set_slug( $term_object->slug );
		$group->set_content( $term_object->description );

		$this->read_group_data( $group );
		$group->set_object_read( true );
	}

	/**
	 * Update an existing group in the database.
	 *
	 * @param Group $group Group object.
	 *
	 * @return void
	 */
	public function update( &$group ): void {
		apply_filters( 'advanced-ads-group-pre-save', $group );

		$changed = array_keys( $group->get_changes() );

		// Only update term when the term data changes.
		if ( in_array( 'name', $changed, true ) ) {
			wp_update_term( $group->get_id(), Constants::TAXONOMY_GROUP, [ 'name' => $group->get_name( 'edit' ) ] );
		}

		if ( in_array( 'title', $changed, true ) ) {
			wp_update_term( $group->get_id(), Constants::TAXONOMY_GROUP, [ 'name' => $group->get_title( 'edit' ) ] );
		}

		// Only update weights when there is a change.
		if ( in_array( 'ad_weights', $changed, true ) ) {
			( new Group_Ad_Relation() )->relate( $group );
		}

		$this->update_term_meta( $group );
		$group->apply_changes();

		Cache_Invalidator::invalidate_groups();
	}

	/**
	 * Delete an group from the database.
	 *
	 * @param Group $group Group object or Id.
	 *
	 * @return void
	 */
	public function delete( &$group ): void {
		// Early bail!!
		if ( ! $group || ! $group->get_id() ) {
			return;
		}

		wp_delete_term( $group->get_id(), Constants::TAXONOMY_GROUP );
		$this->update_old_storage( $group->get_id() );

		$group->set_id( 0 );
		$group->set_status( 'trash' );

		Cache_Invalidator::invalidate_groups();
	}

	/* Finder Methods ------------------- */

	/**
	 * Get all groups object.
	 *
	 * Prefer get_group_summaries() for admin lists that only need id, title, or type.
	 *
	 * @return Group[]
	 */
	public function get_all_groups(): array {
		return $this->hydrate_groups( array_keys( $this->get_group_summaries() ) );
	}

	/**
	 * Get lightweight group summaries for list UIs (cached cross-request).
	 *
	 * @return array<int, array{id: int, title: string, slug: string, type: string, ad_weights: array<int, int>, publish_date: string, modified_date: string}>
	 */
	public function get_group_summaries(): array {
		$summaries = Cache::get( Cache::PREFIX_GROUPS, Cache::KEY_SUMMARIES );

		if ( null === $summaries ) {
			$summaries = $this->query_group_summaries();
			Cache::set( Cache::PREFIX_GROUPS, Cache::KEY_SUMMARIES, $summaries );
		}

		return $summaries;
	}

	/**
	 * Get all group as dropdown (ID => title), derived from cached summaries.
	 *
	 * @return array<int, string>
	 */
	public function get_groups_dropdown(): array {
		$summaries = $this->get_group_summaries();

		if ( empty( $summaries ) ) {
			return [];
		}

		return wp_list_pluck( $summaries, 'title', 'id' );
	}

	/**
	 * Get ads belonging to a group in the requested shape.
	 *
	 * @param int    $group_id Group term ID.
	 * @param string $output   OBJECT for hydrated ads, 'ids' for ad IDs, 'summaries' for cached ad rows.
	 *
	 * @return Ad[]|int[]|array<int, array{id: int, title: string, type: string, status: string, author_id: int, expiry_date: int}>
	 */
	public function get_ads_by_group_id( int $group_id, $output = OBJECT ): array {
		$ad_ids = $this->get_ad_ids_by_group_id( $group_id );

		if ( empty( $ad_ids ) ) {
			return [];
		}

		if ( 'ids' === $output ) {
			return $ad_ids;
		}

		if ( 'summaries' === $output ) {
			return array_intersect_key( wp_advads_get_ad_summaries(), array_flip( $ad_ids ) );
		}

		$group = wp_advads_get_group( $group_id );

		if ( ! $group ) {
			return [];
		}

		return $group->get_ads();
	}

	/**
	 * Get ad IDs assigned to a group from cached summaries.
	 *
	 * @param int $group_id Group term ID.
	 *
	 * @return int[]
	 */
	private function get_ad_ids_by_group_id( int $group_id ): array {
		$summaries = $this->get_group_summaries();

		if ( ! isset( $summaries[ $group_id ] ) ) {
			return [];
		}

		return array_map( 'absint', array_keys( $summaries[ $group_id ]['ad_weights'] ?? [] ) );
	}

	/**
	 * Query lightweight group summaries without hydrating Group objects.
	 *
	 * @return array<int, array{id: int, title: string, slug: string, type: string, ad_weights: array<int, int>, publish_date: string, modified_date: string}>
	 */
	private function query_group_summaries(): array {
		$terms = get_terms(
			[
				'taxonomy'               => Constants::TAXONOMY_GROUP,
				'hide_empty'             => false,
				'number'                 => 0,
				'orderby'                => 'name',
				'update_term_meta_cache' => false,
				'suppress_filters'       => defined( 'ICL_SITEPRESS_VERSION' ) ? true : false, // Suppress filters if WPML is present.
			]
		);

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return [];
		}

		$term_ids = wp_list_pluck( $terms, 'term_id' );
		update_termmeta_cache( $term_ids );

		$summaries = [];
		foreach ( $terms as $term ) {
			$type        = get_term_meta( $term->term_id, self::TYPE_METAKEY, true );
			$meta_values = get_term_meta( $term->term_id, self::OPTION_METAKEY, true );

			if ( ! $type ) {
				$type = is_array( $meta_values ) ? ( $meta_values['type'] ?? 'refresh' ) : 'refresh';
			}

			if ( 'ordered' === $type || 'default' === $type ) {
				$type = 'refresh';
			}

			$summaries[ (int) $term->term_id ] = [
				'id'            => (int) $term->term_id,
				'title'         => $term->name,
				'slug'          => $term->slug,
				'type'          => $type,
				'ad_weights'    => $this->extract_ad_weights_from_meta( $meta_values, $type ),
				'publish_date'  => (string) get_term_meta( $term->term_id, 'publish_date', true ),
				'modified_date' => (string) get_term_meta( $term->term_id, 'modified_date', true ),
			];
		}

		return $summaries;
	}

	/**
	 * Extract ad weights from stored group term meta.
	 *
	 * @param mixed  $meta_values Group option meta values.
	 * @param string $type        Resolved group type.
	 *
	 * @return array<int, int>
	 */
	private function extract_ad_weights_from_meta( $meta_values, $type ): array {
		if ( ! is_array( $meta_values ) ) {
			return [];
		}

		if ( isset( $meta_values['options'][ $type ] ) && is_array( $meta_values['options'][ $type ] ) ) {
			$meta_values = array_merge( $meta_values['options'][ $type ], $meta_values );
		}

		$ad_weights = $meta_values['ad_weights'] ?? [];

		return is_array( $ad_weights ) ? $ad_weights : [];
	}

	/**
	 * Hydrate group objects for the given term IDs.
	 *
	 * @param int[] $term_ids Group term IDs.
	 *
	 * @return Group[]
	 */
	private function hydrate_groups( array $term_ids ): array {
		$term_ids = array_values( array_filter( array_map( 'absint', $term_ids ) ) );

		if ( ! empty( $term_ids ) ) {
			if ( function_exists( 'wp_prime_term_caches' ) ) {
				wp_prime_term_caches( $term_ids, Constants::TAXONOMY_GROUP );
			} else {
				foreach ( $term_ids as $term_id ) {
					get_term( $term_id, Constants::TAXONOMY_GROUP );
				}
			}

			update_termmeta_cache( $term_ids );
		}

		$groups = [];
		foreach ( $term_ids as $term_id ) {
			$group = wp_advads_get_group( $term_id );

			if ( $group ) {
				$groups[ $term_id ] = $group;
			}
		}

		return $groups;
	}

	/**
	 * Get groups associated with a given ad id.
	 *
	 * @param int $ad_id The ID of the ad.
	 *
	 * @return Group[] Groups array
	 */
	public function get_groups_by_ad_id( $ad_id ) {
		$ad_id = absint( $ad_id );

		if ( ! $ad_id ) {
			return [];
		}

		$group_ids = get_post_meta( $ad_id, Constants::AD_META_GROUP_IDS, true );

		if ( ! is_array( $group_ids ) || empty( $group_ids ) ) {
			return [];
		}

		return $this->hydrate_groups( $group_ids );
	}

	/**
	 * Hydrate group objects for the given term IDs.
	 *
	 * @param int[] $term_ids Group term IDs.
	 *
	 * @return array<int, Group>
	 */
	public function get_groups_by_ids( array $term_ids ): array {
		return $this->hydrate_groups( $term_ids );
	}

	/* Additional Methods ------------------- */

	/**
	 * Read group data. Can be overridden by child classes to load other props.
	 *
	 * @param Group $group Group object.
	 *
	 * @return void
	 */
	private function read_group_data( &$group ): void {
		$type          = get_term_meta( $group->get_id(), self::TYPE_METAKEY, true );
		$meta_values   = get_term_meta( $group->get_id(), self::OPTION_METAKEY, true );
		$publish_date  = get_term_meta( $group->get_id(), 'publish_date', true );
		$modified_date = get_term_meta( $group->get_id(), 'modified_date', true );

		if ( empty( $meta_values ) ) {
			$meta_values = $this->migrate_values( $group );
			$type        = $meta_values['type'] ?? $type;
		}

		if ( 'ordered' === $type || 'default' === $type ) {
			$type = 'refresh';
		}

		if ( isset( $meta_values['options'], $meta_values['options'][ $type ] ) ) {
			$meta_values = array_merge( $meta_values['options'][ $type ], $meta_values );
		}

		$meta_values['publish_date']  = $publish_date ?? '';
		$meta_values['modified_date'] = $modified_date ?? '';

		$group->set_props( $meta_values );

		foreach ( [ 'random', 'enabled' ] as $prop ) {
			if ( array_key_exists( $prop, $meta_values ) ) {
				$value = $meta_values[ $prop ];
				$value = Formatting::string_to_bool( $value );
				$group->set_prop( $prop, $value );
			}
		}
	}

	/**
	 * Update group data. Can be overridden by child classes to load other props.
	 *
	 * @param Group $group Group object.
	 *
	 * @return void
	 */
	private function update_term_meta( &$group ): void {
		$current_date = current_time( 'mysql', true );
		$meta_values  = [
			'type'       => $group->get_type(),
			'ad_count'   => $group->get_ad_count(),
			'options'    => $group->get_options(),
			'ad_weights' => $group->get_ad_weights(),
		];

		update_term_meta( $group->get_id(), self::TYPE_METAKEY, $group->get_type() );
		update_term_meta( $group->get_id(), self::OPTION_METAKEY, $meta_values );

		update_term_meta( $group->get_id(), 'modified_date', $current_date );
		if ( empty( $group->get_publish_date() ) ) {
			update_term_meta( $group->get_id(), 'publish_date', $current_date );
		}
	}

	/**
	 * Migrate values to new version
	 *
	 * @param Group $group Group object.
	 *
	 * @return array
	 */
	private function migrate_values( $group ): array {
		$values = [];

		$all_groups = get_option( 'advads-ad-groups', [] );
		$ad_weights = get_option( 'advads-ad-weights', [] );

		if ( isset( $all_groups[ $group->get_id() ] ) && is_array( $all_groups[ $group->get_id() ] ) ) {
			$values = $all_groups[ $group->get_id() ];
		}

		if ( isset( $ad_weights[ $group->get_id() ] ) && is_array( $ad_weights[ $group->get_id() ] ) ) {
			$values['ad_weights'] = $ad_weights[ $group->get_id() ];
		}

		return $values;
	}

	/**
	 * Update old storage.
	 *
	 * TODO: Remove it later
	 *
	 * @param int $id Group ID.
	 *
	 * @return void
	 */
	private function update_old_storage( $id ): void {
		$all_groups  = get_option( 'advads-ad-groups', [] );
		$all_weights = get_option( 'advads-ad-weights', [] );

		if ( $all_groups && isset( $all_groups[ $id ] ) ) {
			unset( $all_groups[ $id ] );
			update_option( 'advads-ad-groups', $all_groups );
		}

		if ( $all_weights && isset( $all_weights[ $id ] ) ) {
			unset( $all_weights[ $id ] );
			update_option( 'advads-ad-weights', $all_weights );
		}
	}
}
