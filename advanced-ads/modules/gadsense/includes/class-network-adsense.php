<?php // phpcs:ignoreFile

use AdvancedAds\Abstracts\Ad;
use AdvancedAds\Modules\AdSense\Utils;

/**
 * Class Advanced_Ads_Network_Adsense
 */
class Advanced_Ads_Network_Adsense extends Advanced_Ads_Ad_Network {
	/**
	 * An array containing all the AdSense status codes that flag an {$link Advanced_Ads_Ad_Network_Ad_Unit} ad unit as active
	 * for downward compatibility with PHP < 5.6 the const had to be changed to static field. you can revert to const when PHP5 support is FINALLY dropped
	 *
	 * @var list<string>
	 */
	private static $status_codes_active = [ 'ACTIVE', 'NEW' ];

	/**
	 * A globally usable instance, that will be created when calling {$link Advanced_Ads_Ad_Network#get_instance) for the first time
	 *
	 * @var Advanced_Ads_Network_Adsense
	 */
	private static $instance;

	/**
	 * Instance of Advanced_Ads_Network_Adsense
	 *
	 * @return Advanced_Ads_Network_Adsense
	 */
	final public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new Advanced_Ads_Network_Adsense();
		}
		return self::$instance;
	}

	/**
	 * Advanced_Ads_Network_Adsense constructor.
	 */
	public function __construct() {
		parent::__construct( 'adsense', 'AdSense' );
	}

	/**
	 * Save publisher id from new ad unit if not given in main options
	 *
	 * @param Ad    $ad        Ad instance.
	 * @param array<string, mixed> $post_data Post data array.
	 *
	 * @return void
	 */
	public function sanitize_ad_settings( Ad $ad, $post_data ): void {
		if ( ! $ad->is_type( 'adsense' ) ) {
			return;
		}

		// Save AdSense publisher ID if there is no one stored yet.
		if ( ! empty( $post_data['adsense-pub-id'] ) ) {
			$adsense_options = get_option( 'advanced-ads-adsense', [] );

			if ( empty( $adsense_options['adsense-id'] ) ) {
				$adsense_options['adsense-id'] = $post_data['adsense-pub-id'];
				update_option( 'advanced-ads-adsense', $adsense_options );
			}
		}

		$ad->unset_prop( 'adsense-pub-id' );
	}

	/**
	 * Return ad units loaded through the API.
	 *
	 * @return array<int|string, \Advanced_Ads_Ad_Network_Ad_Unit>
	 */
	public function get_external_ad_units() {
		$adsense_id = Utils::get_adsense_id();

		$units        = [];
		$mapi_options = Advanced_Ads_AdSense_MAPI::get_option();

		if (
			isset( $mapi_options['ad_codes'] )
			 && isset( $mapi_options['accounts'] )
			 && isset( $mapi_options['accounts'][ $adsense_id ] )
			 && isset( $mapi_options['accounts'][ $adsense_id ]['ad_units'] )
		) {
			$ad_codes = $mapi_options['ad_codes'];
			foreach ( $mapi_options['accounts'][ $adsense_id ]['ad_units'] as $id => $raw ) {
				$ad_unit          = new Advanced_Ads_Ad_Network_Ad_Unit( $raw );
				$ad_unit->id      = $id;
				$ad_unit->slot_id = isset( $raw['code'] ) ? $raw['code'] : '-';
				$ad_unit->name    = isset( $raw['name'] ) ? $raw['name'] : '-';
				// phpcs:ignore
				$ad_unit->active  = isset( $raw['status'] ) && in_array( $raw['status'], self::$status_codes_active );

				if ( isset( $ad_codes[ $id ] ) ) {
					$ad_unit->code = $ad_codes[ $id ];
				}
				if ( isset( $raw['contentAdsSettings'] ) ) {
					if ( isset( $raw['contentAdsSettings']['type'] ) ) {
						$ad_unit->display_type = $raw['contentAdsSettings']['type'];
						$ad_unit->display_type = Advanced_Ads_AdSense_MAPI::format_ad_data( $ad_unit, 'type' );
					}
					if ( isset( $raw['contentAdsSettings']['size'] ) ) {
						$ad_unit->display_size = $raw['contentAdsSettings']['size'];
						$ad_unit->display_size = Advanced_Ads_AdSense_MAPI::format_ad_data( $ad_unit, 'size' );
					}
				}
				$units[] = $ad_unit;
			}
		}
		return $units;
	}

	/**
	 * Render the list of ads loaded through the API.
	 *
	 * @param bool $hide_idle_ads true to hide inactive ads.
	 * @param null $ad_unit_id ID of the ad unit.
	 *
	 * @return mixed|void
	 */
	public function print_external_ads_list( $hide_idle_ads = true, $ad_unit_id = null ) {
		Utils::get_mapi_ad_selector( $hide_idle_ads );
	}

	/**
	 * Whether the loaded AdSense ad is supported through the API.
	 * at the time we wrote this, native ad formats like In-article, In-feed and Matched Content are not supported.
	 *
	 * @param object $ad_unit ad unit object.
	 *
	 * @return bool
	 */
	public function is_supported( $ad_unit ) {
		$mapi_options = Advanced_Ads_AdSense_MAPI::get_option();
		$supported    = ! array_key_exists( $ad_unit->id, $mapi_options['unsupported_units'] );
		if ( ! $supported ) {
			$supported = array_key_exists( $ad_unit->id, $mapi_options['ad_codes'] );
		}

		return $supported;
	}

	/**
	 * Update the list of external ad units.
	 *
	 * @return void
	 */
	public function update_external_ad_units() {
		Advanced_Ads_AdSense_MAPI::get_instance()->ajax_get_adUnits();
	}

	/**
	 * If the AdSense account is connected.
	 *
	 * @return bool
	 */
	public function is_account_connected() {
		return Utils::is_setup();
	}

	/**
	 * Return path to module-specific JavaScript.
	 *
	 * @return string
	 */
	public function get_javascript_base_path() {
		return ADVADS_BASE_URL . 'modules/gadsense/assets/js/adsense.js';
	}

	/**
	 * JavaScript data to print in the source code.
	 *
	 * @inheritDoc
	 *
	 * @param array<string, mixed> $data data to be printed.
	 * @return array<string, mixed>
	 */
	public function append_javascript_data( &$data ) {
		$pub_id            = Utils::get_adsense_id();
		$data['pubId']     = $pub_id;
		$data['connected'] = $this->is_account_connected();
		$data['ad_types']  = [
			'matched_content' => _x( 'Multiplex', 'AdSense ad type', 'advanced-ads' ),
			'in_article'      => _x( 'In-article', 'AdSense ad type', 'advanced-ads' ),
			'in_feed'         => _x( 'In-feed', 'AdSense ad type', 'advanced-ads' ),
			'display'         => _x( 'Display', 'AdSense ad type', 'advanced-ads' ),
			'link'            => _x( 'Link', 'AdSense ad type', 'advanced-ads' ),
		];

		return $data;
	}

	/**
	 * If the ad also has a manual ad setup option.
	 *
	 * @return bool
	 */
	public function supports_manual_ad_setup() {
		return true;
	}

	/**
	 * Get the ad unit associated with a given ad ID.
	 *
	 * @param int $ad_id The ID of the ad.
	 * @return object|null The ad unit object associated with the given ad ID, or null if not found.
	 */
	function get_ad_unit( $ad_id ){
		$adense_ad = wp_advads_get_ad( $ad_id );
		// Early bail!!
		if ( ! $adense_ad || ! $adense_ad->is_type( 'adsense' ) || empty( $adense_ad->get_content() ) ) {
			return null;
		}

		$ad_units = $this->get_external_ad_units();
		if ( empty( $ad_units ) ) {
			return null;
		}

		$json_content = json_decode( $adense_ad->get_content() );
		$unit_code = $json_content->slotId ?? null;

		foreach( $ad_units as $ad_unit ) {
			if( $ad_unit->slot_id === $unit_code){
				return $ad_unit;
			}
		}

		return null;
	}
}
