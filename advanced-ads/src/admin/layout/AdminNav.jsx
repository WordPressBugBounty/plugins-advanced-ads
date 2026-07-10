import { useEffect } from '@wordpress/element';

import { navigate } from '@admin/router';

function normalizeAppPath( path ) {
	if ( ! path || path === '/' ) {
		return '/';
	}

	return path.startsWith( '/' ) ? path : `/${ path }`;
}

function getCurrentAppPath() {
	return normalizeAppPath(
		new URLSearchParams( window.location.search ).get( 'path' )
	);
}

function syncAppSubmenuCurrent() {
	const submenu = document.querySelector(
		'#toplevel_page_advanced-ads .wp-submenu'
	);
	if ( ! submenu ) {
		return;
	}

	const currentPath = getCurrentAppPath();

	submenu.querySelectorAll( 'li.current, a.current' ).forEach( ( el ) => {
		el.classList.remove( 'current' );
	} );

	let matched = false;

	submenu.querySelectorAll( 'a.advads-app-link' ).forEach( ( anchor ) => {
		const href = anchor.getAttribute( 'href' );
		if ( ! href || href.includes( 'admin.php' ) ) {
			return;
		}

		if ( normalizeAppPath( href ) === currentPath ) {
			anchor.classList.add( 'current' );
			anchor.parentElement?.classList.add( 'current' );
			matched = true;
		}
	} );

	if ( matched ) {
		return;
	}

	const appLink = submenu.querySelector( 'a[href*="page=advanced-ads-app"]' );
	if ( appLink ) {
		appLink.classList.add( 'current' );
		appLink.parentElement?.classList.add( 'current' );
	}
}

export function AdminNav() {
	useEffect( () => {
		const handler = ( e ) => {
			const anchor = e.target.closest( 'a.advads-app-link' );
			if ( ! anchor ) {
				return;
			}

			const href = anchor.getAttribute( 'href' );
			if ( ! href ) {
				return;
			}

			e.preventDefault();

			navigate( href );
			syncAppSubmenuCurrent();
		};

		syncAppSubmenuCurrent();
		document.addEventListener( 'click', handler );

		return () => {
			document.removeEventListener( 'click', handler );
		};
	}, [ navigate ] );

	return null;
}
