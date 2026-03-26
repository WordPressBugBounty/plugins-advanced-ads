import domReady from '@wordpress/dom-ready';

import './common.css';

import './toaster';

import { tabs } from './tabs';
import { modal } from './modal';
import { toggles } from './toggles';
import { screenOptions } from './screen-options';

domReady( function () {
	screenOptions();
	tabs();
	toggles();
	modal();
} );
