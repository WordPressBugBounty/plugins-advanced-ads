<?php
/**
 * Module registry: what backs the Modules settings tab, and what conditionally loads each real module's actual code.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

namespace AdvancedAds\Modules;

use AdvancedAds\Framework\Interfaces\Initializer_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Registry class.
 */
class Registry implements Initializer_Interface {

	/**
	 * Registered modules, keyed by id.
	 *
	 * @var array<string, Module>
	 */
	private $modules = [];

	/**
	 * Whether hooks() has already run — guards against booting every active module a second time (e.g. Boot classes with an unconditional define() would fatal on redefinition) if something calls it defensively, such as Installation\Uninstall::delete_modules() making sure the registry is populated in a context where the normal `plugins_loaded` timing may have already passed.
	 *
	 * @var bool
	 */
	private $hooked = false;

	/**
	 * Runs this initializer — synchronously, at plugin-file-load time. Defers the real work to `plugins_loaded`, which always completes before `init` on every request type, so a module's own code (once conditionally booted) can safely use `init` itself with no ordering risk, and Settings\Registry (on `init`) is always guaranteed a fully-resolved module registry.
	 *
	 * @return void
	 */
	public function initialize(): void {
		add_action( 'plugins_loaded', [ $this, 'hooks' ] );
	}

	/**
	 * Register core modules, let add-ons register/unregister theirs, then boot whichever real modules are currently active. A no-op after the first call — see $hooked.
	 *
	 * @return void
	 */
	public function hooks(): void {
		if ( $this->hooked ) {
			return;
		}

		$this->hooked = true;

		$registry = $this; // In scope for core-modules.php, same convention as Settings\Registry::load_tab_files().
		// require, not require_once: $hooked is the re-entrancy guard. require_once is process-global and skips registration on a second Registry instance (PHPUnit).
		require __DIR__ . '/core-modules.php';
		require __DIR__ . '/pro-modules.php';

		/**
		 * Register/unregister modules against the Modules-tab registry.
		 *
		 * @param Registry $registry
		 */
		do_action( 'advanced-ads-register-modules', $this );

		$this->boot_active_modules();
	}

	/**
	 * Register a module, overwriting any existing registration with the same id.
	 *
	 * @param Module $module Module.
	 *
	 * @return void
	 */
	public function register( Module $module ): void {
		$this->modules[ $module->get_id() ] = $module;
	}

	/**
	 * Remove a registered module.
	 *
	 * @param string $id Module id.
	 *
	 * @return void
	 */
	public function unregister( string $id ): void {
		unset( $this->modules[ $id ] );
	}

	/**
	 * Look up a registered module by id.
	 *
	 * @param string $id Module id.
	 *
	 * @return Module|null
	 */
	public function get( string $id ): ?Module {
		return $this->modules[ $id ] ?? null;
	}

	/**
	 * Every registered module, keyed by id, in registration order.
	 *
	 * @return array<string, Module>
	 */
	public function all(): array {
		return $this->modules;
	}

	/**
	 * Whether a real module is currently active. Always false for an unknown id or a locked/upsell (is_upgradeable) module; always true for a hidden module (no card, no toggle — it always boots). Otherwise reads the shared 'modules' option directly via get_option() rather than through Settings\Store, since Settings\Registry isn't initialized yet at `plugins_loaded`, when this is called from boot_active_modules().
	 *
	 * @param string $id Module id.
	 *
	 * @return bool
	 */
	public function is_active( string $id ): bool {
		$module = $this->get( $id );

		if ( null === $module || $module->is_upgradeable() ) {
			return false;
		}

		if ( $module->is_hidden() ) {
			return true;
		}

		$stored = get_option( ADVADS_SLUG . '-modules', [] );

		return $stored[ $module->get_active_key() ] ?? $module->get_default_active();
	}

	/**
	 * Boot every real, active module that has an entry class.
	 *
	 * @return void
	 */
	private function boot_active_modules(): void {
		foreach ( $this->modules as $module ) {
			$entry = $module->get_entry();

			if ( null === $entry || ! $this->is_active( $module->get_id() ) ) {
				continue;
			}

			if ( ! class_exists( $entry ) ) {
				_doing_it_wrong(
					__METHOD__,
					sprintf(
						'Module entry class "%s" does not exist.',
						esc_html( $entry )
					),
					esc_html( ADVADS_VERSION )
				);
				continue;
			}

			( new $entry() )->boot();
		}
	}
}
