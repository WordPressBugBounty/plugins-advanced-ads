<?php
/**
 * Utilities Sanitize.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.50.0
 */

namespace AdvancedAds\Utilities;

defined( 'ABSPATH' ) || exit;

/**
 * Utilities Sanitize.
 */
class Sanitize {
	/**
	 * Sanitize the frontend prefix to result in valid HTML classes — see https://www.w3.org/TR/selectors-3/#grammar for valid tokens.
	 *
	 * @param string $prefix The HTML class to sanitize.
	 * @param string $fallback The fallback if the class is invalid.
	 *
	 * @return string
	 */
	public static function frontend_prefix( $prefix, $fallback = '' ): string {
		$prefix   = sanitize_html_class( $prefix );
		$nonascii = '[^\0-\177]';
		$unicode  = '\\[0-9a-f]{1,6}(\r\n|[ \n\r\t\f])?';
		$escape   = sprintf( '%s|\\[^\n\r\f0-9a-f]', $unicode );
		$nmstart  = sprintf( '[_a-z]|%s|%s', $nonascii, $escape );
		$nmchar   = sprintf( '[_a-z0-9-]|%s|%s', $nonascii, $escape );

		if ( ! preg_match( sprintf( '/-?(?:%s)(?:%s)*/i', $nmstart, $nmchar ), $prefix, $matches ) ) {
			return $fallback;
		}

		return $matches[0];
	}

	/**
	 * Sanitize email list
	 *
	 * @param string $emails List of addresses to sanitize.
	 *
	 * @return string
	 */
	public static function email_addresses( $emails ): string {
		// Early bail!!
		if ( ! is_string( $emails ) || empty( $emails ) ) {
			return '';
		}

		$emails = stripslashes( $emails );
		$emails = explode( ',', $emails );
		$emails = array_map( 'sanitize_email', $emails );
		$emails = array_filter( $emails );

		return implode( ',', $emails );
	}

	/**
	 * Sanitize Google Analytics UID
	 *
	 * @param string $ids Google Analytics UID.
	 *
	 * @return string
	 */
	public static function analytics_uid( $ids ): string {
		$ids = explode( ',', $ids );
		$ids = array_map(
			function ( $ga_id ) {
				return trim( $ga_id, ' /][)(#' );
			},
			$ids
		);
		$ids = array_filter( $ids );

		return implode( ', ', $ids );
	}

	/**
	 * Kses an ad-label string with the same tag allowlist used when rendering the label.
	 *
	 * @param mixed $value Raw label HTML.
	 *
	 * @return string
	 */
	public static function ad_label( $value ): string {
		return wp_kses(
			(string) $value,
			[
				'a'          => [
					'href'  => true,
					'title' => true,
				],
				'b'          => [],
				'blockquote' => [
					'cite' => true,
				],
				'cite'       => [],
				'code'       => [],
				'del'        => [
					'datetime' => true,
				],
				'em'         => [],
				'i'          => [],
				'q'          => [
					'cite' => true,
				],
				's'          => [],
				'span'       => [
					'style' => true,
				],
				'strike'     => [],
				'br'         => [],
				'strong'     => [],
			]
		);
	}

	/**
	 * Normalize a raw stored value: the legacy string encodings become real types, everything else passes through unchanged. Recurses into arrays.
	 *
	 * `'true'`/`'on'`/`'yes'` become true, `'false'`/`'off'`/`'no'` false, and `'0'`/`'1'` the
	 * ints 0/1 (not booleans). A three-way choice stored as `yes`/`no`/`default` therefore reads
	 * as true, false or `default`.
	 *
	 * @param mixed $value Value to normalize.
	 *
	 * @return mixed
	 */
	public static function normalize_value( $value ) {
		if ( is_array( $value ) ) {
			return array_map( [ self::class, 'normalize_value' ], $value );
		}

		if ( 'true' === $value || 'on' === $value || 'yes' === $value ) {
			return true;
		}

		if ( 'false' === $value || 'off' === $value || 'no' === $value ) {
			return false;
		}

		if ( '0' === $value || '1' === $value ) {
			return (int) $value;
		}

		return $value;
	}
}
