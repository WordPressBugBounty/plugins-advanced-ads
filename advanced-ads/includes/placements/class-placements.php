<?php
/**
 * This class is responsible to hold all the Placements functionality.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.47.0
 */

namespace AdvancedAds\Placements;

defined( 'ABSPATH' ) || exit;

/**
 * Placements.
 */
class Placements {

	/**
	 * Hold factory instance
	 *
	 * @var Placement_Factory
	 */
	public $factory;

	/**
	 * Hold repository instance
	 *
	 * @var Placement_Repository
	 */
	public $repository;

	/**
	 * Hold types manager
	 *
	 * @var Placement_Types
	 */
	public $types;

	/**
	 * Construct placement services.
	 */
	public function __construct() {
		$this->factory    = new Placement_Factory();
		$this->types      = new Placement_Types();
		$this->repository = new Placement_Repository();

		$this->types->hooks();
	}
}
