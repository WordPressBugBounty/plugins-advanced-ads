import domReady from '@wordpress/dom-ready';
import { versionControl } from './version-control';
import { openTabByHash } from '../partials/tabs';

import './tools.css';

domReady( function () {
	versionControl();
	openTabByHash();
} );
