/*
 * Runs assets/frontend.js against a minimal fake DOM with one review button
 * in the page footer, and clicks it.
 *
 * Usage: node tests/js/frontend-review-confirm.js <path to frontend.js> <scenario>
 *
 * Scenario "ok": the route answers 200 with the new review entry. Prints the
 * request, the entry's markup afterwards and the status line.
 * Scenario "error": the route answers 403. Prints whether the button is usable
 * again and the status line.
 */
'use strict';

const fs = require( 'fs' );
const vm = require( 'vm' );

const scenario = process.argv[ 3 ] || 'ok';

class Element {
	constructor( tag ) {
		this.tagName = tag.toUpperCase();
		this.attributes = {};
		this.children = [];
		this.className = '';
		this.textContent = '';
		this.innerHTML = '';
		this.disabled = false;
		this.parentElement = null;
		this.closestMap = {};
	}
	setAttribute( name, value ) {
		this.attributes[ name ] = String( value );
	}
	getAttribute( name ) {
		return Object.prototype.hasOwnProperty.call( this.attributes, name ) ? this.attributes[ name ] : null;
	}
	appendChild( child ) {
		child.parentElement = this;
		this.children.push( child );
		return child;
	}
	querySelector( selector ) {
		const cls = selector.replace( /^\./, '' );
		return this.children.find( ( c ) => ( ' ' + c.className + ' ' ).indexOf( ' ' + cls + ' ' ) !== -1 ) || null;
	}
	querySelectorAll() {
		return [];
	}
	closest( selector ) {
		return this.closestMap[ selector ] || null;
	}
	addEventListener() {}
}

// <div class="item"><dt/><dd class="living-handbook-metagrid__date">… <button/> …</dd></div>
const item = new Element( 'div' );
const entry = new Element( 'dd' );
entry.className = 'living-handbook-metagrid__date';
item.appendChild( entry );
const button = new Element( 'button' );
button.className = 'living-handbook-review-confirm';
button.setAttribute( 'data-post', '42' );
button.setAttribute( 'data-people', '1' );
button.closestMap = { '.living-handbook-review-confirm': button, '.living-handbook-metagrid__date': entry };

const documentListeners = {};
const calls = [];

const sandbox = {
	Element,
	document: {
		readyState: 'loading',
		createElement: ( tag ) => new Element( tag ),
		querySelector: () => null,
		querySelectorAll: () => [],
		addEventListener( type, fn ) {
			( documentListeners[ type ] = documentListeners[ type ] || [] ).push( fn );
		},
		removeEventListener() {}
	},
	location: { pathname: '/handbook/page/' },
	history: { replaceState() {} },
	setTimeout: ( fn ) => fn(),
	clearTimeout: () => {},
	URLSearchParams,
	livingHandbook: {
		review: 'https://example.test/wp-json/living-handbook/v1/review',
		nonce: 'fresh',
		reviewError: 'Review failed.'
	},
	fetch: ( url, options ) => {
		calls.push( { url, method: options.method, nonce: options.headers[ 'X-WP-Nonce' ] || null, body: JSON.parse( options.body ) } );
		const ok = 'ok' === scenario;
		const body = ok ? { html: '2026-11-09 <span class="living-handbook-badge living-handbook-badge--ok">Up to date</span>', message: 'Review confirmed.' } : { code: 'rest_forbidden' };
		return Promise.resolve( { ok, status: ok ? 200 : 403, json: () => Promise.resolve( body ), clone() { return this; } } );
	}
};
sandbox.window = sandbox;

vm.runInNewContext( fs.readFileSync( process.argv[ 2 ], 'utf8' ), sandbox );
( documentListeners.DOMContentLoaded || [] ).forEach( ( fn ) => fn() );

( async () => {
	( documentListeners.click || [] ).forEach( ( fn ) => fn( { target: button } ) );
	const disabledWhileSent = button.disabled;
	for ( let i = 0; i < 10; i++ ) {
		await new Promise( ( r ) => setImmediate( r ) );
	}
	const status = item.querySelector( '.living-handbook-review-status' );
	process.stdout.write(
		JSON.stringify( {
			calls,
			disabledWhileSent,
			disabledAfter: button.disabled,
			entry: entry.innerHTML,
			status: status ? status.textContent : null,
			statusRole: status ? status.getAttribute( 'role' ) : null
		} )
	);
} )();
