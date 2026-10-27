/*
 * Runs assets/blocks.js against stub wp packages, renders every block it
 * registers through React's rules in miniature (a function component is
 * called, an element is a plain object), and reports, per block, whether the
 * root element of its editor view carries the props of useBlockProps().
 *
 * Usage: node tests/js/blocks-wrapper.js <path to blocks.js>
 *
 * Prints, as JSON: { "<block name>": true|false }.
 */
'use strict';

const fs = require( 'fs' );
const vm = require( 'vm' );
const stubs = require( './wp-stubs' );

const BLOCK_PROPS = { 'data-block-props': true };
const registered = {};

const createElement = ( type, props, ...children ) => ( { type, props: props || {}, children } );
const component = ( name ) => {
	const fn = () => null;
	Object.defineProperty( fn, 'name', { value: name } );
	return fn;
};

const sandbox = {
	wp: {
		...stubs,
		blocks: {
			registerBlockType: ( name, settings ) => {
				registered[ name ] = settings;
			},
		},
		element: { createElement, Fragment: component( 'Fragment' ) },
		serverSideRender: component( 'ServerSideRender' ),
		blockEditor: {
			useBlockProps: () => BLOCK_PROPS,
			InspectorControls: component( 'InspectorControls' ),
		},
		components: {
			PanelBody: component( 'PanelBody' ),
			SelectControl: component( 'SelectControl' ),
			ToggleControl: component( 'ToggleControl' ),
			RangeControl: component( 'RangeControl' ),
			TextControl: component( 'TextControl' ),
		},
	},
};
sandbox.window = sandbox;

vm.runInNewContext( fs.readFileSync( process.argv[ 2 ], 'utf8' ), sandbox );

const props = { attributes: {}, setAttributes() {} };
const out = {};
Object.keys( registered ).forEach( ( name ) => {
	// A block's edit is a function component: calling it gives its root.
	const root = registered[ name ].edit( props );
	out[ name ] = !! root && !! root.props && true === root.props[ 'data-block-props' ];
} );
process.stdout.write( JSON.stringify( out ) );
