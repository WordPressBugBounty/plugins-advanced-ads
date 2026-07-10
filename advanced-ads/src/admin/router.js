/**
 * External Dependencies
 */
import { useMemo, useSyncExternalStore } from '@wordpress/element';

/**
 * Internal Dependencies
 */
import { routes } from './routes';

function getSearch() {
	return globalThis.location.search;
}

function subscribe( callback ) {
	globalThis.addEventListener( 'popstate', callback );
	return () => globalThis.removeEventListener( 'popstate', callback );
}

function useSearchParams() {
	const search = useSyncExternalStore( subscribe, getSearch );
	return useMemo( () => new URLSearchParams( search ), [ search ] );
}

function useCurrentPath() {
	const params = useSearchParams();
	return params.get( 'path' ) ?? '/';
}

function useCurrentRoute() {
	const params = useSearchParams();
	const path = params.get( 'path' ) ?? '/';
	const query = useMemo( () => Object.fromEntries( params ), [ params ] );

	const route = useMemo(
		() =>
			routes.find( ( r ) => r.path === path ) ??
			routes.find( ( r ) => r.name === 'not-found' ),
		[ path ]
	);

	return { route, query };
}

function useArea( area ) {
	const { route, query } = useCurrentRoute();
	if ( ! area ) {
		return { route, query };
	}

	if ( ! route?.areas?.[ area ] ) {
		return null;
	}

	return route.areas[ area ]( { query } );
}

function normalizeAdminAppPathname( pathname ) {
	const marker = '/wp-admin/admin.php';
	const first = pathname.indexOf( marker );

	if ( first === -1 ) {
		return pathname;
	}

	const duplicate = pathname.indexOf( marker, first + 1 );

	if ( duplicate === -1 ) {
		return pathname;
	}

	return pathname.slice( 0, duplicate );
}

function buildUrl( path, query = {} ) {
	const url = new URL( globalThis.location.href );
	url.pathname = normalizeAdminAppPathname( url.pathname );
	url.search = '';

	const page = new URLSearchParams( globalThis.location.search ).get(
		'page'
	);
	if ( page ) {
		url.searchParams.set( 'page', page );
	}
	if ( path && path !== '/' ) {
		url.searchParams.set( 'path', path );
	}

	Object.entries( query ).forEach( ( [ k, v ] ) => {
		if ( v !== null ) {
			url.searchParams.set( k, String( v ) );
		}
	} );

	return url;
}

function navigate( path, query = {} ) {
	const url = buildUrl( path, query );

	globalThis.history.pushState( {}, '', url );
	globalThis.dispatchEvent( new Event( 'popstate' ) );
}

export { buildUrl, useCurrentRoute, useCurrentPath, useArea, navigate };
