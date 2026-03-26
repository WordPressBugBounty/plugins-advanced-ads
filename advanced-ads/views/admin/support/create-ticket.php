<?php
/**
 * Create ticket modal.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

?>
<div class="advads-form">
	<div class="advads-field">
		<div class="advads-field-label">
			<label for="email"><?php esc_html_e( 'Email address', 'advanced-ads' ); ?>*</label>
		</div>
		<div class="advads-field-input">
			<input
				type="email"
				name="email"
				id="email"
				class="regular-text"
				required
				pattern="^[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,63}$"
				placeholder="<?php esc_attr_e( 'Enter your email address', 'advanced-ads' ); ?>"
				value="<?php echo esc_attr( get_current_user_id() ? wp_get_current_user()->user_email : '' ); ?>"
			>
		</div>
	</div>

	<div class="advads-field">
		<div class="advads-field-label">
			<label for="subject"><?php esc_html_e( 'Subject', 'advanced-ads' ); ?>*</label>
		</div>
		<div class="advads-field-input">
			<input type="text" name="subject" id="subject" class="regular-text" required placeholder="<?php esc_attr_e( 'Enter the subject of your issue', 'advanced-ads' ); ?>">
		</div>
	</div>

	<div class="advads-field">
		<div class="advads-field-label">
			<label for="message"><?php esc_html_e( 'Your Message', 'advanced-ads' ); ?>*</label>
		</div>
		<div class="advads-field-input">
			<textarea name="message" id="message" class="large-text" required placeholder="<?php esc_attr_e( 'Enter the message of your issue', 'advanced-ads' ); ?>" rows="5"></textarea>
		</div>
	</div>

	<div class="advads-field">
		<div class="advads-field-label">
			<label for="attachments"><?php esc_html_e( 'Attachments', 'advanced-ads' ); ?></label>
		</div>
		<div id="attachments-container" class="advads-field-input advads-file-uploader">
			<div id="attachments-drop-zone" class="dropzone">

				<svg xmlns="http://www.w3.org/2000/svg" class="size-8 mb-4" viewBox="0 0 30 30" fill="none">
					<path d="M26.25 18.75V23.75C26.25 24.413 25.9866 25.0489 25.5178 25.5178C25.0489 25.9866 24.413 26.25 23.75 26.25H6.25C5.58696 26.25 4.95107 25.9866 4.48223 25.5178C4.01339 25.0489 3.75 24.413 3.75 23.75V18.75M21.25 10L15 3.75M15 3.75L8.75 10M15 3.75V18.75" stroke="#1E1E1E" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
				</svg>

				<p class="text-sm mt-0 mb-2">
					<?php
					printf(
						/* translators: 1: strong tag with class text-blue-500, 2: closing strong tag */
						esc_html__( 'Drag & drop or %1$sclick to upload%2$s', 'advanced-ads' ),
						'<span class="text-blue-500">',
						'</span>'
					);
					?>
				</p>

				<input id="attachments" type="file" multiple data-max-files="5" data-max-file-size="5" data-allowed-ext="jpg,jpeg,png,gif,pdf,txt,docx,log" />
			</div>
		</div>
	</div>

	<div class="advads-field">
		<label for="terms" class="flex items-start gap-2">
			<input type="checkbox" name="terms" id="terms" class="mt-0.5" required>
			<span>
				<?php
				echo wp_kses_post(
					sprintf(
						/* translators: 1: opening a tag, 2: opening a tag, 3: closing a tag */
						__( 'By submitting this form, I accept the %1$sTerms%3$s and %2$sPrivacy Policy%3$s and consent that my personal information in this form will be stored and processed for the purposes of providing support.', 'advanced-ads' ),
						'<a href="https://wpadvancedads.com/terms/" target="_blank">',
						'<a href="https://wpadvancedads.com/privacy-policy/" target="_blank">',
						'</a>'
					)
				);
				?>
			</span>
		</label>
	</div>

	<input type="hidden" name="domain" value="<?php echo esc_attr( get_site_url() ); ?>">
	<input type="hidden" name="php_version" value="<?php echo esc_attr( phpversion() ); ?>">
	<input type="hidden" name="wp_version" value="<?php echo esc_attr( get_bloginfo( 'version' ) ); ?>">
</div>
