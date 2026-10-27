/*
 * Runs assets/js/training-lessons.js against a minimal fake DOM and answers its
 * two searches out of order: the first one ("onb") last. Prints the titles in
 * the result list as JSON. With the latest-request check the list shows the
 * matches for "onboarding"; without it, the late answer for "onb" wins.
 *
 * Usage: node tests/js/training-lessons-race.js <path to training-lessons.js>
 */
'use strict';

const fs = require( 'fs' );
const vm = require( 'vm' );
const stubs = require( './wp-stubs' );

function element( tag ) {
	const el = {
		tagName: tag.toUpperCase(),
		children: [],
		attributes: {},
		listeners: {},
		className: '',
		hidden: false,
		disabled: false,
		value: '',
		parentNode: null,
		get textContent() {
			return this._text !== undefined ? this._text : this.children.map( ( c ) => c.textContent ).join( '' );
		},
		set textContent( value ) {
			this._text = value;
			this.children = [];
		},
		appendChild( child ) {
			if ( this._text !== undefined && '' === this._text ) {
				delete this._text;
			}
			child.parentNode = this;
			this.children.push( child );
			return child;
		},
		setAttribute( name, value ) {
			this.attributes[ name ] = String( value );
		},
		getAttribute( name ) {
			return Object.prototype.hasOwnProperty.call( this.attributes, name ) ? this.attributes[ name ] : null;
		},
		removeAttribute( name ) {
			delete this.attributes[ name ];
		},
		addEventListener( type, fn ) {
			( this.listeners[ type ] = this.listeners[ type ] || [] ).push( fn );
		},
		querySelector() {
			return null;
		},
		focus() {},
		trim() {}
	};
	return el;
}

const root = element( 'div' );
root.setAttribute( 'data-training', '7' );
const parts = {
	'.living-handbook-lessons__value': element( 'input' ),
	'.living-handbook-lessons__list': element( 'ol' ),
	'.living-handbook-lessons__empty': element( 'p' ),
	'.living-handbook-lessons__search': element( 'input' ),
	'.living-handbook-lessons__results': element( 'ul' )
};
root.querySelector = ( selector ) => parts[ selector ] || null;

const pending = [];
const timers = [];

const sandbox = {
	document: {
		readyState: 'complete',
		createElement: element,
		querySelectorAll: ( selector ) => ( '.living-handbook-lessons' === selector ? [ root ] : [] ),
		addEventListener() {}
	},
	setTimeout: ( fn ) => {
		timers.push( fn );
		return timers.length;
	},
	clearTimeout: () => {},
	lhTraining: { searchPath: '/living-handbook/v1/training-lessons' },
	wp: {
		...stubs,
		apiFetch: ( options ) => new Promise( ( resolve ) => {
			pending.push( { path: options.path, resolve } );
		} )
	}
};
sandbox.window = sandbox;

vm.runInNewContext( fs.readFileSync( process.argv[ 2 ], 'utf8' ), sandbox );

const search = parts[ '.living-handbook-lessons__search' ];
const results = parts[ '.living-handbook-lessons__results' ];

function type( value ) {
	search.value = value;
	search.listeners.input.forEach( ( fn ) => fn() );
	timers.splice( 0 ).forEach( ( fn ) => fn() );
}

( async () => {
	type( 'onb' );
	type( 'onboarding' );

	const answer = ( title ) => ( { results: [ { id: 1, title } ] } );
	const first = pending.find( ( p ) => p.path.includes( 'q=onb&' ) || p.path.endsWith( 'q=onb' ) );
	const second = pending.find( ( p ) => p.path.endsWith( 'q=onboarding' ) );

	second.resolve( answer( 'Onboarding checklist' ) );
	await new Promise( ( r ) => setImmediate( r ) );
	first.resolve( answer( 'Onboarding (stale)' ) );
	await new Promise( ( r ) => setImmediate( r ) );

	process.stdout.write( JSON.stringify( results.children.map( ( li ) => li.textContent ) ) );
} )();
