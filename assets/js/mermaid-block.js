/**
 * Editor registration of the Mermaid diagram block, with a live preview.
 *
 * Metadata comes from blocks/mermaid/block.json; only edit and save live here.
 * The block renders on the server, so save() returns null. The Mermaid library
 * is about 3.5 MB, so the editor loads it only once a block has code to draw,
 * from the URL the server passes as lhMermaid.src.
 */

/* global lhMermaid */

( function () {
	'use strict';

	const wp = window.wp;
	if ( ! wp || ! wp.blocks ) {
		return;
	}
	const { blocks, blockEditor, element, components } = wp;
	const __ = wp.i18n ? wp.i18n.__ : ( text ) => text;
	const el = element.createElement;
	const { useBlockProps } = blockEditor;
	const { useEffect, useRef } = element;

	/** Id of the script element that loads the Mermaid library. */
	const LIBRARY_ID = 'lh-mermaid-lib';

	/**
	 * Picks the Mermaid colour scheme for the preview.
	 *
	 * The editor canvas can be light while the site is dark, or the other way
	 * round, so the preview asks its own surroundings rather than the front
	 * end. Same rule as scheme() in mermaid-view.js: the WCAG channel weights
	 * without gamma linearisation, enough to tell light from dark.
	 *
	 * @param {Element|null} node The element the diagram is drawn into.
	 * @return {string} "dark" or "default".
	 */
	function editorScheme( node ) {
		let colour = '';
		while ( node ) {
			const background = window.getComputedStyle( node ).backgroundColor;
			if (
				background &&
				background !== 'transparent' &&
				background.indexOf( 'rgba(0, 0, 0, 0)' ) === -1
			) {
				colour = background;
				break;
			}
			node = node.parentElement;
		}
		const parts = colour ? colour.match( /\d+(\.\d+)?/g ) : null;
		if ( ! parts || parts.length < 3 ) {
			return 'default';
		}
		const brightness =
			( 0.2126 * parts[ 0 ] +
				0.7152 * parts[ 1 ] +
				0.0722 * parts[ 2 ] ) /
			255;
		return brightness < 0.5 ? 'dark' : 'default';
	}

	/**
	 * Loads the Mermaid library once, then calls back.
	 *
	 * The callback also runs when the library cannot be loaded, so the preview
	 * can say so.
	 *
	 * @param {() => void} callback Runs when the library is there, or is not
	 *                              going to be.
	 * @return {void}
	 */
	function ensureMermaid( callback ) {
		if ( window.mermaid ) {
			callback();
			return;
		}
		const src =
			typeof lhMermaid !== 'undefined' && lhMermaid.src
				? lhMermaid.src
				: '';
		if ( ! src ) {
			callback();
			return;
		}
		const existing = document.getElementById( LIBRARY_ID );
		if ( existing ) {
			existing.addEventListener( 'load', callback );
			return;
		}
		const script = document.createElement( 'script' );
		script.id = LIBRARY_ID;
		script.src = src;
		script.onload = callback;
		document.head.appendChild( script );
	}

	/**
	 * Draws the diagram into the preview element, or says why it cannot.
	 *
	 * @param {{current: HTMLElement|null}} previewRef Ref to the preview.
	 * @param {string}                      code       Mermaid source.
	 * @return {void}
	 */
	function drawPreview( previewRef, code ) {
		const target = previewRef.current;
		if ( ! target ) {
			return;
		}
		if ( ! window.mermaid ) {
			target.textContent = __(
				'mermaid.min.js is missing in assets/js/.',
				'living-handbook'
			);
			return;
		}
		target.innerHTML = '';
		/**
		 * Shows a Mermaid error in the preview.
		 *
		 * @param {Error|string} error What Mermaid threw.
		 * @return {void}
		 */
		const showError = ( error ) => {
			if ( previewRef.current ) {
				previewRef.current.textContent =
					'Mermaid: ' +
					( error && error.message ? error.message : error );
			}
		};
		try {
			window.mermaid.initialize( {
				startOnLoad: false,
				theme: editorScheme( target ),
			} );
			window.mermaid
				.render( 'lhm' + Date.now(), code )
				.then( ( result ) => {
					if ( previewRef.current ) {
						previewRef.current.innerHTML = result.svg;
					}
				} )
				.catch( showError );
		} catch ( error ) {
			showError( error );
		}
	}

	/**
	 * Renders the block in the editor: the code, the caption and description
	 * fields, and a preview of the diagram.
	 *
	 * Named as a component, because it calls hooks.
	 *
	 * @param {Object} props Block edit props.
	 * @return {Object} The element.
	 */
	function MermaidEdit( props ) {
		const code = props.attributes.code || '';
		const title = props.attributes.title || '';
		const description = props.attributes.description || '';
		const blockProps = useBlockProps();
		const previewRef = useRef();

		useEffect( () => {
			const node = previewRef.current;
			if ( ! node ) {
				return;
			}
			if ( ! code ) {
				// Nothing to render yet, so do not load the library.
				node.innerHTML = '';
				return;
			}
			ensureMermaid( () => drawPreview( previewRef, code ) );
		}, [ code ] );

		return el(
			'div',
			blockProps,
			el( components.TextareaControl, {
				label: __( 'Mermaid code', 'living-handbook' ),
				value: code,
				rows: 8,
				onChange( value ) {
					props.setAttributes( { code: value } );
				},
			} ),
			el( components.TextControl, {
				label: __( 'Diagram title (caption)', 'living-handbook' ),
				value: title,
				onChange( value ) {
					props.setAttributes( { title: value } );
				},
			} ),
			el( components.TextareaControl, {
				label: __(
					'Diagram description (for screen readers)',
					'living-handbook'
				),
				value: description,
				rows: 2,
				onChange( value ) {
					props.setAttributes( { description: value } );
				},
			} ),
			el( 'div', {
				ref: previewRef,
				style: {
					background: '#fff',
					padding: '1em',
					border: '1px solid #ccd0d4',
					minHeight: '2em',
				},
			} )
		);
	}

	blocks.registerBlockType( 'living-handbook/mermaid', {
		edit: MermaidEdit,
		save() {
			return null;
		},
	} );
} )();
