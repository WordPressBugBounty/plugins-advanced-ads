<?php
/**
 * Get/save option values, per store, with dot-path keys — a plain storage layer, independent of the field schema Registry builds on top of it, with no knowledge of tabs/groups/fields/ids. Owned internally by Registry, the single settings entry point the rest of the plugin should use (wp_advads()->settings_registry).
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.0.0
 */

namespace AdvancedAds\Settings;

use AdvancedAds\Framework\Utilities\Arr;
use AdvancedAds\Utilities\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * Store class.
 */
class Store {

	/**
	 * Registered store name => wp_options option name.
	 *
	 * @var array<string, string>
	 */
	private $stores = [];

	/**
	 * Registered store name => default value map (dot-path key => default value).
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private $defaults = [];

	/**
	 * Register a store against a real wp_options option name — calling this more than once for the same name merges the new defaults into whatever was registered before, so more than one registrar can contribute defaults for the same store.
	 *
	 * @param string               $name        Store name used in field definitions (e.g. "main").
	 * @param string               $option_name The wp_options row this store reads/writes.
	 * @param array<string, mixed> $defaults    Dot-path key => default value, used by get() when a key is missing.
	 *
	 * @return void
	 */
	public function register( string $name, string $option_name, array $defaults = [] ): void {
		$this->stores[ $name ]   = $option_name;
		$this->defaults[ $name ] = array_merge( $this->defaults[ $name ] ?? [], $defaults );
	}

	/**
	 * Get the option array for a registered store.
	 *
	 * @param string $name Store name.
	 *
	 * @return array<string, mixed>
	 */
	public function get_store( string $name ) {
		return isset( $this->stores[ $name ] ) ? get_option( $this->stores[ $name ], [] ) : [];
	}

	/**
	 * Read a dot-path value from a registered store, with defaults and optional value_callback.
	 *
	 * @param string        $store          Store name.
	 * @param string        $key            Dot-path key within the store's option array.
	 * @param callable|null $value_callback Optional: raw value in, decoded value out — replaces Sanitize::normalize_value().
	 *
	 * @return mixed
	 */
	public function get( string $store, string $key, ?callable $value_callback = null ) {
		if ( ! isset( $this->stores[ $store ] ) ) {
			return null;
		}

		$data = get_option( $this->stores[ $store ], [] );
		if ( ! is_array( $data ) ) {
			$data = [];
		}

		$default = Arr::get( $this->defaults[ $store ], $key );
		$value   = Arr::get( $data, $key, $default );

		if ( null !== $value_callback ) {
			return $value_callback( $value );
		}

		return Sanitize::normalize_value( $value );
	}

	/**
	 * Merge dot-path values into a store's option array with a single update_option() call.
	 *
	 * @param string               $store Store name.
	 * @param array<string, mixed> $pairs Dot-path key => value pairs to merge.
	 *
	 * @return void
	 */
	public function save( string $store, array $pairs ): void {
		if ( ! isset( $this->stores[ $store ] ) ) {
			return;
		}

		$option_name = $this->stores[ $store ];
		$data        = get_option( $option_name, [] );

		if ( ! is_array( $data ) ) {
			$data = [];
		}

		foreach ( $pairs as $key => $value ) {
			Arr::set( $data, $key, $value );
		}

		update_option( $option_name, $data );
	}

	/**
	 * Overwrite specific top-level keys of a store's option row in one update_option() call — unlike save(), $data isn't merged key-by-key via a dot path, it's a flat top-level key => value overwrite.
	 *
	 * @param string               $store Store name.
	 * @param array<string, mixed> $data  Top-level key => value pairs to set.
	 *
	 * @return void
	 */
	public function replace_keys( string $store, array $data ): void {
		if ( ! isset( $this->stores[ $store ] ) ) {
			return;
		}

		$option_name = $this->stores[ $store ];
		$row         = get_option( $option_name, [] );

		if ( ! is_array( $row ) ) {
			$row = [];
		}

		update_option( $option_name, array_merge( $row, $data ) );
	}
}
