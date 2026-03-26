import { btnCloseLabel } from '@advancedAds/i18n';

export function screenOptions() {
	const screenMetaLinks = document.querySelector( '#screen-meta-links' );

	if ( ! screenMetaLinks ) {
		return;
	}

	const header = document.querySelector( '#advads-header' );
	const innerWrap = header.querySelector( '.inner-wrap' );
	const screenMeta = document.querySelector( '#screen-meta' );

	// Move elements after header
	innerWrap.after( screenMeta );
	innerWrap.after( screenMetaLinks );

	// Append to #adv-settings .submit inside #advads-header
	const submitContainer = header.querySelector( '#adv-settings .submit' );

	if ( submitContainer ) {
		// Create close button
		const closeBtn = document.createElement( 'button' );
		closeBtn.type = 'button';
		closeBtn.className = 'advads-button button button-secondary';
		closeBtn.textContent = btnCloseLabel;

		submitContainer.appendChild( closeBtn );

		// Click handler
		closeBtn.addEventListener( 'click', () => {
			document.querySelector( '#show-settings-link' ).click();
		} );
	}
}
