<?php
/**
 * The class provides plugin uninstallation routines.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.47.0
 */

namespace AdvancedAds\Installation;

use Advanced_Ads;
use AdvancedAds\Admin\Metabox_Ad_Settings;
use AdvancedAds\Constants;
use AdvancedAds\Entities;
use AdvancedAds\Framework\Interfaces\Initializer_Interface;
use AdvancedAds\Widget;

defined( 'ABSPATH' ) || exit;

/**
 * Installation Uninstall.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
 */
class Uninstall implements Initializer_Interface {

	/**
	 * Runs this initializer.
	 *
	 * @return void
	 */
	public function initialize(): void {
		if ( class_exists( '\\ActionScheduler_QueueRunner', false ) ) {
			\ActionScheduler_QueueRunner::instance()->unhook_dispatch_async_request();
		}

		$advads_options = Advanced_Ads::get_instance()->options();

		// Early bail!!
		if ( empty( $advads_options['uninstall-delete-data'] ) ) {
			return;
		}

		// Delete assets (main blog).
		( new Entities() )->init();

		if ( ! is_multisite() ) {
			$this->uninstall();
			return;
		}

		$site_ids = Install::get_sites();

		if ( empty( $site_ids ) ) {
			return;
		}

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );
			$this->uninstall();
			restore_current_blog();
		}
	}

	/**
	 * Fired for each blog when the plugin is uninstalled.
	 *
	 * @return void
	 */
	private function uninstall(): void { // phpcs:ignore Universal.CodeAnalysis.ConstructorDestructorReturn.ReturnTypeFound
		// Before delete_options(): a module's uninstall() reads its own
		// persisted options (e.g. Ad Blocker's folder_name) to know what to
		// clean up on disk — delete_options()'s option-prefix wipe would
		// already be gone by the time it runs otherwise.
		$this->delete_modules();
		$this->delete_post_types();
		$this->delete_groups();
		$this->delete_options();
		$this->delete_usermeta();

		wp_cache_flush();
	}

	/**
	 * Let every registered module (active or not) clean up anything beyond what delete_options()'s option-prefix wipe covers, e.g. files on disk.
	 *
	 * @return void
	 */
	private function delete_modules(): void {
		$module_registry = wp_advads()->module_registry;

		// register_uninstall_hook()'s callback runs from a re-include of the
		// main plugin file after `plugins_loaded` has already fired for this
		// request, so Modules\Registry::initialize()'s own
		// add_action( 'plugins_loaded', ... ) never gets a chance to run —
		// call hooks() directly to make sure modules are actually registered.
		$module_registry->hooks();

		foreach ( $module_registry->all() as $module ) {
			$entry = $module->get_entry();

			if ( null === $entry || ! class_exists( $entry ) ) {
				continue;
			}

			$instance = new $entry();
			if ( method_exists( $instance, 'uninstall' ) ) {
				$instance->uninstall();
			}
		}
	}

	/**
	 * Delete ads posts and postmeta.
	 *
	 * @return void
	 */
	private function delete_post_types(): void {
		$post_ids = get_posts(
			[
				'post_type'      => [ Constants::POST_TYPE_AD, Constants::POST_TYPE_PLACEMENT ],
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			]
		);

		if ( ! empty( $post_ids ) ) {
			foreach ( $post_ids as $ad_id ) {
				wp_delete_post( $ad_id, true );
			}
		}

		// Delete from postmeta.
		delete_metadata( 'post', null, Metabox_Ad_Settings::SETTING_METAKEY, '', true );
	}

	/**
	 * Delete groups.
	 *
	 * @return void
	 */
	private function delete_groups(): void {
		global $wpdb;

		$term_ids = $wpdb->get_col(
			$wpdb->prepare( "SELECT t.term_id FROM {$wpdb->terms} AS t INNER JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id WHERE tt.taxonomy = %s", Constants::TAXONOMY_GROUP )
		);

		foreach ( $term_ids as $term_id ) {
			wp_delete_term( $term_id, Constants::TAXONOMY_GROUP );
		}
	}

	/**
	 * Delete options.
	 *
	 * @return void
	 */
	private function delete_options(): void {
		global $wpdb;

		$prefixes = [
			'advads_',
			'advads-',
			'advanced_ads_',
			'advanced-ads-',
		];

		foreach ( $prefixes as $prefix ) {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
					$wpdb->esc_like( $prefix ) . '%'
				)
			);
		}

		delete_option( 'advanced-ads' );
		delete_option( 'widget_' . Widget::get_base_id() );
		delete_option( 'Advanced Ads Pro-internal' );

		// Transients.
		delete_transient( 'advanced-ads_add-on-updates-checked' );
		delete_transient( 'advanced-ads-daily-ad-health-check-ran' );
		delete_transient( 'advads-versions-list' );
		delete_transient( 'advads_feed_posts_v2' );
		delete_transient( 'advanced-ads-gam-all-units' );
	}

	/**
	 * Delete usermeta.
	 *
	 * @return void
	 */
	private function delete_usermeta(): void {
		delete_metadata( 'user', null, Constants::USER_WIZARD_DISMISS, '', true );
		delete_metadata( 'user', null, 'advanced-ads-hide-wizard', '', true );
		delete_metadata( 'user', null, 'advanced-ads-subscribed', '', true );
		delete_metadata( 'user', null, 'advanced-ads-ad-list-screen-options', '', true );
		delete_metadata( 'user', null, 'advanced-ads-screen-options', '', true );
		delete_metadata( 'user', null, 'advanced-ads-welcome', '', true );
		delete_metadata( 'user', null, 'advanced-ads-admin-settings', '', true );
		delete_metadata( 'user', null, 'advanced-ads-role', '', true );
		delete_metadata( 'user', null, 'edit_advanced_ads_per_page', '', true );
		delete_metadata( 'user', null, 'meta-box-order_advanced_ads', '', true );
		delete_metadata( 'user', null, 'screen_layout_advanced_ads', '', true );
		delete_metadata( 'user', null, 'closedpostboxes_advanced_ads', '', true );
		delete_metadata( 'user', null, 'metaboxhidden_advanced_ads', '', true );
		delete_metadata( 'user', null, 'advads-ad-screen-options', '', true );
	}
}
