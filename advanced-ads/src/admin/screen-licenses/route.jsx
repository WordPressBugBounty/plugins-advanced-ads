/**
 * External Dependencies
 */
import { __ } from '@wordpress/i18n';
/**
 * Internal Dependencies
 */
import { License } from './License';

export const licenseRoute = {
	name: 'license',
	path: '/license',
	link: {
		label: __( 'License', 'advanced-ads' ),
		exact: true,
		priority: 90,
	},
	header: {
		title: __( 'License', 'advanced-ads' ),
		breadcrumbTitle: 'License',
		headerActions: null,
		upgradeUrl:
			'https://wpadvancedads.com/add-ons/?utm_source=advanced-ads&utm_medium=link&utm_campaign=header-upgrade-license',
		manualUrl:
			'https://wpadvancedads.com/manual/?utm_source=advancedads&utm_medium=in-plugin&utm_campaign=a2-in_plugin-licenses_addons-header-manual',
	},
	areas: {
		content: () => <License />,
	},
};
