<?php
/**
 * Cache invalidation for Advanced Ads entity list caches.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   2.0.14
 */

namespace AdvancedAds;

use AdvancedAds\Framework\Interfaces\Integration_Interface;
use AdvancedAds\Utilities\Cache;
use AdvancedAds\Utilities\Validation;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Cache_Invalidator.
 */
class Cache_Invalidator implements Integration_Interface {

	/**
	 * Group term meta keys that affect list summaries.
	 *
	 * @var string[]
	 */
	private const GROUP_META_KEYS = [
		'_advads_group_type',
		'advanced_ads_group_options',
		'modified_date',
		'publish_date',
	];

	/**
	 * Placement post meta keys that affect list summaries.
	 *
	 * @var string[]
	 */
	private const PLACEMENT_META_KEYS = [
		'item',
		'type',
	];

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'save_post', [ $this, 'invalidate_on_save_post' ], 99, 2 );
		add_action( 'clean_post_cache', [ $this, 'invalidate_on_clean_post_cache' ], 99, 2 );
		add_action( 'advanced-ads-import', [ self::class, 'invalidate_all' ], 99 );

		foreach ( [ 'deleted_post', 'trashed_post', 'untrashed_post' ] as $hook ) {
			add_action( $hook, [ $this, 'invalidate_on_post_change' ], 99 );
		}

		foreach ( [ 'created_term', 'edited_term', 'delete_term' ] as $hook ) {
			add_action( $hook, [ $this, 'invalidate_on_term_change' ], 99, 3 );
		}

		foreach ( [ 'added_term_meta', 'updated_term_meta', 'deleted_term_meta' ] as $hook ) {
			add_action( $hook, [ $this, 'invalidate_on_term_meta_change' ], 99, 4 );
		}

		foreach ( [ 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ] as $hook ) {
			add_action( $hook, [ $this, 'invalidate_on_post_meta_change' ], 99, 4 );
		}
	}

	/**
	 * Invalidate ad list caches and factory instances.
	 *
	 * @return void
	 */
	public static function invalidate_ads(): void {
		Cache::flush_group( Cache::PREFIX_ADS );
		wp_advads()->ads->factory->clear_instance_cache();
	}

	/**
	 * Invalidate group list caches and factory instances.
	 *
	 * @return void
	 */
	public static function invalidate_groups(): void {
		Cache::flush_group( Cache::PREFIX_GROUPS );
		wp_advads()->groups->factory->clear_instance_cache();
	}

	/**
	 * Invalidate placement list caches and factory instances.
	 *
	 * @return void
	 */
	public static function invalidate_placements(): void {
		Cache::flush_group( Cache::PREFIX_PLACEMENTS );
		wp_advads()->placements->factory->clear_instance_cache();
	}

	/**
	 * Invalidate all entity list caches and factory instances.
	 *
	 * @return void
	 */
	public static function invalidate_all(): void {
		Cache::flush_all();
		wp_advads()->ads->factory->clear_instance_cache();
		wp_advads()->groups->factory->clear_instance_cache();
		wp_advads()->placements->factory->clear_instance_cache();
	}

	/**
	 * Invalidate caches when an ad or placement post is saved.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 *
	 * @return void
	 */
	public function invalidate_on_save_post( $post_id, $post ): void {
		if ( Validation::check_save_post( $post_id, $post ) ) {
			$this->invalidate_post_type( $post->post_type );
		}
	}

	/**
	 * Invalidate caches when an ad or placement post is trashed, untrashed, or deleted.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	public function invalidate_on_post_change( $post_id ): void {
		$this->invalidate_post_type( (string) get_post_type( $post_id ) );
	}

	/**
	 * Invalidate ad caches when post cache is cleaned outside save_post.
	 *
	 * Covers text-ad $wpdb updates that call clean_post_cache() without firing save_post.
	 *
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    Post object when provided by core.
	 *
	 * @return void
	 */
	public function invalidate_on_clean_post_cache( $post_id, $post = null ): void {
		if ( doing_action( 'save_post' ) ) {
			return;
		}

		$post = $post instanceof WP_Post ? $post : get_post( $post_id );

		if ( Validation::check_save_post( $post_id, $post ) && Constants::POST_TYPE_AD === $post->post_type ) {
			self::invalidate_ads();
		}
	}

	/**
	 * Invalidate caches when a group term is created, edited, or deleted.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 *
	 * @return void
	 */
	public function invalidate_on_term_change( $term_id, $tt_id, $taxonomy ): void {
		unset( $term_id, $tt_id );

		if ( Constants::TAXONOMY_GROUP === $taxonomy ) {
			self::invalidate_groups();
		}
	}

	/**
	 * Invalidate caches when summary-affecting group term meta changes.
	 *
	 * @param int|int[] $meta_id    Meta ID(s).
	 * @param int       $object_id  Term ID.
	 * @param string    $meta_key   Meta key.
	 * @param mixed     $meta_value Meta value.
	 *
	 * @return void
	 */
	public function invalidate_on_term_meta_change( $meta_id, $object_id, $meta_key, $meta_value ): void {
		unset( $meta_id, $meta_value );

		if ( ! in_array( $meta_key, self::GROUP_META_KEYS, true ) ) {
			return;
		}

		$term = get_term( $object_id );
		if ( $term && ! is_wp_error( $term ) && Constants::TAXONOMY_GROUP === $term->taxonomy ) {
			self::invalidate_groups();
		}
	}

	/**
	 * Invalidate caches when summary-affecting post meta changes without save_post.
	 *
	 * @param int|int[] $meta_id    Meta ID(s).
	 * @param int       $object_id  Post ID.
	 * @param string    $meta_key   Meta key.
	 * @param mixed     $meta_value Meta value.
	 *
	 * @return void
	 */
	public function invalidate_on_post_meta_change( $meta_id, $object_id, $meta_key, $meta_value ): void {
		unset( $meta_id, $meta_value );

		$post_type = get_post_type( $object_id );

		if ( Constants::POST_TYPE_AD === $post_type && 'advanced_ads_ad_options' === $meta_key ) {
			self::invalidate_ads();
			return;
		}

		if ( Constants::POST_TYPE_PLACEMENT === $post_type && in_array( $meta_key, self::PLACEMENT_META_KEYS, true ) ) {
			self::invalidate_placements();
		}
	}

	/**
	 * Invalidate caches for a supported post type.
	 *
	 * @param string $post_type Post type slug.
	 *
	 * @return void
	 */
	private function invalidate_post_type( string $post_type ): void {
		if ( Constants::POST_TYPE_AD === $post_type ) {
			self::invalidate_ads();
		} elseif ( Constants::POST_TYPE_PLACEMENT === $post_type ) {
			self::invalidate_placements();
		}
	}
}
