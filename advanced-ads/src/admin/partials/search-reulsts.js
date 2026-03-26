import { searchResultsLabel } from '@advancedAds/i18n';

export function searchResults( pageClass ) {
	if ( ! document.body.classList.contains( pageClass ) ) {
		return;
	}

	const params = new URLSearchParams( window.location.search );
	const isSearching = params.has( 's' ) && params.get( 's' ).trim() !== '';

	if ( isSearching ) {
		const searchBox = document.querySelector( 'p.search-box' );
		const subtitle = document.querySelector( '.subtitle' );

		if ( subtitle ) {
			subtitle.classList.add( 'hidden' );
		}

		const label = document.createElement( 'span' );
		label.className = 'advads-search-results-label';
		label.textContent = searchResultsLabel;
		searchBox.insertAdjacentElement( 'afterbegin', label );
	}
}
