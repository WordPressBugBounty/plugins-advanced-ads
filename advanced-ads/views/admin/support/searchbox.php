<?php
/**
 * Searchbox for support page.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

?>
<div class="advads-card text-center">
	<h2 class="m-0 text-2xl"><?php esc_html_e( 'How can we help?', 'advanced-ads' ); ?></h2>
	<p><?php esc_html_e( 'Search for a topic or browse our documentation.', 'advanced-ads' ); ?></p>

	<div class="advads-searchbox">
		<svg xmlns="http://www.w3.org/2000/svg" class="icon" viewBox="0 0 21 21" fill="none">
			<path d="M19.25 19.25L14.9 14.9M17.25 9.25C17.25 13.6683 13.6683 17.25 9.25 17.25C4.83172 17.25 1.25 13.6683 1.25 9.25C1.25 4.83172 4.83172 1.25 9.25 1.25C13.6683 1.25 17.25 4.83172 17.25 9.25Z" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
		</svg>
		<input
			type="text"
			class="input"
			id="advads-support-search"
			placeholder="<?php esc_attr_e( 'Search help articles, guides and FAQs', 'advanced-ads' ); ?>"
			autocomplete="off"
			role="combobox"
			aria-autocomplete="list"
			aria-haspopup="true"
			aria-expanded="false"
			aria-controls="suggestions-list"
		>
		<ul id="suggestions-list" class="suggestions-list">
			<li>
				<a href="#">
					<?php esc_html_e( 'How to create a new ad', 'advanced-ads' ); ?>
				</a>
			</li>
		</ul>
	</div>
</div>
