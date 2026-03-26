import jQuery from 'jquery';

import './dashboard.css';

import welcome from './welcome';
import addonBox from './addon-box';
import backupAdstxt from './backup-adstxt';
import subscribe from './subscribe';

jQuery( () => {
	welcome();
	subscribe();

	jQuery( '#advads-overview' ).on(
		'click',
		'.notice-dismiss',
		function ( event ) {
			event.preventDefault();
			const button = jQuery( this );
			const notice = button.parent();
			notice.fadeOut( 500, function () {
				notice.remove();
			} );
		}
	);

	addonBox();
	backupAdstxt();
} );
