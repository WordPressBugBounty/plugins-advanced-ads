<?php
/**
 * Ads.txt module frontend.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

namespace AdvancedAds\Modules\AdsTxt;

defined( 'ABSPATH' ) || exit;

/**
 * Frontend class.
 */
class Frontend {
	/**
	 * Top comment.
	 *
	 * @var string
	 */
	public const TOP = '# Advanced Ads ads.txt';

	/**
	 * The Constructor.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'display' ] );
	}

	/**
	 * Display the 'ads.txt' file on the frontend.
	 *
	 * @return void
	 */
	public function display() {
		// Early bail!!
		if ( ! Utils::is_multisite_subdomain() || ! wp_advads_is_module_active( 'ads-txt' ) ) {
			return;
		}

		$request = sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );

		if ( '/ads.txt' === $request || '/ads.txt?' === substr( $request, 0, 9 ) ) {
			$content = $this->get_frontend_output();
			if ( $content ) {
				// Set custom header for ads-txt.
				header( 'X-Ads-Txt-Generator: https://wordpress.org/plugins/advanced-ads/' );
				header( 'Content-Type: text/plain; charset=utf-8' );

				// Add top comment.
				$content = self::TOP . "\n" . $content;

				/**
				 * Filter the ads.txt content.
				 *
				 * @param string $content The ads.txt content.
				 */
				echo esc_html( $content );
				die();
			}
		}
	}

	/**
	 * Get output for frontend.
	 *
	 * @return string
	 */
	public function get_frontend_output() {
		$content     = wp_advads_get_setting( 'ads-txt.content' );
		$add_adsense = wp_advads_get_setting( 'ads-txt.adsense-toggle' );

		if ( $add_adsense ) {
			$adsense_data = Utils::get_adsense_data();
			if ( $adsense_data ) {
				$content .= "\n" . $adsense_data . "\n";
			}
		}

		$content = apply_filters( 'advanced-ads-ads-txt-content', $content, get_current_blog_id() );

		return $content;
	}
}
