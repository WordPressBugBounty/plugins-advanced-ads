<?php
/**
 * This class is responsible to hold all the Ads functionality.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.48.0
 */

namespace AdvancedAds\Ads;

defined( 'ABSPATH' ) || exit;

/**
 * Ads Ads.
 */
class Ads {

	/**
	 * Hold factory instance
	 *
	 * @var Ad_Factory
	 */
	public $factory;

	/**
	 * Hold repository instance
	 *
	 * @var Ad_Repository
	 */
	public $repository;

	/**
	 * Hold types manager
	 *
	 * @var Ad_Types
	 */
	public $types;

	/**
	 * Construct ads services.
	 */
	public function __construct() {
		$this->factory    = new Ad_Factory();
		$this->types      = new Ad_Types();
		$this->repository = new Ad_Repository();

		$this->types->hooks();
	}
}
