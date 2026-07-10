/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';

const EMAIL_PATTERN = /^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,63}$/i;

/**
 * Convert Base64 data URL from the file uploader into a Blob.
 *
 * @param {string} dataUrl Data URL string.
 * @return {Blob} File blob.
 */
export function base64ToBlob( dataUrl ) {
	const [ header, base64 ] = dataUrl.split( ',' );
	const mimeMatch = /:(.*?);/.exec( header );
	const mime = mimeMatch ? mimeMatch[ 1 ] : 'application/octet-stream';
	const binary = atob( base64 );
	const bytes = new Uint8Array( binary.length );
	for ( let i = 0; i < binary.length; i++ ) {
		bytes[ i ] = binary.codePointAt( i );
	}
	return new Blob( [ bytes ], { type: mime } );
}

export function validate( email, subject, message, termsAccepted ) {
	const next = {};
	const trimmedEmail = email.trim();
	const trimmedSubject = subject.trim();
	const trimmedMessage = message.trim();

	if ( ! trimmedEmail ) {
		next.email = __( 'Please enter your email address.', 'advanced-ads' );
	} else if ( ! EMAIL_PATTERN.test( trimmedEmail ) ) {
		next.email = __(
			'Please enter a valid email address.',
			'advanced-ads'
		);
	}

	if ( ! trimmedSubject ) {
		next.subject = __( 'Please enter a subject.', 'advanced-ads' );
	}

	if ( ! trimmedMessage ) {
		next.message = __( 'Please enter your message.', 'advanced-ads' );
	}

	if ( ! termsAccepted ) {
		next.terms = __(
			'Please accept the terms and privacy policy to continue.',
			'advanced-ads'
		);
	}

	return { next, trimmedEmail, trimmedSubject, trimmedMessage };
}
