/**
 * WordPress Dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { X } from 'lucide-react';
import { Modal } from '@wordpress/components';

/**
 * Internal Dependencies
 */
import { PricingTable } from './PricingTable';

/**
 * Fullscreen pricing comparison inside a modal overlay.
 *
 * @param {Object}                   props
 * @param {() => void}               props.onClose
 * @param {string}                   [props.currentPlanLabel]
 * @param {'pro'|'all-access-single'|'all-access-five'|null} [props.currentPlanId]
 * @param {(planId: string) => void} [props.onSelectPlan]
 * @param {string}                   props.currentActionLabel
 */
export function PricingModal( {
	onClose,
	currentPlanLabel = __( 'Free', 'advanced-ads' ),
	currentPlanId = null,
	onSelectPlan,
	currentActionLabel = __( 'Upgrade plan', 'advanced-ads' ),
} ) {
	return (
		<Modal
			title={ currentActionLabel }
			__experimentalHideHeader
			onRequestClose={ onClose }
			shouldCloseOnEscape={ true }
			shouldCloseOnClickOutside={ false }
			className="advads-modal bg-gray-100 max-h-screen! max-w-screen! h-screen! w-screen! rounded-none! m-0! translate-x-0! translate-y-0! overflow-y-auto"
			overlayClassName="bg-black/50 backdrop-blur-[1px]"
		>
			<div className="mx-auto w-full max-w-7xl">
				<header className="flex items-center mb-10 text-left">
					<div>
						<h2 className="text-3xl font-bold text-black md:text-4xl mb-4">
							{ currentActionLabel }
						</h2>
						<p className="m-0 text-base text-gray-600">
							{ sprintf(
								/* translators: %s: Current plan name (e.g. Free). */
								__( 'Current plan: %s', 'advanced-ads' ),
								currentPlanLabel
							) }
						</p>
					</div>
					<button
						type="button"
						className="button is-ghost ml-auto"
						onClick={ onClose }
					>
						<X
							className="size-8 text-gray-400"
							strokeWidth={ 1.4 }
						/>
					</button>
				</header>
				<PricingTable
					currentPlanId={ currentPlanId }
					onSelectPlan={ onSelectPlan }
				/>
			</div>
		</Modal>
	);
}
