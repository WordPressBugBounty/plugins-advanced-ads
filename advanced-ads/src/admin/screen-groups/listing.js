import domReady from '@wordpress/dom-ready';
import jQuery from 'jquery';

import './listing.css';
import { listShowMore } from './listShowMore';
import { sortAds } from './sort-ads';
import { deleteGroup } from './delete-group';

domReady( function () {
	listShowMore();
	sortAds();
	deleteGroup();
} );

import editGroup from './edit-group';
import formSubmission from './form-submission';

jQuery( function () {
	editGroup();
	formSubmission();
} );
