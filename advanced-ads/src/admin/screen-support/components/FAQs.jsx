/**
 * External Dependencies
 */
import { __ } from '@wordpress/i18n';
import { CircleArrowRight, MessageSquare } from 'lucide-react';

/**
 * Internal Dependencies
 */
import { Card } from '@admin/components/Card';
import { Accordion } from '@admin/components/Accordion';
import { FAQS } from '../faqs';

export function FAQs() {
	const isLicensed = false;

	return (
		<Card className="advads-card grid sm:grid-cols-3 gap-5">
			<div>
				<Card.HeaderIcon>
					<MessageSquare />
				</Card.HeaderIcon>

				<Card.HeaderTitle>
					{ __( "FAQ's", 'advanced-ads' ) }
				</Card.HeaderTitle>

				<p>
					{ __(
						'Everything you need to know about the product.',
						'advanced-ads'
					) }
				</p>
				{ ! isLicensed && (
					<>
						<p>
							{ __(
								'Couldn’t find what you were looking for?',
								'advanced-ads'
							) }
							<br />
							{ __(
								'Get help from the community.',
								'advanced-ads'
							) }
						</p>
						<p>
							<a
								className="advads-view-all-link"
								href="https://wordpress.org/support/plugin/advanced-ads/"
								target="_blank"
								rel="noreferrer"
							>
								{ __(
									'Visit WordPress Forum',
									'advanced-ads'
								) }
								<CircleArrowRight />
							</a>
						</p>
					</>
				) }
			</div>

			<Accordion className="col-span-2">
				{ FAQS.map( ( faq ) => (
					<Accordion.Item key={ faq.title } { ...faq } />
				) ) }
			</Accordion>
		</Card>
	);
}
