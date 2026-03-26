import domReady from '@wordpress/dom-ready';

import './support.css';

import { searchCombobox } from './autocomplete';
import { ticketForm } from './submit-ticket';

domReady( function () {
	searchCombobox(
		'advads-support-search',
		'suggestions-list',
		'https://wpadvancedads.com/wp-json/wp/v2/search?subtype=post,bwl_kb&search={{search}}',
		( suggestion ) => {
			const subtitle =
				suggestion.subtype === 'post' ? 'Turotials' : 'Knowledge Base';
			return `
				<span class="main-text">${ suggestion.title }</span>
				<span class="secondary-text">${ subtitle }</span>
			`;
		},
		( suggestion, input ) => {
			window.open(
				suggestion.url +
					'?utm_source=advanced-ads&utm_medium=link&utm_campaign=plugin_support_searchbox',
				'_blank'
			);
			input.value = '';
		}
	);
	ticketForm();
} );
