<?php
/**
 * Placements types manager..
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.47.0
 */

namespace AdvancedAds\Placements;

use AdvancedAds\Abstracts\Types;
use AdvancedAds\Placements\Types\Footer;
use AdvancedAds\Placements\Types\Header;
use AdvancedAds\Placements\Types\Content;
use AdvancedAds\Placements\Types\Unknown;
use AdvancedAds\Interfaces\Placement_Type;
use AdvancedAds\Placements\Types\Standard;
use AdvancedAds\Placements\Types\After_Content;
use AdvancedAds\Placements\Types\Before_Content;
use AdvancedAds\Placements\Types\Sidebar_Widget;

defined( 'ABSPATH' ) || exit;

/**
 * Placements Types.
 */
class Placement_Types extends Types {

	/**
	 * Hook to filter types.
	 *
	 * @var string
	 */
	protected $hook = 'advanced-ads-placement-types';

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
	protected $type_interface = Placement_Type::class;

	/**
	 * Register custom types.
	 *
	 * @return void
	 */
	public function register_types(): void {
		parent::register_types();

		$this->sort_types();
	}

	/**
	 * Register default types.
	 *
	 * @return void
	 */
	protected function register_default_types(): void {
		$this->register_type( After_Content::class );
		$this->register_type( Before_Content::class );
		$this->register_type( Content::class );
		$this->register_type( Footer::class );
		$this->register_type( Header::class );
		$this->register_type( Sidebar_Widget::class );
		$this->register_type( Standard::class );
	}

	/**
	 * Get type dropdown options
	 *
	 * @return array
	 */
	public function get_dropdown_options(): array {
		$options = [];

		foreach ( $this->get_types() as $type ) {
			$options[ $type->get_id() ] = $type->get_title();
		}

		asort( $options );

		return $options;
	}

	/**
	 * Sort screens by order.
	 *
	 * @return void
	 */
	private function sort_types(): void {
		uasort(
			$this->types,
			static function ( $a, $b ) {
				$order_a = $a->get_order();
				$order_b = $b->get_order();

				if ( $order_a === $order_b ) {
					return 0;
				}

				return ( $order_a < $order_b ) ? -1 : 1;
			}
		);
	}
}
