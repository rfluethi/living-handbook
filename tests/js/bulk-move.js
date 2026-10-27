/*
 * Runs assets/js/bulk-move.js against a minimal fake DOM with the two bulk
 * action bars of a WordPress list table. Chooses the move action in the upper
 * bar only, the way it arrives when core's marryControls() updates the lower
 * dropdown after this script's handler, then picks a handbook in the lower
 * bar. Prints, as JSON, per bar: shown, enabled, required and the value.
 *
 * Usage: node tests/js/bulk-move.js <path to bulk-move.js>
 */
'use strict';

const fs = require( 'fs' );
const vm = require( 'vm' );

function element( tag, props = {} ) {
	const el = {
		tagName: tag.toUpperCase(),
		children: [],
		attributes: {},
		listeners: {},
		parentNode: null,
		hidden: false,
		disabled: false,
		required: false,
		value: '',
		id: '',
		...props,
		appendChild( child ) {
			child.parentNode = this;
			this.children.push( child );
			return child;
		},
		insertBefore( child, reference ) {
			child.parentNode = this;
			const at = this.children.indexOf( reference );
			this.children.splice( -1 === at ? this.children.length : at, 0, child );
			return child;
		},
		get nextSibling() {
			const siblings = this.parentNode ? this.parentNode.children : [];
			return siblings[ siblings.indexOf( this ) + 1 ] || null;
		},
		setAttribute( name, value ) {
			this.attributes[ name ] = String( value );
		},
		addEventListener( type, fn ) {
			( this.listeners[ type ] = this.listeners[ type ] || [] ).push( fn );
		},
		dispatch( type ) {
			( this.listeners[ type ] || [] ).forEach( ( fn ) =>
				fn( { target: this, currentTarget: this } )
			);
		},
		querySelector( selector ) {
			return (
				this.children.find(
					( child ) => child.tagName === selector.toUpperCase()
				) || null
			);
		},
		cloneNode() {
			const copy = element( this.tagName.toLowerCase(), {
				className: this.className,
			} );
			this.children.forEach( ( child ) =>
				copy.appendChild( child.cloneNode() )
			);
			return copy;
		},
	};
	return el;
}

const source = element( 'span', { className: 'living-handbook-move-target' } );
source.appendChild( element( 'label' ) );
source.appendChild( element( 'select', { id: 'lh_handbook' } ) );

const bars = [ 'top', 'bottom' ].map( ( where ) => {
	const bar = element( 'div', { className: 'bulkactions ' + where } );
	const selector = element( 'select', { id: 'bulk-action-selector-' + where, value: '-1' } );
	bar.appendChild( selector );
	return { bar, selector };
} );

let ready = null;
const sandbox = {
	livingHandbookBulkMove: { action: 'lh_move_to_handbook', field: 'lh_handbook' },
	document: {
		querySelector: ( selector ) =>
			'.living-handbook-move-target' === selector ? source : null,
		querySelectorAll: () => bars.map( ( entry ) => entry.selector ),
		addEventListener: ( type, fn ) => {
			ready = fn;
		},
	},
};
sandbox.window = sandbox;

vm.runInNewContext( fs.readFileSync( process.argv[ 2 ], 'utf8' ), sandbox );
ready();

bars[ 0 ].selector.value = 'lh_move_to_handbook';
bars[ 0 ].selector.dispatch( 'change' );
bars[ 1 ].selector.value = 'lh_move_to_handbook';

const field = ( index ) => bars[ index ].bar.children[ 1 ].children[ 1 ];
const lower = field( 1 );
if ( lower ) {
	lower.value = '15';
	lower.dispatch( 'change' );
}

const state = bars.map( ( entry, index ) => {
	const control = entry.bar.children[ 1 ];
	const select = field( index );
	return {
		shown: !! control && ! control.hidden,
		enabled: !! select && ! select.disabled,
		required: !! select && select.required,
		value: select ? select.value : null,
	};
} );
process.stdout.write( JSON.stringify( state ) );
