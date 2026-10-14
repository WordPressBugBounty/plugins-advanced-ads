<?php
/**
 * Plugin deactivation feedback popup.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 * @since   1.47.0
 */

$logo_url = ADVADS_BASE_URL . 'assets/img/logo.svg';

?>
<div class="advads-page">
	<div id="advanced-ads-feedback-overlay" class="fixed inset-0 z-[100000] items-center justify-center overflow-hidden bg-black/50 p-4" style="display: none;" hidden>
		<div id="advanced-ads-feedback-content" class="relative w-full max-w-md overflow-hidden rounded-lg bg-white shadow-xl" role="dialog" aria-modal="true" aria-labelledby="advanced-ads-feedback-title">
			<div class="flex items-center justify-between gap-3 px-6 pt-6 pb-2">
				<div class="flex items-center gap-2.5">
					<img class="size-6 shrink-0" src="<?php echo esc_url( $logo_url ); ?>" alt="" width="24" height="24" />
					<h2 id="advanced-ads-feedback-title" class="m-0 text-base font-semibold text-gray-900">
						<?php esc_html_e( 'Quick Feedback', 'advanced-ads' ); ?>
					</h2>
				</div>
				<button
					type="button"
					id="advanced-ads-feedback-overlay-close-button"
					class="flex size-7 cursor-pointer items-center justify-center rounded-full border-0 bg-transparent p-0 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-wordpress focus-visible:ring-offset-2"
					aria-label="<?php esc_attr_e( 'Close', 'advanced-ads' ); ?>"
				>
					<svg class="size-4" viewBox="0 0 16 16" fill="none" aria-hidden="true">
						<path d="M4 4l8 8M12 4l-8 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
					</svg>
				</button>
			</div>

			<form class="m-0" action="" method="post">
				<div class="px-6 pt-2 pb-1">
					<p class="mb-5 text-sm font-semibold leading-snug text-gray-900">
						<?php esc_html_e( 'If you have a moment, please share why you are deactivating Advanced Ads:', 'advanced-ads' ); ?>
					</p>

					<div class="flex flex-col gap-1">
						<div class="advads-feedback-option">
							<label class="flex cursor-pointer items-center gap-3 rounded-sm px-2 py-2.5 text-sm font-normal text-gray-700 transition-colors hover:bg-gray-50">
								<input class="size-4 shrink-0 accent-wordpress" type="radio" name="advanced_ads_disable_reason" value="get help" />
								<span><?php esc_html_e( 'I have a problem, a question or need help.', 'advanced-ads' ); ?></span>
							</label>
							<div class="advads-feedback-followup mb-2 ml-9 hidden flex-col gap-2.5" hidden>
								<textarea
									class="advanced_ads_disable_help_text w-full resize-y rounded-sm border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-xs focus:border-wordpress focus:outline-hidden focus:ring-1 focus:ring-wordpress"
									name="advanced_ads_disable_text[]"
									rows="2"
									aria-label="<?php esc_attr_e( 'Please let us know how we can help', 'advanced-ads' ); ?>"
									placeholder="<?php esc_attr_e( 'Please let us know how we can help', 'advanced-ads' ); ?>"
								></textarea>
								<?php if ( $email ) : ?>
									<label class="advanced_ads_disable_reply flex flex-col gap-1 text-sm text-gray-600">
										<span><?php esc_html_e( 'Send me free help to', 'advanced-ads' ); ?></span>
										<input
											class="w-full rounded-sm border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-xs focus:border-wordpress focus:outline-hidden focus:ring-1 focus:ring-wordpress"
											type="email"
											name="advanced_ads_disable_reply_email"
											value="<?php echo esc_attr( $email ); ?>"
										/>
									</label>
								<?php endif; ?>
							</div>
						</div>

						<div class="advads-feedback-option">
							<label class="flex cursor-pointer items-center gap-3 rounded-sm px-2 py-2.5 text-sm font-normal text-gray-700 transition-colors hover:bg-gray-50">
								<input class="size-4 shrink-0 accent-wordpress" type="radio" name="advanced_ads_disable_reason" value="ads not showing up" />
								<span><?php esc_html_e( 'Ads are not showing up', 'advanced-ads' ); ?></span>
							</label>
						</div>

						<div class="advads-feedback-option">
							<label class="flex cursor-pointer items-center gap-3 rounded-sm px-2 py-2.5 text-sm font-normal text-gray-700 transition-colors hover:bg-gray-50">
								<input class="size-4 shrink-0 accent-wordpress" type="radio" name="advanced_ads_disable_reason" value="temporary" />
								<span><?php esc_html_e( 'It is only temporary', 'advanced-ads' ); ?></span>
							</label>
						</div>

						<div class="advads-feedback-option">
							<label class="flex cursor-pointer items-center gap-3 rounded-sm px-2 py-2.5 text-sm font-normal text-gray-700 transition-colors hover:bg-gray-50">
								<input class="size-4 shrink-0 accent-wordpress" type="radio" name="advanced_ads_disable_reason" value="missing feature" />
								<span><?php esc_html_e( 'I miss a feature', 'advanced-ads' ); ?></span>
							</label>
							<div class="advads-feedback-followup mb-2 ml-9 hidden flex-col gap-2.5" hidden>
								<input
									class="w-full rounded-sm border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-xs focus:border-wordpress focus:outline-hidden focus:ring-1 focus:ring-wordpress"
									type="text"
									name="advanced_ads_disable_text[]"
									value=""
									aria-label="<?php esc_attr_e( 'Which one?', 'advanced-ads' ); ?>"
									placeholder="<?php esc_attr_e( 'Which one?', 'advanced-ads' ); ?>"
								/>
							</div>
						</div>

						<div class="advads-feedback-option">
							<label class="flex cursor-pointer items-center gap-3 rounded-sm px-2 py-2.5 text-sm font-normal text-gray-700 transition-colors hover:bg-gray-50">
								<input class="size-4 shrink-0 accent-wordpress" type="radio" name="advanced_ads_disable_reason" value="stopped showing ads" />
								<span><?php esc_html_e( 'I stopped using ads on my site.', 'advanced-ads' ); ?></span>
							</label>
						</div>

						<div class="advads-feedback-option">
							<label class="flex cursor-pointer items-center gap-3 rounded-sm px-2 py-2.5 text-sm font-normal text-gray-700 transition-colors hover:bg-gray-50">
								<input class="size-4 shrink-0 accent-wordpress" type="radio" name="advanced_ads_disable_reason" value="other plugin" />
								<span><?php esc_html_e( 'I switched to another plugin', 'advanced-ads' ); ?></span>
							</label>
						</div>
					</div>
				</div>

				<?php if ( $from ) : ?>
					<input type="hidden" name="advanced_ads_disable_from" value="<?php echo esc_attr( $from ); ?>"/>
				<?php endif; ?>
				<?php wp_nonce_field( 'advanced_ads_disable_form', 'advanced_ads_disable_form_nonce' ); ?>

				<p
					id="advanced-ads-feedback-error"
					class="mx-6 mb-0 mt-3 hidden rounded-sm border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
					role="alert"
					hidden
					data-default-message="<?php echo esc_attr__( 'Could not send feedback. Please try again or deactivate anyway.', 'advanced-ads' ); ?>"
				></p>

				<div class="mt-4 flex items-center justify-between gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4">
					<button
						type="button"
						class="advanced-ads-feedback-submit cursor-pointer rounded-sm border border-wordpress bg-wordpress px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-wordpress/90 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-wordpress focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
						name="advanced_ads_disable_submit"
					>
						<?php esc_html_e( 'Submit & Deactivate', 'advanced-ads' ); ?>
					</button>
					<button
						type="button"
						class="advanced-ads-feedback-only-deactivate cursor-pointer rounded-sm border border-wordpress bg-white px-4 py-2 text-sm font-medium text-wordpress transition-colors hover:bg-wordpress/10 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-wordpress focus-visible:ring-offset-2"
					>
						<?php esc_html_e( 'Skip & Deactivate', 'advanced-ads' ); ?>
					</button>
				</div>
			</form>

			<div id="advanced-ads-feedback-after-submit" class="px-6 py-10 text-center" hidden>
				<h2 id="advanced-ads-feedback-after-submit-waiting" class="m-0 mb-3 text-base font-semibold text-gray-900" hidden>
					<?php esc_html_e( 'Thanks for submitting your feedback. I will reply within 24 hours on working days.', 'advanced-ads' ); ?>
				</h2>
				<h2 id="advanced-ads-feedback-after-submit-goodbye" class="m-0 mb-3 text-base font-semibold text-gray-900" hidden>
				<?php
				printf(
					/* translators: %s is the title of the website. */
					wp_kses_post( __( 'All the best to you and <em>%s</em>.', 'advanced-ads' ) ),
					esc_html( get_bloginfo( 'name' ) )
				);
				?>
				</h2>
				<p id="advanced-ads-feedback-after-submit-disabling-plugin" class="m-0 text-sm text-gray-600" hidden>
					<?php esc_html_e( 'Disabling the plugin now…', 'advanced-ads' ); ?>
				</p>
			</div>
		</div>
	</div>
</div>
