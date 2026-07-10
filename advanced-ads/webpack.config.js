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
		rules: [
			...defaultConfig.module.rules.map( ( rule ) => {
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
												...( typeof useEntry ===
												'object'
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

				if ( rule.test?.toString().includes( 'svg' ) ) {
					return { ...rule, exclude: /\.svg$/i };
				}

				return rule;
			} ),
			{
				test: /\.svg$/i,
				use: [ '@svgr/webpack' ],
			},
		],
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
			'@assets': path.join( __dirname, 'assets' ),
			'@admin': path.join( basePath, 'admin' ),
			'@components': path.join( __dirname, 'assets/src/components' ),
			'@utilities': path.join( __dirname, 'assets/src/utilities' ),
		},
	},
	entry: {
		...getWebpackEntryPoints(),
		// Backend.
		app: path.join( basePath, 'admin/main.js' ),
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
		'frontend-picker': path.join( basePath, 'public/frontend-picker.js' ),
	},
	output: {
		filename: '[name].js', // Dynamically generate output file names
		path: path.resolve( __dirname, 'assets/dist' ),
	},
};
