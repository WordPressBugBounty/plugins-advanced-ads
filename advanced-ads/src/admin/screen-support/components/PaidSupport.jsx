/**
 * External Dependencies
 */
import { __ } from '@wordpress/i18n';
import { Folder } from 'lucide-react';
import { useState } from '@wordpress/element';

/**
 * Internal Dependencies
 */
import { Card } from '@admin/components/Card';
import { TicketModal } from './TicketModal';

export function PaidSupport() {
	const [ isTicketModalOpen, setIsTicketModalOpen ] = useState( false );
	const p1 = __(
		'As a paid user, you receive <strong>priority, premium support from our specialists</strong>. Submit a support ticket and get fast, tailored assistance to resolve your request.',
		'advanced-ads'
	);

	const openTicketModal = () => {
		setIsTicketModalOpen( true );
	};

	const closeTicketModal = () => {
		setIsTicketModalOpen( false );
	};

	return (
		<Card className="advads-card bg-gray-100 flex flex-col">
			<div>
				<Card.Header>
					<Card.HeaderIcon size="stroke-gray-700 size-6">
						<Folder />
					</Card.HeaderIcon>
					<Card.HeaderTitle>
						{ __( 'Access Priority Support', 'advanced-ads' ) }
					</Card.HeaderTitle>
				</Card.Header>

				<p dangerouslySetInnerHTML={ { __html: p1 } } />
			</div>

			<div className="mt-auto">
				<button
					type="button"
					data-dialog="advads-create-ticket"
					className="button advads-button-secondary is-block w-full"
					onClick={ openTicketModal }
				>
					{ __( 'Submit a ticket', 'advanced-ads' ) }
				</button>
			</div>
			{ isTicketModalOpen && (
				<TicketModal closeModal={ closeTicketModal } />
			) }
		</Card>
	);
}
