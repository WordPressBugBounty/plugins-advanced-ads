/**
 * External Dependencies
 */
import { __ } from '@wordpress/i18n';
/**
 * Internal Dependencies
 */
import { Support } from './Support';

export const supportRoute = {
	name: 'support',
	path: '/support',
	link: {
		label: __( 'Support', 'advanced-ads' ),
		exact: true,
		priority: 90,
	},
	header: {
		title: __( 'Support', 'advanced-ads' ),
		breadcrumbTitle: 'Support',
		headerActions: null,
		upgradeUrl:
			'https://wpadvancedads.com/add-ons/?utm_source=advanced-ads&utm_medium=link&utm_campaign=header-upgrade-license',
	},
	areas: {
		content: () => <Support />,
	},
};
