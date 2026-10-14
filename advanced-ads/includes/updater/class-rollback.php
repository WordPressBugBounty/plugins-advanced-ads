<?php
/**
 * Plugin rollback — lists usable versions and installs another version of Advanced Ads.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.50.0
 */

namespace AdvancedAds\Updater;

use AdvancedAds\Framework\Utilities\Params;
use AdvancedAds\Framework\Interfaces\Integration_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin rollback.
 */
class Rollback implements Integration_Interface {
	/**
	 * Includes up to this amount of latest minor version into the usable version, including all the in between patches.
	 *
	 * @var int
	 */
	private const MINOR_VERSION_COUNT = 3;

	/**
	 * The version list transient name
	 *
	 * @var string
	 */
	public const VERSIONS_TRANSIENT = 'advads-versions-list';

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'wp_ajax_advads_get_usable_versions', [ $this, 'get_usable_versions' ] );
		add_action( 'wp_ajax_advads_install_alternate_version', [ $this, 'install_plugin' ] );
	}

	/**
	 * Get usable version, fetch from the info API if needed
	 *
	 * @return void
	 */
	public function get_usable_versions() {
		$this->check_user_capabilities();

		if ( ! wp_verify_nonce( Params::post( 'nonce', '', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'advads-version-control' ) ) {
			wp_send_json_error( 'Not authorized', 401 );
		}

		$versions = $this->get_or_fetch_usable_versions();

		if ( is_wp_error( $versions ) ) {
			wp_send_json_error( $versions->get_error_message(), 500 );
		}

		wp_send_json_success( $versions, 200 );
	}

	/**
	 * Download and install the desired version
	 *
	 * @return void
	 */
	public function install_plugin(): void {
		$this->check_user_capabilities();
		$nonce = sanitize_key( Params::post( 'version-control-nonce', '' ) );

		if ( ! wp_verify_nonce( $nonce, 'advads-version-control' ) ) {
			wp_send_json_error( 'Not authorized', 401 );
		}

		$version = sanitize_text_field( Params::post( 'version', '' ) );

		if ( '' === $version ) {
			wp_send_json_error(
				[
					'error_code'    => 'invalid_version',
					'error_message' => __( 'No version selected.', 'advanced-ads' ),
				],
				400
			);
		}

		$versions = $this->get_or_fetch_usable_versions();
		if ( is_wp_error( $versions ) ) {
			wp_send_json_error(
				[
					'error_code'    => $versions->get_error_code(),
					'error_message' => $versions->get_error_message(),
				],
				400
			);
		}

		if ( empty( $versions['versions'][ $version ] ) ) {
			wp_send_json_error(
				[
					'error_code'    => 'version_not_allowed',
					'error_message' => __( 'Selected version is not available.', 'advanced-ads' ),
				],
				400
			);
		}

		$result = Installer::install_from_url( sanitize_url( $versions['versions'][ $version ] ), true );

		// The upgrader answers false or null when it fails without naming a reason.
		if ( ! $result ) {
			$result = new \WP_Error( 'install_failed', __( 'The selected version could not be installed.', 'advanced-ads' ) );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				[
					'error_code'    => $result->get_error_code(),
					'error_message' => $result->get_error_message(),
				],
				400
			);
		}

		if ( version_compare( $version, '2.0.0', '<' ) ) {
			// Sites installed on 2.0.0 or later have no backup to restore.
			$placements = get_option( 'advads-ads-placements_backup' );
			if ( false !== $placements ) {
				update_option( 'advads-ads-placements', $placements );
				delete_option( 'advads-ads-placements_backup' );
			}
			update_option( 'advanced_ads_db_version', '1.52.1' );
		}

		wp_send_json_success(
			[
				'result'   => $result,
				'redirect' => admin_url( 'plugins.php?rollback=1' ),
			],
			200
		);
	}

	/**
	 * Perform capabilities check
	 *
	 * @return void
	 */
	private function check_user_capabilities() {
		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_send_json_error( 'Not enough permissions', 401 );
		}
	}

	/**
	 * Cached usable versions map, fetching from the API when missing.
	 *
	 * @return array|\WP_Error
	 */
	private function get_or_fetch_usable_versions() {
		$stored = get_transient( self::VERSIONS_TRANSIENT );
		if ( is_array( $stored ) && ! empty( $stored['versions'] ) ) {
			return $stored;
		}

		$fetched = $this->get_version_from_api();
		if ( is_wp_error( $fetched ) ) {
			return $fetched;
		}

		$versions = $this->filter_version_number( $fetched );
		set_transient( self::VERSIONS_TRANSIENT, $versions, 3 * HOUR_IN_SECONDS );

		return $versions;
	}

	/**
	 * Filter the versions list from the info API, keeping all updates until the last three minor updates plus the last version before the last major update.
	 *
	 * @param array<string, mixed> $versions All version the info API.
	 *
	 * @return array<string, mixed>
	 */
	public function filter_version_number( $versions ) {
		$results = [];

		// Remove the "dev" version.
		unset( $versions['trunk'] );

		$version_numbers = array_keys( $versions );

		usort( $version_numbers, 'version_compare' );

		$version_numbers = array_reverse( $version_numbers );
		array_shift( $version_numbers );

		$major                 = '';
		$minor                 = '';
		$minor_version_changes = 0;
		$major_version_changes = 0;

		foreach ( $version_numbers as $number ) {
			// Skip pre-release versions.
			if ( preg_match( '/(rc|alpha|beta)/i', $number ) ) {
				continue;
			}

			$parts      = explode( '.', $number );
			$major_part = $parts[1];
			$minor_part = $parts[2];

			if ( $major !== $major_part ) {
				$major = $major_part;
				++$major_version_changes;
				$minor_version_changes = 0;
			}

			if ( $minor !== $minor_part ) {
				$minor = $minor_part;
				++$minor_version_changes;
			}

			if ( $minor_version_changes <= self::MINOR_VERSION_COUNT ) {
				$results[ $number ] = $versions[ $number ];
			}

			if ( $major_version_changes >= self::MINOR_VERSION_COUNT ) {
				break;
			}
		}

		return [
			'versions' => $results,
			'order'    => array_keys( $results ),
		];
	}

	/**
	 * Get all version from the info API
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	private function get_version_from_api() {
		$aa_info = wp_remote_get( 'https://api.wordpress.org/plugins/info/1.0/advanced-ads.json' );

		if ( is_wp_error( $aa_info ) ) {
			return $aa_info;
		}

		$info = json_decode( wp_remote_retrieve_body( $aa_info ), true );

		if ( ! empty( $info['versions'] ) ) {
			return $info['versions'];
		}

		// Likely a change in the WP info API.
		return new \WP_Error( 404, __( 'Plugin info not found', 'advanced-ads' ) );
	}
}
