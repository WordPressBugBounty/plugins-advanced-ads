import jQuery from 'jquery';
import domReady from '@wordpress/dom-ready';

import './listing.css';

import { itemSelect } from './item-select';
import { searchResults } from '../partials/search-reulsts';

import frontendPicker from './frontend-picker';
import formSubmission from './form-submission';
import QuickEdit from './quick-edit';

domReady( () => {
	itemSelect();
	searchResults( 'post-type-advanced_ads_plcmnt' );
} );

jQuery( function () {
	frontendPicker();
	formSubmission();
	QuickEdit();
	newPlacement();
} );

function newPlacement() {
	// open modal if no placements are available.
	if ( jQuery( '#posts-filter tr.no-items' ).length ) {
		window.location.hash = 'modal-placement-new';
	}
}
