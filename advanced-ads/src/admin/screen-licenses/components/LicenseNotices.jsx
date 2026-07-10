/**
 * WordPress Dependencies
 */
import { useEffect } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';

/**
 * External Dependencies
 */
import { CircleCheck, TriangleAlert, X } from 'lucide-react';

/**
 * Internal Dependencies
 */
import { clsx } from '@admin/utils';
import { buildUrl, useCurrentPath, useCurrentRoute } from '@admin/router';
import { STORE_NAME } from '@admin/store';
import {
	buildPostCheckoutNoticeCopy,
	getDaysUntilLicenseExpiry,
	getHostnameFromUrl,
	getTypeLabel,
	isLicenseExpiredForDisplay,
	isLicenseExpiringSoon,
	LICENSE_PATH,
} from '../utils';

export const LICENSE_NOTICES_CONTEXT = 'advanced-ads/licenses';

const POST_CHECKOUT_NOTICE_ID = 'advanced-ads/post-checkout-success';
const EXPIRY_NOTICE_ID_PREFIX = 'advanced-ads/license-expiry-';
const POST_CHECKOUT_URL_OMIT = {
	token: null,
	purchase_id: null,
	checkout_intent: null,
	license_id: null,
};

const WARNING_NOTICE_OPTIONS = {
	icon: 'warning',
	variant: 'warning',
};

/** WP notices store only persists `content` — display fields live here keyed by notice id. */
const licenseNoticeDisplayMeta = new Map();

export function setLicenseNoticeDisplayMeta( noticeId, meta ) {
	if ( noticeId ) {
		licenseNoticeDisplayMeta.set( noticeId, meta );
	}
}

export function clearLicenseNoticeDisplayMeta( noticeId ) {
	licenseNoticeDisplayMeta.delete( noticeId );
}

function resolveNoticeDisplay( notice ) {
	const meta = licenseNoticeDisplayMeta.get( notice.id ) ?? {};

	return {
		title: meta.title ?? notice.title,
		message:
			meta.message ??
			notice.message ??
			( meta.title || notice.title ? '' : notice.content ),
		messageContent: meta.messageContent ?? notice.messageContent,
		icon: meta.icon ?? notice.icon,
		variant: meta.variant ?? notice.variant,
	};
}

export function publishLicenseSuccessNotice(
	createSuccessNotice,
	{ id, context, title, message, icon = 'none', messageContent }
) {
	setLicenseNoticeDisplayMeta( id, {
		title,
		message,
		icon,
		variant: 'success',
		messageContent,
	} );

	const spoken = [ title, message ].filter( Boolean ).join( ' ' );

	createSuccessNotice( spoken, {
		id,
		type: 'default',
		context,
		isDismissible: true,
	} );
}

export function publishLicenseWarningNotice(
	createErrorNotice,
	{ id, context, title, message, messageContent }
) {
	setLicenseNoticeDisplayMeta( id, {
		title,
		message,
		icon: 'warning',
		variant: 'warning',
		messageContent,
	} );

	const spoken = [ title, message ].filter( Boolean ).join( ' ' );

	createErrorNotice( spoken, {
		id,
		type: 'default',
		context,
		isDismissible: true,
	} );
}

export function buildLicenseWarningNoticeOptions( message, extra = {} ) {
	const { id, title, messageContent, ...rest } = extra;

	if ( id ) {
		setLicenseNoticeDisplayMeta( id, {
			title,
			message,
			messageContent,
			icon: 'warning',
			variant: 'warning',
		} );
	}

	return {
		message,
		...WARNING_NOTICE_OPTIONS,
		...rest,
	};
}

export function buildLicenseSuccessNoticeOptions( {
	title,
	message,
	icon = 'success',
	id,
	messageContent,
	...extra
} ) {
	if ( id ) {
		setLicenseNoticeDisplayMeta( id, {
			title,
			message,
			icon,
			variant: 'success',
			messageContent,
		} );
	}

	return {
		title,
		message,
		icon,
		variant: 'success',
		messageContent,
		...extra,
	};
}

const shownPurchaseIds = new Set();

