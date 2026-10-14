<?php
/**
 * The plugin bootstrap.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.47.0
 */

namespace AdvancedAds;

use AdvancedAds\Ads\Ads;
use AdvancedAds\Groups\Groups;
use AdvancedAds\Installation\Install;
use AdvancedAds\Placements\Placements;
use AdvancedAds\Rest\Licenses;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin. Containers:
 *
 * @property Shortcodes           $shortcodes        Shortcodes handler.
 * @property Assets_Registry      $registry          Assets registry.
 * @property Framework\JSON       $json              JSON handler.
 * @property Admin\Admin_Menu     $screens           Admin screens.
 * @property Admin\List_Filters   $list_filters      Filters of the ad list.
 * @property Frontend\Ad_Renderer $renderer          Ads renderer.
 * @property Frontend\Manager     $frontend          Frontend manager.
 * @property Importers\Manager    $importers         Importers manager.
 * @property Settings\Registry    $settings_registry Settings field registry (tabs/sections/fields schema).
 * @property Modules\Registry     $module_registry   Module registry (Modules tab cards + conditional module loading).
 */
class Plugin extends Framework\Loader {

	use Traits\Extras;

	/**
	 * The ads container
	 *
	 * @var Ads
	 */
	public $ads = null;

	/**
	 * The groups container
	 *
	 * @var Groups
	 */
	public $groups = null;

	/**
	 * The placements container
	 *
	 * @var Placements
	 */
	public $placements = null;

	/**
	 * The module registry
	 *
	 * @var Modules\Registry
	 */
	public $module_registry = null;

	/**
	 * Main instance — ensures only one instance is loaded or can be loaded.
	 *
	 * @return Plugin
	 */
	public static function get(): Plugin {
		static $instance;

		if ( null === $instance ) {
			$instance = new Plugin();
			$instance->setup();
		}

		return $instance;
	}

	/**
	 * Get plugin version
	 *
	 * @return string
	 */
	public function get_version(): string {
		return ADVADS_VERSION;
	}

	/**
	 * Bootstrap plugin.
	 *
	 * @return void
	 */
	private function setup(): void {
		$this->define_constants();

		$this->includes_functions();
		$this->includes();
		$this->includes_rest();
		$this->includes_admin();
		$this->includes_frontend();
		$this->includes_deprecated();

		// Public-Facing and Core Functionality.
		\Advanced_Ads::get_instance();

		add_action( 'plugins_loaded', [ $this, 'on_plugins_loaded' ], -1 );
		add_action( 'widgets_init', [ $this, 'register_widgets' ] );

		// Load it all.
		$this->load();
	}

	/**
	 * Register the Advanced Ads classic Widget
	 *
	 * @return void
	 */
	public function register_widgets(): void {
		register_widget( '\AdvancedAds\Widget' );
	}

	/**
	 * When WordPress has loaded all plugins, trigger the `advanced-ads-loaded` hook.
	 *
	 * @since 1.47.0
	 *
	 * @return void
	 */
	public function on_plugins_loaded(): void {
		/**
		 * Action trigger after loading finished.
		 *
		 * @since 1.47.0
		 */
		do_action( 'advanced-ads-loaded' );
	}

	/**
	 * Define Advanced Ads constant
	 *
	 * @return void
	 */
	private function define_constants(): void {
		$this->define( 'ADVADS_ABSPATH', dirname( ADVADS_FILE ) . '/' );
		$this->define( 'ADVADS_PLUGIN_BASENAME', plugin_basename( ADVADS_FILE ) );
		$this->define( 'ADVADS_BASE_URL', plugin_dir_url( ADVADS_FILE ) );
		$this->define( 'ADVADS_SLUG', 'advanced-ads' );
		// Shared with wpadvancedads.com Feedback\Rest. Override in wp-config.php for local/staging.
		$this->define( 'ADVADS_DEACTIVATION_FEEDBACK_TOKEN', 'advads-dfb-7c4e9a2b8f1d6e0c3a5b9d2e4f8a1c6b' );

		// Deprecated Constants.
		/**
		 * ADVADS_BASE_PATH
		 *
		 * @deprecated 1.47.0 use ADVADS_ABSPATH now.
		 */
		define( 'ADVADS_BASE_PATH', ADVADS_ABSPATH );

		/**
		 * ADVADS_URL
		 *
		 * @deprecated 1.47.0 Deprecating the constant in favor of using the direct URL to circumvent costly `esc_url` function; please update code accordingly.
		 */
		define( 'ADVADS_URL', 'https://wpadvancedads.com/' );
	}

