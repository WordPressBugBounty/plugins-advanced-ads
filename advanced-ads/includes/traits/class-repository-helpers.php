<?php
/**
 * Shared repository helper methods.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   2.0.24
 */

namespace AdvancedAds\Traits;

defined( 'ABSPATH' ) || exit;

/**
 * Repository helpers for repeated internal repository logic.
 */
trait Repository_Helpers {

	/**
	 * Normalize a list of entity IDs to positive integers.
	 *
	 * @param int[] $ids Entity IDs.
	 *
	 * @return int[]
	 */
	protected function normalize_entity_ids( array $ids ): array {
		return array_values( array_filter( array_map( 'absint', $ids ) ) );
	}

	/**
	 * Convert cached summaries into an ID => title dropdown map.
	 *
	 * @param array<int, array{id: int, title: string}> $summaries Entity summaries.
	 *
	 * @return array<int, string>
	 */
	protected function summaries_to_dropdown( array $summaries ): array {
		if ( empty( $summaries ) ) {
			return [];
		}

		return wp_list_pluck( $summaries, 'title', 'id' );
	}

	/**
	 * Hydrate post-backed entities for the given post IDs.
	 *
	 * @param int[]    $post_ids     Post IDs.
	 * @param callable $loader       Loader callback receiving the post ID.
	 * @param bool     $skip_invalid Whether to skip falsey loader results.
	 *
	 * @return array<int, mixed>
	 */
	protected function hydrate_post_entities( array $post_ids, callable $loader, bool $skip_invalid = false ): array {
		$post_ids = $this->normalize_entity_ids( $post_ids );

		if ( ! empty( $post_ids ) ) {
			_prime_post_caches( $post_ids, false, true );
		}

		$entities = [];
		foreach ( $post_ids as $post_id ) {
			$entity = $loader( $post_id );

			if ( $skip_invalid && ( false === $entity || null === $entity ) ) {
				continue;
			}

			$entities[ $post_id ] = $entity;
		}

		return $entities;
	}

	/**
	 * Update only the modified timestamps for a post-backed entity.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	protected function touch_post_modified_time( int $post_id ): void {
		global $wpdb;

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->posts,
			[
				'post_modified'     => current_time( 'mysql' ),
				'post_modified_gmt' => current_time( 'mysql', 1 ),
			],
			[
				'ID' => $post_id,
			]
		);
		clean_post_cache( $post_id );
	}
}
