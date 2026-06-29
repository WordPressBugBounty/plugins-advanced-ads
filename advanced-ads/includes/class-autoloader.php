<?php
/**
 * The class is responsible for locating and loading the autoloader file used in the plugin.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.47.0
 */

namespace AdvancedAds;

defined( 'ABSPATH' ) || exit;

/**
 * Autoloader.
 */
class Autoloader {

	/**
	 * Hold autoloader.
	 *
	 * @var mixed
	 */
	private $autoloader;

	/**
	 * Main instance
	 *
	 * Ensure only one instance is loaded or can be loaded.
	 *
	 * @return Autoloader
	 */
	public static function get(): Autoloader {
		static $instance;

		if ( null === $instance ) {
			$instance = new Autoloader();
		}

		return $instance;
	}

	/**
	 * Get hold autoloader.
	 *
	 * @return mixed
	 */
	public function get_autoloader() {
		return $this->autoloader;
	}

	/**
	 * Runs this initializer.
	 *
	 * @return void
	 */
	public function initialize(): void {
		$locate = $this->locate();

		if ( ! $locate ) {
			add_action( 'admin_notices', [ $this, 'missing_autoloader' ] );
			return;
		}

		$this->autoloader = require $locate;
		$this->register_wordpress();
	}

	/**
	 * Locate the autoload file
	 *
	 * This function searches for the autoload file in the packages directory and vendor directory.
	 *
	 * @return bool|string
	 */
	private function locate() {
		$directory = dirname( ADVADS_FILE );
		$packages  = $directory . '/packages/autoload.php';
		$vendors   = $directory . '/vendor/autoload.php';
		$is_debug  = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || 'local' === wp_get_environment_type();

		if ( is_readable( $packages ) && ( ! $is_debug || ! is_readable( $vendors ) ) ) {
			return $packages;
		}

		if ( is_readable( $vendors ) ) {
			return $vendors;
		}

		return false;
	}

	/**
	 * Add WordPress classes to map
	 *
	 * @return void
	 */
	private function register_wordpress(): void {
		$this->autoloader->addClassmap(
			[
				'WP_List_Table'       => ABSPATH . 'wp-admin/includes/class-wp-list-table.php',
				'WP_Terms_List_Table' => ABSPATH . 'wp-admin/includes/class-wp-terms-list-table.php',
			]
		);
	}

	/**
	 * If the autoloader is missing, add an admin notice.
	 *
	 * @return void
	 */
	protected function missing_autoloader(): void {
		?>
		<div class="notice notice-error">
			<p>
				<?php
				printf(
					/* translators: 1: is a link to a support document. 2: closing link */
					esc_html__( 'Your installation of Advanced Ads is incomplete. If you installed Advanced Ads from GitHub, %1$s please refer to this document%2$s to set up your development environment.', 'advanced-ads' ),
					'<a href="' . esc_url( 'https://github.com/advanced-ads/advanced-ads/wiki/How-to-set-up-development-environment' ) . '" target="_blank" rel="noopener noreferrer">',
					'</a>'
				);
				?>
			</p>
		</div>
		<?php
	}
}
