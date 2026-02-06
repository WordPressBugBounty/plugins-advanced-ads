<?php
/**
 * Traits Wrapper.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.48.2
 */

namespace AdvancedAds\Traits;

use AdvancedAds\Framework\Utilities\HTML;

defined( 'ABSPATH' ) || exit;

/**
 * Traits Wrapper.
 */
trait Wrapper {

	/**
	 * Get the wrapper ID for the entity.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return string
	 */
	public function get_wrapper_id( $context = 'view' ): string {
		return (string) $this->get_prop( 'wrapper-id', $context );
	}

	/**
	 * Get the wrapper classes for the entity.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return string
	 */
	public function get_wrapper_class( $context = 'view' ): string {
		return (string) $this->get_prop( 'wrapper-class', $context );
	}

	/**
	 * Set wrapper id.
	 *
	 * @param string $wrapper_id Entity wrapper id.
	 *
	 * @return void
	 */
	public function set_wrapper_id( $wrapper_id ): void {
		$this->set_prop( 'wrapper-id', sanitize_key( $wrapper_id ) );
	}

	/**
	 * Set wrapper class.
	 *
	 * @param string $wrapper_class Entity wrapper class.
	 *
	 * @return void
	 */
	public function set_wrapper_class( $wrapper_class ): void {
		$this->set_prop( 'wrapper-class', sanitize_text_field( $wrapper_class ) );
	}

	/**
	 * Creates a wrapper element with the specified tag, attributes, and content.
	 *
	 * @param string $tag     The HTML tag for the wrapper element.
	 * @param array  $attrs   Optional. An array of attributes to add to the wrapper element. Default is an empty array.
	 * @param string $content Optional. The content to be placed inside the wrapper element. Default is an empty string.
	 *
	 * @return string The generated wrapper element.
	 */
	public function create_wrapper( $tag, $attrs = [], $content = '' ) {
		$attrs = HTML::build_attributes( $attrs );

		return "<{$tag} {$attrs}>{$content}</{$tag}>";
	}


	/**
	 * Sets the wrapper styles based on the given position.
	 *
	 * @param array  $wrapper      The wrapper array to store the styles.
	 * @param string $position     The position of the ad.
	 * @param bool   $use_position Whether to use the position or not.
	 *
	 * @return void
	 */
	protected function get_wrapper_styles( &$wrapper, $position, $use_position = false ): void {
		// Always keep margin before handling position (Specific to Ads).
		if ( method_exists( $this, 'get_margin' ) ) {
			$margin = $this->get_margin();
			foreach ( $margin as $key => $value ) {
				if ( ! empty( $value ) ) {
					$wrapper['style'][ 'margin-' . $key ] = $value . 'px';
				}
			}
		}

		switch ( $position ) {
			case 'left':
			case 'left_float':
			case 'left_nofloat':
				$wrapper['style']['float'] = 'left';
				break;
			case 'right':
			case 'right_float':
			case 'right_nofloat':
				$wrapper['style']['float'] = 'right';
				break;
			case 'center':
			case 'center_nofloat':
			case 'center_float':
				if ( method_exists( $this, 'get_margin' ) ) {
					$wrapper['style']['margin-left']  = 'auto';
					$wrapper['style']['margin-right'] = 'auto';
				}

				$width             = method_exists( $this, 'get_width' ) ? $this->get_width() : 0;
				$add_wrapper_sizes = method_exists( $this, 'get_prop' ) ? $this->get_prop( 'add_wrapper_sizes' ) : false;

				if ( empty( $width ) || empty( $add_wrapper_sizes ) || $use_position ) {
					$wrapper['style']['text-align'] = 'center';
				}
				break;
			case 'clearfix':
				$wrapper['style']['clear'] = 'both';
				break;
		}

		if ( method_exists( $this, 'is_space_reserved' ) && $this->is_space_reserved() ) {
			if ( method_exists( $this, 'get_width' ) && ! empty( $this->get_width() ) ) {
				$wrapper['style']['width'] = $this->get_width() . 'px';
			}

			if ( method_exists( $this, 'get_height' ) && ! empty( $this->get_height() ) ) {
				$wrapper['style']['height'] = $this->get_height() . 'px';
			}
		}

		if ( method_exists( $this, 'get_clearfix' ) && ! empty( $this->get_clearfix() ) ) {
			$wrapper['style']['clear'] = 'both';
		}
	}
}
