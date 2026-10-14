<?php
/**
 * Ad Blocker AJAX handlers.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

namespace AdvancedAds\Modules\AdBlocker;

use AdvancedAds\Framework\Interfaces\Integration_Interface;
use AdvancedAds\Framework\Utilities\Params;
use AdvancedAds\Utilities\Conditional;

defined( 'ABSPATH' ) || exit;

/**
 * Ajax class.
 */
class Ajax implements Integration_Interface {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'wp_ajax_advads-adblock-rebuild-assets', [ $this, 'rebuild_assets' ] );
	}

	/**
	 * Rebuild the disguised asset folder.
	 *
	 * @return void
	 */
	public function rebuild_assets(): void {
		check_ajax_referer( 'advanced-ads-admin-ajax-nonce', 'nonce' );

		if ( ! Conditional::user_can( 'advanced_ads_manage_options' ) ) {
			wp_send_json_error( __( 'You are not allowed to do this.', 'advanced-ads' ), 403 );
		}

		$force = boolval( Params::post( 'adblocker-rebuild-force', false, FILTER_VALIDATE_BOOLEAN ) );

		$result = ( new Builder() )->build( $force );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( __( 'The asset folder was rebuilt successfully.', 'advanced-ads' ) );
	}
}
