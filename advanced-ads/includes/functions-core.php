<?php
/**
 * Core functions
 *
 * @since 2.0.0
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

use AdvancedAds\Framework\JSON;

/**
 * Add to JSON object.
 *
 * @since  1.0.0
 *
 * @param mixed ...$args Arguments.
 *
 * Parameters can be
 * 1. array|string Unique identifier or array<key, value>.
 * 2. array|string The data itself can be either a scalar or an array.
 *                 In Case of first param an array this can be object_name.
 * 3. string Name for the JavaScript object.
 *           Passed directly, so it should be qualified JS variable.
 *
 * @return JSON
 */
function wp_advads_json_add( ...$args ): JSON {
	return wp_advads()->json->add( ...$args );
}

/**
 * Remove from JSON object.
 *
 * @since  1.0.0
 *
 * @param string       $key         Unique identifier.
 * @param string|false $object_name Name for the JavaScript object.
 *                            Passed directly, so it should be qualified JS variable.
 * @return JSON
 */
function wp_advads_json_remove( $key, $object_name = false ): JSON {
	return wp_advads()->json->remove( $key, $object_name );
}

/**
 * Get a setting's current value — with no $store, looks up a registered field by id (see AdvancedAds\Settings\Registry, tab()->group()->field()/->toggle()/etc.), null if no field is registered with this id; with $store, bypasses the schema entirely and reads straight from AdvancedAds\Settings\Store, for a key that was never registered as a field (e.g. a module's internal state).
 *
 * @param string $id    Field id, or a raw store key when $store is given.
 * @param string $store Optional: store name to read $id from directly.
 *
 * @return mixed
 */
function wp_advads_get_setting( string $id, string $store = '' ) {
	if ( '' !== $store ) {
		return wp_advads()->settings_registry->store->get( $store, $id );
	}

	return wp_advads()->settings_registry->get_value( $id );
}


/**
 * Check if a module is active.
 *
 * @param string $module_id The module ID.
 * @return bool
 */
function wp_advads_is_module_active( string $module_id ): bool {
	return wp_advads()->module_registry->is_active( $module_id );
}
