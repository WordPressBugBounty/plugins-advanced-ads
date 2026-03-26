import apiFetch from '@wordpress/api-fetch';

import { groups } from '@advancedAds/i18n';
import { notifications } from '@advancedAds';

export function deleteGroup() {
	document.addEventListener( 'click', function ( event ) {
		const target = event.target;

		if ( ! target.matches( '.delete-tag' ) ) {
			return;
		}

		event.preventDefault();

		const groupName = target
			.closest( 'div' )
			.parentElement.querySelector( '.advads-table-name a' ).textContent;

		if (
			// eslint-disable-next-line no-alert
			! window.confirm(
				groups.confirmation.replace( '%s', groupName.trim() )
			)
		) {
			return;
		}

		const href = target.getAttribute( 'href' );
		const queryVars = new URLSearchParams( href );
		const tr = target.closest( 'tr' );

		apiFetch( {
			path: '/advanced-ads/v1/group',
			method: 'DELETE',
			data: {
				id: queryVars.get( 'group_id' ),
				nonce: queryVars.get( '_wpnonce' ),
			},
		} ).then( function ( response ) {
			if ( response.done ) {
				tr.remove();
				notifications.addSuccess( groups.deleted );
			}
		} );
	} );
}
