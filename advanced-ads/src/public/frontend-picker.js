/* eslint-disable @wordpress/no-unused-vars-before-return */
// Highlight elements in frontend, if local storage variable is set.
/* global advads, localStorage */
document.addEventListener( 'DOMContentLoaded', function () {
	/**
	 * Read all localStorage values once — avoids repeated synchronous DOM API calls.
	 */
	function isEnabled() {
		if ( ! advads.supports_localstorage() ) {
			return false;
		}

		const picker = localStorage.getItem( 'advads_frontend_picker' );
		const blogId = localStorage.getItem( 'advads_frontend_blog_id' );
		const startTime = localStorage.getItem( 'advads_frontend_starttime' );
		const optBlogId = globalThis.advads_options.blog_id;

		if ( ! picker ) {
			return false;
		}

		// Check if the frontend picker was started on the current blog.
		if ( optBlogId && blogId && optBlogId !== blogId ) {
			return false;
		}

		// Deactivate if started more than 45 minutes ago.
		if (
			startTime &&
			parseInt( startTime, 10 ) < Date.now() - 45 * 60 * 1000
		) {
			[
				'advads_frontend_action',
				'advads_frontend_element',
				'advads_frontend_picker',
				'advads_prev_url',
				'advads_frontend_pathtype',
				'advads_frontend_boundary',
				'advads_frontend_blog_id',
				'advads_frontend_starttime',
			].forEach( ( key ) => localStorage.removeItem( key ) );

			advads.set_cookie( 'advads_frontend_picker', '', -1 );
			return false;
		}

		return true;
	}

	if ( ! isEnabled() ) {
		return;
	}

	let pickerCur = null;

	// Build the overlay with plain DOM APIs.
	const overlay = document.createElement( 'div' );
	overlay.id = 'advads-picker-overlay';
	Object.assign( overlay.style, {
		position: 'absolute',
		border: 'solid 2px #428bca',
		backgroundColor: 'rgba(66,139,202,0.5)',
		boxSizing: 'border-box',
		zIndex: '1000000',
		pointerEvents: 'none',
		display: 'none',
	} );
	document.body.prepend( overlay );

	if ( 'true' === localStorage.getItem( 'advads_frontend_boundary' ) ) {
		document.body.style.cursor = 'not-allowed';
	}

	// Use a Set for O(1) membership test on every mousemove.
	const pickerNo = new Set( [
		document.body,
		document.documentElement,
		document,
	] );

	/**
	 * Check if we can traverse up the DOM tree.
	 *
	 * @param {HTMLElement} el
	 * @return {boolean} true if the element is a boundary, false otherwise
	 */
	globalThis.advads.is_boundary_reached = function ( el ) {
		if ( 'true' !== localStorage.getItem( 'advads_frontend_boundary' ) ) {
			return false;
		}

		const helpers = document.querySelectorAll(
			'.advads-frontend-picker-boundary-helper'
		);
		const boundaries = new Set(
			Array.from( helpers )
				.map( ( h ) => h.parentElement )
				.filter( Boolean )
		);

		boundaries.forEach( ( b ) => {
			b.style.cursor = 'pointer';
		} );

		// Is el itself a boundary, or does it have no boundary ancestor?
		if ( boundaries.has( el ) ) {
			return true;
		}

		let node = el.parentElement;
		while ( node ) {
			if ( boundaries.has( node ) ) {
				return false;
			}
			node = node.parentElement;
		}
		return true;
	};

	// Determine path function once.
	const fn =
		'xpath' === localStorage.getItem( 'advads_frontend_pathtype' )
			? getXPath
			: getPath;

	/**
	 * Throttle mousemove with requestAnimationFrame — fires 60-100x/sec otherwise.
	 * Each move triggers a full DOM traversal via getPath/getXPath.
	 * rAF limits processing to once per render frame (~16ms).
	 */
	let rafPending = false;
	let lastMouseEvent = null;

	document.addEventListener( 'mousemove', function ( e ) {
		lastMouseEvent = e;
		if ( rafPending ) {
			return;
		}

		rafPending = true;
		globalThis.requestAnimationFrame( function () {
			rafPending = false;

			const ev = lastMouseEvent;
			if ( ! ev ) {
				return;
			}

			if ( ev.target === pickerCur ) {
				return;
			}

			if ( pickerNo.has( ev.target ) ) {
				pickerCur = null;
				overlay.style.display = 'none';
				return;
			}

			pickerCur = ev.target;

			const path = fn( pickerCur );
			if ( ! path ) {
				return;
			}

			// DEBUG only — remove in production.
			// console.log( getPath( pickerCur ), 'getPath' );
			// console.log( getXPath( pickerCur ), 'getXPath' );

			const rect = pickerCur.getBoundingClientRect();
			const scrollX = window.scrollX || window.pageXOffset;
			const scrollY = window.scrollY || window.pageYOffset;

			Object.assign( overlay.style, {
				top: rect.top + scrollY + 'px',
				left: rect.left + scrollX + 'px',
				width: rect.width + 'px',
				height: rect.height + 'px',
				display: 'block',
			} );
		} );
	} );

	// Save on click.
	document.addEventListener( 'click', function () {
		if ( ! pickerCur ) {
			return;
		}
		if ( advads.is_boundary_reached( pickerCur ) ) {
			return;
		}

		const path = fn( pickerCur );
		localStorage.setItem( 'advads_frontend_element', path );
		globalThis.location = localStorage.getItem( 'advads_prev_url' );
	} );
} );

