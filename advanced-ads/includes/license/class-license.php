<?php
/**
 * License class.
 * Handles license management for Advanced Ads add-ons.
 *
 * @since   2.0.17
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

namespace AdvancedAds\License;

use AdvancedAds\Utilities\Data;

defined( 'ABSPATH' ) || exit;

/**
 * License class.
 */
class License {

	/**
	 * Option name for licenses.
	 *
	 * @var string
	 */
	private const OPTION_NAME = 'advanced-ads-licenses';

	/**
	 * Add-on slugs.
	 *
	 * @var array
	 */
	public const ADDONS_SLUGS = [
		'pro',
		'responsive',
		'gam',
		'layer',
		'selling',
		'sticky',
		'tracking',
		'slider-ads',
	];

	/**
	 * Main instance
	 *
	 * Ensure only one instance is loaded or can be loaded.
	 *
	 * @return License
	 */
	public static function get() {
		static $instance;

		if ( null === $instance ) {
			$instance = new License();
		}

		return $instance;
	}

	/**
	 * Get license
	 *
	 * @param string $slug Add-on slug.
	 *
	 * @return array
	 */
	public static function get_license_details( $slug ): array {
		$licenses = get_option( self::OPTION_NAME, [] );
		$license  = $licenses[ $slug ] ?? [];

		if ( ! empty( $license ) ) {
			$license['status']  = get_option( 'advanced-ads-' . $slug . '-license-status', 'invalid' );
			$license['expires'] = get_option( 'advanced-ads-' . $slug . '-license-expires', false );
		}

		return $license;
	}

	/**
	 * Save license
	 *
	 * @param string $slug        Add-on slug.
	 * @param string $license_key License key.
	 * @param string $status      License status.
	 * @param string $expires     License expires.
	 *
	 * @return void
	 */
	public static function update_license_details( $slug, $license_key, $status = 'valid', $expires = false ): void {
		$licenses = get_option( self::OPTION_NAME, [] );

		if ( 'lifetime' === $expires ) {
			$expires = time() + YEAR_IN_SECONDS * 200;
		}

		$licenses[ $slug ] = [
			'license' => $license_key,
			'status'  => $status,
			'expires' => $expires,
		];

		update_option( self::OPTION_NAME, $licenses );
		update_option( 'advanced-ads-' . $slug . '-license-status', $status );
		update_option( 'advanced-ads-' . $slug . '-license-expires', $expires );
	}

	/**
	 * Get license key
	 *
	 * @param string $slug Add-on slug.
	 *
	 * @return bool
	 */
	public static function has_valid_license( $slug ): bool {
		$license = self::get_license_details( $slug );

		if ( empty( $license ) ) {
			return false;
		}

		return 'valid' === $license['status'] && $license['expires'] > time();
	}

	/**
	 * Check if any license is valid
	 *
	 * @return bool
	 */
	public static function has_any_valid_license(): bool {
		foreach ( self::ADDONS_SLUGS as $add_on ) {
			if ( self::has_valid_license( $add_on ) ) {
				return true;
			}
		}

		return false;
	}
}
