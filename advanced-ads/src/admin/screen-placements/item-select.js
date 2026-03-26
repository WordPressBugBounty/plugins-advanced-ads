import { placements as i18n } from '@advancedAds/i18n';
import { endpoints, notifications, placements } from '@advancedAds';

const handleSelectChange = async ( select ) => {
	const wrap = select.parentElement;
	const spinner = wrap.querySelector( '.advads-loader' );

	select.disabled = true;
	spinner.classList.remove( 'hidden' );

	const payload = new FormData();
	payload.append( 'action', 'advads_placement_update_item' );
	payload.append( 'placement_id', select.dataset.placementId );
	payload.append( 'item_id', select.value );
	payload.append( 'security', placements.updateItemNonce );

	try {
		const response = await fetch( endpoints.ajaxUrl, {
			method: 'POST',
			body: payload,
		} );

		const result = await response.json();

		if ( ! response.ok ) {
			throw new Error( result?.data?.message || 'Error occurred.' );
		}

		const { data } = result;
		const modalForm = document.querySelector(
			`#modal-placement-edit-${ data.placement_id }`
		);

		const editLinks = [
			wrap.querySelector( '.advads-placement-item-edit' ),
			modalForm.querySelector( '.advads-placement-item-edit' ),
		];

		editLinks.forEach( ( link ) => {
			if ( ! link ) {
				return;
			}
			link.href = data.edit_href;
			link.style.display = data.edit_href ? 'inline' : 'none';
		} );

		const modalSelect = modalForm.querySelector(
			`select#placement-item-${ data.placement_id }`
		);
		if ( modalSelect ) {
			modalSelect.value = data.item_id;
		}

		notifications.addSuccess( i18n.updated );
	} catch ( error ) {
		notifications.addError( error.message );
	} finally {
		select.disabled = false;
		spinner.classList.add( 'hidden' );
	}
};

export function itemSelect() {
	const theList = document.querySelector( '#the-list' );
	if ( ! theList ) {
		return;
	}

	theList.addEventListener( 'change', function ( e ) {
		if ( e.target.matches( '.advads-placement-item-select' ) ) {
			handleSelectChange( e.target );
		}
	} );
}
