/**
 * ESLint configuration: the WordPress recommended preset, Prettier included.
 *
 * The scripts in assets/ are served as they are, with no bundler and no
 * transpiler, so they run in the browser and see the globals WordPress prints
 * (wp, jQuery and the objects passed with wp_localize_script). The few Node
 * scripts in bin/ are build tooling and run under Node only.
 */
import wordpress from '@wordpress/eslint-plugin';
import globals from 'globals';

export default [
	{
		// Third-party bundle, replaced only by bin/update-mermaid.sh.
		ignores: [ 'assets/js/mermaid.min.js', 'node_modules/', 'vendor/' ],
	},
	...wordpress.configs.recommended,
	{
		files: [ 'assets/**/*.js' ],
		languageOptions: {
			sourceType: 'script',
			globals: {
				...globals.browser,
			},
		},
	},
	{
		files: [ 'bin/**/*.js' ],
		languageOptions: {
			sourceType: 'commonjs',
		},
		rules: {
			// A command-line script reports on stdout and stderr by design.
			'no-console': 'off',
		},
	},
];
