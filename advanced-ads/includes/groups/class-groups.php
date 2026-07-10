<?php
/**
 * This class is responsible to hold all the Groups functionality.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.47.0
 */

namespace AdvancedAds\Groups;

defined( 'ABSPATH' ) || exit;

/**
 * Groups.
 */
class Groups {

	/**
	 * Hold factory instance
	 *
	 * @var Group_Factory
	 */
	public $factory;

	/**
	 * Hold repository instance
	 *
	 * @var Group_Repository
	 */
	public $repository;

	/**
	 * Hold types manager
	 *
	 * @var Group_Types
	 */
	public $types;

	/**
	 * Construct group services.
	 */
	public function __construct() {
		$this->factory    = new Group_Factory();
		$this->types      = new Group_Types();
		$this->repository = new Group_Repository();

		$this->types->hooks();
	}
}
