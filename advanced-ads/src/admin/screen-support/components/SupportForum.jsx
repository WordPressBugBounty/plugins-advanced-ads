/**
 * External Dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal Dependencies
 */
import { Card } from '@admin/components/Card';

export function SupportForum() {
	const p1 = __(
		'Upgrade to <strong>A2 Pro</strong> to get:',
		'advanced-ads'
	);

	return (
		<Card className="advads-card bg-gray-100 flex flex-col">
			<div>
				<h3 className="mt-0 mb-2">
					{ __( 'Need more help?', 'advanced-ads' ) }
				</h3>
				<p>
					{ __(
						'On the free plan, support is available via guides and community forums.',
						'advanced-ads'
					) }
				</p>
				<p dangerouslySetInnerHTML={ { __html: p1 } } />
				<ul className="list-disc list-inside">
					<li>
						{ __(
							'Priority support from our team',
							'advanced-ads'
						) }
					</li>
					<li>
						{ __(
							'Help directly inside the plugin',
							'advanced-ads'
						) }
					</li>
					<li>{ __( 'Faster issue resolution', 'advanced-ads' ) }</li>
				</ul>
			</div>

			<div className="mt-auto">
				<a
					href="https://wpadvancedads.com/checkout/?edd_action=add_to_cart&download_id=95170&utm_source=advanced-ads&utm_medium=link&utm_campaign=plugin_support_need_help_upgrade"
					className="button advads-button-secondary is-block mt-6"
				>
					{ __( 'Upgrade to Premium', 'advanced-ads' ) }
				</a>
			</div>
		</Card>
	);
}
