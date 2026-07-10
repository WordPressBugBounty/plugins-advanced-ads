/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import { Modal } from '@wordpress/components';

export function SitesModal( { title, description, sites, onClose } ) {
	return (
		<Modal
			title={ title }
			size="small"
			onRequestClose={ onClose }
			shouldCloseOnEscape={ true }
			shouldCloseOnClickOutside={ false }
			className="advads-modal mt-auto w-lg max-w-lg"
			overlayClassName="bg-black/50 backdrop-blur-[1px]"
		>
			<p className="-mt-2 text-base text-gray-500">{ description }</p>
			<div className="border border-gray-200 rounded-lg bg-gray-50 divide-y divide-gray-200">
				{ sites.map( ( site ) => (
					<div
						className="flex justify-between items-center p-3"
						key={ site.domain }
					>
						<a
							href={ `${ site.domain }${
								String( site.domain ).includes( '?' )
									? '&'
									: '?'
							}utm_source=advancedads&utm_medium=in-plugin&utm_campaign=a2-in_plugin-licenses_addons-view_sites` }
							target="_blank"
							rel="noopener noreferrer"
							className="text-base no-underline"
						>
							{ site.domain
								.replace( 'https://', '' )
								.replace( 'http://', '' )
								.trim( '/' ) }
						</a>
						<span className="text-sm text-gray-500">
							{ site.createdAt }
						</span>
					</div>
				) ) }
			</div>
			<div className="flex justify-end mt-4">
				<button
					type="button"
					className="button advads-button-secondary"
					onClick={ onClose }
				>
					{ __( 'Close', 'advanced-ads' ) }
				</button>
			</div>
		</Modal>
	);
}
