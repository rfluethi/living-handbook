/*
 * Runs assets/frontend.js against a minimal fake DOM on a page that carries a
 * nonce the server no longer accepts, as a page cache serves it to a guest:
 * every request that sends the nonce gets 403 rest_cookie_invalid_nonce.
 *
 * Usage: node tests/js/frontend-stale-nonce.js <path to frontend.js> <scenario>
 *
 * Scenario "stale": a quick search, then a filter. Prints the requests made
 * (URL path and the nonce sent, or null), the quick search results and the
 * filtered list.
 * Scenario "error": the search endpoint answers 500. Prints what the quick
 * search shows and announces.
 */
'use strict';

const fs = require( 'fs' );
const vm = require( 'vm' );

const scenario = process.argv[ 3 ] || 'stale';

function element( tag ) {
	const el = {
		tagName: tag.toUpperCase(),
		children: [],
		attributes: {},
		listeners: {},
		className: '',
		hidden: false,
		value: '',
		parentNode: null,
		parts: {},
		classList: {
			add() {},
			remove() {},
			contains: () => false,
			toggle: () => false
		},
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
		get firstChild() {
			return this.children[ 0 ] || null;
		},
		appendChild( child ) {
			child.parentNode = this;
			this.children.push( child );
			return child;
		},
		insertBefore( child ) {
			child.parentNode = this;
			this.children.unshift( child );
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
		hasAttribute( name ) {
			return Object.prototype.hasOwnProperty.call( this.attributes, name );
		},
		addEventListener( type, fn ) {
			( this.listeners[ type ] = this.listeners[ type ] || [] ).push( fn );
		},
		querySelector( selector ) {
			return this.parts[ selector ] || null;
		},
		querySelectorAll() {
			return [];
		},
		contains: () => false,
		focus() {}
	};
	return el;
}

// The quick search box on a single page.
const box = element( 'div' );
box.setAttribute( 'data-term-id', '5' );
const quickInput = element( 'input' );
const quickResults = element( 'ul' );
const quickStatus = element( 'p' );
box.parts = {
	'.living-handbook-page-search__input': quickInput,
	'.living-handbook-page-search__results': quickResults,
	'[role="status"]': quickStatus
};

// The handbook start page with its result column and search field.
const entry = element( 'div' );
entry.setAttribute( 'data-term-id', '5' );
const main = element( 'div' );
entry.parts = { '.living-handbook-main': main };
const filterInput = element( 'input' );

const documentListeners = {};
const timers = [];
const calls = [];

function respond( status, body ) {
	const response = {
		ok: status >= 200 && status < 300,
		status,
		json: () => Promise.resolve( body ),
		clone: () => response
	};
	return response;
}

const sandbox = {
	document: {
		readyState: 'loading',
		createElement: element,
		querySelector: ( selector ) => {
			if ( '.living-handbook-entry[data-term-id]' === selector ) {
				return entry;
			}
			if ( '.living-handbook-search__input' === selector ) {
				return filterInput;
			}
			return null;
		},
		querySelectorAll: ( selector ) => ( '.living-handbook-page-search' === selector ? [ box ] : [] ),
		addEventListener( type, fn ) {
			( documentListeners[ type ] = documentListeners[ type ] || [] ).push( fn );
		},
		removeEventListener() {}
	},
	location: { pathname: '/handbook/' },
	history: { replaceState() {} },
	setTimeout: ( fn ) => {
		timers.push( fn );
		return timers.length;
	},
	clearTimeout: () => {},
	URLSearchParams,
	livingHandbook: {
		filter: 'https://example.test/wp-json/living-handbook/v1/filter',
		search: 'https://example.test/wp-json/living-handbook/v1/search',
		rest: 'https://example.test/wp-json/living-handbook/v1/feedback',
		nonce: 'stale',
		filterError: 'Filter failed.',
		searchEmpty: 'No matches.',
		searchError: 'Search failed.'
	},
	fetch: ( url, options ) => {
		const headers = ( options && options.headers ) || {};
		const nonce = Object.prototype.hasOwnProperty.call( headers, 'X-WP-Nonce' ) ? headers[ 'X-WP-Nonce' ] : null;
		calls.push( { path: new URL( url ).pathname.replace( '/wp-json/living-handbook/v1/', '' ), nonce } );
		if ( null !== nonce ) {
			return Promise.resolve( respond( 403, { code: 'rest_cookie_invalid_nonce', message: 'Cookie check failed', data: { status: 403 } } ) );
		}
		if ( url.indexOf( '/search' ) !== -1 ) {
			if ( 'error' === scenario ) {
				return Promise.resolve( respond( 500, { code: 'internal_server_error' } ) );
			}
			return Promise.resolve( respond( 200, { results: [ { url: '/handbook/linux/', title: 'Linux', snippet: [] } ], summary: '1 page found' } ) );
		}
		return Promise.resolve( respond( 200, { html: '<p>filtered</p>' } ) );
	}
};
sandbox.window = sandbox;

vm.runInNewContext( fs.readFileSync( process.argv[ 2 ], 'utf8' ), sandbox );
( documentListeners.DOMContentLoaded || [] ).forEach( ( fn ) => fn() );

function type( input, value ) {
	input.value = value;
	( input.listeners.input || [] ).forEach( ( fn ) => fn() );
	timers.splice( 0 ).forEach( ( fn ) => fn() );
}

async function settle() {
	for ( let i = 0; i < 10; i++ ) {
		await new Promise( ( r ) => setImmediate( r ) );
	}
}

( async () => {
	type( quickInput, 'linux' );
	await settle();
	const quick = {
		results: quickResults.children.map( ( li ) => li.textContent ),
		status: quickStatus.textContent,
		hidden: quickResults.hidden
	};

	if ( 'error' === scenario ) {
		process.stdout.write( JSON.stringify( quick ) );
		return;
	}

	type( filterInput, 'linux' );
	await settle();

	process.stdout.write( JSON.stringify( { calls, quick: quick.results, list: main.innerHTML } ) );
} )();
