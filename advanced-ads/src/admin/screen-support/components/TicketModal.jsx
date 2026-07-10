/**
 * External Dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { Modal } from '@wordpress/components';
import { useRef, useState } from '@wordpress/element';

/**
 * Internal Dependencies
 */
import { siteInfo } from '@advancedAds';
import { createToast } from '@advancedAds/utils';
import { FileUploaderField } from '@admin/components/FileUploaderField';

import { base64ToBlob, validate } from '../utils';

const TICKET_API =
	'https://wpadvancedads.com/wp-json/advanced-ads/v1/create-ticket';

export function TicketModal( { closeModal } ) {
	const uploaderRef = useRef( null );

	const [ email, setEmail ] = useState( siteInfo.currentUserEmail ?? '' );
	const [ subject, setSubject ] = useState( '' );
	const [ message, setMessage ] = useState( '' );
	const [ termsAccepted, setTermsAccepted ] = useState( false );

	const [ errors, setErrors ] = useState( {} );
	const [ isSubmitting, setIsSubmitting ] = useState( false );

	const p2 = sprintf(
		/* translators: 1: opening a tag, 2: opening a tag, 3: closing a tag */
		__(
			'By submitting this form, I accept the %1$sTerms%3$s and %2$sPrivacy Policy%3$s and consent that my personal information in this form will be stored and processed for the purposes of providing support.',
			'advanced-ads'
		),
		'<a href="https://wpadvancedads.com/terms/" target="_blank">',
		'<a href="https://wpadvancedads.com/privacy-policy/" target="_blank">',
		'</a>'
	);

	async function handleSubmit( e ) {
		e.preventDefault();

		const { next, trimmedEmail, trimmedSubject, trimmedMessage } = validate(
			email,
			subject,
			message,
			termsAccepted
		);
		setErrors( next );

		if ( Object.keys( next ).length > 0 ) {
			return;
		}

		const uploader = uploaderRef.current;
		const selectedFiles = uploader?.selectedFiles ?? [];
		const hasFiles = selectedFiles.length > 0;

		const basePayload = {
			email: trimmedEmail,
			subject: trimmedSubject,
			message: trimmedMessage,
			terms: termsAccepted ? 'on' : '',
			domain: siteInfo.siteUrl,
			php_version: siteInfo.phpVersion,
			wp_version: siteInfo.wpVersion,
		};

		setIsSubmitting( true );

		let submitBody;
		/** @type {Record<string, string>} */
		const headers = {};

		if ( hasFiles ) {
			const submitFormData = new FormData();
			for ( const [ key, value ] of Object.entries( basePayload ) ) {
				submitFormData.append( key, String( value ) );
			}
			selectedFiles.forEach( ( fileObj ) => {
				const blob = base64ToBlob( fileObj.fileimage );
				submitFormData.append(
					'attachments[]',
					blob,
					fileObj.filename
				);
			} );
			submitBody = submitFormData;
		} else {
			submitBody = JSON.stringify( basePayload );
			headers[ 'Content-Type' ] = 'application/json';
		}

		try {
			const response = await fetch( TICKET_API, {
				method: 'POST',
				body: submitBody,
				headers,
			} );

			if ( response.ok ) {
				uploader?.reset?.();
				closeModal();
				createToast( {
					type: 'muted',
					iconType: 'success',
					title: __( 'Ticket submitted', 'advanced-ads' ),
					message: __(
						'Your ticket has been submitted successfully.',
						'advanced-ads'
					),
				} );
				return;
			}

			throw new Error( __( 'Failed to submit form', 'advanced-ads' ) );
		} catch ( error ) {
			createToast( {
				type: 'error',
				title: __( 'Failed to submit form', 'advanced-ads' ),
				message:
					error instanceof Error
						? error.message
						: __( 'Something went wrong.', 'advanced-ads' ),
				inDialog: true,
			} );
		} finally {
			setIsSubmitting( false );
		}
	}

	return (
		<Modal
			title={ __( 'Get help from our support team', 'advanced-ads' ) }
			size="small"
			onRequestClose={ closeModal }
			shouldCloseOnEscape={ true }
			shouldCloseOnClickOutside={ false }
			className="advads-modal w-lg max-w-lg"
			overlayClassName="bg-black/50 backdrop-blur-[1px]"
		>
			<p className="mt-0">
				{ __(
					'Describe your issue and our support team will review it as soon as possible. You’ll receive a confirmation email after submitting your request.',
					'advanced-ads'
				) }
			</p>

			<form onSubmit={ handleSubmit }>
				<div className="advads-form">
					<div
						className={
							'advads-field' + ( errors.email ? ' invalid' : '' )
						}
					>
						<div className="advads-field-label">
							<label htmlFor="email">
								{ __( 'Email address', 'advanced-ads' ) } *
							</label>
						</div>
						<div className="advads-field-input">
							<input
								type="email"
								name="email"
								id="email"
								className="regular-text"
								autoComplete="email"
								placeholder={ __(
									'Enter your email address',
									'advanced-ads'
								) }
								value={ email }
								onChange={ ( ev ) => {
									setEmail( ev.target.value );
									if ( errors.email ) {
										setErrors( ( prev ) => {
											const next = { ...prev };
											delete next.email;
											return next;
										} );
									}
								} }
								aria-invalid={ errors.email ? 'true' : 'false' }
								aria-describedby={
									errors.email ? 'email-error' : undefined
								}
							/>
						</div>
						{ errors.email && (
							<p
								id="email-error"
								className="text-sm text-red-600 mt-2"
								role="alert"
							>
								{ errors.email }
							</p>
						) }
					</div>

					<div
						className={
							'advads-field' +
							( errors.subject ? ' invalid' : '' )
						}
					>
						<div className="advads-field-label">
							<label htmlFor="subject">
								{ __( 'Subject', 'advanced-ads' ) } *
							</label>
						</div>
						<div className="advads-field-input">
							<input
								type="text"
								name="subject"
								id="subject"
								className="regular-text"
								placeholder={ __(
									'Enter the subject of your issue',
									'advanced-ads'
								) }
								value={ subject }
								onChange={ ( ev ) => {
									setSubject( ev.target.value );
									if ( errors.subject ) {
										setErrors( ( prev ) => {
											const next = { ...prev };
											delete next.subject;
											return next;
										} );
									}
								} }
								aria-invalid={
									errors.subject ? 'true' : 'false'
								}
								aria-describedby={
									errors.subject ? 'subject-error' : undefined
								}
							/>
						</div>
						{ errors.subject && (
							<p
								id="subject-error"
								className="text-sm text-red-600 mt-2"
								role="alert"
							>
								{ errors.subject }
							</p>
						) }
					</div>

					<div
						className={
							'advads-field' +
							( errors.message ? ' invalid' : '' )
						}
					>
						<div className="advads-field-label">
							<label htmlFor="message">
								{ __( 'Your Message', 'advanced-ads' ) } *
							</label>
						</div>
						<div className="advads-field-input">
							<textarea
								name="message"
								id="message"
								className={
									'large-text' +
									( errors.message ? ' !border-red-300' : '' )
								}
								placeholder={ __(
									'Enter the message of your issue',
									'advanced-ads'
								) }
								rows="5"
								value={ message }
								onChange={ ( ev ) => {
									setMessage( ev.target.value );
									if ( errors.message ) {
										setErrors( ( prev ) => {
											const next = { ...prev };
											delete next.message;
											return next;
										} );
									}
								} }
								aria-invalid={
									errors.message ? 'true' : 'false'
								}
								aria-describedby={
									errors.message ? 'message-error' : undefined
								}
							/>
						</div>
						{ errors.message && (
							<p
								id="message-error"
								className="text-sm text-red-600 mt-2"
								role="alert"
							>
								{ errors.message }
							</p>
						) }
					</div>

					<div className="advads-field">
						<FileUploaderField
							uploaderRef={ uploaderRef }
							maxFiles={ 5 }
							maxSizeMB={ 5 }
							allowedExt={ [
								'jpg',
								'jpeg',
								'png',
								'gif',
								'pdf',
								'txt',
								'docx',
								'log',
							] }
						/>
					</div>

					<div
						className={
							'advads-field' + ( errors.terms ? ' invalid' : '' )
						}
					>
						<div className="flex items-start gap-2">
							<input
								type="checkbox"
								name="terms"
								id="terms"
								className="mt-0.5"
								checked={ termsAccepted }
								onChange={ ( ev ) => {
									setTermsAccepted( ev.target.checked );
									if ( errors.terms ) {
										setErrors( ( prev ) => {
											const next = { ...prev };
											delete next.terms;
											return next;
										} );
									}
								} }
								aria-labelledby="terms-consent-label"
								aria-invalid={ errors.terms ? 'true' : 'false' }
								aria-describedby={
									errors.terms ? 'terms-error' : undefined
								}
							/>
							<label
								htmlFor="terms"
								id="terms-consent-label"
								dangerouslySetInnerHTML={ { __html: p2 } }
							/>
						</div>
						{ errors.terms && (
							<p
								id="terms-error"
								className="text-sm text-red-600 mt-2"
								role="alert"
							>
								{ errors.terms }
							</p>
						) }
					</div>
				</div>

				<div className="pt-12 flex gap-x-4 justify-end -mb-2">
					<button
						type="button"
						data-dialog-close
						className="button button-secondary"
						onClick={ closeModal }
						disabled={ isSubmitting }
					>
						{ __( 'Cancel', 'advanced-ads' ) }
					</button>
					<button
						type="submit"
						className={
							'button button-primary' +
							( isSubmitting ? ' submitting' : '' )
						}
						disabled={ isSubmitting }
					>
						{ __( 'Submit', 'advanced-ads' ) }
					</button>
				</div>
			</form>
		</Modal>
	);
}
