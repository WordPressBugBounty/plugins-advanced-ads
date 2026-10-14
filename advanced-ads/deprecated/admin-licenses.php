<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Backward compatibility shim for add-ons reading the license status.
 *
 * @deprecated 2.0.27 Use {@see \AdvancedAds\License\License} instead.
 * @package AdvancedAds
 */

use AdvancedAds\License\License;

/**
 * Handle add-on licenses.
 *
 * @deprecated 2.0.27 Use {@see License}.
 */
class Advanced_Ads_Admin_Licenses {

	/**
	 * Return an instance of this class.
	 *
	 * @deprecated 2.0.27
	 *
	 * @return self
	 */
	public static function get_instance() {
		static $instance;

		if ( null === $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Get license status of an add-on.
	 *
	 * @deprecated 2.0.27 Use {@see License::get_mirror_status_for_options_slug()}.
	 *
	 * @param string $slug Options slug of the add-on.
	 *
	 * @return string|false
	 */
	public function get_license_status( $slug = '' ) {
		_deprecated_function( __METHOD__, '2.0.27', '\AdvancedAds\License\License::get_mirror_status_for_options_slug()' );

		return License::get_mirror_status_for_options_slug( (string) $slug );
	}
}
