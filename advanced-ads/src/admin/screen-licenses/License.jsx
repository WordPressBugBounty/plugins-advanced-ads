/**
 * Internal Dependencies
 */
import { EmptyState } from './components/EmptyState';
import { Loader } from './components/Loader';
import { LicenseItem } from './components/LicenseItem';
import {
	LicenseNotices,
	LICENSE_NOTICES_CONTEXT,
} from './components/LicenseNotices';
import { useLicenseData } from './hooks/useLicenseData';

export function License() {
	const { licenses, appliedAddonKeyMap, hasLicenses, isLoading, error } =
		useLicenseData();

	if ( isLoading ) {
		return (
			<>
				<LicenseNotices isLoading={ isLoading } />
				<Loader />
			</>
		);
	}

	if ( ! hasLicenses ) {
		return (
			<>
				<LicenseNotices isLoading={ isLoading } />
				{ error?.message && (
					<div
						className="mb-6 p-3 border border-red-200 bg-red-50 text-red-700 rounded"
						role="alert"
					>
						{ error.message }
					</div>
				) }
				<EmptyState />
			</>
		);
	}

	return (
		<>
			<LicenseNotices isLoading={ isLoading } />
			<div className="flex flex-col gap-6">
				{ licenses.map( ( license ) => (
					<LicenseItem
						key={ license.licenseId ?? license.licenseKey }
						{ ...license }
						allLicenses={ licenses }
						appliedAddonKeyMap={ appliedAddonKeyMap }
						noticesContext={ LICENSE_NOTICES_CONTEXT }
					/>
				) ) }
			</div>
		</>
	);
}
