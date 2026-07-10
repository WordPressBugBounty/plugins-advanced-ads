/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import { useEffect, useRef, useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';

/**
 * Internal Dependencies
 */
import { STORE_NAME } from '@admin/store';
import { getManualInstallGuideUrl } from '../addon-catalog';
import {
	activateLicenseOnShop,
	deactivateLicenseOnSite,
	extractApiErrorMessage,
	extractApiMessage,
} from '../hooks/licenses-api';
import {
	clearLicenseNoticeDisplayMeta,
	publishLicenseSuccessNotice,
	publishLicenseWarningNotice,
} from './LicenseNotices';

const ACTIVATION_NOTICE_ID = 'advanced-ads/license-activation';

export function DownloadActivateButton( {
	licenseKey,
	isActivated = false,
	canActivate = true,
	noticesContext,
	downloadUrl = '',
	addonId = '',
} ) {
	const [ isSubmitting, setIsSubmitting ] = useState( false );
	const [ installFailed, setInstallFailed ] = useState( false );
	const abortRef = useRef( null );
	const dismissTimerRef = useRef( null );
	const { createSuccessNotice, createErrorNotice, removeNotice } =
		useDispatch( noticesStore );
	const licensesFromStore = useSelect(
		( select ) => select( STORE_NAME ).getLicenses(),
		[]
	);

	useEffect( () => {
		return () => {
			if ( abortRef.current ) {
				abortRef.current.abort();
			}
			if ( dismissTimerRef.current ) {
				clearTimeout( dismissTimerRef.current );
			}
		};
	}, [] );

	function hasNoticeMessage( value ) {
		return (
			value !== undefined &&
			value !== null &&
			String( value ).trim() !== ''
		);
	}

	function showNotice( type, apiMessage ) {
		removeNotice( ACTIVATION_NOTICE_ID, noticesContext );
		clearLicenseNoticeDisplayMeta( ACTIVATION_NOTICE_ID );

		if ( type === 'error' ) {
			const title = __(
				"Automatic setup didn't complete",
				'advanced-ads'
			);
			const message = hasNoticeMessage( apiMessage )
				? String( apiMessage ).trim()
				: __( 'Failed to activate license.', 'advanced-ads' );

			publishLicenseWarningNotice( createErrorNotice, {
				id: ACTIVATION_NOTICE_ID,
				context: noticesContext,
				title,
				message,
			} );
		} else {
			const title = __( 'All set', 'advanced-ads' );
			const message = hasNoticeMessage( apiMessage )
				? String( apiMessage ).trim()
				: __(
						'Your license and plugin are fully set up and ready to use.',
						'advanced-ads'
				  );
			const icon = hasNoticeMessage( apiMessage ) ? 'loading' : 'success';

			publishLicenseSuccessNotice( createSuccessNotice, {
				id: ACTIVATION_NOTICE_ID,
				context: noticesContext,
				title,
				message,
				icon,
			} );
		}

		if ( dismissTimerRef.current ) {
			clearTimeout( dismissTimerRef.current );
		}
		dismissTimerRef.current = setTimeout( () => {
			clearLicenseNoticeDisplayMeta( ACTIVATION_NOTICE_ID );
			removeNotice( ACTIVATION_NOTICE_ID, noticesContext );
		}, 6500 );
	}

	async function handleActivate() {
		if ( isSubmitting || isActivated || ! canActivate ) {
			return;
		}

		const utmUrl = `${ advancedAds.endpoints.shopUrl }/?utm_source=advancedads&utm_medium=in-plugin&utm_campaign=a2-in_plugin-licenses_addons-download_activate`;
		fetch( utmUrl, { mode: 'no-cors', keepalive: true } );

		if ( abortRef.current ) {
			abortRef.current.abort();
		}

		const controller = new AbortController();
		abortRef.current = controller;

		setIsSubmitting( true );
		setInstallFailed( false );

		try {
			const result = await activateLicenseOnShop(
				licenseKey,
				controller.signal
			);
			showNotice( 'success', extractApiMessage( result ) );
		} catch ( err ) {
			if ( err?.name === 'AbortError' ) {
				return;
			}
			const errorMessage = extractApiErrorMessage( err );
			setInstallFailed( true );
			showNotice( 'error', errorMessage );
		} finally {
			setIsSubmitting( false );
		}
	}

	async function handleDeactivate() {
		if ( isSubmitting || ! isActivated || ! licenseKey ) {
			return;
		}

		setIsSubmitting( true );

		try {
			await deactivateLicenseOnSite( licenseKey, licensesFromStore );
		} catch ( err ) {
			const errorMessage =
				err instanceof Error
					? err.message
					: __( 'Deactivation failed.', 'advanced-ads' );
			const noticeId = 'advanced-ads/license-deactivation';

			removeNotice( noticeId, noticesContext );
			publishLicenseWarningNotice( createErrorNotice, {
				id: noticeId,
				context: noticesContext,
				message: errorMessage,
			} );
		} finally {
			setIsSubmitting( false );
		}
	}

	// eslint-disable-next-line no-unused-vars
	function handleManualInstall() {
		const packageUrl = String( downloadUrl ?? '' ).trim();
		const guideUrl = getManualInstallGuideUrl( addonId );

		if ( packageUrl ) {
			window.open( packageUrl, '_blank', 'noopener,noreferrer' );
		}

		window.open( guideUrl, '_blank', 'noopener,noreferrer' );
	}

	if ( isActivated ) {
		return (
			<div className="flex flex-col items-end">
				<button
					type="button"
					className="button advads-button-secondary whitespace-nowrap px-4 py-2"
					onClick={ handleDeactivate }
					disabled={ isSubmitting }
					aria-busy={ isSubmitting }
				>
					{ isSubmitting
						? __( 'Deactivating…', 'advanced-ads' )
						: __( 'Deactivate', 'advanced-ads' ) }
				</button>
			</div>
		);
	}

	const isDisabled = isSubmitting || ! licenseKey || ! canActivate;

	const buttonLabel = isSubmitting
		? __( 'Activating…', 'advanced-ads' )
		: __( 'Download and activate', 'advanced-ads' );

	return (
		<div className="flex flex-col items-end gap-2">
			<button
				type="button"
				className="button advads-button-neutral whitespace-nowrap px-4 py-2"
				onClick={ handleActivate }
				disabled={ isDisabled }
				aria-busy={ isSubmitting }
				aria-disabled={ isDisabled }
			>
				{ buttonLabel }
			</button>
		</div>
	);
}
