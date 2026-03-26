// ── Scroll Lock ───────────────────────────────────────────────────────────────

let _openCount = 0;
let _scrollbarWidth = 0;

function _measureScrollbar() {
	return window.innerWidth - document.documentElement.clientWidth;
}

function lockScroll() {
	if ( ++_openCount !== 1 ) {
		return;
	}

	_scrollbarWidth = _measureScrollbar();
	const { style } = document.body;
	style.overflow = 'hidden';
	if ( _scrollbarWidth > 0 ) {
		style.paddingRight = `${ _scrollbarWidth }px`;
	}
}

function unlockScroll() {
	if ( _openCount === 0 || --_openCount !== 0 ) {
		return;
	}

	const { style } = document.body;
	style.overflow = '';
	style.paddingRight = '';
}

// ── Core open / close ─────────────────────────────────────────────────────────

/**
 * Opens a `<dialog>` element with an enter animation.
 *
 * @param {HTMLDialogElement} dialog   The dialog element to open.
 * @param {Element|null}      [opener] The opener element that triggered the open.
 */
function openDialog( dialog, opener ) {
	if ( dialog.open ) {
		return;
	}

	// Dispatch global event so external code can hook in
	window.dispatchEvent(
		new CustomEvent( 'advanced-ads-dialog-open', {
			detail: { dialog, opener },
		} )
	);

	dialog.dataset.state = 'closed';
	dialog.showModal();
	lockScroll();

	// Trigger enter animation on the next frame so the browser has painted
	// the initial "closed" state first.
	window.requestAnimationFrame( () => {
		dialog.dataset.state = 'enter';
	} );

	// Move focus inside the dialog — fall back to the dialog itself.
	const focusTarget =
		dialog.querySelector( '.advads-dialog-frame' ) ?? dialog;

	// Ensure the fallback target is programmatically focusable
	if ( focusTarget === dialog && ! dialog.hasAttribute( 'tabindex' ) ) {
		dialog.setAttribute( 'tabindex', '-1' );
	}

	focusTarget.focus();
}

/**
 * Closes a `<dialog>` element with a leave animation, then cleans up.
 *
 * @param {HTMLDialogElement} dialog The dialog element to close.
 */
function closeDialog( dialog ) {
	if ( ! dialog.open || dialog.dataset.state === 'leave' ) {
		return;
	}

	window.dispatchEvent(
		new CustomEvent( 'advanced-ads-dialog-close', {
			detail: { dialog },
		} )
	);

	dialog.dataset.state = 'leave';

	dialog.addEventListener(
		'transitionend',
		() => {
			dialog.dataset.state = 'closed';
			dialog.close();
			unlockScroll();

			if ( window.location.hash === `#${ dialog.id }` ) {
				window.history.replaceState(
					'',
					document.title,
					window.location.pathname + window.location.search
				);
			}
		},
		{ once: true }
	);
}

// ── Hash routing ──────────────────────────────────────────────────────────────

/**
 * Opens a dialog whose opener has `data-dialog="<hash>"`.
 *
 * @param {string} hash — without the leading `#`
 */
function openDialogByHash( hash ) {
	if ( ! hash ) {
		return;
	}

	const opener = document.querySelector(
		`[data-dialog="${ window.CSS.escape( hash ) }"]`
	);
	opener?.click();
}

// ── init ──────────────────────────────────────────────────────────────────────

/**
 * Bootstraps all dialog behaviour.
 * Call once per page after the DOM is ready.
 */
export function modal() {
	// Use event delegation on document to handle dynamically added elements.

	// Single delegated click handler covers openers, close buttons, and backdrop.
	document.addEventListener( 'click', ( { target } ) => {
		// Opener: [data-dialog="id"]
		const opener = target.closest( '[data-dialog]' );
		if ( opener ) {
			const dialog = document.querySelector(
				`#${ window.CSS.escape( opener.dataset.dialog ) }`
			);
			if ( dialog ) {
				openDialog( dialog, opener );
			}
			return;
		}

		// Close button: [data-dialog-close]
		const closer = target.closest( '[data-dialog-close]' );
		if ( closer ) {
			const dialog = closer.closest( 'dialog' );
			if ( dialog ) {
				closeDialog( dialog );
			}
			return;
		}

		// Backdrop: click fell outside .advads-dialog-frame
		const dialog = target.closest( 'dialog.advads-dialog' );
		if (
			dialog &&
			! dialog.classList.contains( 'manual' ) &&
			! dialog.querySelector( '.advads-dialog-frame' )?.contains( target )
		) {
			closeDialog( dialog );
		}
	} );

	// ── Native Escape key ─────────────────────────────────────────────────────
	// The browser fires `cancel` on the dialog before closing it natively.
	// We intercept to apply our animated close and prevent the native close.
	document.addEventListener( 'cancel', ( event ) => {
		event.preventDefault();
		closeDialog( event.target );
	} );

	// ── Hash-based deep linking ───────────────────────────────────────────────
	// Hash-based deep linking.
	const openByHash = () =>
		window.location.hash &&
		openDialogByHash( window.location.hash.slice( 1 ) );
	openByHash();
	window.addEventListener( 'hashchange', openByHash );
}
