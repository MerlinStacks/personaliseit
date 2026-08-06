const defaultConfig = require( '@wordpress/scripts/config/eslint.config.cjs' );

module.exports = [
	...defaultConfig,
	{
		files: [ 'src/frontend/customiser/design-variants.js' ],
		languageOptions: {
			globals: {
				CSS: 'readonly',
			},
		},
		// Preserve the existing module's formatting while still checking behavior.
		rules: {
			'prettier/prettier': 'off',
		},
	},
];
