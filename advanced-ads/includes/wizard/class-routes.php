<?php
/**
 * Rest Wizard (app setup wizard publish).
 *
 * @package AdvancedAds
 */

namespace AdvancedAds\Wizard;

use AdvancedAds\Abstracts\Ad;
use AdvancedAds\Abstracts\Placement;
use AdvancedAds\Constants;
use AdvancedAds\Framework\Interfaces\Routes_Interface;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;
use AdvancedAds\Crons\Data_Usage;

defined( 'ABSPATH' ) || exit;

/**
 * Wizard REST route.
 */
class Routes implements Routes_Interface {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			Constants::REST_BASE,
			'/wizard',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'save' ],
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
			]
		);

		register_rest_route(
			Constants::REST_BASE,
			'/wizard/subscribe',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'subscribe' ],
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
				'args'                => [
					'email' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					],
				],
			]
		);
	}

	/**
	 * Publish wizard: create ad + placement, save visibility settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public function save( WP_REST_Request $request ) {
		$result = $this->save_ad( $request );
		$this->save_visibility( $request );
		$this->save_data_usage( $request );

		update_option( Constants::OPTION_WIZARD_COMPLETED, true );

		$posts = get_posts(
			[
				'posts_per_page' => 1,
				'post_type'      => 'post',
				'post_status'    => 'publish',
			]
		);

		if ( is_wp_error( $result ) ) {
			return [
				'success' => false,
				'error'   => $result->get_error_message(),
			];
		}

		return [
			'success'           => true,
			'adEditLink'        => $result['adEditLink'],
			'placementEditLink' => $result['placementEditLink'],
			'postLink'          => ! empty( $posts ) ? get_permalink( $posts[0] ) : home_url( '/' ),
		];
	}

	/**
	 * Subscribe the entered email to the free-addons newsletter.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public function subscribe( WP_REST_Request $request ) {
		$email = (string) $request->get_param( 'email' );
		if ( ! is_email( $email ) ) {
			return new WP_Error(
				'invalid_email',
				__( 'Enter a valid email address.', 'advanced-ads' ),
				[ 'status' => 400 ]
			);
		}

		return [
			'success' => true,
			'message' => \Advanced_Ads_Admin_Notices::get_instance()->subscribe( 'nl_free_addons', $email ),
		];
	}

	/**
	 * Save ad.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	private function save_ad( WP_REST_Request $request ) {
		$source  = (string) $request->get_param( 'source' );
		$allowed = [ 'adsense', 'image', 'plain', 'content', 'gam', 'dummy' ];

		if ( ! in_array( $source, $allowed, true ) ) {
			return new WP_Error(
				'invalid_source',
				__( 'Invalid ad type.', 'advanced-ads' ),
				[ 'status' => 400 ]
			);
		}

		$ad = $this->create_ad( $request, $source );
		if ( is_wp_error( $ad ) ) {
			return $ad;
		}

		$placement = $this->create_placement( $request, $ad );
		return [
			'adEditLink'        => $ad->get_edit_link(),
			'placementEditLink' => $placement->get_edit_link(),
		];
	}

	/**
	 * Create an ad from the wizard payload.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string          $source  Ad source.
	 * @return Ad|WP_Error
	 */
	private function create_ad( WP_REST_Request $request, string $source ) {
		$type_map = [
			'adsense' => 'adsense',
			'image'   => 'image',
			'plain'   => 'plain',
			'content' => 'content',
			'gam'     => 'gam',
			'dummy'   => 'dummy',
		];

		if ( 'adsense' === $source ) {
			// Register the AdSense ad type if it's not already registered.
			if ( ! defined( 'GADSENSE_OPT_NAME' ) ) {
				define( 'GADSENSE_OPT_NAME', ADVADS_SLUG . '-adsense' );
			}

			if ( ! wp_advads_has_ad_type( 'adsense' ) ) {
				wp_advads_register_ad_type( \AdvancedAds\Modules\AdSense\Types\Adsense::class );
			}
		}

		$ad = wp_advads_create_new_ad( $type_map[ $source ] );
		if ( ! $ad ) {
			return new WP_Error( 'ad_create_failed', __( 'Could not create ad.', 'advanced-ads' ), [ 'status' => 500 ] );
		}

		$title = sanitize_text_field( (string) $request->get_param( 'adName' ) );
		$ad->set_title( $title ? $title : __( 'My First Ad', 'advanced-ads' ) );

		if ( 'adsense' === $source ) {
			$pub = sanitize_text_field( (string) $request->get_param( 'pub' ) );
			if ( $pub ) {
				$adsense               = wp_advads()->settings_registry->store->get_store( 'adsense' );
				$adsense               = is_array( $adsense ) ? $adsense : [];
				$adsense['adsense-id'] = $pub;
				update_option( GADSENSE_OPT_NAME, $adsense );
			}

			$format    = sanitize_text_field( (string) $request->get_param( 'adFormat' ) );
			$type      = sanitize_text_field( (string) $request->get_param( 'adType' ) );
			$unit_type = 'responsive';
			if ( in_array( $type, [ 'in-feed', 'in-article' ], true ) ) {
				$unit_type = $type;
			} elseif ( 'fixed' === $format ) {
				$unit_type = 'normal';
			}

			$unit = [
				'pubId'    => $pub,
				'slotId'   => sanitize_text_field( (string) $request->get_param( 'slot' ) ),
				'unitType' => $unit_type,
			];

			if ( 'normal' !== $unit_type ) {
				$unit['resize'] = 'auto';
			}

			$ad->set_content( wp_json_encode( $unit ) );

			if ( 'normal' === $unit_type ) {
				$ad->set_width( absint( $request->get_param( 'adW' ) ) );
				$ad->set_height( absint( $request->get_param( 'adH' ) ) );
			}
		}

		if ( 'image' === $source ) {
			$image_id = absint( $request->get_param( 'imageId' ) );
			if ( $image_id ) {
				$ad->set_image_id( $image_id );
			}

			$url = esc_url_raw( (string) $request->get_param( 'affUrl' ) );
			if ( $url ) {
				$ad->set_url( $url );
			}

			$width  = absint( $request->get_param( 'adW' ) );
			$height = absint( $request->get_param( 'adH' ) );
			if ( $width ) {
				$ad->set_width( $width );
			}
			if ( $height ) {
				$ad->set_height( $height );
			}
		}

		if ( in_array( $source, [ 'plain', 'content', 'gam' ], true ) ) {
			$code = (string) (
				$request->get_param( 'customCode' ) ?? $request->get_param( 'netCode' )
			);
			$ad->set_content( $code );
		}

		if ( 'gam' === $source ) {
			$path = sanitize_text_field( (string) $request->get_param( 'netName' ) );
			if ( $path ) {
				$ad->set_prop( 'unit_path', $path );
			}
			$ad->set_width( absint( $request->get_param( 'adW' ) ) );
			$ad->set_height( absint( $request->get_param( 'adH' ) ) );
		}

		$ad->save();
		return $ad;
	}

	/**
	 * Create a placement for the published ad.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param Ad              $ad      Ad.
	 * @return Placement
	 */
	private function create_placement( WP_REST_Request $request, Ad $ad ): Placement {
		$placement_id = sanitize_key( (string) $request->get_param( 'placement' ) );
		if ( ! $placement_id ) {
			$placement_id = 'content';
		}

		$map = [
			'header'  => 'header',
			'footer'  => 'footer',
			'sidebar' => 'sidebar_widget',
			'before'  => 'post_top',
			'after'   => 'post_bottom',
			'content' => 'post_content',
		];

		$type      = $map[ $placement_id ] ?? 'post_content';
		$placement = wp_advads_create_new_placement( $type );
		$placement->set_title( $ad->get_title() . ' Placement' );
		$placement->set_item( 'ad_' . $ad->get_id() );

		if ( 'post_content' === $type ) {
			$placement->set_prop( 'position', 'after' );
			$placement->set_prop( 'index', 3 );
			$placement->set_prop( 'tag', 'p' );
		}

		$placement->save();
		return $placement;
	}

	/**
	 * Save wizard Data
	 *
	 * @param WP_REST_Request $request Request.
	 * @return void
	 */
	private function save_visibility( WP_REST_Request $request ): void {
		$options = get_option( ADVADS_SLUG, [] );
		if ( ! is_array( $options ) ) {
			$options = [];
		}

		// Disable ads.
		$disable = $request->get_param( 'disableAds' );

		if ( is_array( $disable ) ) {
			$disabled_ads = (array) ( $options['disabled-ads'] ?? [] );

			foreach ( $disable as $key ) {
				$disabled_ads[ $key ] = true;
			}

			$options['disabled-ads'] = $disabled_ads;
		}

		// Hide ads from user roles.
		$roles = $request->get_param( 'hideFrom' );

		if ( is_array( $roles ) ) {
			$existing = $options['hide-for-user-role'] ?? [];

			$options['hide-for-user-role'] = array_unique(
				array_merge( $existing, $roles )
			);
		}

		// Hide ads from bots.
		$hide_bots = $request->get_param( 'hideBots' );

		if ( null !== $hide_bots ) {
			$options['block-bots'] = (bool) $hide_bots;
		}

		// Hide ads from IP addresses.
		$ip_module = $request->get_param( 'ipModule' );
		$ip_list   = $request->get_param( 'ipList' );

		if ( null !== $ip_module || null !== $ip_list ) {
			$existing = $options['hide-for-ip-address'] ?? [];

			$existing_ips = preg_split(
				'/[\r\n,]+/',
				$existing['ips'] ?? ''
			);

			if ( is_string( $ip_list ) ) {
				$ip_list = preg_split( '/[\r\n,]+/', $ip_list );
			}

			$ip_list = is_array( $ip_list ) ? $ip_list : [];

			$options['hide-for-ip-address'] = [
				'enabled' => (bool) ( $ip_module ?? $existing['enabled'] ?? false ),
				'ips'     => implode(
					"\n",
					array_unique( array_merge( $existing_ips, $ip_list ) )
				),
			];
		}

		update_option( ADVADS_SLUG, $options );
	}

	/**
	 * Save data usage
	 *
	 * @param WP_REST_Request $request Request.
	 * @return void
	 */
	private function save_data_usage( WP_REST_Request $request ): void {
		$freq = (string) $request->get_param( 'dataFreq' );
		if ( ! in_array( $freq, [ 'weekly', 'monthly' ], true ) ) {
			$freq = 'monthly';
		}

		update_option(
			Constants::OPTION_DATA_USAGE,
			[
				'enabled'     => 'yes' === $request->get_param( 'dataShare' ),
				'anonymize'   => rest_sanitize_boolean( $request->get_param( 'anonymize' ) ),
				'inclPlugins' => rest_sanitize_boolean( $request->get_param( 'inclPlugins' ) ),
				'inclEnv'     => rest_sanitize_boolean( $request->get_param( 'inclEnv' ) ),
				'dataFreq'    => $freq,
			]
		);

		Data_Usage::schedule_job();
	}
}
