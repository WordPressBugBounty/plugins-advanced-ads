<?php
/**
 * Group types manager.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.47.0
 */

namespace AdvancedAds\Groups;

use AdvancedAds\Abstracts\Types;
use AdvancedAds\Groups\Types\Grid;
use AdvancedAds\Groups\Types\Slider;
use AdvancedAds\Groups\Types\Ordered;
use AdvancedAds\Groups\Types\Unknown;
use AdvancedAds\Groups\Types\Standard;
use AdvancedAds\Interfaces\Group_Type;

defined( 'ABSPATH' ) || exit;

/**
 * Group Types.
 */
class Group_Types extends Types {

	/**
	 * Hook to filter types.
	 *
	 * @var string
	 */
	protected $hook = 'advanced-ads-group-types';

	/**
	 * Class for unknown type.
	 *
	 * @var string
	 */
	protected $type_unknown = Unknown::class;

	/**
	 * Type interface to check.
	 *
	 * @var string
	 */
	protected $type_interface = Group_Type::class;

	/**
	 * Built-in group type classes.
	 *
	 * @return string[]
	 */
	protected function default_type_classes(): array {
		return [
			Standard::class,
			Ordered::class,
			Grid::class,
			Slider::class,
		];
	}
}