function LicenseNoticeLoader( { className } ) {
	return (
		<svg
			width="20"
			height="20"
			viewBox="0 0 20 20"
			fill="none"
			xmlns="http://www.w3.org/2000/svg"
			className={ className }
			aria-hidden="true"
		>
			<path
				d="M10.0003 1.66699V5.00033M10.0003 15.0003V18.3337M4.10866 4.10866L6.46699 6.46699M13.5337 13.5337L15.892 15.892M1.66699 10.0003H5.00033M15.0003 10.0003H18.3337M4.10866 15.892L6.46699 13.5337M13.5337 6.46699L15.892 4.10866"
				stroke="currentColor"
				strokeWidth="2"
				strokeLinecap="round"
				strokeLinejoin="round"
			/>
		</svg>
	);
}

const SHOP_QUERY_ERRORS = [
	{
		param: 'advads_upgrade_error',
		value: 'unavailable',
		id: 'advanced-ads/upgrade-unavailable',
		message: __(
			'This license could not be upgraded to the selected plan. Ensure upgrade paths are configured in the shop (Pro → All Access), then try again.',
			'advanced-ads'
		),
	},
	{
		param: 'advads_renew_error',
		value: 'unavailable',
		id: 'advanced-ads/renew-unavailable',
		message: __(
			'This license could not be renewed. It may not be expired, renewals may be disabled on the shop, or the license may be lifetime.',
			'advanced-ads'
		),
	},
	{
		param: 'advads_exchange_error',
		value: 'invalid_token',
		id: 'advanced-ads/exchange-invalid-token',
		message: __(
			'License connection failed. The link may have expired — try connecting again from the license screen.',
			'advanced-ads'
		),
	},
	{
		param: 'advads_exchange_error',
		value: 'network',
		id: 'advanced-ads/exchange-network',
		message: __(
			'Could not reach the license server. Check your connection and try again.',
			'advanced-ads'
		),
	},
	{
		param: 'advads_exchange_error',
		value: 'no_licenses',
		id: 'advanced-ads/exchange-no-licenses',
		message: __(
			'This account has no licenses. Please connect with another account or buy a license.',
			'advanced-ads'
		),
	},
];

function handleActivationQueryErrors( {
	query,
	createErrorNotice,
	removeNotice,
} ) {
	const code = query?.advads_activation_error;
	if ( ! code ) {
		return;
	}

	const customMessage = query?.advads_activation_message;
	let message;

	switch ( code ) {
		case 'network':
			message = __(
				'Could not reach the license server. Check your connection and try again.',
				'advanced-ads'
			);
			break;
		case 'activate_failed':
			message =
				customMessage ||
				__(
					'Failed to activate license on the shop.',
					'advanced-ads'
				);
			break;
		default:
			message =
				customMessage ||
				__( 'Failed to activate license.', 'advanced-ads' );
	}

	showLicenseWarningNotice(
		createErrorNotice,
		removeNotice,
		'advanced-ads/activation-error',
		message
	);
	replaceLicenseUrl( query, {
		advads_activation_error: null,
		advads_activation_message: null,
	} );
}

function getNoticeVariant( notice, display ) {
	if ( display.variant === 'success' || display.variant === 'warning' ) {
		return display.variant;
	}

	return notice.status === 'error' ? 'warning' : 'success';
}

function getNoticeIcon( notice, display ) {
	if (
		display.icon === 'success' ||
		display.icon === 'warning' ||
		display.icon === 'loading' ||
		display.icon === 'none'
	) {
		return display.icon;
	}

	return notice.status === 'error' ? 'warning' : 'none';
}

