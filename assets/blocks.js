( function ( blocks, element, serverSideRender, i18n ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;

	blocks.registerBlockType( 'living-handbook/overview', {
		apiVersion: 3,
		title: __( 'Handbook overview', 'living-handbook' ),
		category: 'living-handbook',
		icon: 'book',
		edit: function () {
			return el( serverSideRender, { block: 'living-handbook/overview' } );
		},
		save: function () {
			return null;
		}
	} );

	blocks.registerBlockType( 'living-handbook/navigation', {
		apiVersion: 3,
		title: __( 'Handbook navigation', 'living-handbook' ),
		category: 'living-handbook',
		icon: 'list',
		edit: function () {
			return el(
				'div',
				{ style: { padding: '1rem', border: '1px dashed #ccc' } },
				__( 'Handbook navigation: shown on handbook pages, listing the current handbook.', 'living-handbook' )
			);
		},
		save: function () {
			return null;
		}
	} );
}( window.wp.blocks, window.wp.element, window.wp.serverSideRender, window.wp.i18n ) );
