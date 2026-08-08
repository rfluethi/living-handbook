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
	var TextControl = components ? components.TextControl : null;

	// Block metadata (title, category, icon, keywords, attributes, supports) comes
	// from each block's blocks/<name>/block.json, registered server-side; this
	// script only supplies the editor edit and save functions.

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

	function displayControl( props ) {
		if ( ! SelectControl ) {
			return null;
		}
		return el( SelectControl, {
			label: __( 'Display', 'living-handbook' ),
			value: props.attributes.display,
			options: [
				{ label: __( 'Cards', 'living-handbook' ), value: 'cards' },
				{ label: __( 'List', 'living-handbook' ), value: 'list' }
			],
			onChange: function ( value ) {
				props.setAttributes( { display: value } );
			}
		} );
	}

	// A block that only shows a descriptive note in the editor, because it renders
	// from the front-end page context, which the editor does not have.
	function dynamic( name, text ) {
		blocks.registerBlockType( name, {
			edit: function () {
				return note( text );
			},
			save: function () {
				return null;
			}
		} );
	}

	blocks.registerBlockType( 'living-handbook/overview', {
		edit: function ( props ) {
			return el(
				Fragment,
				{},
				panel( __( 'Overview', 'living-handbook' ), displayControl( props ) ),
				el( serverSideRender, { block: 'living-handbook/overview', attributes: props.attributes } )
			);
		},
		save: function () {
			return null;
		}
	} );

	blocks.registerBlockType( 'living-handbook/navigation', {
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
				note( __( 'Handbook navigation: the page tree of the current handbook. Choose Menu or Accordion in the block settings.', 'living-handbook' ) )
			);
		},
		save: function () {
			return null;
		}
	} );

	blocks.registerBlockType( 'living-handbook/toc', {
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
				panel( __( 'Table of Contents', 'living-handbook' ), controls ),
				note( __( 'Table of Contents: a collapsible list built from the headings of the current page, up to the chosen depth. A page can override the depth in its Handbook maintenance box. Empty if the page has no headings.', 'living-handbook' ) )
			);
		},
		save: function () {
			return null;
		}
	} );

	blocks.registerBlockType( 'living-handbook/pagemeta', {
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

	function toggle( props, attribute, label ) {
		if ( ! ToggleControl ) {
			return null;
		}
		return el( ToggleControl, {
			key: attribute,
			label: label,
			checked: props.attributes[ attribute ],
			onChange: function ( value ) {
				var change = {};
				change[ attribute ] = value;
				props.setAttributes( change );
			}
		} );
	}

	function text( props, attribute, label, help ) {
		if ( ! TextControl ) {
			return null;
		}
		return el( TextControl, {
			key: attribute,
			label: label,
			help: help,
			value: props.attributes[ attribute ],
			onChange: function ( value ) {
				var change = {};
				change[ attribute ] = value;
				props.setAttributes( change );
			}
		} );
	}

	blocks.registerBlockType( 'living-handbook/entry', {
		edit: function ( props ) {
			return el(
				Fragment,
				{},
				panel( __( 'Entry', 'living-handbook' ), displayControl( props ) ),
				note( __( 'Handbook entry (results): the areas and recently updated pages of this handbook, or the matches while a search or filter is active. The search bar and the filter bar are blocks of their own.', 'living-handbook' ) )
			);
		},
		save: function () {
			return null;
		}
	} );

	blocks.registerBlockType( 'living-handbook/search-form', {
		edit: function ( props ) {
			var controls = [
				toggle( props, 'showLabel', __( 'Show the label', 'living-handbook' ) ),
				text( props, 'label', __( 'Label', 'living-handbook' ), __( 'Read out to screen readers even when it is not shown.', 'living-handbook' ) ),
				text( props, 'placeholder', __( 'Placeholder', 'living-handbook' ) ),
				text( props, 'buttonText', __( 'Button text', 'living-handbook' ) )
			];
			if ( SelectControl ) {
				controls.push( el( SelectControl, {
					key: 'buttonPosition',
					label: __( 'Button position', 'living-handbook' ),
					value: props.attributes.buttonPosition,
					options: [
						{ label: __( 'Outside the field', 'living-handbook' ), value: 'button-outside' },
						{ label: __( 'Inside the field', 'living-handbook' ), value: 'button-inside' },
						{ label: __( 'No button', 'living-handbook' ), value: 'no-button' }
					],
					onChange: function ( value ) {
						props.setAttributes( { buttonPosition: value } );
					}
				} ) );
			}
			return el(
				Fragment,
				{},
				panel( __( 'Search', 'living-handbook' ), controls ),
				note( __( 'Handbook search: searches the handbook this page belongs to and narrows the result column. Colours, border, typography and spacing are in the block settings; the wording is here.', 'living-handbook' ) )
			);
		},
		save: function () {
			return null;
		}
	} );

	blocks.registerBlockType( 'living-handbook/filters', {
		edit: function () {
			return note( __( 'Handbook filter bar: page type, topic, responsibility and audience of the pages in this handbook. It only offers terms that are actually used, so it is empty until pages carry them.', 'living-handbook' ) );
		},
		save: function () {
			return null;
		}
	} );

	blocks.registerBlockType( 'living-handbook/menu', {
		edit: function () {
			return el( serverSideRender, { block: 'living-handbook/menu' } );
		},
		save: function () {
			return null;
		}
	} );

	dynamic(
		'living-handbook/badges',
		__( 'Handbook badges: page type, topic and audience of the current page.', 'living-handbook' )
	);

	dynamic(
		'living-handbook/feedback',
		__( 'Handbook feedback: the "Was this helpful?" prompt for the current page.', 'living-handbook' )
	);

	blocks.registerBlockType( 'living-handbook/search', {
		edit: function ( props ) {
			return el(
				Fragment,
				{},
				panel( __( 'Quick search', 'living-handbook' ), [
					toggle( props, 'showLabel', __( 'Show the label', 'living-handbook' ) ),
					text( props, 'label', __( 'Label', 'living-handbook' ), __( 'Read out to screen readers even when it is not shown.', 'living-handbook' ) ),
					text( props, 'placeholder', __( 'Placeholder', 'living-handbook' ) )
				] ),
				note( __( 'Handbook quick search: lists matching pages as you type, each with the sentence it was found in, so a reader jumps straight to a page. Does not narrow the result column; that is the Handbook search block.', 'living-handbook' ) )
			);
		},
		save: function () {
			return null;
		}
	} );
}( window.wp.blocks, window.wp.element, window.wp.serverSideRender, window.wp.i18n, window.wp.blockEditor, window.wp.components ) );
