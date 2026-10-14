<?php
/**
 * This class is responsible to model plain ads.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.48.0
 */

namespace AdvancedAds\Ads;

use AdvancedAds\Abstracts\Ad;
use AdvancedAds\Interfaces\Ad_Interface;
use AdvancedAds\Utilities\Conditional;
use AdvancedAds\Utilities\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Plain ad.
 */
class Ad_Plain extends Ad implements Ad_Interface {

	/**
	 * Prepare output for frontend.
	 *
	 * @return string
	 */
	public function prepare_frontend_output(): string {
		$content            = $this->get_content();
		$author_can_use_php = Conditional::can_author_unfiltered_html( (int) get_post_field( 'post_author', $this->get_id() ) );

		// Run the ad code as PHP only when the ad has "Allow PHP" set, PHP is not disallowed for the site and the author of the ad may use unfiltered HTML.
		if ( $this->is_php_allowed() && Conditional::is_php_allowed() && $author_can_use_php ) {
			ob_start();
			eval( '?>' . $content ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- the "Allow PHP" feature of plain ads, guarded by the three checks above.
			$content = ob_get_clean();
		}

		if ( ! is_string( $content ) ) {
			return '';
		}

		/**
		 * Apply do_blocks if the content has block code — works with WP 5.0.0 and later.
		 */
		if ( function_exists( 'has_blocks' ) && has_blocks( $content ) ) {
			$content = do_blocks( $content );
		}

		if ( $this->is_shortcode_allowed() ) {
			$content = $this->do_shortcode( $content );
		}

		// Add 'loading' attribute if applicable, available from WP 5.5.
		if (
			function_exists( 'wp_lazy_loading_enabled' )
			&& wp_lazy_loading_enabled( 'img', 'the_content' )
			&& preg_match_all( '/<img\s[^>]+>/', $content, $matches )
		) {
			foreach ( $matches[0] as $image ) {
				if ( strpos( $image, 'loading=' ) !== false ) {
					continue;
				}

				// Optimize image HTML tag with loading attributes based on WordPress filter context.
				$content = str_replace( $image, WordPress::img_tag_add_loading_attr( $image, 'the_content' ), $content );
			}
		}

		return (
			(
				( defined( 'DISALLOW_UNFILTERED_HTML' ) && DISALLOW_UNFILTERED_HTML ) ||
				! $author_can_use_php
			)
			&& version_compare( $this->get_prop( 'last_save_version' ) ?? '0', '1.35.0', 'ge' )
		)
			? wp_kses_post( $content )
			: $content;
	}
}
