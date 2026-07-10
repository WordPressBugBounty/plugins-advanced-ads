/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import { Modal, ToggleControl } from '@wordpress/components';

/**
 * Internal Dependencies
 */
import { LICENSE_ADDON_CATALOG } from '../addon-catalog';

/**
 * @param {Object}   props
 * @param {Array}    props.toggles    Rows: { addonId, label?, enabled }.
 * @param {Function} props.onToggle   ( addonId, enabled ) => void.
 * @param {string}   [props.savingId] Add-on id currently saving.
 * @param {Function} props.onClose    Close handler.
 */
export function AutoUpdateModal( { toggles, onToggle, savingId = '', onClose } ) {
	const isSingle = toggles.length === 1;

	return (
		<Modal
			title={ __( 'Plugin auto-updates', 'advanced-ads' ) }
			size="small"
			onRequestClose={ onClose }
			shouldCloseOnEscape={ true }
			shouldCloseOnClickOutside={ false }
			className="advads-modal mt-auto w-lg max-w-lg"
			overlayClassName="bg-black/50 backdrop-blur-[1px]"
		>
			<div className="space-y-4">
				{ toggles.map( ( toggle ) => {
					const { addonId, enabled } = toggle;
					const label =
						toggle.label ??
						LICENSE_ADDON_CATALOG[ addonId ]?.title ??
						( addonId === 'main'
							? __( 'Advanced Ads', 'advanced-ads' )
							: addonId );
					const isSaving = savingId === addonId;

					return (
						<ToggleControl
							key={ addonId }
							checked={ enabled }
							disabled={ isSaving }
							label={
								isSingle
									? __(
											'Enable plugin auto-updates',
											'advanced-ads'
									  )
									: label
							}
							help={
								isSingle
									? __(
											"When enabled, updates are automatically installed. When off, you'll be notified when updates are available to install.",
											'advanced-ads'
									  )
									: undefined
							}
							onChange={ ( checked ) =>
								onToggle( addonId, checked )
							}
						/>
					);
				} ) }
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
