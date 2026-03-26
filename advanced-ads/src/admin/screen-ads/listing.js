import domReady from '@wordpress/dom-ready';

import './listing.css';

import quickBulkEdit from './quick-bulk-edit';
import { searchResults } from '../partials/search-reulsts';

domReady( () => {
	quickBulkEdit();
	searchResults( 'post-type-advanced_ads' );
} );
