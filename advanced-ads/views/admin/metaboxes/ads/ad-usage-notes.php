<?php
/**
 * Option to enter notes for a given ad
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.48.2
 *
 * @var Ad $ad Ad instance.
 */

use AdvancedAds\Abstracts\Ad;

$description = $ad->get_description();
?>
<label class="label" for="advads-usage-notes"><?php esc_html_e( 'notes', 'advanced-ads' ); ?></label>
<div id="advads-ad-notes">
	<textarea name="advanced_ad[description]" id="advads-usage-notes" rows="3"><?php echo esc_html( $description ); ?></textarea>
</div>
<hr/>
