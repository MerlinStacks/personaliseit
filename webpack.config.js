/* eslint-disable import/no-extraneous-dependencies */

const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const DependencyExtractionPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );
const path = require( 'path' );

// WooCommerce Blocks packages that are not handled by the default plugin.
const WC_EXTERNALS = {
	'@woocommerce/blocks-checkout': [ 'wc', 'blocksCheckout' ],
	'@woocommerce/block-data': [ 'wc', 'wcBlocksData' ],
};
const WC_HANDLES = {
	'@woocommerce/blocks-checkout': 'wc-blocks-checkout',
	'@woocommerce/block-data': 'wc-block-data',
};

class RemoveSourceMapCommentsPlugin {
	apply( compiler ) {
		compiler.hooks.thisCompilation.tap(
			'RemoveSourceMapCommentsPlugin',
			( compilation ) => {
				const { RawSource } = compiler.webpack.sources;
				compilation.hooks.processAssets.tap(
					{
						name: 'RemoveSourceMapCommentsPlugin',
						stage: compiler.webpack.Compilation
							.PROCESS_ASSETS_STAGE_OPTIMIZE,
					},
					( assets ) => {
						Object.keys( assets ).forEach( ( filename ) => {
							if ( ! /\.(?:js|css)$/.test( filename ) ) {
								return;
							}

							const source = assets[ filename ]
								.source()
								.toString()
								.replace(
									/^\/\/# sourceMappingURL=.*(?:\r?\n)?/gm,
									''
								)
								.replace(
									/\/\*# sourceMappingURL=[\s\S]*?\*\//g,
									''
								);
							compilation.updateAsset(
								filename,
								new RawSource( source )
							);
						} );
					}
				);
			}
		);
	}
}

module.exports = {
	...defaultConfig,
	devtool: false,
	entry: {
		// Admin pages
		'admin/products-page': './src/admin/products-page.js',
		'admin/mockup-library': './src/admin/mockup-library.js',
		'admin/font-manager': './src/admin/font-manager.js',
		'admin/colour-manager': './src/admin/colour-manager.js',
		'admin/clipart-manager': './src/admin/clipart-manager.js',
		'admin/print-bounds-editor': './src/admin/print-bounds-editor.js',
		// Frontend customiser
		'frontend/customiser-app': './src/frontend/customiser-app.js',
		// WooCommerce Blocks integration
		'frontend/blocks-integration': './src/frontend/blocks-integration.js',
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'assets/build' ),
	},
	plugins: [
		// Replace the default DependencyExtractionWebpackPlugin with one that
		// also maps WooCommerce Blocks packages to their window globals.
		...defaultConfig.plugins.filter(
			( p ) => p.constructor.name !== 'DependencyExtractionWebpackPlugin'
		),
		new DependencyExtractionPlugin( {
			requestToExternal( request ) {
				if ( WC_EXTERNALS[ request ] ) {
					return WC_EXTERNALS[ request ];
				}
			},
			requestToHandle( request ) {
				if ( WC_HANDLES[ request ] ) {
					return WC_HANDLES[ request ];
				}
			},
		} ),
		new RemoveSourceMapCommentsPlugin(),
	],
};
