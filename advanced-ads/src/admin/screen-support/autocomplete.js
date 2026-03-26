// Debounce function to limit the rate of API calls
const debounce = ( func, delay ) => {
	let timeoutId;
	return ( ...args ) => {
		if ( timeoutId ) {
			clearTimeout( timeoutId );
		}
		timeoutId = setTimeout( () => {
			func( ...args );
		}, delay );
	};
};

export function searchCombobox(
	inputId,
	suggestionsListId,
	endpoint,
	formatSuggestion,
	onSelect
) {
	const input = document.getElementById( inputId );
	const suggestionsList = document.getElementById( suggestionsListId );
	let selectedSuggestionIndex = -1;

	// fetch users from API
	const fetchData = async ( search ) => {
		try {
			const response = await fetch(
				endpoint.replace( '{{search}}', search )
			);
			return await response.json();
		} catch ( error ) {
			console.error( 'Error fetching:', error );
			return [];
		}
	};

	// Render suggestions in the dropdown
	const renderSuggestions = ( suggestions ) => {
		// clear previous suggestions
		clearSuggestions();
		// set aria-expanded attribute
		input.setAttribute( 'aria-expanded', 'true' );
		suggestionsList.style.display = 'block';

		// Show "No results found" message if no suggestions
		if ( suggestions.length === 0 ) {
			const li = document.createElement( 'li' );
			li.textContent = 'No results found';
			li.classList.add( 'no-results' );
			suggestionsList.appendChild( li );
			return;
		}

		// iterate over suggestions and create list items
		// for each suggestion, create a list item and append it to the suggestions list
		suggestions.forEach( ( suggestion, index ) => {
			const li = document.createElement( 'li' );
			li.id = `suggestion-${ index }`;
			li.setAttribute( 'role', 'option' );
			li.innerHTML = formatSuggestion( suggestion );
			li.dataset.index = index;

			// add event listener for suggestion click
			// when a suggestion is clicked, set the input value to the suggestion name
			li.addEventListener( 'click', () => {
				onSelect( suggestion, input );
				clearSuggestions();
			} );

			// append the list item to the suggestions list
			suggestionsList.appendChild( li );
		} );
	};

	// clear suggestions
	const clearSuggestions = () => {
		suggestionsList.innerHTML = '';
		selectedSuggestionIndex = -1;
		// set aria-expanded attribute
		input.setAttribute( 'aria-expanded', 'false' );
		suggestionsList.style.display = '';
		// remove aria-activedescendant attribute
		input.removeAttribute( 'aria-activedescendant' );
	};

	// Update the selected suggestion
	// Highlight the selected suggestion
	const updateSelectedSuggestion = () => {
		const suggestions = suggestionsList.querySelectorAll(
			'li:not(.no-results)'
		);
		// find the currently selected suggestion and highlight it
		suggestions.forEach( ( li, index ) => {
			if ( index === selectedSuggestionIndex ) {
				// add selected class
				li.classList.add( 'selected' );
				// scroll the selected suggestion into view
				li.scrollIntoView( { block: 'nearest' } );
				// set aria-activedescendant attribute
				input.setAttribute( 'aria-activedescendant', li.id );
			} else {
				// remove selected class
				li.classList.remove( 'selected' );
			}
		} );
	};

	// Handle input event
	const handleInput = async ( e ) => {
		// Get the input value
		const query = e.target.value.toLowerCase();

		// Clear suggestions if input is empty
		if ( query.length < 1 ) {
			clearSuggestions();
			return;
		}

		const data = await fetchData( query );
		renderSuggestions( data );
	};

	// add debounce to input event
	input.addEventListener( 'input', debounce( handleInput, 300 ) );
	// Handle keyboard navigation
	input.addEventListener( 'keydown', ( e ) => {
		// get the current suggestions
		const suggestions = suggestionsList.querySelectorAll(
			'li:not(.no-results)'
		);
		// check if there are any suggestions and if the suggestions list is open
		if (
			suggestions.length === 0 ||
			input.getAttribute( 'aria-expanded' ) === 'false'
		) {
			return;
		}

		// Handle arrow key down navigation and move down the suggestions
		if ( e.key === 'ArrowDown' ) {
			e.preventDefault();
			selectedSuggestionIndex =
				( selectedSuggestionIndex + 1 ) % suggestions.length;
			updateSelectedSuggestion();

			// Handle arrow key up navigation and move up the suggestions
		} else if ( e.key === 'ArrowUp' ) {
			e.preventDefault();
			selectedSuggestionIndex =
				( selectedSuggestionIndex - 1 + suggestions.length ) %
				suggestions.length;
			updateSelectedSuggestion();

			// Handle enter key and select the suggestion
		} else if ( e.key === 'Enter' ) {
			e.preventDefault();
			if ( selectedSuggestionIndex > -1 ) {
				suggestions[ selectedSuggestionIndex ].click();
			}
			// Handle escape key and close the suggestions
		} else if ( e.key === 'Escape' ) {
			input.value = '';
			clearSuggestions();
		}
	} );

	// Close suggestions when Escape is pressed anywhere in the document
	document.addEventListener( 'keydown', ( e ) => {
		if (
			e.key === 'Escape' &&
			input.getAttribute( 'aria-expanded' ) === 'true'
		) {
			input.value = '';
			clearSuggestions();
		}
	} );
}
