/**
 * Check if an ad blocker is enabled.
 *
 * @param function callback A callback function that is executed after the check has been done.
 *                 The 'isEnabled' (bool) variable is passed as the callback's first argument.
 */
window.advanced_ads_check_adblocker = ( function () {
	let pendingCallbacks = [];
	let isEnabled = null;

	function RAF( rafCallback ) {
		const fn =
			window.requestAnimationFrame ||
			window.mozRequestAnimationFrame ||
			window.webkitRequestAnimationFrame ||
			function ( fallbackCallback ) {
				return setTimeout( fallbackCallback, 16 );
			};

		fn.call( window, rafCallback );
	}

	RAF( function () {
		// Create a bait.
		const ad = document.createElement( 'div' );
		ad.innerHTML = '&nbsp;';
		ad.setAttribute(
			'class',
			'ad_unit ad-unit text-ad text_ad pub_300x250'
		);
		ad.setAttribute(
			'style',
			'width: 1px !important; height: 1px !important; position: absolute !important; left: 0px !important; top: 0px !important; overflow: hidden !important;'
		);
		document.body.appendChild( ad );

		RAF( function () {
			const styles = window.getComputedStyle?.( ad );
			const mozBinding = styles?.getPropertyValue( '-moz-binding' );

			isEnabled =
				( styles && styles.getPropertyValue( 'display' ) === 'none' ) ||
				( typeof mozBinding === 'string' &&
					mozBinding.indexOf( 'about:' ) !== -1 );

			ad.remove();

			// Call pending callbacks.
			for (
				let i = 0, length = pendingCallbacks.length;
				i < length;
				i++
			) {
				pendingCallbacks[ i ]( isEnabled );
			}
			pendingCallbacks = [];
		} );
	} );

	return function ( callback ) {
		// eslint-disable-next-line camelcase
		if ( 'undefined' === typeof advanced_ads_adblocker_test ) {
			isEnabled = true;
		}
		if ( isEnabled === null ) {
			pendingCallbacks.push( callback );
			return;
		}
		// Run the callback immediately
		callback( isEnabled );
	};
} )();
