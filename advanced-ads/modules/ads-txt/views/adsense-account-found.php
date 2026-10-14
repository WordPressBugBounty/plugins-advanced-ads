<?php
/**
 * AdSense account found view.
 *
 * @package AdvancedAds\Modules\AdsTxt
 */

use AdvancedAds\Modules\AdsTxt\Utils;

$adsense_line = Utils::get_adsense_data(); ?>
<p>
	<span>
		<?php
		echo wp_kses_post(
			sprintf(
				/* translators: %s: The adsense line added automatically by Advanced Ads. */
				__( 'The following line will be added automatically because you connected your AdSense account with Advanced Ads: %s', 'advanced-ads' ),
				'<br><code>' . $adsense_line . '</code>'
			)
		);
		?>
	</span>
</p>
