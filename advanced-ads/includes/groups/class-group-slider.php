<?php
/**
 * This class is responsible to model slider groups.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.48.0
 */

namespace AdvancedAds\Groups;

use AdvancedAds\Abstracts\Group;
use AdvancedAds\Utilities\Conditional;
use AdvancedAds\Interfaces\Group_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Slider group.
 */
class Group_Slider extends Group implements Group_Interface {

	/**
	 * Get delay.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return int
	 */
	public function get_delay( $context = 'view' ): int {
		return $this->get_prop( 'delay', $context );
	}

	/**
	 * Is grid display random.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return bool
	 */
	public function is_random( $context = 'view' ): bool {
		return $this->get_prop( 'random', $context );
	}

	/**
	 * Get ordered ids of the ads that belong to the group
	 *
	 * @return array
	 */
	public function get_ordered_ad_ids() {
		$ordered_ad_ids = [];
		$weights        = $this->get_ad_weights();

		$ordered_ad_ids = ( $this->is_random() || Conditional::is_amp() )
			? $this->shuffle_ads()
			: array_keys( $weights );

		return apply_filters( 'advanced-ads-group-output-ad-ids', $ordered_ad_ids, $this->get_type(), $this->get_ads(), $weights, $this );
	}
}
