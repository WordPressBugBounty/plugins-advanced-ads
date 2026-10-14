<?php // phpcs:ignore WordPress.Files.FileName

use AdvancedAds\Modules\Privacy\Utils;

/**
 * Handles Advanced Ads privacy settings.
 *
 * @deprecated 1.12.0
 */
class Advanced_Ads_Privacy {

	const OPTION_KEY = 'advanced-ads-privacy';

	/**
	 * Module options.
	 *
	 * @var array<string, mixed>|null
	 */
	private $options;

	/**
	 * Get the instance of the class.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		static $instance = null;
		if ( null === $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Return module options
	 *
	 * @deprecated 1.12.0 Use wp_advads_get_setting() instead.
	 *
	 * @return array<string, mixed>
	 */
	public function options() {
		if ( ! isset( $this->options ) ) {
			$this->options            = get_option( 'advanced-ads-privacy', [] );
			$this->options['enabled'] = wp_advads_is_module_active( 'privacy' );
			if ( $this->options['enabled'] && empty( $this->options['consent-method'] ) ) {
				$this->options['enabled'] = false;
			}
		}

		return $this->options;
	}

	/**
	 * Get the consent method.
	 *
	 * @return string
	 */
	public function get_state() {
		return Utils::parse_state();
	}
}
