<?php
/**
 * Fluent settings registry: tabs, groups, fields.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.0.0
 */

namespace AdvancedAds\Settings;

use AdvancedAds\Framework\Interfaces\Initializer_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Registry class.
 */
class Registry implements Initializer_Interface {

	/**
	 * Tab instances, keyed by tab id, in registration order.
	 *
	 * @var array<string, Tab>
	 */
	private $tabs = [];

	/**
	 * Memoized get_fields() result — null until computed. See get_fields() for why this is safe to cache for the registry's whole lifetime.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private $fields_cache = null;

	/**
	 * Whether tab files and the register action have already run for this instance.
	 *
	 * @var bool
	 */
	private $initialized = false;

	/**
	 * Get/save layer for this registry's option stores.
	 *
	 * @var Store
	 */
	public $store;

	/**
	 * Context layer.
	 *
	 * @var Context
	 */
	private $context;

	/**
	 * Registry constructor.
	 */
	public function __construct() {
		$this->store   = new Store();
		$this->context = new Context();
	}

	/**
	 * Runs this initializer — synchronously, at plugin-file-load time. Defers the real work to `plugins_loaded`, which always completes before `init` on every request type, so a module's own code (once conditionally booted) can safely use `init` itself with no ordering risk, and Settings\Registry (on `init`) is always guaranteed a fully-resolved module registry.
	 *
	 * @return void
	 */
	public function initialize(): void {
		// Deferred to `plugins_loaded` priority 20 — after Modules\Registry's own
		// `plugins_loaded` hooks() (default priority 10) boots every active module —
		// so this is appended to $this->integrations after any module that registers
		// its own settings integration during boot(). Registry::hooks() fires
		// `advanced-ads-register-settings` synchronously, so whatever is earlier in
		// that list (i.e. resolved earlier within load_integrations() on `init`) is
		// guaranteed to have already added its listener before that action fires.
		add_action(
			'plugins_loaded',
			function (): void {
				add_action( 'init', [ $this, 'init' ], -99 );
			},
			20
		);
	}

	/**
	 * Initialize the registry.
	 *
	 * @return void
	 */
	public function init(): void {
		if ( $this->initialized ) {
			return;
		}

		$this->initialized = true;

		$this->load_tab_files();

		/**
		 * Register settings fields against this registry — bundled modules (Privacy) own their fields from their own folder rather than being required from includes/settings/tabs/, and separate add-on plugins (Pro, Tracking) do the same when active, appending to the tab shell load_tab_files() already registered for them (Registry::tab() resumes by id), rather than this plugin requiring anything from their folder.
		 *
		 * @param Registry $registry The settings registry.
		 */
		do_action( 'advanced-ads-register-settings', $this );
	}

	/**
	 * Require each core tab file with `$registry` in scope.
	 *
	 * @return void
	 */
	private function load_tab_files(): void {
		$registry = $this;

		require __DIR__ . '/tabs/modules.php';
		require __DIR__ . '/tabs/general.php';
		require __DIR__ . '/tabs/pro.php';
		require __DIR__ . '/tabs/tracking.php';
	}

	/**
	 * Start (or resume) a tab. Hold onto the returned Tab and call ->group() directly on it to keep adding to the same tab across multiple statements (e.g. an add-on plugin resuming a tab shell another file already registered).
	 *
	 * @param string               $id    Tab id.
	 * @param string               $label Tab label.
	 * @param array<string, mixed> $args  Optional: icon, order.
	 *
	 * @return Tab
	 */
	public function tab( string $id, string $label, array $args = [] ): Tab {
		if ( isset( $this->tabs[ $id ] ) ) {
			return $this->tabs[ $id ];
		}

		$tab               = new Tab( $id, $label, $args, $this );
		$this->tabs[ $id ] = $tab;

		return $tab;
	}

	/**
	 * Invalidate the memoized get_fields() cache. Every Tab (and, from there, every Group) this registry creates holds a reference back to it, so Group::field() can call this directly regardless of how long a caller holds onto that Tab/Group and keeps adding to it — see Group's $registry.
	 *
	 * @return void
	 */
	public function invalidate_cache(): void {
		$this->fields_cache = null;
	}

	/**
	 * Look up a tab by id.
	 *
	 * @param string $id Tab id.
	 *
	 * @return Tab|null
	 */
	public function get_tab( string $id ): ?Tab {
		return $this->tabs[ $id ] ?? null;
	}

	/**
	 * Look up a top-level group by id, across every tab.
	 *
	 * @param string $id Group id.
	 *
	 * @return Group|null
	 */
	public function get_group( string $id ): ?Group {
		foreach ( $this->tabs as $tab ) {
			$group = $tab->get_group( $id );
			if ( null !== $group ) {
				return $group;
			}
		}

		return null;
	}

	/**
	 * Register a read-only system-level context value.
	 *
	 * @param string   $key      Context key.
	 * @param callable $resolver Zero-arg callable returning the value.
	 *
	 * @return self
	 */
	public function register_context( string $key, callable $resolver ): self {
		$this->context->register( $key, $resolver );

		return $this;
	}

	/**
	 * Assemble the tabs/groups/fields schema (no values).
	 *
	 * @return array{tabs: array<int, array<string, mixed>>}
	 */
	public function to_schema(): array {
		$tabs = array_map( static fn( Tab $tab ) => $tab->to_array(), $this->tabs );

		usort( $tabs, fn( $a, $b ) => $a['order'] <=> $b['order'] );

		return [ 'tabs' => $tabs ];
	}

	/**
	 * Read every registered field's current value from its store.
	 *
	 * @return array<string, mixed>
	 */
	public function get_values(): array {
		$values = [];

		foreach ( array_keys( $this->get_fields() ) as $id ) {
			$values[ $id ] = $this->get_value( $id );
		}

		return $values;
	}

	/**
	 * Resolve every registered context value.
	 *
	 * @return array<string, mixed>
	 */
	public function get_context(): array {
		return $this->context->resolve();
	}

	/**
	 * Read a single registered field's current value from its store.
	 *
	 * Unregistered stores still run `value_callback` with null so locked Pro fields can default without free owning that store.
	 *
	 * @param string $id Field id.
	 *
	 * @return mixed Null if no field is registered with this id.
	 */
	public function get_value( string $id ) {
		$field = $this->get_field( $id );
		if ( null === $field ) {
			return null;
		}

		$callback = $field['value_callback'] ?? null;
		$value    = $this->store->get( $field['store'], $field['key'], $callback );

		if ( null === $value && is_callable( $callback ) ) {
			return $callback( null );
		}

		return $value;
	}

	/**
	 * Look up a leaf field definition by id (including nested group children).
	 *
	 * @param string $id Field id.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get_field( string $id ): ?array {
		return $this->get_fields()[ $id ] ?? null;
	}

	/**
	 * Every leaf field keyed by id, memoized until Group::field() invalidates via the registry back-reference.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_fields(): array {
		if ( null !== $this->fields_cache ) {
			return $this->fields_cache;
		}

		$fields = [];

		foreach ( $this->tabs as $tab ) {
			$tab->each_field(
				static function ( $field ) use ( &$fields ) {
					$fields[ $field['id'] ] = $field;
				}
			);
		}

		$this->fields_cache = $fields;

		return $fields;
	}
}
