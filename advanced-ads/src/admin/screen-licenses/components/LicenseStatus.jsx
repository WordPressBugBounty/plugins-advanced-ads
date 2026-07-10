/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal Dependencies
 */
import { clsx } from '@admin/utils';

export function LicenseStatus( { status, showRenew = false, onRenew } ) {
	const isActiveStatus = status === 'active' || status === 'valid';
	const isInactiveStatus = status === 'inactive';
	const isExpiredStatus = status === 'expired';

	return (
		<span className="inline-flex items-center gap-2">
			<span
				className={ clsx(
					'inline-block h-2 w-2 shrink-0 rounded-full',
					isActiveStatus && 'bg-green-500',
					isExpiredStatus && 'bg-red-500',
					isInactiveStatus && 'bg-orange-500',
					! isActiveStatus &&
						! isExpiredStatus &&
						! isInactiveStatus &&
						'bg-orange-500'
				) }
				aria-hidden
			/>
			<span className="capitalize">{ status }</span>
			{ showRenew && onRenew ? (
				<button
					type="button"
					className="button button-link underline! underline-offset-3"
					onClick={ onRenew }
				>
					{ __( 'Renew', 'advanced-ads' ) }
				</button>
			) : null }
		</span>
	);
}
