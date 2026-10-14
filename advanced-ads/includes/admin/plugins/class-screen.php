<?php
/**
 * Plugins screen — action links, assets, and add-on recommendations on the Add Plugins screen.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   2.0.27
 */

namespace AdvancedAds\Admin\Plugins;

use AdvancedAds\Enums\UTM;
use AdvancedAds\Framework\Interfaces\Integration_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Plugins screen.
 */
class Screen implements Integration_Interface {

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_filter( 'plugin_action_links_' . ADVADS_PLUGIN_BASENAME, [ $this, 'add_links' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_filter( 'plugins_api_result', [ $this, 'recommend_suitable_add_ons' ], 11, 3 );
	}

	/**
	 * Enqueue plugins-screen assets.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		if ( in_array( $screen->id, [ 'plugins', 'plugins-network' ], true ) ) {
			wp_advads()->registry->enqueue_style( 'screen-plugins' );
			wp_advads()->registry->enqueue_script( 'screen-plugins' );
		}
	}

	/**
	 * Add links to the plugins list
	 *
	 * @param array<string, string> $links Array of links for the plugins, adapted when the current plugin is found.
	 *
	 * @return array<string, string>
	 */
	public function add_links( $links ): array {
		// Early bail!!
		if ( ! is_array( $links ) ) {
			return $links;
		}

		// Add support page link.
		$support_link = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( admin_url( 'admin.php?page=advanced-ads-app&path=/support' ) ),
			__( 'Support', 'advanced-ads' )
		);

		// Add add-ons link.
		$extend_link = defined( 'AAP_VERSION' )
			? sprintf(
				'<a href="%1$s" target="_blank">%2$s</a>',
				esc_url( UTM::with_plugin_link( 'https://wpadvancedads.com/add-ons/', 'plugin-page-add-ons' ) ),
				__( 'Add-Ons', 'advanced-ads' )
			)
			: sprintf(
				'<a href="%1$s" target="_blank" class="aa-get-pro">%2$s</a>',
				esc_url(
					admin_url( 'admin.php?page=advanced-ads-app&path=/plans' )
				),
				__( 'See Pro Features', 'advanced-ads' )
			);

		array_unshift( $links, $support_link, $extend_link );

		return $links;
	}

	/**
	 * Recommend additional add-ons
	 *
	 * @param object|WP_Error $result Response object or WP_Error.
	 * @param string          $action The type of information being requested from the Plugin Installation API.
	 * @param object          $args Plugin API arguments.
	 *
	 * @return object|WP_Error Response object or WP_Error.
	 */
	public function recommend_suitable_add_ons( $result, $action, $args ) {
		if (
			empty( $args->browse )
			|| ! in_array( $args->browse, [ 'featured', 'recommended', 'popular' ], true )
			|| ( isset( $result->info['page'] ) && $result->info['page'] > 1 )
		) {
			return $result;
		}

		// Grab all slugs from the api results.
		$result_slugs = wp_list_pluck( $result->plugins, 'slug' );

		// Recommend AdSense In-Feed add-on.
		$result = $this->recommend_plugin(
			'advanced-ads-adsense-in-feed',
			'advanced-ads-adsense-in-feed/advanced-ads-in-feed.php',
			$args,
			$result,
			$result_slugs
		);

		// Recommend Genesis Ads add-on.
		if ( defined( 'PARENT_THEME_NAME' ) && 'Genesis' === PARENT_THEME_NAME ) {
			$result = $this->recommend_plugin(
				'advanced-ads-genesis',
				'advanced-ads-genesis/genesis-ads.php',
				$args,
				$result,
				$result_slugs
			);
		}

		// Recommend WP Bakery (former Visual Composer) add-on.
		if ( defined( 'WPB_VC_VERSION' ) ) {
			$result = $this->recommend_plugin(
				'ads-for-visual-composer',
				'ads-for-visual-composer/advanced-ads-vc.php',
				$args,
				$result,
				$result_slugs
			);
		}

		return $result;
	}

	/**
	 * Recommends a plugin based on the provided slug and file path — checks if the plugin is already active or recommended, and if not, fetches the plugin data using the WordPress Plugin API and adds it to the result.
	 *
	 * @param string             $slug         The slug of the plugin to recommend.
	 * @param string             $file_path    The file path of the plugin.
	 * @param object             $args         Additional arguments for the recommendation.
	 * @param object             $result       The current result object containing recommended plugins.
	 * @param array<int, string> $result_slugs An array of slugs of already recommended plugins.
	 *
	 * @return object The updated result object with the recommended plugin added.
	 */
	private function recommend_plugin( $slug, $file_path, $args, $result, $result_slugs ) {
		// Check if the plugin is already active or recommended.
		if (
			is_plugin_active( $file_path )
			|| is_plugin_active_for_network( $file_path )
			|| in_array( $slug, $result_slugs, true )
		) {
			return $result;
		}

		// Prepare query arguments to fetch plugin data.
		$query_args  = [
			'slug'   => $slug,
			'fields' => [
				'icons'             => true,
				'active_installs'   => true,
				'short_description' => true,
				'group'             => true,
			],
		];
		$plugin_data = plugins_api( 'plugin_information', $query_args );

		// Add plugin data to the result if fetched successfully.
		if ( ! is_wp_error( $plugin_data ) ) {
			if ( 'featured' === $args->browse ) {
				array_push( $result->plugins, $plugin_data );
			} else {
				array_unshift( $result->plugins, $plugin_data );
			}
		}

		return $result;
	}
}
