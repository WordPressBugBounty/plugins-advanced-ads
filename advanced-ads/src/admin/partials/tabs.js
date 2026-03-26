export function openTabByHash() {
	// Trigger initial tab
	const hash = window.location.hash;

	if ( hash ) {
		const elem = document.getElementById( hash.replace( '#', '' ) );
		if ( elem ) {
			elem.click();
		}
	}
}
