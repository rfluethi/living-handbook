/*
 * Runs assets/js/markdown-import.js against a minimal fake DOM, presses the
 * "Load handbook" button of the app handbook tab and keeps the server's answer
 * waiting. Prints, as JSON, which import buttons are locked while the import
 * runs and after it has finished.
 *
 * Usage: node tests/js/markdown-import-busy.js <path to markdown-import.js>
 */
'use strict';

const fs = require( 'fs' );
const vm = require( 'vm' );
const stubs = require( './wp-stubs' );

function element( tag ) {
	return {
		tagName: tag.toUpperCase(),
		children: [],
		listeners: {},
		style: {},
		disabled: false,
		value: '',
		checked: true,
		textContent: '',
		innerHTML: '',
		appendChild( child ) {
			this.children.push( child );
			return child;
		},
		setAttribute() {},
		addEventListener( type, fn ) {
			( this.listeners[ type ] = this.listeners[ type ] || [] ).push( fn );
		},
		querySelector( selector ) {
			return ( this.parts && this.parts[ selector ] ) || null;
		},
	};
}

const handbook = element( 'select' );
handbook.value = '5';
const panel = element( 'div' );
panel.parts = { '.lh-import-handbook': handbook };

const buttons = {
	paste: element( 'button' ),
	github: element( 'button' ),
	app: element( 'button' ),
};
const ids = {
	'lh-import-status': element( 'p' ),
	'lh-import-results': element( 'ul' ),
	'lh-import-notes': element( 'div' ),
	'lh-import-run-paste': buttons.paste,
	'lh-import-run-github': buttons.github,
	'lh-app-btn': buttons.app,
	'lh-app-entry': element( 'select' ),
	'lh-app-keep': element( 'input' ),
};

let answer = null;
const sandbox = {
	document: {
		readyState: 'complete',
		createElement: element,
		getElementById: ( id ) => ids[ id ] || null,
		querySelector: ( selector ) =>
			'.living-handbook-import__panel:not([hidden])' === selector
				? panel
				: null,
		addEventListener() {},
	},
	lhImport: { githubPath: '/living-handbook/v1/import-github' },
	confirm: () => true,
	wp: {
		...stubs,
		blocks: {},
		apiFetch: () =>
			new Promise( ( resolve ) => {
				answer = resolve;
			} ),
	},
};
sandbox.window = sandbox;

vm.runInNewContext( fs.readFileSync( process.argv[ 2 ], 'utf8' ), sandbox );

const locked = () =>
	Object.keys( buttons ).filter( ( name ) => buttons[ name ].disabled );

( async () => {
	( buttons.app.listeners.click || [] ).forEach( ( fn ) => fn() );
	const during = locked();
	if ( answer ) {
		answer( { pages: [ { id: 3, title: 'Start', editUrl: '#' } ], notes: [] } );
	}
	for ( let i = 0; i < 10; i++ ) {
		await new Promise( ( r ) => setImmediate( r ) );
	}
	process.stdout.write( JSON.stringify( { during, after: locked() } ) );
} )();
