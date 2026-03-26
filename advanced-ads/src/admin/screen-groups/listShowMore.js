export function listShowMore() {
	const triggers = document.querySelectorAll(
		'.advads-group-ads-list-show-more'
	);

	triggers.forEach( ( trigger ) => {
		trigger.addEventListener( 'click', function () {
			this.style.display = 'none';

			const target = this.parentElement.previousElementSibling;
			if ( target ) {
				const divs = target.querySelectorAll( 'div' );
				divs.forEach( ( div ) => {
					div.style.display = '';
				} );
			}
		} );
	} );
}
