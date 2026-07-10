/**
 * External Dependencies
 */
import { Search } from 'lucide-react';
/**
 * Internal Dependencies
 */
import { clsx } from '@admin/utils';
import { useSearchCombobox } from '@admin/hooks/useSearchCombobox';

export function SearchComboBox( {
	id,
	placeholder = 'Search…',
	endpoint,
	formatSuggestion,
	onSelect,
	debounceDelay = 300,
} ) {
	const {
		suggestions,
		isOpen,
		activeIndex,
		inputProps,
		listProps,
		handleSelect,
	} = useSearchCombobox( { endpoint, onSelect, delay: debounceDelay } );

	return (
		<div className="relative w-full">
			<Search className="absolute top-1/2 right-4 -translate-y-1/2 stroke-gray-600 size-4.5" />
			<input
				id={ id }
				placeholder={ placeholder }
				className="border border-border rounded-lg px-4 py-3 w-full leading-0 focus:shadow-none placeholder:text-gray-400"
				{ ...inputProps }
			/>

			{ isOpen && (
				<ul
					className="absolute w-full bg-white text-left border border-border max-h-60 overflow-y-auto m-0 mt-0.75 rounded-lg shadow-lg py-2 advads-scrollbar"
					{ ...listProps }
				>
					{ suggestions.length === 0 ? (
						<li className="p-2.5 text-gray-400 cursor-default">
							No results found
						</li>
					) : (
						suggestions.map( ( suggestion, index ) => (
							<li key={ index } id={ `suggestion-${ index }` }>
								<button
									type="button"
									onClick={ () => handleSelect( suggestion ) }
									className={ clsx(
										'flex w-full bg-transparent border-0! cursor-pointer px-4 py-2 text-sm transition-colors text-left',
										index === activeIndex
											? 'bg-gray-100'
											: ''
									) }
								>
									{ formatSuggestion( suggestion ) }
								</button>
							</li>
						) )
					) }
				</ul>
			) }
		</div>
	);
}
