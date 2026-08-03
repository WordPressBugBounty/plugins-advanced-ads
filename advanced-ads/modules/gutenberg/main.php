<?php
/**
 * Gutenberg module bootstrap.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

if ( class_exists( 'Advanced_Ads', false ) ) {
	Advanced_Ads_Gutenberg::get_instance();
}
