/**
 * Internal Dependencies
 */
import { clsx } from '@admin/utils';

export function LicenseField( { label, value, className } ) {
	return (
		<div className={ clsx( 'min-w-0 space-y-1', className ) }>
			<div className="text-sm font-semibold">{ label }</div>
			<div className="text-sm text-gray-500">{ value }</div>
		</div>
	);
}
