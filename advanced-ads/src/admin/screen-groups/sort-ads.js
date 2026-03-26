/**
 * Extracts the sortable value from a table row.
 *
 * @param {HTMLTableRowElement} row      The table row element.
 * @param {string}              sortBy   The column to sort by.
 * @param {number}              colIndex The index of the column to sort by.
 *
 * @return {string} The sortable value.
 */
function getCellValue( row, sortBy, colIndex ) {
	const cell = row.cells[ colIndex ];
	if ( ! cell ) {
		return '';
	}

	if ( 'weight' === sortBy ) {
		const select = cell.querySelector( 'select' );
		return select ? Number( select.value ) || 0 : 0;
	}

	if ( 'ad' === sortBy ) {
		const link = cell.querySelector( 'a' );
		return link ? link.textContent.trim() : '';
	}

	return cell.textContent.trim();
}

/**
 * Generic comparator that handles numbers and strings safely.
 *
 * @param {number|string} a         The first value to compare.
 * @param {number|string} b         The second value to compare.
 * @param {boolean}       ascending Whether to sort in ascending order.
 *
 * @return {number} The comparison result.
 */
function compareValues( a, b, ascending ) {
	if ( typeof a === 'number' && typeof b === 'number' ) {
		return ascending ? a - b : b - a;
	}

	return ascending
		? String( a ).localeCompare( String( b ) )
		: String( b ).localeCompare( String( a ) );
}

/**
 * Sorts table rows by column and updates UI state.
 *
 * @param {HTMLTableElement} table     The table to sort.
 * @param {string}           sortBy    The column to sort by.
 * @param {boolean}          ascending Whether to sort in ascending order.
 *
 * @return {void}
 */
function sortTable( table, sortBy, ascending ) {
	const headers = Array.from( table.querySelectorAll( 'th' ) );
	const colIndex = headers.findIndex(
		( th ) => th.dataset.sortby === sortBy
	);

	if ( colIndex === -1 ) {
		return;
	}

	const tbody = table.tBodies[ 0 ];
	const rows = Array.from( tbody.rows );

	rows.sort( ( rowA, rowB ) => {
		const valueA = getCellValue( rowA, sortBy, colIndex );
		const valueB = getCellValue( rowB, sortBy, colIndex );
		return compareValues( valueA, valueB, ascending );
	} );

	// Reattach sorted rows
	rows.forEach( ( row ) => tbody.appendChild( row ) );

	// Update sort indicator classes
	headers.forEach( ( th ) => th.classList.remove( 'asc', 'desc' ) );
	headers[ colIndex ].classList.add( ascending ? 'asc' : 'desc' );
}

export function sortAds() {
	document.querySelectorAll( '.advads-group-ads' ).forEach( ( table ) => {
		const sortStates = {
			ad: true,
			status: true,
			weight: true,
		};

		table.querySelectorAll( 'th.group-sort' ).forEach( ( header ) => {
			header.addEventListener( 'click', () => {
				const sortBy = header.dataset.sortby;

				sortTable( table, sortBy, sortStates[ sortBy ] );

				sortStates[ sortBy ] = ! sortStates[ sortBy ];
			} );
		} );
	} );
}