function LicenseNoticeBanner( { notice, onDismiss } ) {
	const display = resolveNoticeDisplay( notice );
	const variant = getNoticeVariant( notice, display );
	const iconType = getNoticeIcon( notice, display );
	const title = display.title;
	const message = display.message ?? notice.content;
	const messageContent = display.messageContent;

	const containerClasses =
		variant === 'warning'
			? 'border-orange-200 bg-orange-50'
			: 'border-green-200 bg-green-50';

	const titleClasses =
		variant === 'warning' ? 'text-orange-950' : 'text-gray-900';

	const bodyClasses =
		variant === 'warning' ? 'text-orange-900' : 'text-gray-700';

	function renderIcon() {
		if ( iconType === 'loading' ) {
			return (
				<LicenseNoticeLoader className="size-5 shrink-0 text-gray-900" />
			);
		}

		if ( iconType === 'success' ) {
			return (
				<CircleCheck
					className="size-5 shrink-0 text-gray-900"
					aria-hidden="true"
				/>
			);
		}

		if ( iconType === 'warning' ) {
			return (
				<TriangleAlert
					className="size-5 shrink-0 text-orange-950"
					aria-hidden="true"
				/>
			);
		}

		return null;
	}

	return (
		<div
			role="status"
			className={ clsx(
				'advads-license-notice relative mb-6 flex items-start gap-3 rounded-lg border px-4 py-4 pr-12',
				containerClasses
			) }
		>
			{ renderIcon() }
			<div className="min-w-0 flex-1">
				{ title && (
					<p
						className={ clsx(
							'm-0 text-sm font-semibold leading-snug',
							titleClasses
						) }
					>
						{ title }
					</p>
				) }
				{ messageContent ? (
					<div
						className={ clsx(
							'm-0 text-sm leading-snug',
							bodyClasses,
							title && 'mt-0.5'
						) }
					>
						{ messageContent }
					</div>
				) : (
					message && (
						<p
							className={ clsx(
								'm-0 text-sm leading-snug',
								bodyClasses,
								title && 'mt-0.5'
							) }
						>
							{ message }
						</p>
					)
				) }
			</div>
			<button
				type="button"
				className="absolute top-4 right-4 m-0 flex size-4 items-center justify-center border-0 bg-transparent p-0 text-gray-500 shadow-none hover:text-gray-900"
				aria-label={ __( 'Dismiss notice', 'advanced-ads' ) }
				onClick={ onDismiss }
			>
				<X className="size-4" strokeWidth={ 1.5 } aria-hidden="true" />
			</button>
		</div>
	);
}

export function LicenseNotices( {
	context = LICENSE_NOTICES_CONTEXT,
	isLoading,
} ) {
	useLicenseNoticesSync( { isLoading } );

	const notices = useSelect(
		( select ) => select( noticesStore ).getNotices( context ),
		[ context ]
	);
	const { removeNotice } = useDispatch( noticesStore );

	const dismissibleNotices = notices.filter(
		( { isDismissible, type } ) => isDismissible && type === 'default'
	);

	if ( dismissibleNotices.length === 0 ) {
		return null;
	}

	return dismissibleNotices.map( ( notice ) => (
		<LicenseNoticeBanner
			key={ notice.id }
			notice={ notice }
			onDismiss={ () => {
				clearLicenseNoticeDisplayMeta( notice.id );
				removeNotice( notice.id, context );
			} }
		/>
	) );
}

function useLicenseNoticesSync( { isLoading } ) {
	const { query } = useCurrentRoute();
	const path = useCurrentPath();
	const purchaseId = query?.purchase_id ? String( query.purchase_id ) : '';

	const { createSuccessNotice, createErrorNotice, removeNotice } =
		useDispatch( noticesStore );

	const { licenses, hasLicenses, expiryNoticeFlags, appliedAddonKeyMap, addonInstallStates } =
		useSelect( ( select ) => {
			const store = select( STORE_NAME );
			return {
				licenses: store.getLicenses(),
				hasLicenses: store.hasLicenses(),
				expiryNoticeFlags: store.getExpiryNoticeFlags(),
				appliedAddonKeyMap: store.getAppliedAddonKeyMap(),
				addonInstallStates: store.getAddonInstallStates(),
			};
		}, [] );

	useEffect( () => {
		if ( path !== LICENSE_PATH ) {
			return;
		}

		handleShopQueryErrors( { query, createErrorNotice, removeNotice } );
		handleActivationQueryErrors( { query, createErrorNotice, removeNotice } );
		handlePostCheckoutNotice( {
			purchaseId,
			isLoading,
			query,
			licenses,
			appliedAddonKeyMap,
			addonInstallStates,
			createSuccessNotice,
			removeNotice,
		} );
		handleExpiryNotices( {
			isLoading,
			hasLicenses,
			licenses,
			expiryNoticeFlags,
			createErrorNotice,
			removeNotice,
		} );
	}, [
		path,
		query,
		purchaseId,
		isLoading,
		hasLicenses,
		licenses,
		expiryNoticeFlags,
		appliedAddonKeyMap,
		addonInstallStates,
		createErrorNotice,
		createSuccessNotice,
		removeNotice,
	] );
}

export function replaceLicenseUrl( query, omit ) {
	const url = buildUrl( LICENSE_PATH, { ...query, ...omit } );
	globalThis.history.replaceState( {}, '', url );
	globalThis.dispatchEvent( new Event( 'popstate' ) );
}

