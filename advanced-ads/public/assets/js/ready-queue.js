( function () {
	window.advanced_ads_ready_queue = window.advanced_ads_ready_queue || [];

	// replace native push method with our advanced_ads_ready function; do this early to prevent race condition between pushing and the loop.
	window.advanced_ads_ready_queue.push = window.advanced_ads_ready;

	// handle all callbacks that have been added to the queue previously.
	for ( let i = 0, length = window.advanced_ads_ready_queue.length; i < length; i ++ ) {
		window.advanced_ads_ready( window.advanced_ads_ready_queue[i] );
	}
} )();