	/**
	 * Includes core files used in admin and on the frontend.
	 *
	 * @return void
	 */
	private function includes(): void {
		$this->ads             = new Ads();
		$this->groups          = new Groups();
		$this->placements      = new Placements();
		$this->module_registry = new Modules\Registry();
		$this->module_registry->initialize();

		// Common.
		$this->register_initializer( Install::class );
		$this->register_initializer( Settings\Registry::class, 'settings_registry' );
		$this->register_initializer( Entities::class );
		$this->register_integration( Assets_Registry::class, 'registry' );
		$this->register_integration( Framework\JSON::class, 'json', [ 'advancedAds' ] );
		$this->register_integration( Compatibility\Compatibility::class );
		$this->register_integration( Compatibility\Inline_JS::class );
		$this->register_integration( Compatibility\AAWP::class );
		$this->register_integration( Compatibility\Peepso::class );
		$this->register_integration( Post_Data::class );
		$this->register_integration( Cache_Invalidator::class );
		$this->register_integration( Crons\Ads::class );
		$this->register_integration( Crons\Licenses::class );
		$this->register_integration( Crons\Data_Usage::class );
		$this->register_integration( Updater\Addon_Updater::class );
		$this->register_integration( Updater\Update_Guard::class );
		$this->register_integration( Shortcodes::class, 'shortcodes' );
		$this->register_integration( Frontend\Debug_Ads::class );
	}

	/**
	 * Includes files used on the frontend.
	 *
	 * @return void
	 */
	private function includes_frontend(): void {
		// Early bail!!
		if ( is_admin() ) {
			return;
		}

		$this->register_integration( Frontend\Ad_Renderer::class, 'renderer' );
		$this->register_integration( Frontend\Manager::class, 'frontend' );
		$this->register_integration( Frontend\Scripts::class );
	}

	/**
	 * Includes files used in admin.
	 *
	 * @return void
	 */
	private function includes_admin(): void {
		// Early bail!!
		if ( ! is_admin() ) {
			return;
		}

		// App.
		$this->register_integration( Admin\App::class );
		$this->register_integration( Admin\License_Admin_Post::class );

		// Ads.
		$this->register_integration( Admin\Metabox_Ad::class );
		$this->register_integration( Admin\Metabox_Ad_Settings::class );
		$this->register_integration( Admin\Ads\Quick_Bulk_Edit::class );

		// Placements.
		$this->register_integration( Admin\Placements\Ajax::class );
		$this->register_integration( Admin\Placements\Bulk_Edit::class );

		// Entities.
		$this->register_integration( Admin\Page_Quick_Edit::class );
		$this->register_integration( Compatibility\Capability_Manager::class );
		$this->register_initializer( Upgrades::class );
		$this->register_integration( Admin\Plugins\Screen::class );
		$this->register_integration( Admin\Plugins\Deactivation_Feedback::class );
		$this->register_integration( Admin\Admin_Menu::class, 'screens' );
		$this->register_integration( Admin\Admin_Notices::class );
		$this->register_integration( Admin\Assets::class );
		$this->register_integration( Admin\Marketing::class );
		$this->register_integration( Admin\Post_Types::class );
		$this->register_integration( Admin\Screen_Options::class );
		$this->register_integration( Admin\Shortcode_Creator::class );
		$this->register_integration( Admin\TinyMCE::class );
		$this->register_integration( Admin\WordPress_Dashboard::class );
		$this->register_integration( Importers\Manager::class, 'importers' );
		$this->register_integration( Admin\AJAX::class );
		$this->register_integration( Updater\Rollback::class );
		$this->register_integration( Admin\Upsell::class );
		$this->register_integration( Admin\Authors::class );
		$this->register_integration( Admin\Misc::class );
		$this->register_integration( Admin\Post_List::class );

		if ( ! wp_doing_ajax() ) {
			$this->register_integration( Admin\List_Filters::class, 'list_filters' );
		}
	}

	/**
	 * Includes rest api files used in admin and on the frontend.
	 *
	 * @return void
	 */
	private function includes_rest(): void {
		$this->register_route( Rest\Groups::class );
		$this->register_route( Rest\Licenses::class );
		$this->register_route( Rest\Page_Quick_Edit::class );
		$this->register_route( Rest\Placements::class );
		$this->register_route( Rest\Utilities::class );
		$this->register_route( Rest\Whats_New::class );
		$this->register_route( Licenses::class );
		$this->register_route( Settings\Routes::class );
		$this->register_route( Wizard\Routes::class );
	}

	/**
	 * Includes the necessary functions files.
	 *
	 * @return void
	 */
	private function includes_functions(): void {
		require_once ADVADS_ABSPATH . 'includes/functions.php';
		require_once ADVADS_ABSPATH . 'includes/functions-core.php';
		require_once ADVADS_ABSPATH . 'includes/functions-conditional.php';
		require_once ADVADS_ABSPATH . 'includes/functions-ad.php';
		require_once ADVADS_ABSPATH . 'includes/functions-group.php';
		require_once ADVADS_ABSPATH . 'includes/functions-placement.php';
		require_once ADVADS_ABSPATH . 'includes/default-hooks.php';
	}

	/**
	 * Includes deprecated files.
	 *
	 * @return void
	 */
	private function includes_deprecated(): void {
		require_once ADVADS_ABSPATH . 'deprecated/ad_type_abstract.php';
		require_once ADVADS_ABSPATH . 'deprecated/in-content-injector.php';
		require_once ADVADS_ABSPATH . 'deprecated/admin-licenses.php';
		require_once ADVADS_ABSPATH . 'deprecated/filesystem.php';
		require_once ADVADS_ABSPATH . 'deprecated/load_modules.php';
	}
}
