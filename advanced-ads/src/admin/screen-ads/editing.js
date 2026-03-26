import jQuery from 'jquery';

import './editing.css';

import adTypes from './ad-types';
import placementBox from './placement-box';
import codeHighlighter from './code-highlighter';

jQuery( function () {
	adTypes();
	placementBox();
	codeHighlighter();
} );
