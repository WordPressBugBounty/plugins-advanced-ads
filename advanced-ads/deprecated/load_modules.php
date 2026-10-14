<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
use AdvancedAds\Autoloader;

/**
 * Class Advanced_Ads_ModuleLoader
 *
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
 * phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
 */
final class Advanced_Ads_ModuleLoader {

	/**
	 * Hold modules instances.
	 *
	 * @var array<string, string>
	 */
	protected static $modules = [];

	/**
	 * Module loader options:
	 * - array 'disabled': Pretty name by (module) dirname
	 *
	 * @param string               $path    Path to modules.
	 * @param array<string, mixed> $options Optional. Module loader options.
	 */
	public static function loadModules( $path, $options = [] ) {
		$loader = Autoloader::get()->get_autoloader();

		$isAdmin         = is_admin();
		$disabledModules = (array) ( $options['disabled'] ?? [] );

		// Iterate modules.
		foreach ( glob( $path . '*/main.php' ) as $module ) {
			$modulePath = dirname( $module );
			$moduleName = basename( $modulePath );

			// Configuration is enabled by default (localisation, autoloading and other undemanding stuff).
			if ( file_exists( $modulePath . '/config.php' ) ) {
				$config = require $modulePath . '/config.php';
				// Append autoload classmap.
				if ( isset( $config['classmap'] ) && is_array( $config['classmap'] ) ) {
					$loader->addClassmap( $config['classmap'] );
				}
			}

			// Admin is enabled by default.
			if ( $isAdmin && is_readable( $modulePath . '/admin.php' ) ) {
				include $modulePath . '/admin.php'; // Do not care if this fails.
			}

			// Skip if disabled.
			if ( isset( $disabledModules[ $moduleName ] ) ) {
				continue;
			}

			self::$modules[ $moduleName ] = $modulePath;
		}

		// Load modules.
		foreach ( self::$modules as $name => $path ) {
			require_once $path . '/main.php';
		}
	}
}
