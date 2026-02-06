<?php
/**
 * The interface to provide a contract for Entities.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.48.0
 */

namespace AdvancedAds\Interfaces;

defined( 'ABSPATH' ) || exit;

/**
 * Interfaces Entity.
 */
interface Entity_Interface {

	/**
	 * Prepare frontend output.
	 *
	 * @return string
	 */
	public function generate_html(): string;

	/**
	 * Get the wrapper attributes.
	 *
	 * @return array
	 */
	public function get_wrapper_attributes(): array;

	/**
	 * Prepare output.
	 *
	 * @return string
	 */
	public function prepare_output(): string;
}
