/**
 * Toggle (no CSS dependency)
 *
 * @param {string} selector
 */
function toggle( selector ) {
	document.querySelectorAll( selector ).forEach( ( el ) => {
		el.classList.toggle( 'hidden' );
	} );
}

export function toggles() {
	document.querySelectorAll( '[data-toggle]' ).forEach( function ( el ) {
		el.addEventListener( 'click', function () {
			const targets = this.getAttribute( 'data-toggle' );
			toggle( targets );
		} );
	} );
}
