/**
 * External Dependencies
 */
import { useDebounce } from '@wordpress/compose';
import { useState, useRef, useCallback, useEffect } from '@wordpress/element';

export function useSearchCombobox( { endpoint, onSelect, delay = 300 } ) {
	const [ suggestions, setSuggestions ] = useState( [] );
	const [ isOpen, setIsOpen ] = useState( false );
	const [ activeIndex, setActiveIndex ] = useState( -1 );
	const inputRef = useRef( null );

	const fetchData = useCallback(
		async ( query ) => {
			try {
				const res = await fetch(
					endpoint.replace( '{{search}}', query )
				);
				const data = await res.json();
				setSuggestions( data );
				setIsOpen( data.length > 0 );
				setActiveIndex( -1 );
			} catch ( err ) {
				console.error( 'useSearchCombobox fetch error:', err );
				setSuggestions( [] );
			}
		},
		[ endpoint ]
	);

	const debouncedFetch = useDebounce( fetchData, delay );

	const close = useCallback( () => {
		setSuggestions( [] );
		setIsOpen( false );
		setActiveIndex( -1 );
	}, [] );

	const handleInputChange = useCallback(
		( e ) => {
			const query = e.target.value;
			if ( ! query.trim() ) {
				close();
				return;
			}
			debouncedFetch( query );
		},
		[ debouncedFetch, close ]
	);

	const handleKeyDown = useCallback(
		( e ) => {
			if ( ! isOpen ) {
				return;
			}

			if ( e.key === 'ArrowDown' ) {
				e.preventDefault();
				setActiveIndex( ( i ) => ( i + 1 ) % suggestions.length );
			} else if ( e.key === 'ArrowUp' ) {
				e.preventDefault();
				setActiveIndex(
					( i ) => ( i - 1 + suggestions.length ) % suggestions.length
				);
			} else if ( e.key === 'Enter' ) {
				e.preventDefault();
				if ( activeIndex > -1 ) {
					onSelect( suggestions[ activeIndex ], inputRef );
					close();
				}
			} else if ( e.key === 'Escape' ) {
				if ( inputRef.current ) {
					inputRef.current.value = '';
				}
				close();
			}
		},
		[ isOpen, suggestions, activeIndex, onSelect, close ]
	);

	const handleSelect = useCallback(
		( suggestion ) => {
			onSelect( suggestion, inputRef );
			close();
		},
		[ onSelect, close ]
	);

	useEffect( () => {
		const onKeyDown = ( e ) => {
			if ( e.key === 'Escape' && isOpen ) {
				if ( inputRef.current ) {
					inputRef.current.value = '';
				}
				close();
			}
		};
		document.addEventListener( 'keydown', onKeyDown );
		return () => document.removeEventListener( 'keydown', onKeyDown );
	}, [ isOpen, close ] );

	// Spread these directly onto your <input>
	const inputProps = {
		ref: inputRef,
		role: 'combobox',
		'aria-autocomplete': 'list',
		'aria-expanded': isOpen,
		'aria-activedescendant':
			activeIndex > -1 ? `suggestion-${ activeIndex }` : undefined,
		onChange: handleInputChange,
		onKeyDown: handleKeyDown,
	};

	// Spread these directly onto your <ul>
	const listProps = {
		role: 'listbox',
	};

	return {
		suggestions,
		isOpen,
		activeIndex,
		inputProps,
		listProps,
		handleSelect,
	};
}
