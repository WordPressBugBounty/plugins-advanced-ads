<?php
/**
 * Ad Blocker scripts
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

namespace AdvancedAds\Modules\AdBlocker;

use AdvancedAds\Framework\Interfaces\Integration_Interface;
use AdvancedAds\Framework\Utilities\Str;

defined( 'ABSPATH' ) || exit;

/**
 * Scripts class.
 */
class Scripts implements Integration_Interface {

	/**
	 * Plugins URL.
	 *
	 * @var string
	 */
	private $plugins_url;

	/**
	 * Disguise folder name under uploads.
	 *
	 * @var string|null
	 */
	private $folder_name;

	/**
	 * Source-relative URL => disguised path lookup.
	 *
	 * @var array
	 */
	private $lookup_table;

	/**
	 * Uploads base URL.
	 *
	 * @var string
	 */
	private $uploads_baseurl;

	/**
	 * Whether folder_name / lookup_table / uploads_baseurl have been loaded.
	 *
	 * @var bool
	 */
	private $cached = false;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function hooks(): void {
		$this->plugins_url = plugins_url();

		add_filter( 'script_loader_src', [ $this, 'disguise_src' ], PHP_INT_MAX, 2 );
		add_filter( 'style_loader_src', [ $this, 'disguise_src' ], PHP_INT_MAX, 2 );
	}

	/**
	 * Rewrite an Advanced Ads asset URL to the disguised copy as it is printed.
	 *
	 * @param string|false $src    Original src, or false for an alias handle.
	 * @param string       $handle Script or style handle.
	 *
	 * @return string|false
	 */
	public function disguise_src( $src, $handle ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WP filter signature.
		if (
			! wp_advads_get_setting( 'use-adblocker', 'adblocker' )
			|| ! Str::is_non_empty( $src )
			|| ! Str::contains( 'advanced-ads', $src )
		) {
			return $src;
		}

		$this->ensure_cache();

		if ( ! $this->folder_name ) {
			return $src;
		}

		$url = str_replace( $this->plugins_url, '', $src );

		if ( ! isset( $this->lookup_table[ $url ]['path'] ) ) {
			return $src;
		}

		return trailingslashit( $this->uploads_baseurl ) . trailingslashit( $this->folder_name ) . $this->lookup_table[ $url ]['path'];
	}

	/**
	 * Load folder_name, lookup_table, and uploads base URL once per request.
	 *
	 * @return void
	 */
	private function ensure_cache(): void {
		if ( $this->cached ) {
			return;
		}

		$this->folder_name     = wp_advads_get_setting( 'folder_name', 'adblocker' );
		$this->lookup_table    = wp_advads_get_setting( 'lookup_table', 'adblocker' ) ?? [];
		$this->uploads_baseurl = wp_upload_dir()['baseurl'];
		$this->cached          = true;
	}
}
