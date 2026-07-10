import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';

import './main.css';
import { Layout } from './layout/Layout';

domReady( () => {
	createRoot( document.getElementById( 'advads-app' ) ).render( <Layout /> );
} );
