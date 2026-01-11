const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		admin: path.resolve( process.cwd(), 'src/admin', 'index.js' ),
		frontend: path.resolve( process.cwd(), 'src/frontend', 'index.js' ),
	},
};
