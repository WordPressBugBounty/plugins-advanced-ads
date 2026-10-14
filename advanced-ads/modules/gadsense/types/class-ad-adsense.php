<?php
/**
 * AdSense Ad Type
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

namespace AdvancedAds\Modules\AdSense\Types;

use AdvancedAds\Abstracts\Ad;
use AdvancedAds\Framework\Utilities\HTML;
use AdvancedAds\Interfaces\Ad_Interface;
use AdvancedAds\Modules\AdSense\Utils;
use AdvancedAds\Utilities\Conditional;

defined( 'ABSPATH' ) || exit;

/**
 * Adsense ad type
 *
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
 */
class Ad_Adsense extends Ad implements Ad_Interface {
	/**
	 * Unit types that are rendered through the responsive code path.
	 *
	 * "link" was a static format until AdSense stopped filling them in March 2021. Their responsive
	 * format serves as a fallback recommended by AdSense.
	 */
	private const RESPONSIVE_UNIT_TYPES = [ 'responsive', 'link', 'link-responsive', 'matched-content', 'in-article', 'in-feed' ];

	/**
	 * Deprecated link unit types.
	 */
	private const LINK_UNIT_TYPES = [ 'link', 'link-responsive' ];

	/**
	 * Overview icon per unit type.
	 */
	private const UNIT_TYPE_ICONS = [
		'matched-content' => 'adsense-multiplex.svg',
		'in-article'      => 'adsense-in-article.svg',
		'in-feed'         => 'adsense-in-feed.svg',
	];

	/**
	 * Return an array with AdSense ad type keys and readable labels
	 *
	 * @return array<string, string>
	 */
	public static function get_ad_types() {
		return [
			'normal'          => __( 'Normal', 'advanced-ads' ),
			'responsive'      => __( 'Responsive', 'advanced-ads' ),
			'matched-content' => __( 'Multiplex', 'advanced-ads' ),
			'link'            => __( 'Link ads', 'advanced-ads' ),
			'link-responsive' => __( 'Link ads (Responsive)', 'advanced-ads' ),
			'in-article'      => __( 'In-article', 'advanced-ads' ),
			'in-feed'         => __( 'In-feed', 'advanced-ads' ),
		];
	}

	/**
	 * Get readable names for each AdSense ad type
	 *
	 * @param string $ad_type ad type key.
	 * @return string
	 */
	public static function get_ad_type_label( $ad_type ) {
		$ad_types = self::get_ad_types();
		return $ad_types[ $ad_type ] ?? __( 'Normal', 'advanced-ads' );
	}

	/**
	 * Render icon on the ad overview list
	 *
	 * @param Ad $ad Ad instance.
	 *
	 * @return void
	 */
	public function render_icon( Ad $ad ) {
		$content   = json_decode( wp_unslash( (string) $ad->get_content() ), true );
		$unit_type = is_array( $content ) ? ( $content['unitType'] ?? '' ) : '';
		$image     = self::UNIT_TYPE_ICONS[ $unit_type ] ?? 'adsense-display.svg';

		printf(
			'<img src="%s" width="50" height="50" alt="%s">',
			esc_url( ADVADS_BASE_URL . '/modules/gadsense/assets/img/' . $image ),
			esc_attr( self::get_ad_type_label( $unit_type ) )
		);
	}

	/**
	 * Prepare output for frontend.
	 *
	 * @return string
	 */
	public function prepare_frontend_output(): string {
		global $gadsense;

		$ad_args = $this->get_prop( 'ad_args' );

		if (
			! empty( $ad_args['wp_the_query']['is_404'] )
			&& ! defined( 'ADVADS_ALLOW_ADSENSE_ON_404' )
		) {
			return '';
		}

		$content = json_decode( stripslashes( (string) $this->get_content() ) );
		$pub_id  = Utils::get_adsense_id( $this );

		if ( ! isset( $content->unitType ) || empty( $pub_id ) ) {
			return '';
		}

		// Deprecated since the adsbygoogle.js file is now always loaded.
		$gadsense['google_loaded'] = true;

		// Check if passive cb is used.
		$gadsense['adsense_count'] = ( $gadsense['adsense_count'] ?? 0 ) + 1;

		$custom_output = apply_filters( 'advanced-ads-gadsense-output', false, $this, $pub_id, $content );
		if ( false !== $custom_output ) {
			return $custom_output;
		}

		// Prevent output on AMP pages.
		if ( Conditional::is_amp() ) {
			return '';
		}

		// Add notice when a link unit is used.
		if ( in_array( $content->unitType, self::LINK_UNIT_TYPES, true ) ) {
			\Advanced_Ads_Ad_Health_Notices::get_instance()->add( 'adsense_link_units_deprecated' );
		}

		if ( ! in_array( $content->unitType, self::RESPONSIVE_UNIT_TYPES, true ) ) {
			return $this->get_static_content( $pub_id, $content );
		}

		/**
		 * The value of $ad->content->resize should be tested to format the output correctly.
		 */
		$output = apply_filters( 'advanced-ads-gadsense-responsive-output', '', $this, $pub_id );

		if ( '' !== $output ) {
			return $output;
		}

		/*
		 * If the output has not been modified, perform a default responsive output.
		 * A simple did_action check isn't sufficient, some hooks may be attached and fired but didn't touch the output.
		 */
		$this->append_defaut_responsive_content( $output, $pub_id, $content );

		// Remove float setting if this is a responsive ad unit without custom sizes.
		unset( $this->wrapper['style']['float'] );

		return $output;
	}