/* --------------------------------------------------------------------------
 * Path helpers — no jQuery dependency.
 * Both use iterative while loops over raw DOM APIs (el.parentNode, el.tagName,
 * el.id, el.className) instead of recursive jQuery calls, eliminating per-node
 * jQuery object allocation, .attr(), .siblings(), .addBack(), .index() overhead.
 * -------------------------------------------------------------------------- */

const OVERLAY_ID = 'advads-picker-overlay';
const MAX_DEPTH = 3;
const HAS_DIGIT = /\d/;

/**
 * Returns true if cls contains any digit character.
 *
 * @param {string} cls
 * @return {boolean} true if the class contains any digit character, false otherwise
 */
function hasDigit( cls ) {
	return HAS_DIGIT.test( cls );
}

/**
 * Get up to `limit` CSS classes from an element's className,
 * skipping any class that contains a digit.
 *
 * @param {string} className Raw className string.
 * @param {number} limit     Max classes to return.
 * @return {string[]} up to `limit` CSS classes from an element's className, skipping any class that contains a digit
 */
function getFilteredClasses( className, limit ) {
	if ( ! className ) {
		return [];
	}

	const result = [];
	for ( const cls of className.split( /[\s\n]+/ ) ) {
		if ( cls && ! hasDigit( cls ) ) {
			result.push( cls );
			if ( result.length === limit ) {
				break;
			}
		}
	}
	return result;
}

/**
 * Count children of `parent` matching `tagName` (and optionally classes),
 * excluding the overlay element. Returns { total, selfIndex }.
 *
 * @param {HTMLElement} parent
 * @param {HTMLElement} self
 * @param {string}      tagName
 * @param {string[]}    classes Optional class list to match against.
 * @return {{ total: number, selfIndex: number }} total number of children matching the tagName and classes, and the index of the self element
 */
function getSiblingIndex( parent, self, tagName, classes ) {
	if ( ! parent ) {
		return { total: 0, selfIndex: -1 };
	}

	const tag = tagName.toLowerCase();
	const hasClasses = classes && classes.length > 0;

	let total = 0;
	let selfIndex = -1;

	for ( const child of parent.children ) {
		if ( child.id === OVERLAY_ID ) {
			continue;
		}
		if ( child.nodeName.toLowerCase() !== tag ) {
			continue;
		}

		if ( hasClasses ) {
			// Check all required classes are present.
			const childClasses = child.className
				? child.className.split( /\s+/ )
				: [];
			const classSet = new Set( childClasses );
			if ( ! classes.every( ( c ) => classSet.has( c ) ) ) {
				continue;
			}
		}

		if ( child === self ) {
			selfIndex = total;
		}
		total++;
	}

	return { total, selfIndex };
}

/**
 * Get a CSS-selector-style path for the element.
 * Walks up to MAX_DEPTH levels, stopping at <html>.
 *
 * @param {HTMLElement} el
 * @return {string} a CSS-selector-style path for the element
 */
function getPath( el ) {
	const parts = [];
	let node = el;

	while (
		node &&
		node.nodeName.toLowerCase() !== 'html' &&
		parts.length < MAX_DEPTH
	) {
		const tag = node.nodeName.toLowerCase();
		const elId = node.id;
		const classes = getFilteredClasses( node.className, 2 );

		let cur = tag;

		if ( elId && ! hasDigit( elId ) ) {
			cur += '#' + elId;
		} else if ( classes.length ) {
			cur += '.' + classes.join( '.' );
		}

		const { total, selfIndex } = getSiblingIndex(
			node.parentElement,
			node,
			tag,
			classes
		);

		if ( total > 1 && selfIndex !== -1 ) {
			// :eq() is 0-based — matches jQuery's selector behaviour.
			cur += ':eq(' + selfIndex + ')';
		}

		parts.unshift( cur );
		node = node.parentElement;
	}

	if ( node && node.nodeName.toLowerCase() === 'html' ) {
		parts.unshift( 'html' );
	}

	return parts.join( ' > ' );
}

/**
 * Get an XPath expression for the element.
 * Walks up to MAX_DEPTH levels, stopping at <body>.
 * XPath indexes are 1-based — selfIndex + 1 corrects the :eq() off-by-one.
 *
 * @param {HTMLElement} el
 * @return {string} an XPath expression for the element
 */
function getXPath( el ) {
	const parts = [];
	let node = el;
	let depth = 0;

	while (
		node &&
		node.nodeName.toLowerCase() !== 'body' &&
		depth < MAX_DEPTH
	) {
		if ( advads.is_boundary_reached( node ) ) {
			break;
		}

		const tag = node.nodeName.toLowerCase();
		const elId = node.id;
		const classes = getFilteredClasses( node.className, 2 );

		let cur = tag;

		if ( elId && ! hasDigit( elId ) ) {
			// @id is unique — return immediately with a rooted //tag[@id="..."] path.
			parts.unshift( cur + '[@id="' + elId + '"]' );
			return '//' + parts.join( '/' );
		}

		if ( classes.length ) {
			depth++;
			const xpathClasses = classes.map(
				( cls ) =>
					'(@class and contains(concat(" ", normalize-space(@class), " "), " ' +
					cls +
					' "))'
			);
			cur += '[' + xpathClasses.join( ' and ' ) + ']';
		}

		const { total, selfIndex } = getSiblingIndex(
			node.parentElement,
			node,
			tag,
			classes
		);

		if ( total > 1 && selfIndex !== -1 ) {
			// XPath uses 1-based indexing — +1 corrects the 0-based selfIndex.
			cur += '[' + ( selfIndex + 1 ) + ']';
		}

		parts.unshift( cur );
		node = node.parentElement;
	}

	return parts.join( '/' );
}
