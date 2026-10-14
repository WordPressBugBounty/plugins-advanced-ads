<?php
/**
 * Read-only system-level context flags for settings conditions.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.0.0
 */

namespace AdvancedAds\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Context class.
 */
class Context {

	/**
	 * Registered context key => resolver callable.
	 *
	 * @var array<string, callable>
	 */
	private $resolvers = [];

	/**
	 * Register a read-only context value.
	 *
	 * @param string   $key      Context key (e.g. "is_pro_active").
	 * @param callable $resolver Zero-arg callable returning the value.
	 *
	 * @return void
	 */
	public function register( string $key, callable $resolver ): void {
		$this->resolvers[ $key ] = $resolver;
	}

	/**
	 * Resolve every registered context value.
	 *
	 * @return array<string, mixed>
	 */
	public function resolve(): array {
		return array_map( static fn( callable $resolver ) => $resolver(), $this->resolvers );
	}
}
