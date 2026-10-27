/*
 * Runs assets/js/markdown-import.js against a minimal fake DOM, presses the
 * button of one import source and answers with no pages but a note, as the
 * server does when it loads nothing and says why.
 *
 * Usage: node tests/js/markdown-import-notes.js <path to markdown-import.js> <app|github>
 *
 * Prints, as JSON, the status line and the notes shown.
 */
'use strict';

const fs = require( 'fs' );
const vm = require( 'vm' );
const stubs = require( './wp-stubs' );

const source = process.argv[ 3 ] || 'app';

function element( tag ) {
	const el = {
		tagName: tag.toUpperCase(),
		children: [],
		attributes: {},
		listeners: {},
		style: {},
		className: '',
		disabled: false,
		hidden: false,
		value: '',
		checked: true,
		get textContent() {
			return undefined !== this._text ? this._text : this.children.map( ( c ) => c.textContent ).join( '' );
		},
		set textContent( value ) {
			this._text = String( value );
			this.children = [];
		},
		get innerHTML() {
			return undefined !== this._html ? this._html : '';
		},
		set innerHTML( value ) {
			this._html = String( value );
			this.children = [];
			delete this._text;
		},
		appendChild( child ) {
			this.children.push( child );
			return child;
		},
		setAttribute( name, value ) {
			this.attributes[ name ] = String( value );
		},
		getAttribute( name ) {
			return Object.prototype.hasOwnProperty.call( this.attributes, name ) ? this.attributes[ name ] : null;
		},
		addEventListener( type, fn ) {
			( this.listeners[ type ] = this.listeners[ type ] || [] ).push( fn );
		},
		querySelector( selector ) {
			return ( this.parts && this.parts[ selector ] ) || null;
		},
		querySelectorAll() {
			return [];
		}
	};
	return el;
}

const handbook = element( 'select' );
handbook.value = '5';
const panel = element( 'div' );
panel.parts = { '.lh-import-handbook': handbook };

const github = element( 'input' );
github.value = 'https://github.com/example/docs/tree/main/docs';

const ids = {
	'lh-import-status': element( 'p' ),
	'lh-import-results': element( 'ul' ),
	'lh-import-notes': element( 'div' ),
	'lh-import-run-github': element( 'button' ),
	'lh-import-github': github,
	'lh-app-btn': element( 'button' ),
	'lh-app-entry': element( 'select' ),
	'lh-app-keep': element( 'input' )
};

const sandbox = {
	document: {
		readyState: 'complete',
		createElement: element,
		getElementById: ( id ) => ids[ id ] || null,
		querySelector: ( selector ) => ( '.living-handbook-import__panel:not([hidden])' === selector ? panel : null ),
		addEventListener() {}
	},
	lhImport: { githubPath: '/living-handbook/v1/import-github' },
	confirm: () => true,
	wp: {
		...stubs,
		blocks: {},
		apiFetch: () => Promise.resolve( { pages: [], notes: [ 'The handbook folder holds no Markdown files.' ] } )
	}
};
sandbox.window = sandbox;

vm.runInNewContext( fs.readFileSync( process.argv[ 2 ], 'utf8' ), sandbox );

( async () => {
	const button = 'github' === source ? ids[ 'lh-import-run-github' ] : ids[ 'lh-app-btn' ];
	( button.listeners.click || [] ).forEach( ( fn ) => fn() );
	for ( let i = 0; i < 10; i++ ) {
		await new Promise( ( r ) => setImmediate( r ) );
	}

	const notes = [];
	const walk = ( node ) => {
		if ( 'LI' === node.tagName ) {
			notes.push( node.textContent );
		}
		node.children.forEach( walk );
	};
	walk( ids[ 'lh-import-notes' ] );

	process.stdout.write( JSON.stringify( { status: ids[ 'lh-import-status' ].textContent, notes } ) );
} )();
