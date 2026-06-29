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

defined( 'ABSPATH' ) || exit;

/**
 * Cache_Invalidator.
 */
class Cache_Invalidator implements Integration_Interface {

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'save_post', [ $this, 'invalidate_on_save_post' ], 99, 2 );
		add_action( 'deleted_post', [ $this, 'invalidate_on_post_change' ], 99 );
		add_action( 'trashed_post', [ $this, 'invalidate_on_post_change' ], 99 );
		add_action( 'untrashed_post', [ $this, 'invalidate_on_post_change' ], 99 );

		add_action( 'created_term', [ $this, 'invalidate_on_term_change' ], 99, 3 );
		add_action( 'edited_term', [ $this, 'invalidate_on_term_change' ], 99, 3 );
		add_action( 'delete_term', [ $this, 'invalidate_on_term_change' ], 99, 3 );

		add_action( 'advanced-ads-import', [ Cache_Invalidator::class, 'invalidate_all' ], 99 );
	}

	/**
	 * Invalidate ad list caches and factory instances.
	 *
	 * @return void
	 */
	public static function invalidate_ads(): void {
		Cache::flush_group( Cache::PREFIX_ADS );
		wp_advads_get_ad_factory()->clear_instance_cache();
	}

	/**
	 * Invalidate group list caches and factory instances.
	 *
	 * @return void
	 */
	public static function invalidate_groups(): void {
		Cache::flush_group( Cache::PREFIX_GROUPS );
		wp_advads_get_group_factory()->clear_instance_cache();
	}

	/**
	 * Invalidate placement list caches and factory instances.
	 *
	 * @return void
	 */
	public static function invalidate_placements(): void {
		Cache::flush_group( Cache::PREFIX_PLACEMENTS );
		wp_advads_get_placement_factory()->clear_instance_cache();
	}

	/**
	 * Invalidate all entity list caches and factory instances.
	 *
	 * @return void
	 */
	public static function invalidate_all(): void {
		Cache::flush_all();
		wp_advads_get_ad_factory()->clear_instance_cache();
		wp_advads_get_group_factory()->clear_instance_cache();
		wp_advads_get_placement_factory()->clear_instance_cache();
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
		if ( ! Validation::check_save_post( $post_id, $post ) ) {
			return;
		}

		$this->invalidate_post_type( $post->post_type );
	}

	/**
	 * Invalidate caches when an ad or placement post is trashed, untrashed, or deleted.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	public function invalidate_on_post_change( $post_id ): void {
		$post_type = get_post_type( $post_id );

		if ( ! $post_type ) {
			return;
		}

		$this->invalidate_post_type( $post_type );
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

		if ( Constants::TAXONOMY_GROUP !== $taxonomy ) {
			return;
		}

		self::invalidate_groups();
	}

	/**
	 * Invalidate caches for a supported post type.
	 *
	 * @param string $post_type Post type slug.
	 *
	 * @return void
	 */
	private function invalidate_post_type( $post_type ): void {
		if ( Constants::POST_TYPE_AD === $post_type ) {
			self::invalidate_ads();
			return;
		}

		if ( Constants::POST_TYPE_PLACEMENT === $post_type ) {
			self::invalidate_placements();
		}
	}
}
