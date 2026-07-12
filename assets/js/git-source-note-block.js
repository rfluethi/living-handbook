( function ( blocks, blockEditor, element, components, i18n ) {
	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;
	var __ = i18n.__;
	var defaultLabel = __( 'This page is maintained on GitHub and updated automatically.', 'living-handbook' );

	blocks.registerBlockType( 'living-handbook/git-source-note', {
		apiVersion: 3,
		title: __( 'GitHub source note', 'living-handbook' ),
		description: __( 'Shows a note on pages synced from GitHub; renders nothing on other pages.', 'living-handbook' ),
		icon: 'admin-links',
		category: 'living-handbook',
		attributes: {
			label: { type: 'string', default: '' }
		},
		edit: function ( props ) {
			var value = props.attributes.label || '';
			return el(
				'div',
				useBlockProps( { style: { padding: '.6em .9em', borderLeft: '4px solid #2271b1', background: '#f0f6fc' } } ),
				el( components.TextControl, {
					label: __( 'Note text (shown only on GitHub pages)', 'living-handbook' ),
					value: value,
					placeholder: defaultLabel,
					onChange: function ( next ) {
						props.setAttributes( { label: next } );
					}
				} )
			);
		},
		save: function () {
			return null;
		}
	} );
}( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.components, window.wp.i18n ) );
