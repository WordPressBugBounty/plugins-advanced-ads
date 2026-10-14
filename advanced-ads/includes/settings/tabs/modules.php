<?php
/**
 * Modules tab shell.
 *
 * @package AdvancedAds
 * @var \AdvancedAds\Settings\Registry $registry
 */

defined( 'ABSPATH' ) || exit;

$group = $registry
	->tab( 'modules', __( 'Modules', 'advanced-ads' ), [ 'icon' => 'blocks', 'order' => 5 ] )
	->group( 'modules', [ 'label' => __( 'Modules', 'advanced-ads' ) ] );

$modules = wp_advads()->module_registry->all();
uasort(
	$modules,
	static fn( $a, $b ) => strcasecmp( $a->get_label(), $b->get_label() )
);

foreach ( $modules as $module ) {
	if ( $module->is_hidden() ) {
		continue; // Always boots (see Registry::is_active()) — no card, no toggle.
	}

	$group->module( $module->to_field() );

	if ( ! $module->is_upgradeable() ) {
		$registry->store->register(
			'modules',
			ADVADS_SLUG . '-modules',
			[ $module->get_active_key() => $module->get_default_active() ]
		);
	}
}
