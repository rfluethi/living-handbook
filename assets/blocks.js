( function ( blocks, element, serverSideRender, i18n, blockEditor, components ) {
	'use strict';

	var el = element.createElement;
	var Fragment = element.Fragment;
	var __ = i18n.__;
	var InspectorControls = blockEditor ? blockEditor.InspectorControls : null;
	var PanelBody = components ? components.PanelBody : null;
	var SelectControl = components ? components.SelectControl : null;
	var ToggleControl = components ? components.ToggleControl : null;
	var RangeControl = components ? components.RangeControl : null;

	function note( text ) {
		return el(
			'div',
			{ style: { padding: '1rem', border: '1px dashed #ccc' } },
			text
		);
	}

	function panel( title, children ) {
		if ( ! InspectorControls || ! PanelBody ) {
			return null;
		}
		return el( InspectorControls, {}, el( PanelBody, { title: title }, children ) );
	}

	function dynamic( name, title, icon, text ) {
		blocks.registerBlockType( name, {
			apiVersion: 3,
			title: title,
			category: 'living-handbook',
			icon: icon,
			edit: function () {
				return note( text );
			},
			save: function () {
				return null;
			}
		} );
	}

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
		icon: 'list-view',
		attributes: {
			variant: { type: 'string', default: 'sidebar' }
		},
		edit: function ( props ) {
			var control = SelectControl ? el( SelectControl, {
				label: __( 'Display', 'living-handbook' ),
				value: props.attributes.variant,
				options: [
					{ label: __( 'Menu', 'living-handbook' ), value: 'sidebar' },
					{ label: __( 'Accordion', 'living-handbook' ), value: 'accordion' }
				],
				onChange: function ( value ) {
					props.setAttributes( { variant: value } );
				}
			} ) : null;
			return el(
				Fragment,
				{},
				panel( __( 'Navigation', 'living-handbook' ), control ),
				note( __( 'Handbook navigation: the page tree of the current handbook, styled by the VSN plugin. Choose Menu or Accordion in the block settings.', 'living-handbook' ) )
			);
		},
		save: function () {
			return null;
		}
	} );

	blocks.registerBlockType( 'living-handbook/toc', {
		apiVersion: 3,
		title: __( 'On this page', 'living-handbook' ),
		category: 'living-handbook',
		icon: 'editor-ol',
		attributes: {
			variant: { type: 'string', default: 'desktop' },
			maxDepth: { type: 'number', default: 6 }
		},
		edit: function ( props ) {
			var controls = [];
			if ( SelectControl ) {
				controls.push( el( SelectControl, {
					key: 'placement',
					label: __( 'Placement', 'living-handbook' ),
					value: props.attributes.variant,
					options: [
						{ label: __( 'Desktop (side column, open)', 'living-handbook' ), value: 'desktop' },
						{ label: __( 'Mobile (above content, collapsed)', 'living-handbook' ), value: 'mobile' }
					],
					onChange: function ( value ) {
						props.setAttributes( { variant: value } );
					}
				} ) );
			}
			if ( RangeControl ) {
				controls.push( el( RangeControl, {
					key: 'depth',
					label: __( 'Heading depth (up to H…)', 'living-handbook' ),
					value: props.attributes.maxDepth,
					min: 1,
					max: 6,
					onChange: function ( value ) {
						props.setAttributes( { maxDepth: value } );
					}
				} ) );
			}
			return el(
				Fragment,
				{},
				panel( __( 'On this page', 'living-handbook' ), controls ),
				note( __( 'On this page: a collapsible table of contents built from the headings of the current page, up to the chosen depth. A page can override the depth in its Handbook maintenance box. Empty if the page has no headings.', 'living-handbook' ) )
			);
		},
		save: function () {
			return null;
		}
	} );

	blocks.registerBlockType( 'living-handbook/pagemeta', {
		apiVersion: 3,
		title: __( 'Handbook page meta', 'living-handbook' ),
		category: 'living-handbook',
		icon: 'info-outline',
		attributes: {
			showPeople: { type: 'boolean', default: true }
		},
		edit: function ( props ) {
			var control = ToggleControl ? el( ToggleControl, {
				label: __( 'Show people (avatar and name)', 'living-handbook' ),
				checked: props.attributes.showPeople,
				onChange: function ( value ) {
					props.setAttributes( { showPeople: value } );
				}
			} ) : null;
			return el(
				Fragment,
				{},
				panel( __( 'Page meta', 'living-handbook' ), control ),
				note( __( 'Handbook page meta: the created, updated, reviewed and responsible-role footer. Turn the people on or off in the block settings.', 'living-handbook' ) )
			);
		},
		save: function () {
			return null;
		}
	} );

	dynamic(
		'living-handbook/entry',
		__( 'Handbook entry', 'living-handbook' ),
		'welcome-learn-more',
		__( 'Handbook entry: on a handbook page it shows the search, filters, areas and recently updated pages of that handbook.', 'living-handbook' )
	);

	dynamic(
		'living-handbook/badges',
		__( 'Handbook badges', 'living-handbook' ),
		'tag',
		__( 'Handbook badges: page type, topic and audience of the current page.', 'living-handbook' )
	);

	dynamic(
		'living-handbook/feedback',
		__( 'Handbook feedback', 'living-handbook' ),
		'thumbs-up',
		__( 'Handbook feedback: the "Was this helpful?" prompt for the current page.', 'living-handbook' )
	);
}( window.wp.blocks, window.wp.element, window.wp.serverSideRender, window.wp.i18n, window.wp.blockEditor, window.wp.components ) );
