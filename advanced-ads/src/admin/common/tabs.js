import jQuery from 'jquery';

export function tabs() {
	const tabElems = jQuery( '.advads-tab-menu', '.advads-tab-container' );
	tabElems.on( 'click', 'a', function ( event ) {
		event.preventDefault();
		const link = jQuery( this );
		const parent = link.closest( '.advads-tab-container' );
		const target = jQuery( link.attr( 'href' ) );

		parent.find( 'a.is-active' ).removeClass( 'is-active' );
		link.addClass( 'is-active' );

		parent.find( '.advads-tab-target' ).hide();
		target.show();
	} );

	// Trigger tab
	tabElems.each( function () {
		const thisContainer = jQuery( this );
		const { hash = false } = globalThis.location;
		let tab = thisContainer.find( 'a:first' );

		if ( hash && thisContainer.find( 'a[href=' + hash + ']' ).length > 0 ) {
			tab = thisContainer.find( 'a[href=' + hash + ']' );
		}
		tab.trigger( 'click' );
	} );
}
