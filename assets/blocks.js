( function ( blocks, element, serverSideRender, i18n ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;

	function registerDynamic( name, title ) {
		blocks.registerBlockType( name, {
			apiVersion: 3,
			title: title,
			category: 'theme',
			icon: 'book',
			edit: function () {
				return el( serverSideRender, { block: name } );
			},
			save: function () {
				return null;
			}
		} );
	}

	registerDynamic( 'living-handbook/overview', __( 'Handbook overview', 'living-handbook' ) );
	registerDynamic( 'living-handbook/navigation', __( 'Handbook navigation', 'living-handbook' ) );
}( window.wp.blocks, window.wp.element, window.wp.serverSideRender, window.wp.i18n ) );
