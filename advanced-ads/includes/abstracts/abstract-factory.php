<?php
/**
 * Abstracts Factory.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.47.0
 */

namespace AdvancedAds\Abstracts;

defined( 'ABSPATH' ) || exit;

/**
 * Abstracts Factory.
 */
abstract class Factory {

	/**
	 * Request-scoped cache of loaded entity instances keyed by entity ID and optional type override.
	 *
	 * @var array<string, object|false>
	 */
	protected $instances = [];

	/**
	 * Clear request-scoped entity instances (e.g. after CRUD writes).
	 *
	 * @return void
	 */
	public function clear_instance_cache(): void {
		$this->instances = [];
	}

	/**
	 * Build a cache key for a loaded entity instance.
	 *
	 * @param int    $id       Entity ID.
	 * @param string $new_type Optional type override passed to the factory getter.
	 *
	 * @return string
	 */
	protected function get_instance_cache_key( int $id, string $new_type = '' ): string {
		return '' !== $new_type ? $id . ':' . $new_type : (string) $id;
	}
}
