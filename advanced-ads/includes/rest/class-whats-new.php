<?php
/**
 * Whats New REST endpoints.
 *
 * Fetches all changelog releases from the Advanced Ads mothership,
 * annotates each with a read/unread flag, and exposes two endpoints
 * so the React app can split them into Unread / Read tabs.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.53.0
 */

namespace AdvancedAds\Rest;

use AdvancedAds\Constants;
use AdvancedAds\Framework\Interfaces\Routes_Interface;
use AdvancedAds\License\License_Shop_Client;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Rest Whats_New.
 */
class Whats_New implements Routes_Interface {

	/**
	 * Transient key for caching the external API response.
	 */
	const TRANSIENT_KEY = 'advanced_ads_whats_new_releases';

	/**
	 * Option key that stores per-site read state.
	 * Shape: [ post_id (int) => read (bool) ]
	 */
	const OPTION_KEY = 'advanced_ads_whats_new';

	/**
	 * Number of unread releases to surface at a time.
	 */
	const MAX_UNREAD = 20;

	/**
	 * How long to cache the external response (seconds).
	 */
	const CACHE_TTL = 24 * HOUR_IN_SECONDS;

	/**
	 * Registers routes with WordPress.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			Constants::REST_BASE,
			'/whats-new',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_releases' ],
				'permission_callback' => [ $this, 'can_manage' ],
			]
		);

		register_rest_route(
			Constants::REST_BASE,
			'/whats-new/mark-read',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'mark_read' ],
				'permission_callback' => [ $this, 'can_manage' ],
				'args'                => [
					'ids' => [
						'required' => true,
						'type'     => 'array',
						'items'    => [ 'type' => 'integer' ],
					],
				],
			]
		);
	}

	/**
	 * Return all releases annotated with a `read` boolean.
	 * The JS splits them into Unread / Read tabs.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_releases() {
		$releases   = $this->fetch_releases();
		$read_state = $this->get_read_state();

		$annotated = array_map(
			static function ( array $release ) use ( $read_state ): array {
				$release['read'] = isset( $read_state[ $release['id'] ] ) && true === $read_state[ $release['id'] ];
				return $release;
			},
			$releases
		);

		return rest_ensure_response( $annotated );
	}

	/**
	 * Mark an array of release IDs as read.
	 *
	 * @param WP_REST_Request $request Contains { ids: int[] }.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function mark_read( WP_REST_Request $request ) {
		$raw_ids = (array) $request->get_param( 'ids' );
		$ids     = array_map(
			/**
			 * Sanitize ID array input.
			 *
			 * @param mixed $id Input value.
			 * @return int
			 */
			static function ( $id ): int {
				return is_numeric( $id ) ? (int) $id : 0;
			},
			$raw_ids
		);
		$read_state = $this->get_read_state();

		foreach ( $ids as $id ) {
			if ( $id > 0 ) {
				$read_state[ $id ] = true;
			}
		}

		update_option( self::OPTION_KEY, $read_state, false );

		return rest_ensure_response( [ 'success' => true ] );
	}

	/**
	 * Permission callback — manage_options only.
	 *
	 * @return bool
	 */
	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Fetch releases from the external API, with transient caching.
	 *
	 * @return array<int, array{id: int, version: string, synced: string, content: string}>
	 */
	private function fetch_releases(): array {
		$cached = get_transient( self::TRANSIENT_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$response = wp_remote_get(
			$this->get_changelog_api_url(),
			[
				'timeout'    => 10,
				'sslverify'  => false,
				'user-agent' => 'AdvancedAds/' . ADVADS_VERSION . '; ' . get_bloginfo( 'url' ),
			]
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return [];
		}

		$releases = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $releases ) ) {
			return [];
		}

		/**
		 * Validated array of release items.
		 *
		 * @var array<int, array{id: int, version: string, synced: string, content: string}> $valid_releases
		 */
		$valid_releases = [];

		// Allowed markup rules to strip out any unexpected scripts, headings, or tracking tags.
		$allowed_html = [
			'div'    => [],
			'strong' => [],
			'em'     => [],
			'code'   => [],
			'span'   => [ 'class' => true ],
			'a'      => [
				'href'   => true,
				'target' => true,
				'rel'    => true,
			],
		];

		foreach ( array_slice( $releases, 0, self::MAX_UNREAD ) as $release ) {
			if ( $this->is_valid_release( $release ) ) {
				// Extract embedded taxonomy term name.
				$addon_name     = 'Advanced Ads';
				$embedded_terms = isset( $release['_embedded']['wp:term'] ) && is_array( $release['_embedded']['wp:term'] )
					? $release['_embedded']['wp:term']
					: [];

				$addon_name = $this->extract_addon_name( $embedded_terms, $addon_name );

				$valid_releases[] = [
					'id'      => (int) $release['id'],
					'version' => sanitize_text_field( $release['title']['rendered'] ),
					'synced'  => sanitize_text_field( $release['date_gmt'] ),
					'content' => wp_kses( $release['content']['rendered'], $allowed_html ),
					'addon'   => sanitize_text_field( $addon_name ),
				];
			}
		}

		set_transient( self::TRANSIENT_KEY, $valid_releases, self::CACHE_TTL );

		return $valid_releases;
	}

	/**
	 * Check if a raw release item contains all required valid fields.
	 *
	 * @param mixed $release Raw release item.
	 * @phpstan-assert-if-true array{id: int|string, title: array{rendered: string}, date_gmt: string, content: array{rendered: string}, _embedded?: array<string, mixed>} $release
	 * @return bool
	 */
	private function is_valid_release( $release ): bool {
		if (
			! is_array( $release )
			|| ! isset( $release['id'], $release['title'], $release['title']['rendered'], $release['date_gmt'], $release['content'], $release['content']['rendered'] )
		) {
			return false;
		}

		return true;
	}

	/**
	 * Load the read-state map from wp_options.
	 *
	 * @return array<int, bool>
	 */
	private function get_read_state(): array {
		$stored = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $stored ) ) {
			return [];
		}

		/**
		 * Sanitized read state map.
		 *
		 * @var array<int, bool> $read_state
		 */
		$read_state = [];
		foreach ( $stored as $key => $val ) {
			if ( is_numeric( $key ) ) {
				$read_state[ absint( $key ) ] = boolval( $val );
			}
		}

		return $read_state;
	}

	/**
	 * Extract the add-on name from embedded taxonomy terms.
	 *
	 * @param array<mixed> $embedded_terms Embedded taxonomy term groups.
	 * @param string       $fallback        Fallback name.
	 * @return string
	 */
	private function extract_addon_name( array $embedded_terms, string $fallback ): string {
		foreach ( $embedded_terms as $taxonomy_terms ) {
			if ( ! is_array( $taxonomy_terms ) ) {
				continue;
			}
			$first = $taxonomy_terms[0] ?? null;
			if ( is_array( $first ) && isset( $first['name'] ) && is_string( $first['name'] ) && '' !== $first['name'] ) {
				return $first['name'];
			}
		}

		return $fallback;
	}

	/**
	 * Get the changelog API URL dynamically.
	 *
	 * @return string
	 */
	private function get_changelog_api_url(): string {
		$base_url = License_Shop_Client::shop_base_url();

		return add_query_arg(
			[
				'_embed'  => 'wp:term',
				'_fields' => 'id,title.rendered,date_gmt,content.rendered,_links,_embedded',
			],
			trailingslashit( $base_url ) . 'wp-json/wp/v2/advads_changelog'
		);
	}
}
