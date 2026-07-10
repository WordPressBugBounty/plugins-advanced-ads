import { useEffect, useRef, useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { useCurrentPath } from '@admin/router';
import { STORE_NAME } from '@admin/store';
import { LICENSE_PATH } from '../utils';
import { initLicenses } from './licenses-api';

export function useLicenseData() {
	const path = useCurrentPath();
	const onLicenseScreen = path === LICENSE_PATH;
	const isInitialLoad = useRef( true );

	const { licenses, appliedAddonKeyMap, hasLicenses } = useSelect(
		( select ) => {
			const store = select( STORE_NAME );
			return {
				licenses: store.getLicenses(),
				appliedAddonKeyMap: store.getAppliedAddonKeyMap(),
				hasLicenses: store.hasLicenses(),
			};
		},
		[]
	);

	const [ isLoading, setIsLoading ] = useState( true );
	const [ error ] = useState( null );

	useEffect( () => {
		if ( ! onLicenseScreen ) {
			return undefined;
		}

		return bindLicenseRefresh( { isInitialLoad, setIsLoading } );
	}, [ path, onLicenseScreen ] );

	return { licenses, appliedAddonKeyMap, hasLicenses, isLoading, error };
}

function bindLicenseRefresh( { isInitialLoad, setIsLoading } ) {
	let cancelled = false;
	const showLoader = isInitialLoad.current;

	if ( showLoader ) {
		isInitialLoad.current = false;
		setIsLoading( true );
	}

	const refresh = () =>
		initLicenses()
			.catch( () => {} )
			.finally( () => {
				if ( ! cancelled && showLoader ) {
					setIsLoading( false );
				}
			} );

	refresh();

	const onVisible = () => {
		if ( document.visibilityState === 'visible' ) {
			refresh();
		}
	};
	const onPageShow = ( event ) => {
		if ( event?.persisted || document.visibilityState === 'visible' ) {
			refresh();
		}
	};

	document.addEventListener( 'visibilitychange', onVisible );
	globalThis.addEventListener( 'focus', onVisible );
	globalThis.addEventListener( 'pageshow', onPageShow );
	globalThis.addEventListener( 'popstate', refresh );

	return () => {
		cancelled = true;
		document.removeEventListener( 'visibilitychange', onVisible );
		globalThis.removeEventListener( 'focus', onVisible );
		globalThis.removeEventListener( 'pageshow', onPageShow );
		globalThis.removeEventListener( 'popstate', refresh );
	};
}
