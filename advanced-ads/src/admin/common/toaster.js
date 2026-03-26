const TOAST_CONTAINER_ID = 'advads-toast-container';

/**
 * Ensures a single container for toasts exists in the DOM.
 *
 * @param {boolean} inDialog Whether the toast is in a dialog.
 *
 * @return {HTMLElement} The toast container element.
 */
function getToastContainer( inDialog = false ) {
	let container = document.getElementById( TOAST_CONTAINER_ID );
	if ( ! container ) {
		container = document.createElement( 'div' );
		container.id = TOAST_CONTAINER_ID;
		container.setAttribute( 'aria-live', 'polite' );
	}

	let whereToAppend = document.body;

	if ( inDialog ) {
		const dialog = document.querySelector( '.advads-dialog[open]' );
		if ( dialog ) {
			whereToAppend = dialog.querySelector( '.advads-dialog-frame' );
		}
	}

	whereToAppend.appendChild( container );

	return container;
}

function createIconByType( type ) {
	switch ( type ) {
		case 'muted':
		case 'info':
		case 'warning':
			return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>';
		case 'success':
			return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21.801 10A10 10 0 1 1 17 3.335"/><path d="m9 11 3 3L22 4"/></svg>';
		case 'error':
			return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>';
	}
}

/**
 * Adds a notice (toast) to the page.
 *
 * @param {Object}  options                    Options object.
 * @param {string}  options.type               Notice type: 'success' | 'error' | 'warning' | 'info'.
 * @param {string}  options.iconType           Notice icon type: 'success' | 'error' | 'warning' | 'info'.
 * @param {string}  options.title              Notice title.
 * @param {string}  options.message            Notice message body.
 * @param {boolean} [options.isDismissible]    Whether the notice can be dismissed. Default true.
 * @param {string}  [options.actions]          Optional HTML string for action buttons/links.
 * @param {boolean} [options.inDialog]         Whether the toast is in a dialog. Default false.
 * @param {boolean} [options.autoClose]        Whether the toast should auto close. Default true.
 * @param {number}  [options.autoCloseTimeout] The timeout in milliseconds for auto close. Default 4000.
 *
 * @return {HTMLElement} The created toast element.
 */
export function createToast( {
	type = 'muted',
	iconType,
	title,
	message,
	autoClose = true,
	autoCloseTimeout = 4000,
	isDismissible = true,
	actions = '',
	inDialog = false,
} ) {
	const container = getToastContainer( inDialog );
	const toast = document.createElement( 'div' );
	toast.className = `advads-toast advads-toast-${ type }`;
	if ( isDismissible ) {
		toast.classList.add( 'advads-toast-dismissible' );
	}

	const iconEl = document.createElement( 'div' );
	iconEl.className = 'advads-toast-icon';
	iconEl.innerHTML = createIconByType( iconType || type || 'info' );
	toast.appendChild( iconEl );

	const titleEl = document.createElement( 'div' );
	titleEl.className = 'advads-toast-title';
	titleEl.textContent = title;

	const messageEl = document.createElement( 'div' );
	messageEl.className = 'advads-toast-message';
	messageEl.textContent = message;

	const contentEl = document.createElement( 'div' );
	contentEl.className = 'advads-toast-content';
	contentEl.appendChild( titleEl );
	contentEl.appendChild( messageEl );
	toast.appendChild( contentEl );

	if ( actions ) {
		const actionsWrap = document.createElement( 'div' );
		actionsWrap.className = 'advads-toast-actions';
		actionsWrap.innerHTML = actions;
		toast.appendChild( actionsWrap );
	}

	if ( isDismissible ) {
		const closeBtn = document.createElement( 'a' );
		closeBtn.href = '#';
		closeBtn.className = 'advads-toast-dismiss';
		closeBtn.setAttribute( 'aria-label', 'Dismiss' );
		closeBtn.innerHTML = '&times;';
		closeBtn.addEventListener( 'click', () => {
			toast.remove();
		} );
		toast.appendChild( closeBtn );
	}

	container.appendChild( toast );

	if ( autoClose ) {
		setTimeout( () => {
			toast.remove();
		}, autoCloseTimeout );
	}

	return toast;
}

globalThis.advancedAds.utils = globalThis.advancedAds.utils || {};
globalThis.advancedAds.utils.createToast = createToast;
