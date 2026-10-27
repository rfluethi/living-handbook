/**
 * Editor registrations of the server-rendered Living Handbook blocks.
 *
 * Block metadata (title, category, icon, keywords, attributes, supports) comes
 * from each block's blocks/<name>/block.json, registered server-side; this
 * script only supplies the editor's edit and save functions. Every block here
 * renders on the server, so save() returns null. The Mermaid and GitHub source
 * note blocks register in their own scripts (assets/js/mermaid-block.js and
 * assets/js/git-source-note-block.js).
 */

( function () {
	'use strict';

	const { blocks, element, serverSideRender, i18n, blockEditor, components } =
		window.wp;
	const { useBlockProps } = blockEditor;

	const el = element.createElement;
	const Fragment = element.Fragment;
	const __ = i18n.__;
	const InspectorControls = blockEditor
		? blockEditor.InspectorControls
		: null;
	const PanelBody = components ? components.PanelBody : null;
	const SelectControl = components ? components.SelectControl : null;
	const ToggleControl = components ? components.ToggleControl : null;
	const RangeControl = components ? components.RangeControl : null;
	const TextControl = components ? components.TextControl : null;

	/**
	 * Returns the placeholder shown in the editor for a block that renders
	 * only on the front end.
	 *
	 * @param {string} text What the block shows there.
	 * @return {Object} The element.
	 */
	function note( text ) {
		return el(
			'div',
			{ style: { padding: '1rem', border: '1px dashed #ccc' } },
			text
		);
	}

	/**
	 * Returns a block settings panel in the inspector sidebar.
	 *
	 * @param {string}               title    Panel title.
	 * @param {Object|Array<Object>} children Controls inside the panel.
	 * @return {Object|null} The element, or null where the editor lacks the
	 *                       components.
	 */
	function panel( title, children ) {
		if ( ! InspectorControls || ! PanelBody ) {
			return null;
		}
		return el(
			InspectorControls,
			{},
			el( PanelBody, { title }, children )
		);
	}

	/**
	 * Returns the Cards or List choice of the overview and entry blocks.
	 *
	 * @param {Object} props Block edit props.
	 * @return {Object|null} The control, or null without the components.
	 */
	function displayControl( props ) {
		if ( ! SelectControl ) {
			return null;
		}
		return el( SelectControl, {
			label: __( 'Display', 'living-handbook' ),
			value: props.attributes.display,
			options: [
				{ label: __( 'Cards', 'living-handbook' ), value: 'cards' },
				{ label: __( 'List', 'living-handbook' ), value: 'list' },
			],
			onChange( value ) {
				props.setAttributes( { display: value } );
			},
		} );
	}

	/**
	 * Registers a block, with its editor view inside the block wrapper.
	 *
	 * Every block here declares apiVersion 3 in its block.json. From version 2
	 * on, the editor leaves the wrapper element to the block: whatever edit()
	 * returns is the block's root, and it is only selectable, outlined and
	 * given its toolbar, anchor and class when it carries useBlockProps(). Up
	 * to 0.75.1 the edit functions returned their note or preview bare, so a
	 * placed block could not be clicked to select it in the editor.
	 *
	 * @param {string} name     Block name.
	 * @param {Object} settings Client-side settings; edit() renders the
	 *                          content inside the wrapper.
	 * @return {void}
	 */
	function registerBlockType( name, settings ) {
		const render = settings.edit;

		/**
		 * Renders the block's wrapper around its editor view.
		 *
		 * @param {Object} props Block edit props.
		 * @return {Object} The element.
		 */
		function BlockEdit( props ) {
			return el( 'div', useBlockProps(), render( props ) );
		}

		blocks.registerBlockType( name, { ...settings, edit: BlockEdit } );
	}

	/**
	 * Registers a block that only shows a descriptive note in the editor,
	 * because it renders from the front-end page context, which the editor
	 * does not have.
	 *
	 * @param {string} name Block name.
	 * @param {string} text The note.
	 * @return {void}
	 */
	function dynamic( name, text ) {
		registerBlockType( name, {
			edit() {
				return note( text );
			},
			save() {
				return null;
			},
		} );
	}

	registerBlockType( 'living-handbook/overview', {
		edit( props ) {
			const preview = RangeControl
				? el( RangeControl, {
						key: 'previewCount',
						label: __(
							'Page titles under each handbook',
							'living-handbook'
						),
						help: __(
							'Zero shows none. The list is the first pages of the handbook, plus a link to the rest.',
							'living-handbook'
						),
						value: props.attributes.previewCount,
						min: 0,
						max: 10,
						onChange( value ) {
							props.setAttributes( { previewCount: value } );
						},
					} )
				: null;
			return el(
				Fragment,
				{},
				panel( __( 'Overview', 'living-handbook' ), [
					displayControl( props ),
					preview,
				] ),
				el( serverSideRender, {
					block: 'living-handbook/overview',
					attributes: props.attributes,
				} )
			);
		},
		save() {
			return null;
		},
	} );

	// The attribute value "sidebar" is the editor's "Menu" and renders the
	// class living-handbook-nav--tree (Frontend\Navigation): the whole tree,
	// open. "accordion" renders living-handbook-nav--accordion.
	registerBlockType( 'living-handbook/navigation', {
		edit( props ) {
			const control = SelectControl
				? el( SelectControl, {
						label: __( 'Display', 'living-handbook' ),
						value: props.attributes.variant,
						options: [
							{
								label: __( 'Menu', 'living-handbook' ),
								value: 'sidebar',
							},
							{
								label: __( 'Accordion', 'living-handbook' ),
								value: 'accordion',
							},
						],
						onChange( value ) {
							props.setAttributes( { variant: value } );
						},
					} )
				: null;
			return el(
				Fragment,
				{},
				panel( __( 'Navigation', 'living-handbook' ), control ),
				note(
					__(
						'Handbook navigation: the page tree of the current handbook. Choose Menu or Accordion in the block settings.',
						'living-handbook'
					)
				)
			);
		},
		save() {
			return null;
		},
	} );

	registerBlockType( 'living-handbook/toc', {
		edit( props ) {
			const controls = [];
			if ( SelectControl ) {
				controls.push(
					el( SelectControl, {
						key: 'placement',
						label: __( 'Placement', 'living-handbook' ),
						value: props.attributes.variant,
						options: [
							{
								label: __(
									'Desktop (side column, open)',
									'living-handbook'
								),
								value: 'desktop',
							},
							{
								label: __(
									'Mobile (above content, collapsed)',
									'living-handbook'
								),
								value: 'mobile',
							},
						],
						onChange( value ) {
							props.setAttributes( { variant: value } );
						},
					} )
				);
			}
			if ( RangeControl ) {
				controls.push(
					el( RangeControl, {
						key: 'depth',
						label: __(
							'Heading depth (up to H…)',
							'living-handbook'
						),
						value: props.attributes.maxDepth,
						min: 1,
						max: 6,
						onChange( value ) {
							props.setAttributes( { maxDepth: value } );
						},
					} )
				);
			}
			return el(
				Fragment,
				{},
				panel( __( 'Table of Contents', 'living-handbook' ), controls ),
				note(
					__(
						'Table of Contents: a collapsible list built from the headings of the current page, up to the chosen depth. A page can override the depth in its Handbook maintenance box. Empty if the page has no headings.',
						'living-handbook'
					)
				)
			);
		},
		save() {
			return null;
		},
	} );

	registerBlockType( 'living-handbook/pagemeta', {
		edit( props ) {
			const control = ToggleControl
				? el( ToggleControl, {
						label: __(
							'Show people (avatar and name)',
							'living-handbook'
						),
						checked: props.attributes.showPeople,
						onChange( value ) {
							props.setAttributes( { showPeople: value } );
						},
					} )
				: null;
			return el(
				Fragment,
				{},
				panel( __( 'Page meta', 'living-handbook' ), control ),
				note(
					__(
						'Handbook page meta: the created, updated, reviewed and responsible-role footer. Turn the people on or off in the block settings.',
						'living-handbook'
					)
				)
			);
		},
		save() {
			return null;
		},
	} );

	/**
	 * Returns an on/off control bound to a boolean block attribute.
	 *
	 * @param {Object} props     Block edit props.
	 * @param {string} attribute Attribute name.
	 * @param {string} label     Control label.
	 * @return {Object|null} The control, or null without the components.
	 */
	function toggleControl( props, attribute, label ) {
		if ( ! ToggleControl ) {
			return null;
		}
		return el( ToggleControl, {
			key: attribute,
			label,
			checked: props.attributes[ attribute ],
			onChange( value ) {
				const change = {};
				change[ attribute ] = value;
				props.setAttributes( change );
			},
		} );
	}

	/**
	 * Returns a text field bound to a string block attribute.
	 *
	 * @param {Object} props     Block edit props.
	 * @param {string} attribute Attribute name.
	 * @param {string} label     Field label.
	 * @param {string} [help]    Help text under the field.
	 * @return {Object|null} The control, or null without the components.
	 */
	function textControl( props, attribute, label, help ) {
		if ( ! TextControl ) {
			return null;
		}
		return el( TextControl, {
			key: attribute,
			label,
			help,
			value: props.attributes[ attribute ],
			onChange( value ) {
				const change = {};
				change[ attribute ] = value;
				props.setAttributes( change );
			},
		} );
	}

	registerBlockType( 'living-handbook/entry', {
		edit( props ) {
			return el(
				Fragment,
				{},
				panel(
					__( 'Entry', 'living-handbook' ),
					displayControl( props )
				),
				note(
					__(
						'Handbook entry (results): the areas and recently updated pages of this handbook, or the matches while a search or filter is active. The search bar and the filter bar are blocks of their own.',
						'living-handbook'
					)
				)
			);
		},
		save() {
			return null;
		},
	} );

	registerBlockType( 'living-handbook/search-form', {
		edit( props ) {
			const controls = [
				toggleControl(
					props,
					'showLabel',
					__( 'Show the label', 'living-handbook' )
				),
				textControl(
					props,
					'label',
					__( 'Label', 'living-handbook' ),
					__(
						'Read out to screen readers even when it is not shown.',
						'living-handbook'
					)
				),
				textControl(
					props,
					'placeholder',
					__( 'Placeholder', 'living-handbook' )
				),
				textControl(
					props,
					'buttonText',
					__( 'Button text', 'living-handbook' )
				),
			];
			if ( SelectControl ) {
				controls.push(
					el( SelectControl, {
						key: 'buttonPosition',
						label: __( 'Button position', 'living-handbook' ),
						value: props.attributes.buttonPosition,
						options: [
							{
								label: __(
									'Outside the field',
									'living-handbook'
								),
								value: 'button-outside',
							},
							{
								label: __(
									'Inside the field',
									'living-handbook'
								),
								value: 'button-inside',
							},
							{
								label: __( 'No button', 'living-handbook' ),
								value: 'no-button',
							},
						],
						onChange( value ) {
							props.setAttributes( { buttonPosition: value } );
						},
					} )
				);
			}
			return el(
				Fragment,
				{},
				panel( __( 'Search', 'living-handbook' ), controls ),
				note(
					__(
						'Handbook search: searches the handbook this page belongs to and narrows the result column. Colours, border, typography and spacing are in the block settings; the wording is here.',
						'living-handbook'
					)
				)
			);
		},
		save() {
			return null;
		},
	} );

	registerBlockType( 'living-handbook/filters', {
		edit() {
			return note(
				__(
					'Handbook filter bar: page type, topic, responsibility and audience of the pages in this handbook. It only offers terms that are actually used, so it is empty until pages carry them.',
					'living-handbook'
				)
			);
		},
		save() {
			return null;
		},
	} );

	registerBlockType( 'living-handbook/menu', {
		edit() {
			return el( serverSideRender, { block: 'living-handbook/menu' } );
		},
		save() {
			return null;
		},
	} );

	dynamic(
		'living-handbook/badges',
		__(
			'Handbook badges: page type, topic and audience of the current page.',
			'living-handbook'
		)
	);

	dynamic(
		'living-handbook/feedback',
		__(
			'Handbook feedback: the "Was this helpful?" prompt for the current page.',
			'living-handbook'
		)
	);

	// The three learning path blocks render from the path or the lesson being
	// read, which the editor does not have either. Without a client-side
	// registration the Site Editor showed them as unsupported, and deleting
	// such a placeholder removed the lesson list or the path bar from the
	// template (up to 0.75.0).
	dynamic(
		'living-handbook/lessons',
		__(
			'Learning path lessons: the ordered lessons of the learning path being read, with the start button.',
			'living-handbook'
		)
	);

	dynamic(
		'living-handbook/paths',
		__(
			'Learning paths of this handbook: the paths of the handbook whose entry page this is.',
			'living-handbook'
		)
	);

	dynamic(
		'living-handbook/path-nav',
		__(
			'Learning path bar: on a lesson opened from a learning path, which lesson of how many and the way to the previous and next one.',
			'living-handbook'
		)
	);

	registerBlockType( 'living-handbook/search', {
		edit( props ) {
			return el(
				Fragment,
				{},
				panel( __( 'Quick search', 'living-handbook' ), [
					toggleControl(
						props,
						'showLabel',
						__( 'Show the label', 'living-handbook' )
					),
					textControl(
						props,
						'label',
						__( 'Label', 'living-handbook' ),
						__(
							'Read out to screen readers even when it is not shown.',
							'living-handbook'
						)
					),
					textControl(
						props,
						'placeholder',
						__( 'Placeholder', 'living-handbook' )
					),
				] ),
				note(
					__(
						'Handbook quick search: lists matching pages as you type, each with the sentence it was found in, so a reader jumps straight to a page. Does not narrow the result column; that is the Handbook search block.',
						'living-handbook'
					)
				)
			);
		},
		save() {
			return null;
		},
	} );
} )();
