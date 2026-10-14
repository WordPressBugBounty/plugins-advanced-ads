<?php
/**
 * Container class for custom filters on admin ad list page.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.x.x
 */

namespace AdvancedAds\Admin;

use AdvancedAds\Constants;
use AdvancedAds\Framework\Interfaces\Integration_Interface;
use AdvancedAds\Framework\Utilities\Params;
use WP_Post;
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Class List_Filters
 */
class List_Filters implements Integration_Interface {
	/**
	 * Ads data for the ad list table
	 *
	 * @var array<int, \WP_Post>
	 */
	protected $all_ads = [];

	/**
	 * Ads ad groups
	 *
	 * @var array<int|string, mixed>
	 */
	protected $all_groups = [];

	/**
	 * Ads in each group
	 *
	 * @var array<int, array<int, int>>
	 */
	protected $ads_in_groups = [];

	/**
	 * All filters available in the current ad list table
	 *
	 * @var array<string, mixed>
	 */
	protected $all_filters = [];

	/**
	 * All ad options for the ad list table
	 *
	 * @var array<int, array<string, mixed>>
	 */
	protected $all_ads_options = [];

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_filter( 'posts_results', [ $this, 'post_results' ], 10, 2 );
		add_filter( 'post_limits', [ $this, 'limit_filter' ], 10, 2 );
	}

	/**
	 * Collect available filters for ad overview page.
	 *
	 * @param array<int, \WP_Post> $posts Array of ads.
	 *
	 * @return void
	 */
	private function collect_filters( $posts ): void {
		$all_filters = [
			'all_sizes'   => [],
			'all_types'   => [],
			'all_dates'   => [],
			'all_groups'  => [],
			'all_authors' => $this->collect_authors(),
		];

		// Can not filter correctly with "trashed" posts. Do not display any filtering option in this case.
		if ( Params::request( 'post_status' ) === 'trash' ) {
			$this->all_filters = $all_filters;
			return;
		}

		foreach ( $this->all_groups as $group_id => $group ) {
			$all_filters['all_groups'][ $group_id ] = $group['name'];
		}

		foreach ( $posts as $post ) {
			$ad_option = $this->all_ads_options[ $post->ID ];

			if ( isset( $ad_option['width'], $ad_option['height'] ) && $ad_option['width'] && $ad_option['height'] ) {
				if ( ! array_key_exists( $ad_option['width'] . 'x' . $ad_option['height'], $all_filters['all_sizes'] ) ) {
					$all_filters['all_sizes'][ $ad_option['width'] . 'x' . $ad_option['height'] ] = $ad_option['width'] . ' x ' . $ad_option['height'];
				}
			}

			if ( isset( $ad_option['type'] ) && 'adsense' === $ad_option['type'] ) {
				$content     = $this->all_ads[ array_search( $post->ID, wp_list_pluck( $this->all_ads, 'ID' ), true ) ]->post_content;
				$adsense_obj = is_string( $content ) ? json_decode( $content, true ) : null;

				if (
					is_array( $adsense_obj )
					&& ! empty( $adsense_obj['unitType'] )
					&& 'responsive' === $adsense_obj['unitType']
					&& ! array_key_exists( 'responsive', $all_filters['all_sizes'] )
				) {
					$all_filters['all_sizes']['responsive'] = __( 'Responsive', 'advanced-ads' );
				}
			}

			if (
				isset( $ad_option['type'] ) // could be missing for new ads that are stored only by WP auto-save.
				&& ! array_key_exists( $ad_option['type'], $all_filters['all_types'] )
				&& wp_advads_has_ad_type( $ad_option['type'] )
			) {
				$all_filters['all_types'][ $ad_option['type'] ] = wp_advads_get_ad_type( $ad_option['type'] )->get_title();
			}

			$all_filters = apply_filters( 'advanced-ads-ad-list-column-filter', $all_filters, $post, $ad_option );
		}

		$this->all_filters = $all_filters;
	}

	/**
	 * Collects all ads data.
	 *
	 * @param WP_Post[] $posts array of ads.
	 *
	 * @return void
	 */
	public function collect_all_ads( $posts ) {
		$ad_summaries = wp_advads_get_ad_summaries();
		$post_ids     = wp_list_pluck( $posts, 'ID' );

		if ( ! empty( $post_ids ) ) {
			update_meta_cache( 'post', $post_ids );
		}

		foreach ( $posts as $post ) {
			$this->all_ads_options[ $post->ID ] = get_post_meta( $post->ID, 'advanced_ads_ad_options', true );

			// Handle invalid or legacy meta values.
			if ( ! is_array( $this->all_ads_options[ $post->ID ] ) ) {
				$this->all_ads_options[ $post->ID ] = [];
			}

			if ( isset( $ad_summaries[ $post->ID ] ) ) {
				$this->all_ads_options[ $post->ID ]['type']        = $ad_summaries[ $post->ID ]['type'] ?? '';
				$this->all_ads_options[ $post->ID ]['expiry_date'] = $ad_summaries[ $post->ID ]['expiry_date'] ?? '';
			}
		}

		$this->all_ads = $posts;
	}

	/**
	 * Collects all ads groups, fills the $all_groups class property.
	 *
	 * @return void
	 */
	private function collect_all_groups(): void {
		$groups = [];

		foreach ( wp_advads_get_group_summaries() as $group_id => $summary ) {
			$ad_ids = wp_advads_get_ads_by_group_id( $group_id, 'ids' );

			if ( empty( $ad_ids ) ) {
				continue;
			}

			$groups[ $group_id ] = [ 'name' => $summary['title'] ];

			foreach ( $ad_ids as $ad_id ) {
				$this->ads_in_groups[ $group_id ][] = $ad_id;
			}
		}

		$this->all_groups = $groups;
	}

	/**
	 * Retrieve the stored ads list.
	 *
	 * @return array<int|string, mixed>
	 */
	public function get_all_ads() {
		return $this->all_ads;
	}

	/**
	 * Retrieve all filters that can be applied.
	 *
	 * @return array<int|string, mixed>
	 */
	public function get_all_filters() {
		return $this->all_filters;
	}

	/**
	 * Remove limits because we need to get all ads.
	 *
	 * @param string    $limits    The LIMIT clause of the query.
	 * @param \WP_Query $the_query The current WP_Query object.
	 * @return string $limits The LIMIT clause of the query.
	 */
	public function limit_filter( $limits, $the_query ) {
		if ( ! $this->is_ad_list_main_query( $the_query ) ) {
			return $limits;
		}

		if ( ! $this->needs_unpaginated_results() ) {
			return $limits;
		}

		return '';
	}

	/**
	 * Edit the query for list table.
	 *
	 * @param array<int, \WP_Post> $posts The posts array from the query.
	 * @param WP_Query             $the_query the current WP_Query object.
	 *
	 * @return array<int, \WP_Post> with posts
	 */
	public function post_results( $posts, $the_query ): array {
		if ( ! $this->is_ad_list_main_query( $the_query ) ) {
			return $posts;
		}

		// Searching an ad ID.
		if ( 0 !== (int) $the_query->query_vars['s'] ) {
			$searched_id = (int) $the_query->query_vars['s'];
			$single_ad   = wp_advads_get_ad( $searched_id );
			$single_post = $single_ad ? get_post( $searched_id ) : null;

			if ( $single_post instanceof WP_Post && Constants::POST_TYPE_AD === $single_post->post_type ) {
				// Head to the ad edit page if one and only one ad found.
				$redirect = add_query_arg(
					[
						'post'   => $single_post->ID,
						'action' => 'edit',
					],
					admin_url( 'post.php' )
				);
				if ( empty( $posts ) && wp_safe_redirect( $redirect ) ) {
					exit;
				}

				if ( ! in_array( $single_post->ID, wp_list_pluck( $posts, 'ID' ), true ) ) {
					$posts[] = $single_post;
				}
			}
		}

		$this->collect_all_ads( $posts );
		$this->collect_all_groups();

		// DB pagination: skip PHP filter/slice when no Advanced Ads–specific filters are active.
		if ( ! $this->needs_unpaginated_results() ) {
			$this->collect_filters_from_catalog();
			$posts            = apply_filters( 'advanced-ads-ad-list-filter', $posts, $this->all_ads_options );
			$the_query->posts = $posts;

			return $posts;
		}

		$new_posts = Params::request( 'post_status' ) === 'trash'
			? $posts
			: $this->ad_filters( $this->all_ads, $the_query );

		$per_page = $the_query->query_vars['posts_per_page'] ? $the_query->query_vars['posts_per_page'] : 20;

		if ( $per_page < count( $new_posts ) ) {
			$paged                  = Params::request( 'paged', 1, FILTER_VALIDATE_INT );
			$total                  = count( $new_posts );
			$new_posts              = array_slice( $new_posts, ( $paged - 1 ) * $per_page, $per_page );
			$the_query->found_posts = $total;
			$the_query->post_count  = count( $new_posts );
		}

		// replace the post list.
		$the_query->posts = $new_posts;

		return $new_posts;
	}

	/**
	 * Whether the query is the main ads list table query.
	 *
	 * @param \WP_Query $the_query Query object.
	 * @return bool
	 */
	private function is_ad_list_main_query( $the_query ): bool {
		if ( ! $the_query->is_main_query() || ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();

		return $screen && 'edit-advanced_ads' === $screen->id;
	}

	/**
	 * Whether custom list filters require loading every ad into PHP — author / post_status / search are left to WP_Query, but add-ons that filter in `advanced-ads-ad-list-filter` and need the full set can force this via `advanced-ads-ad-list-needs-unpaginated-results`.
	 *
	 * @return bool
	 */
	private function needs_unpaginated_results(): bool {
		$request = wp_unslash( $_REQUEST ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		foreach ( [ 'adgroup', 'adtype', 'adsize', 'addate' ] as $key ) {
			if ( isset( $request[ $key ] ) && '' !== (string) $request[ $key ] ) {
				return true;
			}
		}

		foreach ( [ 'ad_debugmode', 'ad_displayonce', 'ad_privacyignore' ] as $key ) {
			if ( Params::request( $key, false ) ) {
				return true;
			}
		}

		if (
			isset( $request['taxonomy'], $request['term'] )
			&& Constants::TAXONOMY_GROUP === $request['taxonomy']
			&& '' !== (string) $request['term']
		) {
			return true;
		}

		/**
		 * Force the load-all / PHP-filter path for the ads list.
		 *
		 * @param bool  $needed  Whether unpaginated results are required.
		 * @param array $request Request parameters.
		 */
		return (bool) apply_filters( 'advanced-ads-ad-list-needs-unpaginated-results', false, $request );
	}

	/**
	 * Build filter dropdown data without iterating every list-table post.
	 *
	 * @return void
	 */
	private function collect_filters_from_catalog(): void {
		$all_filters = [
			'all_sizes'   => [],
			'all_types'   => [],
			'all_dates'   => [],
			'all_groups'  => [],
			'all_authors' => $this->collect_authors(),
		];

		if ( Params::request( 'post_status' ) === 'trash' ) {
			$this->all_filters = $all_filters;
			return;
		}

		foreach ( $this->all_groups as $group_id => $group ) {
			$all_filters['all_groups'][ $group_id ] = $group['name'];
		}

		$summaries = wp_advads_get_ad_summaries();
		$post_ids  = array_keys( $summaries );

		if ( ! empty( $post_ids ) ) {
			update_meta_cache( 'post', $post_ids );
		}

		foreach ( $summaries as $ad_id => $summary ) {
			$type = $summary['type'] ?? '';
			if (
				'' !== $type
				&& ! array_key_exists( $type, $all_filters['all_types'] )
				&& wp_advads_has_ad_type( $type )
			) {
				$all_filters['all_types'][ $type ] = wp_advads_get_ad_type( $type )->get_title();
			}

			$ad_option = get_post_meta( (int) $ad_id, 'advanced_ads_ad_options', true );
			if ( ! is_array( $ad_option ) ) {
				$ad_option = [];
			}

			if ( ! empty( $ad_option['width'] ) && ! empty( $ad_option['height'] ) ) {
				$size_key = $ad_option['width'] . 'x' . $ad_option['height'];
				if ( ! array_key_exists( $size_key, $all_filters['all_sizes'] ) ) {
					$all_filters['all_sizes'][ $size_key ] = $ad_option['width'] . ' x ' . $ad_option['height'];
				}
			}

			if ( 'adsense' === $type && ! array_key_exists( 'responsive', $all_filters['all_sizes'] ) ) {
				$content = get_post_field( 'post_content', (int) $ad_id );
				$decoded = is_string( $content ) ? json_decode( $content, true ) : null;
				if ( is_array( $decoded ) && ! empty( $decoded['unitType'] ) && 'responsive' === $decoded['unitType'] ) {
					$all_filters['all_sizes']['responsive'] = __( 'Responsive', 'advanced-ads' );
				}
			}
		}

		foreach ( $this->all_ads as $post ) {
			$ad_option   = $this->all_ads_options[ $post->ID ] ?? [];
			$all_filters = apply_filters( 'advanced-ads-ad-list-column-filter', $all_filters, $post, $ad_option );
		}

		$this->all_filters = $all_filters;
	}

	/**
	 * Apply ad filters on post array
	 *
	 * @param array<int, \WP_Post> $posts The original post array.
	 * @param WP_Query             $the_query the current WP_Query object.
	 *
	 * @return array<int, \WP_Post> with posts
	 */
	private function ad_filters( $posts, &$the_query ) {
		$using_original = true;
		$request        = wp_unslash( $_REQUEST ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		/**
		 *  Filter post status
		 */
		if ( isset( $request['post_status'] ) && '' !== $request['post_status'] && ! in_array( $request['post_status'], [ 'all', 'trash' ], true ) ) {
			$new_posts = [];
			foreach ( $this->all_ads as $post ) {
				if ( $request['post_status'] === $post->post_status ) {
					$new_posts[] = $post;
				}
			}
			$posts                  = $new_posts;
			$the_query->found_posts = count( $posts );
			$using_original         = false;
		}

		/**
		 *  Filter post author
		 */
		if ( isset( $request['author'] ) && '' !== $request['author'] ) {
			$author    = absint( $request['author'] );
			$new_posts = [];
			$the_list  = $using_original ? $this->all_ads : $posts;
			foreach ( $the_list as $post ) {
				if ( absint( $post->post_author ) === $author ) {
					$new_posts[] = $post;
				}
			}
			$posts                  = $new_posts;
			$the_query->found_posts = count( $posts );
			$using_original         = false;
		}

		/**
		 *  Filter groups
		 */
		if ( isset( $request['adgroup'] ) && '' !== $request['adgroup'] ) {
			$new_posts = [];
			$the_list  = $using_original ? $this->all_ads : $posts;
			foreach ( $the_list as $post ) {
				if ( isset( $this->ads_in_groups[ absint( $request['adgroup'] ) ] ) &&
					in_array( $post->ID, $this->ads_in_groups[ absint( $request['adgroup'] ) ], true ) ) {
					$new_posts[] = $post;
				}
			}
			$posts                  = $new_posts;
			$the_query->found_posts = count( $posts );
			$using_original         = false;
		}

		/**
		 * Filter by taxonomy
		 */
		if ( isset( $request['taxonomy'] ) && isset( $request['term'] ) && Constants::TAXONOMY_GROUP === $request['taxonomy'] ) {
			$term      = get_term_by( 'slug', $request['term'], Constants::TAXONOMY_GROUP );
			$group_id  = ( $term && ! is_wp_error( $term ) ) ? (int) $term->term_id : 0;
			$ad_ids    = ( $group_id && isset( $this->ads_in_groups[ $group_id ] ) ) ? $this->ads_in_groups[ $group_id ] : [];
			$new_posts = [];
			$the_list  = $using_original ? $this->all_ads : $posts;

			foreach ( $the_list as $post ) {
				if ( in_array( $post->ID, $ad_ids, true ) ) {
					$new_posts[] = $post;
				}
			}

			$posts                  = $new_posts;
			$the_query->found_posts = count( $posts );
			$using_original         = false;
		}

		/**
		 * Filter ad type
		 */
		if ( isset( $request['adtype'] ) && '' !== $request['adtype'] ) {
			$new_posts = [];
			$the_list  = $using_original ? $this->all_ads : $posts;
			foreach ( $the_list as $post ) {
				$option = $this->all_ads_options[ $post->ID ];
				if ( isset( $option['type'] ) && $request['adtype'] === $option['type'] ) {
					$new_posts[] = $post;
				}
			}
			$posts                  = $new_posts;
			$the_query->found_posts = count( $posts );
			$using_original         = false;
		}

		$bool_option_filters = [
			'ad_debugmode'     => static function ( $options ) {
				return $options['debugmode'] ?? '';
			},
			'ad_displayonce'   => static function ( $options ) {
				return $options['once_per_page'] ?? '';
			},
			'ad_privacyignore' => static function ( $options ) {
				return $options['privacy']['ignore-consent'] ?? '';
			},
		];

		foreach ( $bool_option_filters as $request_key => $get_option ) {
			$filtered = $this->filter_posts_by_bool_option( $posts, $using_original, $request_key, $get_option );
			if ( null === $filtered ) {
				continue;
			}

			$posts                  = $filtered;
			$the_query->found_posts = count( $posts );
			$using_original         = false;
		}

		/**
		 * Filter ad size
		 */
		if ( isset( $request['adsize'] ) && '' !== $request['adsize'] ) {
			$new_posts = [];
			$the_list  = $using_original ? $this->all_ads : $posts;
			foreach ( $the_list as $post ) {
				$option = $this->all_ads_options[ $post->ID ];
				if ( 'responsive' === $request['adsize'] ) {
					if ( isset( $option['type'] ) && 'adsense' === $option['type'] ) {
						$content = json_decode( $post->post_content, true );
						if ( is_array( $content ) && isset( $content['unitType'] ) && 'responsive' === $content['unitType'] ) {
							$new_posts[] = $post;
						}
					}
				} else {
					$width  = isset( $option['width'] ) ? $option['width'] : 0;
					$height = isset( $option['height'] ) ? $option['height'] : 0;
					if ( $request['adsize'] === $width . 'x' . $height ) {
						$new_posts[] = $post;
					}
				}
			}
			$posts                  = $new_posts;
			$the_query->found_posts = count( $posts );
			$using_original         = false;
		}

		if ( isset( $request['addate'] ) ) {
			$filter_value = urldecode( $request['addate'] );
			if ( in_array( $filter_value, [ 'advads-filter-expired', 'advads-filter-expiring' ], true ) ) {
				$posts = $this->filter_expired_ads( $filter_value, $using_original ? $this->all_ads : $posts );
			}
		}

		$posts                  = apply_filters( 'advanced-ads-ad-list-filter', $posts, $this->all_ads_options );
		$the_query->found_posts = count( $posts );

		$this->collect_filters( $posts );

		return $posts;
	}

	/**
	 * Filter posts by a yes/no request flag against an ad option value.
	 *
	 * @param array    $posts          Current post list.
	 * @param bool     $using_original Whether to filter from the full ads list.
	 * @param string   $request_key    Request parameter name.
	 * @param callable $get_option     Receives ad options array; returns the compared value.
	 *
	 * @return array|null Filtered posts, or null when the request filter is inactive.
	 */
	private function filter_posts_by_bool_option( array $posts, bool $using_original, string $request_key, callable $get_option ): ?array {
		$flag = Params::request( $request_key, false );
		if ( ! $flag ) {
			return null;
		}

		return array_filter(
			$using_original ? $this->all_ads : $posts,
			function ( $post ) use ( $flag, $get_option ) {
				$option = $get_option( $this->all_ads_options[ $post->ID ] ?? [] );

				return ( 'yes' === $flag && ! empty( $option ) ) || ( 'no' === $flag && empty( $option ) );
			}
		);
	}

	/**
	 * Filter by expiring or expired ads.
	 *
	 * @param string    $filter The current filter name, expired or expiring.
	 * @param WP_Post[] $posts  The array of posts.
	 *
	 * @return WP_Post[]
	 */
	private function filter_expired_ads( $filter, $posts ) {
		$now = time();

		return array_filter(
			$posts,
			function ( WP_Post $post ) use ( $now, $filter ) {
				$option = $this->all_ads_options[ $post->ID ];
				if ( empty( $option['expiry_date'] ) ) {
					return false;
				}

				$is_expired = 'advads-filter-expired' === $filter && $option['expiry_date'] <= $now;
				$in_future  = 'advads-filter-expiring' === $filter && $option['expiry_date'] > $now;

				return $is_expired || $in_future;
			}
		);
	}

	/**
	 * Author filter dropdown data.
	 *
	 * @return array<int, string> An associative array of authors, keys are the author IDs and values are the author display names.
	 */
	private function collect_authors(): array {
		$authors = [];

		foreach ( wp_advads_get_ad_summaries() as $summary ) {
			$author_id = $summary['author_id'];
			if ( ! isset( $authors[ $author_id ] ) ) {
				$authors[ $author_id ] = get_the_author_meta( 'display_name', $author_id );
			}
		}

		return $authors;
	}
}
