import { createToast } from '@advancedAds/utils';
import { FileUploader } from '../partials/file-uploader';

/**
 * Helper to convert Base64 string from the library into a File Blob
 *
 * @param {string} dataUrl attachment url.
 */
function base64ToBlob( dataUrl ) {
	const [ header, base64 ] = dataUrl.split( ',' );
	const mime = header.match( /:(.*?);/ )[ 1 ];
	const binary = atob( base64 );
	const array = [];
	for ( let i = 0; i < binary.length; i++ ) {
		array.push( binary.charCodeAt( i ) );
	}
	return new Blob( [ new Uint8Array( array ) ], { type: mime } );
}

function submitFormToAPI( form, uploader ) {
	const formData = new FormData( form );
	const data = Object.fromEntries( formData );

	const selectedFiles = uploader.selectedFiles || [];
	const hasFiles = selectedFiles.length > 0;

	let submitBody;
	const headers = {};

	// Cosmetics
	const button = form.querySelector( 'button[type="submit"]' );
	button.classList.add( 'submitting' );

	// Use multipart/form-data for file upload
	if ( hasFiles ) {
		const submitFormData = new FormData();
		for ( const [ key, value ] of Object.entries( data ) ) {
			submitFormData.append( key, value );
		}

		// Append all files
		selectedFiles.forEach( ( fileObj ) => {
			const blob = base64ToBlob( fileObj.fileimage );
			submitFormData.append( 'attachments[]', blob, fileObj.filename );
		} );
		submitBody = submitFormData;
		// Do NOT set content-type, browser will set it with multipart boundary
	} else {
		// No files, send as JSON
		submitBody = JSON.stringify( data );
		headers[ 'Content-Type' ] = 'application/json';
	}

	fetch( 'https://wpadvancedads.com/wp-json/advanced-ads/v1/create-ticket', {
		method: 'POST',
		body: submitBody,
		headers,
	} )
		.then( ( response ) => {
			if ( response.ok ) {
				uploader.reset();
				form.querySelector( '.advads-dialog-button-close' ).click();
				createToast( {
					type: 'muted',
					iconType: 'success',
					title: 'Ticket submitted',
					message: 'Your ticket has been submitted successfully',
				} );
				return;
			}

			throw new Error( 'Failed to submit form' );
		} )
		.catch( ( error ) => {
			// Ideally replace this with user feedback instead of console.error in production
			// eslint-disable-next-line no-console
			createToast( {
				type: 'error',
				title: 'Failed to submit form',
				message: error.message,
				inDialog: true,
			} );
		} )
		.finally( () => {
			button.classList.remove( 'submitting' );
		} );
}

export function ticketForm() {
	const form = document.querySelector( '.advads-dialog-create-ticket form' );
	const uploader = new FileUploader(
		document.getElementById( 'attachments-container' ),
		document.getElementById( 'attachments' )
	);

	form.addEventListener( 'submit', function ( e ) {
		e.preventDefault();

		const fields = this.querySelectorAll( '.advads-field' );
		let isValid = true;

		fields.forEach( ( field ) => {
			const input = field.querySelector( 'input, select, textarea' );
			if ( input && ! input.checkValidity() ) {
				field.classList.add( 'invalid' );
				isValid = false;
			} else {
				field.classList.remove( 'invalid' );
			}
		} );

		if ( ! isValid ) {
			return;
		}

		submitFormToAPI( form, uploader );
	} );

	form.querySelectorAll( 'input, select, textarea' ).forEach( ( field ) => {
		field.addEventListener( 'input', function () {
			field.closest( '.advads-field' ).classList.remove( 'invalid' );
		} );
	} );
}
