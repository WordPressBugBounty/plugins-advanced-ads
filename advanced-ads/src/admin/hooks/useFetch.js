/**
 * WordPress Dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect } from '@wordpress/element';

export function useFetch( endpoint, extraArgs = {} ) {
	const [ data, setData ] = useState( [] );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		if ( ! endpoint ) {
			return;
		}

		const isExternal = endpoint.startsWith( 'http' );

		let args = isExternal ? { url: endpoint } : { path: endpoint };
		if ( extraArgs && Object.keys( extraArgs ).length > 0 ) {
			args = { ...args, method: 'POST', data: extraArgs };
		}

		setIsLoading( true );
		apiFetch( args )
			.then( ( json ) => setData( json ) )
			.catch( ( err ) => setError( err ) )
			.finally( () => setIsLoading( false ) );
	}, [ endpoint, extraArgs ] );

	return { data, isLoading, error };
}
