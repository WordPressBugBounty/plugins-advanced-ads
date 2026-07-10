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

	/**
	 * Whether a cached entity instance exists for the given ID and type override.
	 *
	 * @param int    $id       Entity ID.
	 * @param string $new_type Optional type override passed to the factory getter.
	 *
	 * @return bool
	 */
	protected function has_cached_instance( int $id, string $new_type = '' ): bool {
		return array_key_exists( $this->get_instance_cache_key( $id, $new_type ), $this->instances );
	}

	/**
	 * Get a cached entity instance for the given ID and type override.
	 *
	 * @param int    $id       Entity ID.
	 * @param string $new_type Optional type override passed to the factory getter.
	 *
	 * @return object|false
	 */
	protected function get_cached_instance( int $id, string $new_type = '' ) {
		return $this->instances[ $this->get_instance_cache_key( $id, $new_type ) ];
	}

	/**
	 * Store an entity instance in the request-scoped cache.
	 *
	 * @param int           $id       Entity ID.
	 * @param string        $new_type Optional type override passed to the factory getter.
	 * @param object|false  $instance Loaded entity instance.
	 *
	 * @return void
	 */
	protected function store_cached_instance( int $id, string $new_type, $instance ): void {
		$this->instances[ $this->get_instance_cache_key( $id, $new_type ) ] = $instance;
	}

	/**
	 * Create an empty entity from a registered type object.
	 *
	 * @param object|false $type_object Registered type object.
	 * @param int          $id          Entity ID.
	 *
	 * @return object|false
	 */
	protected function create_empty_from_type( $type_object, int $id = 0 ) {
		if ( ! $type_object ) {
			return false;
		}

		$classname = $type_object->get_classname();
		$entity    = new $classname( $id );
		$entity->set_type( $type_object->get_id() );

		return $entity;
	}

}
