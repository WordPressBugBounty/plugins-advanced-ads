<?php
/**
 * Support screen.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.47.0
 */

namespace AdvancedAds\Admin\Pages;

use AdvancedAds\Abstracts\Screen;

defined( 'ABSPATH' ) || exit;

/**
 * Support.
 */
class Support extends Screen {

	/**
	 * Screen unique id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'support';
	}

	/**
	 * Get the order number of the screen.
	 *
	 * @return int
	 */
	public function get_order(): int {
		return 50;
	}

	/**
	 * Register screen into WordPress admin area.
	 *
	 * @return void
	 */
	public function register_screen(): void {
		$hook = add_submenu_page(
			ADVADS_SLUG,
			__( 'Support', 'advanced-ads' ),
			__( 'Support', 'advanced-ads' ),
			'manage_options',
			ADVADS_SLUG . '-support',
			[ $this, 'display' ]
		);

		$this->set_hook( $hook );
	}

	/**
	 * Enqueue assets
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		wp_advads()->registry->enqueue_style( 'screen-support' );
		wp_advads()->registry->enqueue_script( 'screen-support' );
		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'wpstg.admin_notices' );
	}

	/**
	 * Display screen content.
	 *
	 * @return void
	 */
	public function display(): void {
		include_once ADVADS_ABSPATH . 'views/admin/support/page.php';
	}

	/**
	 * Get links from the endpoint.
	 *
	 * @param string $endpoint The endpoint to get the links from.
	 * @param string $cache_key The cache key to store the links.
	 *
	 * @return array The links.
	 */
	public function get_links( $endpoint, $cache_key ): array {
		$cache = get_transient( $cache_key );
		if ( $cache ) {
			return $cache;
		}

		$endpoint = 'https://wpadvancedads.com/wp-json/wp/v2' . $endpoint;
		$response = wp_remote_get( $endpoint );
		if ( is_wp_error( $response ) ) {
			return [];
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data ) ) {
			return [];
		}

		set_transient( $cache_key, $data, 24 * HOUR_IN_SECONDS );
		return $data;
	}

	/**
	 * Render a YouTube video.
	 *
	 * @param string $video_id The video ID.
	 *
	 * @return void
	 */
	public function render_youtube_video( $video_id ): void {
		echo '<iframe src="https://www.youtube.com/embed/' . $video_id . '?controls=0" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>'; // phpcs:ignore
	}
}
