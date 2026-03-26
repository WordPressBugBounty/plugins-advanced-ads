/**
 * External Dependencies
 */
const path = require( 'node:path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const { getWebpackEntryPoints } = require( '@wordpress/scripts/utils/config' );

const isProduction = process.env.NODE_ENV === 'production';

if ( ! isProduction ) {
	defaultConfig.devServer.allowedHosts = 'all';
}

const basePath = path.resolve( __dirname, 'src' );

module.exports = {
	...defaultConfig,
	module: {
		...defaultConfig.module,
		rules: defaultConfig.module.rules.map( ( rule ) => {
			// Check if this rule handles CSS files
			if ( rule.test?.test?.( '.css' ) ) {
				return {
					...rule,
					use: Array.isArray( rule.use )
						? rule.use.map( ( useEntry ) => {
								// Handle both string and object loader formats
								const loaderName =
									typeof useEntry === 'string'
										? useEntry
										: useEntry.loader;

								// Only modify css-loader, not postcss-loader
								if (
									loaderName?.includes( 'css-loader' ) &&
									! loaderName?.includes( 'postcss' )
								) {
									return {
										loader: loaderName,
										options: {
											...( typeof useEntry === 'object'
												? useEntry.options
												: {} ),
											url: false, // Disable all URL processing
										},
									};
								}
								return useEntry;
						  } )
						: rule.use,
				};
			}
			return rule;
		} ),
	},
	externals: {
		...defaultConfig.externals,

		// Global.
		window: 'window',
		jquery: 'jQuery',
		lodash: 'lodash',
		moment: 'moment',

		// Advanced Ads.
		'@advancedAds': 'advancedAds',
		'@advancedAds/i18n': 'advancedAds.i18n',
		'@advancedAds/utils': 'advancedAds.utils',

		// WordPress.
		'@wordpress/dom-ready': 'wp.domReady',
		'@wordpress/hooks': 'wp.hooks',
		'@wordpress/commands': 'wp.commands',
		'@wordpress/i18n': 'wp.i18n',
		'@wordpress/url': 'wp.url',
		'@wordpress/data': 'wp.data',
		'@wordpress/core-data': 'wp.coreData',
		'@wordpress/element': 'wp.element',
		'@wordpress/plugins': 'wp.plugins',
	},
	resolve: {
		...defaultConfig.resolve,
		alias: {
			...defaultConfig.resolve.alias,
			'@root': path.join( __dirname, 'assets/src' ),
			'@components': path.join( __dirname, 'assets/src/components' ),
			'@utilities': path.join( __dirname, 'assets/src/utilities' ),
		},
	},
	entry: {
		...getWebpackEntryPoints(),
		// Backend.
		'admin-common': path.join( basePath, 'admin/common/common.js' ),
		'screen-dashboard': path.join(
			basePath,
			'admin/screen-dashboard/index.js'
		),
		'screen-ads-listing': path.join(
			basePath,
			'admin/screen-ads/listing.js'
		),
		'screen-ads-editing': path.join(
			basePath,
			'admin/screen-ads/editing.js'
		),
		'screen-groups-listing': path.join(
			basePath,
			'admin/screen-groups/listing.js'
		),
		'screen-placements-listing': path.join(
			basePath,
			'admin/screen-placements/listing.js'
		),
		'screen-settings': path.join(
			basePath,
			'admin/screen-settings/index.js'
		),
		'screen-support': path.join(
			basePath,
			'admin/screen-support/support.js'
		),
		'screen-tools': path.join( basePath, 'admin/screen-tools/tools.js' ),
		'wp-dashboard': path.join( basePath, 'admin/wp-dashboard/index.js' ),
		notifications: path.join(
			basePath,
			'admin/notifications/notifications.js'
		),
		'post-quick-edit': path.join(
			basePath,
			'admin/post-quick-edit/listing.js'
		),
		commands: path.join( basePath, 'admin/commands/commands.js' ),

		// Frontend.
		advanced: path.join( basePath, 'public/advanced.js' ),
	},
	output: {
		filename: '[name].js', // Dynamically generate output file names
		path: path.resolve( __dirname, 'assets/dist' ),
	},
};

/** TODO: convert old files to new system */
/**
 * JavaScript Files
 */
// mix.js('public/assets/js/advanced.js', 'public/assets/js/advanced.min.js');
// mix.js('public/assets/js/ready.js', 'public/assets/js/ready.min.js');
// mix.js(
// 	'public/assets/js/ready-queue.js',
// 	'public/assets/js/ready-queue.min.js'
// );
// mix.js(
// 	'public/assets/js/frontend-picker.js',
// 	'public/assets/js/frontend-picker.min.js'
// );
// mix.js(
// 	'modules/adblock-finder/public/adblocker-enabled.js',
// 	'modules/adblock-finder/public/adblocker-enabled.min.js'
// );
// mix.js(
// 	[
// 		'modules/adblock-finder/public/adblocker-enabled.js',
// 		'modules/adblock-finder/public/ga-adblock-counter.js',
// 	],
// 	'modules/adblock-finder/public/ga-adblock-counter.min.js'
// );

// // New files
// // React
// mix.js(
// 	'assets/src/screen-onboarding/onboarding.js',
// 	'assets/js/screen-onboarding.js'
// ).react();

// mix.js(
// 	'assets/src/oneclick/main.js',
// 	'assets/js/admin/oneclick-onboarding.js'
// ).react();
