<?php
/**
 * Update guard — what a site with add-ons should know before Advanced Ads itself is updated, and when WordPress should not update it on its own.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   x.x.x
 */

namespace AdvancedAds\Updater;

use AdvancedAds\Utilities\Addons;
use AdvancedAds\Framework\Interfaces\Integration_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Update guard.
 */
class Update_Guard implements Integration_Interface {

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'in_plugin_update_message-' . ADVADS_PLUGIN_BASENAME, [ $this, 'update_message' ], 10, 2 );
		add_filter( 'auto_update_plugin', [ $this, 'hold_major_auto_update' ], 10, 2 );
	}

	/**
	 * Add the upgrade notice from the WordPress.org readme and the add-on warning to the update row of Advanced Ads.
	 *
	 * @param array<string, mixed> $plugin_data Plugin headers.
	 * @param object               $response    Update data from WordPress.org.
	 *
	 * @return void
	 */
	public function update_message( $plugin_data, $response ): void {
		$notice = trim( wp_strip_all_tags( (string) ( $response->upgrade_notice ?? '' ) ) );

		if ( '' !== $notice ) {
			echo ' <strong>' . esc_html( $notice ) . '</strong>';
		}

		if ( $this->is_major_update_with_addons( (string) ( $response->new_version ?? '' ) ) ) {
			echo ' <strong>' . esc_html__( 'This is a major update. Update your Advanced Ads add-ons to their latest versions first, and back up your site.', 'advanced-ads' ) . '</strong>';
		}
	}

	/**
	 * Keep WordPress from installing a major version of Advanced Ads on its own while add-ons are installed.
	 *
	 * @param bool|null $update Whether to update, null when undecided.
	 * @param object    $item   The update offer.
	 *
	 * @return bool|null
	 */
	public function hold_major_auto_update( $update, $item ) {
		if ( ADVADS_PLUGIN_BASENAME === ( $item->plugin ?? '' ) && $this->is_major_update_with_addons( (string) ( $item->new_version ?? '' ) ) ) {
			return false;
		}

		return $update;
	}

	/**
	 * Whether a version is a major step up from the installed one on a site with add-ons — add-ons do not declare which versions of Advanced Ads they were tested with, so the first version number is the signal.
	 *
	 * @param string $new_version The offered version.
	 *
	 * @return bool
	 */
	private function is_major_update_with_addons( string $new_version ): bool {
		return (int) $new_version > (int) ADVADS_VERSION && [] !== Addons::get_installed_addons();
	}
}
