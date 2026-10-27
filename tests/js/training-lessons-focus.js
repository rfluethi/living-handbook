/*
 * Runs assets/js/training-lessons.js against a minimal fake DOM with three
 * lessons and presses buttons the way a keyboard user does: always the one
 * that has the focus. A disabled button refuses the focus as a browser's
 * does, and the focus then sits on the body.
 *
 * Prints, as JSON, after each press: the order of the lessons and which
 * button has the focus ("<lesson id>:<up|down|remove>", or "body").
 *
 * Usage: node tests/js/training-lessons-focus.js <path to training-lessons.js>
 */
'use strict';

const fs = require( 'fs' );
const vm = require( 'vm' );
const stubs = require( './wp-stubs' );

const body = { name: 'body', click() {} };
let active = body;

function element( tag ) {
	const el = {
		tagName: tag.toUpperCase(),
		children: [],
		attributes: {},
		listeners: {},
		className: '',
		disabled: false,
		value: '',
		type: '',
		parentNode: null,
		get textContent() {
			return undefined !== this._text ? this._text : this.children.map( ( c ) => c.textContent ).join( '' );
		},
		set textContent( value ) {
			this._text = String( value );
			this.children = [];
		},
		get previousElementSibling() {
			const siblings = this.parentNode ? this.parentNode.children : [];
			const at = siblings.indexOf( this );
			return at > 0 ? siblings[ at - 1 ] : null;
		},
		get nextElementSibling() {
			const siblings = this.parentNode ? this.parentNode.children : [];
			const at = siblings.indexOf( this );
			return at >= 0 && at < siblings.length - 1 ? siblings[ at + 1 ] : null;
		},
		appendChild( child ) {
			return this.insertBefore( child, null );
		},
		insertBefore( child, reference ) {
			if ( child.parentNode ) {
				child.parentNode.removeChild( child );
			}
			child.parentNode = this;
			const at = null === reference ? -1 : this.children.indexOf( reference );
			if ( -1 === at ) {
				this.children.push( child );
			} else {
				this.children.splice( at, 0, child );
			}
			return child;
		},
		remove() {
			if ( this.parentNode ) {
				this.parentNode.removeChild( this );
			}
		},
		removeChild( child ) {
			this.children.splice( this.children.indexOf( child ), 1 );
			child.parentNode = null;
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
		querySelector( selector ) {
			const wanted = selector.replace( /^\./, '' );
			const walk = ( node ) => {
				for ( const child of node.children ) {
					if ( ( ' ' + child.className + ' ' ).indexOf( ' ' + wanted + ' ' ) !== -1 ) {
						return child;
					}
					const found = walk( child );
					if ( found ) {
						return found;
					}
				}
				return null;
			};
			return walk( this );
		},
		focus() {
			// A browser does not focus a disabled button; when the focused button
			// is disabled, the focus falls back to the body.
			if ( ! this.disabled ) {
				active = this;
			}
		},
		click() {
			if ( ! this.disabled ) {
				( this.listeners.click || [] ).forEach( ( fn ) => fn() );
			}
			// The button that had the focus was disabled under it.
			if ( active !== body && active.disabled ) {
				active = body;
			}
		}
	};
	return el;
}

const root = element( 'div' );
root.setAttribute( 'data-training', '7' );
const value = element( 'input' );
value.className = 'living-handbook-lessons__value';
const list = element( 'ol' );
list.className = 'living-handbook-lessons__list';
root.appendChild( value );
root.appendChild( list );

[ 1, 2, 3 ].forEach( ( id ) => {
	const item = element( 'li' );
	item.setAttribute( 'data-id', String( id ) );
	const title = element( 'span' );
	title.className = 'living-handbook-lessons__title';
	title.textContent = 'Lesson ' + id;
	item.appendChild( title );
	list.appendChild( item );
} );

const sandbox = {
	document: {
		readyState: 'complete',
		createElement: element,
		querySelectorAll: ( selector ) => ( '.living-handbook-lessons' === selector ? [ root ] : [] ),
		addEventListener() {}
	},
	setTimeout,
	clearTimeout,
	wp: { ...stubs }
};
sandbox.window = sandbox;

vm.runInNewContext( fs.readFileSync( process.argv[ 2 ], 'utf8' ), sandbox );

function describe() {
	if ( active === body ) {
		return 'body';
	}
	const item = active.parentNode.parentNode;
	return item.getAttribute( 'data-id' ) + ':' + active.className.replace( /.*__/, '' );
}

function order() {
	return list.children.map( ( item ) => item.getAttribute( 'data-id' ) ).join( ',' );
}

function button( id, which ) {
	const item = list.children.find( ( child ) => child.getAttribute( 'data-id' ) === String( id ) );
	return item.querySelector( '.living-handbook-lessons__' + which );
}

const steps = [];

// Lesson 3 up twice with the keyboard: the second press makes it first.
button( 3, 'up' ).focus();
active.click();
steps.push( [ order(), describe() ] );
active.click();
steps.push( [ order(), describe() ] );

// Move down on lesson 1, second of three, makes it last.
button( 1, 'down' ).focus();
active.click();
steps.push( [ order(), describe() ] );

// The buttons at the ends stay disabled.
steps.push( [ 'up disabled on first', button( 3, 'up' ).disabled ] );
steps.push( [ 'down disabled on last', button( 1, 'down' ).disabled ] );

process.stdout.write( JSON.stringify( steps ) );
