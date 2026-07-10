/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal Dependencies
 */
import { clsx } from '@admin/utils';
import {
	getAllAccessAddonsInstallSummary,
	getAllAccessAddonsSummaryLabel,
} from '../utils';

/**
 * Add-on setup summary for the All Access license card (none / partial / all installed).
 *
 * @param {Object}                 props
 * @param {string[]}               props.addonIds
 * @param {Object<string, object>} props.addonInstallStates
 * @param {Object<string, string>} props.appliedAddonKeyMap
 * @param {string}                 props.licenseKey
 */
export function AllAccessAddonsSummary( {
	addonIds,
	addonInstallStates,
	appliedAddonKeyMap,
	licenseKey,
} ) {
	const summary = getAllAccessAddonsInstallSummary(
		addonIds,
		addonInstallStates,
		appliedAddonKeyMap,
		licenseKey
	);
	const label = getAllAccessAddonsSummaryLabel( summary );

	return (
		<span className="inline-flex items-center gap-2 text-sm text-gray-800">
			<span
				className={ clsx(
					'inline-block h-2 w-2 shrink-0 rounded-full',
					summary.state === 'complete' && 'bg-green-500',
					summary.state === 'partial' && 'bg-orange-500',
					summary.state === 'none' && 'bg-gray-400'
				) }
				aria-hidden
			/>
			<span>{ label }</span>
		</span>
	);
}