function licenseNoticeOptions( id, extra = {} ) {
	return {
		id,
		type: 'default',
		context: LICENSE_NOTICES_CONTEXT,
		isDismissible: true,
		...extra,
	};
}

function showLicenseWarningNotice(
	createErrorNotice,
	removeNotice,
	id,
	message
) {
	removeNotice( id, LICENSE_NOTICES_CONTEXT );
	clearLicenseNoticeDisplayMeta( id );
	publishLicenseWarningNotice( createErrorNotice, {
		id,
		context: LICENSE_NOTICES_CONTEXT,
		message,
	} );
}

function expiryNoticeMessage( license, flag ) {
	const { status, expiryDate, name, availableSites } = license;
	const licenseName = getTypeLabel( name, availableSites );

	if ( isLicenseExpiredForDisplay( status, expiryDate ) ) {
		return sprintf(
			/* translators: %s: license product name */
			__(
				'%s has expired. Renew to restore updates and support.',
				'advanced-ads'
			),
			licenseName
		);
	}

	const showExpiringSoon =
		flag === 'month' ||
		flag === 'ten_days' ||
		isLicenseExpiringSoon( expiryDate, 30 );

	if ( ! showExpiringSoon ) {
		return null;
	}

	return sprintf(
		/* translators: 1: license name, 2: days remaining */
		__(
			'%1$s expires in %2$s days. Renew to avoid interruption.',
			'advanced-ads'
		),
		licenseName,
		String( getDaysUntilLicenseExpiry( expiryDate ) )
	);
}

function handleShopQueryErrors( { query, createErrorNotice, removeNotice } ) {
	for ( const entry of SHOP_QUERY_ERRORS ) {
		if ( query?.[ entry.param ] !== entry.value ) {
			continue;
		}

		showLicenseWarningNotice(
			createErrorNotice,
			removeNotice,
			entry.id,
			entry.message
		);
		replaceLicenseUrl( query, { [ entry.param ]: null } );
		break;
	}
}

function handlePostCheckoutNotice( {
	purchaseId,
	isLoading,
	query,
	licenses,
	appliedAddonKeyMap,
	addonInstallStates,
	createSuccessNotice,
	removeNotice,
} ) {
	if ( ! purchaseId || isLoading ) {
		return;
	}

	if ( shownPurchaseIds.has( purchaseId ) ) {
		replaceLicenseUrl( query, POST_CHECKOUT_URL_OMIT );
		return;
	}

	shownPurchaseIds.add( purchaseId );

	const hostname = getHostnameFromUrl(
		globalThis.advancedAds?.endpoints?.siteUrl
	);
	const { title, message } = buildPostCheckoutNoticeCopy( {
		checkoutIntent: query?.checkout_intent,
		licenses,
		licenseId: query?.license_id,
		hostname,
		appliedAddonKeyMap,
		addonInstallStates,
	} );

	removeNotice( POST_CHECKOUT_NOTICE_ID, LICENSE_NOTICES_CONTEXT );
	clearLicenseNoticeDisplayMeta( POST_CHECKOUT_NOTICE_ID );
	publishLicenseSuccessNotice( createSuccessNotice, {
		id: POST_CHECKOUT_NOTICE_ID,
		context: LICENSE_NOTICES_CONTEXT,
		title,
		message,
		icon: 'loading',
	} );

	replaceLicenseUrl( query, POST_CHECKOUT_URL_OMIT );
}

function handleExpiryNotices( {
	isLoading,
	hasLicenses,
	licenses,
	expiryNoticeFlags,
	createErrorNotice,
	removeNotice,
} ) {
	if ( isLoading || ! hasLicenses ) {
		return;
	}

	for ( const license of licenses ) {
		const noticeKey = license.licenseId ?? license.licenseKey;
		if ( ! noticeKey || ! license.licenseKey ) {
			continue;
		}

		const noticeId = `${ EXPIRY_NOTICE_ID_PREFIX }${ noticeKey }`;
		const message = expiryNoticeMessage(
			license,
			expiryNoticeFlags[ license.licenseKey ]
		);

		removeNotice( noticeId, LICENSE_NOTICES_CONTEXT );
		if ( message ) {
			showLicenseWarningNotice(
				createErrorNotice,
				removeNotice,
				noticeId,
				message
			);
		}
	}
}
