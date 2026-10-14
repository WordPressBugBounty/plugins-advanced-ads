<?php
/**
 * Uncategorised functionality.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.x.x
 */

namespace AdvancedAds\Admin;

use Advanced_Ads_Admin_Notices;
use AdvancedAds\Constants;
use AdvancedAds\Framework\Interfaces\Integration_Interface;
use AdvancedAds\Framework\Utilities\Params;
use AdvancedAds\Importers\XML_Importer;
use AdvancedAds\Utilities\Conditional;

defined( 'ABSPATH' ) || exit;

/**
 * Class Misc
 */
class Misc implements Integration_Interface {

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'current_screen', [ $this, 'maybe_hook_cheating_message_filter' ] );
		add_filter( 'get_user_option_user-settings', [ $this, 'reset_view_mode_option' ] );
		add_action( 'in_admin_header', [ $this, 'register_admin_notices' ] );
		add_action( 'admin_action_advanced_ads_starter_setup', [ $this, 'import_starter_setup' ] );
	}

	/**
	 * Attach the cheating-message gettext filter only on ad CPT screens.
	 *
	 * @param \WP_Screen|null $screen Current screen.
	 * @return void
	 */
	public function maybe_hook_cheating_message_filter( $screen ): void {
		if ( ! $screen instanceof \WP_Screen || Constants::POST_TYPE_AD !== $screen->post_type ) {
			return;
		}

		add_filter( 'gettext', [ $this, 'replace_cheating_message' ], 20, 2 );
	}

	/**
	 * Replace 'You need a higher level of permission.' message if user role does not have required permissions.
	 *
	 * @param string $translated_text Translated text.
	 * @param string $untranslated_text Text to translate.
	 *
	 * @return string $translation  Translated text.
	 */
	public function replace_cheating_message( $translated_text, $untranslated_text ): string {
		global $typenow;

		if (
			isset( $typenow )
			&& 'You need a higher level of permission.' === $untranslated_text
			&& Constants::POST_TYPE_AD === $typenow
		) {
			$translated_text = __( 'You don’t have access to ads. Please deactivate and re-enable Advanced Ads again to fix this.', 'advanced-ads' )
				. '&nbsp;<a href="https://wpadvancedads.com/manual/user-capabilities/?utm_source=advanced-ads&utm_medium=link&utm_campaign=wrong-user-role#You_dont_have_access_to_ads" target="_blank">' . __( 'Get help', 'advanced-ads' ) . '</a>';
		}

		return (string) $translated_text;
	}

	/**
	 * Set the removed post list mode to "List", if it was set to "Excerpt".
	 *
	 * @param string $user_options Query string containing user options.
	 *
	 * @return string
	 */
	public function reset_view_mode_option( $user_options ): string {
		return str_replace( '&posts_list_mode=excerpt', '&posts_list_mode=list', $user_options );
	}

	/**
	 * Registers Advanced Ads admin notices — prevents other notices from showing up on our own pages.
	 *
	 * @return void
	 */
	public function register_admin_notices(): void {
		/**
		 * Remove all registered admin_notices from AA screens — we need to use this or some users have half or more of their viewports cluttered with unrelated notices.
		 */
		if ( Conditional::is_screen_advanced_ads() ) {
			remove_all_actions( 'admin_notices' );
		}

		add_action( 'admin_notices', [ $this, 'admin_notices' ] );
	}

	/**
	 * Initiate the admin notices class
	 *
	 * @return void
	 */
	public function admin_notices(): void {
		// Display ad block warning to everyone who can edit ads.
		if (
			Conditional::user_can( 'advanced_ads_edit_ads' )
			&& Conditional::is_screen_advanced_ads()
		) {
			$ad_blocker_notice_id = wp_advads()->get_frontend_prefix() . 'abcheck-' . md5( microtime() );
			wp_register_script( $ad_blocker_notice_id . '-adblocker-notice', false, [], ADVADS_VERSION, true );
			wp_enqueue_script( $ad_blocker_notice_id . '-adblocker-notice' );
			wp_add_inline_script(
				$ad_blocker_notice_id . '-adblocker-notice',
				"jQuery( document ).ready( function () {
					let has_brave_shields = ( typeof navigator.brave !== 'undefined' && typeof advanced_ads_admin === 'undefined' ) ? true : false;

					if ( typeof advanced_ads_adblocker_test === 'undefined' && ( typeof navigator.brave === 'undefined' || has_brave_shields ) ) {
						jQuery( '#" . esc_attr( $ad_blocker_notice_id ) . ".message' ).show();
					}
				} );"
			);
			include_once ADVADS_ABSPATH . 'admin/views/notices/adblock.php';
		}

		// Show success notice after starter setup was imported. Registered here because it will be visible only once.
		if ( 'advanced-ads-starter-setup-success' === Params::get( 'message' ) ) {
			add_action( 'advanced-ads-admin-notices', [ $this, 'starter_setup_success_message' ] );
		}

		/*
		Register our own notices on Advanced Ads pages, except
		-> the overview page where they should appear in the notices section,
		-> revision page to prevent duplicate revision controls.
		*/
		$screen = get_current_screen();
		if (
			Conditional::user_can( 'advanced_ads_edit_ads' )
			&& ( ! isset( $screen->id ) || 'toplevel_page_advanced-ads' !== $screen->id )
			&& 'revision' !== $screen->id
		) {
			echo '<div class="wrap">';

			if ( class_exists( 'Advanced_Ads_Admin_Notices' ) ) {
				Advanced_Ads_Admin_Notices::get_instance()->display_notices();
			}

			// Allow other Advanced Ads plugins to show admin notices at this late stage.
			do_action( 'advanced-ads-admin-notices' );
			echo '</div>';
		}
	}

	/**
	 * Show success message after starter setup was created.
	 *
	 * @return void
	 */
	public function starter_setup_success_message(): void {
		$last_post      = get_posts( [ 'numberposts' => 1 ] );
		$last_post_link = isset( $last_post[0]->ID ) ? get_permalink( $last_post[0]->ID ) : false;

		include ADVADS_ABSPATH . 'admin/views/notices/starter-setup-success.php';
	}

	/**
	 * Import a starter setup for new users
	 *
	 * @return void
	 */
	public function import_starter_setup(): void {
		if (
			'advanced_ads_starter_setup' !== Params::get( 'action' )
			|| ! Conditional::user_can( 'advanced_ads_edit_ads' )
		) {
			return;
		}

		check_admin_referer( 'advanced-ads-starter-setup' );

		$xml = file_get_contents( ADVADS_ABSPATH . 'admin/assets/xml/starter-setup.xml' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		( new XML_Importer() )->import_content( $xml );

		// Redirect to the ads overview page.
		wp_safe_redirect( admin_url( 'edit.php?post_type=advanced_ads&message=advanced-ads-starter-setup-success' ) );
	}
}
