/*
 * The parts of the wp global that the plugin's admin scripts declare as
 * dependencies (wp-i18n, wp-dom-ready), reduced to what a harness needs:
 * strings come back untranslated, and the DOM is always ready.
 */
'use strict';

module.exports = {
	i18n: {
		__: ( text ) => text,
		_n: ( single, plural, number ) => ( 1 === number ? single : plural ),
		sprintf: ( format, ...args ) => {
			let next = 0;
			return String( format ).replace( /%(?:(\d+)\$)?[ds]/g, ( match, position ) =>
				String( position ? args[ position - 1 ] : args[ next++ ] )
			);
		},
	},
	domReady: ( fn ) => fn(),
};
