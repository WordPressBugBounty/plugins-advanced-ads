/**
 * WordPress Dependencies
 */
import { useCallback, useState } from '@wordpress/element';

/**
 * Boolean open/close state for modals, dialogs, and similar UI.
 *
 * @param {boolean} [initialOpen=false] Whether the modal starts open.
 * @return {Object} An object with the following properties:
 * - isOpen: boolean
 * - open: () => void
 * - close: () => void
 * - toggle: () => void
 * @example
 * const { isOpen, open, close, toggle } = useModalState( false );
 * return (
 *   <button onClick={ open }>Open</button>
 *   <button onClick={ close }>Close</button>
 *   <button onClick={ toggle }>Toggle</button>
 * );
 */
export function useModalState( initialOpen = false ) {
	const [ isOpen, setIsOpen ] = useState( initialOpen );

	const open = useCallback( () => {
		setIsOpen( true );
	}, [] );

	const close = useCallback( () => {
		setIsOpen( false );
	}, [] );

	const toggle = useCallback( () => {
		setIsOpen( ( prev ) => ! prev );
	}, [] );

	return { isOpen, open, close, toggle };
}
