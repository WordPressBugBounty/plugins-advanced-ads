<?php
/**
 * Ad block finder module bootstrap.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

class_exists( 'Advanced_Ads', false ) || exit();

if ( ! is_admin() ) {
	( new Advanced_Ads_Adblock_Finder() );
} elseif ( ! wp_doing_ajax() ) {
	( new Advanced_Ads_Adblock_Finder_Admin() );
}
