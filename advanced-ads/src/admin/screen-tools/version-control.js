/* eslint-disable no-console */
import { endpoints } from '@advancedAds';

/**
 * Get usable versions.
 */
function getUsableVersions() {
	const nonceField = document.getElementById( 'version-control-nonce' );

	fetch( endpoints.ajaxUrl, {
		method: 'POST',
		body: new URLSearchParams( {
			action: 'advads_get_usable_versions',
			nonce: nonceField ? nonceField.value : '',
		} ),
	} )
		.then( ( response ) => response.json() )
		.then( ( response ) => {
			const versions = [];
			const versionSelect = document.getElementById( 'plugin-version' );

			response.data.order.forEach( ( number, index ) => {
				const option = document.createElement( 'option' );
				option.value = `${ number }|${ response.data.versions[ number ] }`;
				option.textContent = number;
				if ( index === 0 ) {
					option.selected = true;
				}
				versions.push( option );
			} );

			versionSelect.innerHTML = '';
			versions.forEach( ( option ) =>
				versionSelect.appendChild( option )
			);
			versionSelect.disabled = false;

			const installButton = document.getElementById( 'install-version' );
			if ( installButton ) {
				installButton.disabled = false;
			}
		} )
		.catch( ( error ) => {
			console.error( error );
		} );
}

/**
 * Launch the installation process
 *
 * @param {FormData} formData Form data instance.
 */
function installVersion( formData ) {
	const versionSelect = document.getElementById( 'plugin-version' );
	const installButton = document.getElementById( 'install-version' );
	const spinner = installButton
		? installButton.parentElement.querySelector( '.spinner' )
		: null;

	if ( versionSelect ) {
		versionSelect.disabled = true;
	}
	if ( installButton ) {
		installButton.disabled = true;
	}
	if ( spinner ) {
		spinner.style.visibility = 'visible';
	}

	formData.append( 'action', 'advads_install_alternate_version' );

	fetch( endpoints.ajaxUrl, {
		method: 'POST',
		body: formData,
	} )
		.then( ( response ) => response.json() )
		.then( ( response ) => {
			if ( response.data && response.data.redirect ) {
				document.location.href = response.data.redirect;
			}
		} )
		.catch( ( error ) => {
			console.error( error );
		} )
		.finally( () => {
			if ( versionSelect ) {
				versionSelect.disabled = false;
			}
			if ( installButton ) {
				installButton.disabled = false;
			}
			if ( spinner ) {
				spinner.style.visibility = 'hidden';
			}
		} );
}

export function versionControl() {
	const form = document.getElementById( 'alternative-version' );
	if ( form ) {
		form.addEventListener( 'submit', ( event ) => {
			event.preventDefault();
			installVersion( new FormData( form ) );
		} );
	}

	const pluginVersion = document.getElementById( 'plugin-version' );
	if ( pluginVersion && ! pluginVersion.value ) {
		getUsableVersions();
	}
}