	/**
	 * Append responsive content
	 *
	 * @param string $output  Current ad unit code.
	 * @param string $pub_id  AdSense publisher ID.
	 * @param object $content Ad unit content with all parameters.
	 *
	 * @return void
	 */
	protected function append_defaut_responsive_content( &$output, $pub_id, $content ) {
		$style      = 'display:block;';
		$layout     = '';
		$layout_key = '';

		switch ( $content->unitType ) {
			case 'matched-content':
				$format = 'autorelaxed';
				break;
			case 'link-responsive':
			case 'link':
				$format = 'link';
				break;
			case 'in-feed':
				$format     = 'fluid';
				$layout_key = (string) ( $content->layout_key ?? '' );
				break;
			case 'in-article':
				$format = 'fluid';
				$layout = 'in-article';
				$style  = 'display:block; text-align:center;';
				break;
			default:
				$format = 'auto';
		}

		$attributes = [
			'class'          => 'adsbygoogle',
			'style'          => $style,
			'data-ad-client' => 'ca-' . $pub_id,
			'data-ad-slot'   => (string) ( $content->slotId ?? '' ),
		];

		if ( '' !== $layout ) {
			$attributes['data-ad-layout'] = $layout;
		}

		if ( '' !== $layout_key ) {
			$attributes['data-ad-layout-key'] = $layout_key;
		}

		$attributes['data-ad-format'] = $format;

		$fullwidth = wp_advads_get_setting( 'adsense-fullwidth' ) ?? 'default';

		if ( 'default' !== $fullwidth ) {
			$attributes['data-full-width-responsive'] = 'enable' === $fullwidth ? 'true' : 'false';
		}

		$output .= $this->get_script_tag( $pub_id, $attributes );
		$output .= $this->get_push_script( true );
	}

	/**
	 * Build the output for a fixed size ad unit.
	 *
	 * @param string $pub_id  AdSense publisher ID.
	 * @param object $content Ad unit content with all parameters.
	 *
	 * @return string
	 */
	private function get_static_content( string $pub_id, $content ): string {
		$attributes = [
			'class'          => 'adsbygoogle',
			'style'          => sprintf(
				'display:inline-block;width:%dpx;height:%dpx;',
				(int) $this->get_width(),
				(int) $this->get_height()
			),
			'data-ad-client' => 'ca-' . $pub_id,
			'data-ad-slot'   => (string) ( $content->slotId ?? '' ),
		];

		// Ad type for static link unit.
		if ( 'link' === $content->unitType ) {
			$attributes['data-ad-format'] = 'link';
		}

		return $this->get_script_tag( $pub_id, $attributes )
			. $this->get_push_script();
	}

	/**
	 * Build AdSense script tag.
	 *
	 * @param string                $pub_id     AdSense publisher ID.
	 * @param array<string, string> $attributes Attribute name => value. Empty values are skipped.
	 *
	 * @return string
	 */
	protected function get_script_tag( $pub_id, $attributes ): string {
		/**
		 * Filter the output of the publisher ID appended to the AdSense JavaScript Code.
		 *
		 * @param bool $add_publisher_id Whether to append the publisher ID.
		 */
		$query = apply_filters( 'advanced-ads-adsense-publisher-id', true )
			? '?client=' . rawurlencode( 'ca-' . $pub_id )
			: '';

		$script = sprintf(
			// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- don't allow any changes on Google AdSense code.
			'<script async src="//pagead2.googlesyndication.com/pagead/js/adsbygoogle.js%s" crossorigin="anonymous"></script>',
			$query
		);

		// Build the `ins` tag from a set of attributes.
		$script .= '<ins' . HTML::build_attributes( $attributes ) . '></ins>' . "\n";

		return $script;
	}

	/**
	 * Build the adsbygoogle push script.
	 *
	 * @param bool $filtered Whether the push call can be filtered. Only responsive units allow this.
	 *
	 * @return string
	 */
	private function get_push_script( bool $filtered = false ): string {
		$push = '(adsbygoogle = window.adsbygoogle || []).push({}); ' . "\n";

		if ( $filtered ) {
			$push = apply_filters( 'advanced-ads-gadsense-responsive-adsbygoogle', $push );
		}

		return '<script> ' . "\n" . $push . '</script>' . "\n";
	}
}
