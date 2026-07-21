( function ( wp ) {
	if ( ! wp || ! wp.blocks ) {
		return;
	}
	var blocks = wp.blocks;
	var blockEditor = wp.blockEditor;
	var element = wp.element;
	var components = wp.components;
	var __ = wp.i18n ? wp.i18n.__ : function ( s ) { return s; };
	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;
	var useEffect = element.useEffect;
	var useRef = element.useRef;

	// Load the mermaid library on demand, so the block editor does not pull in
	// 3.5 MB on every open. The URL is provided by the server (lhMermaid.src).
	function ensureMermaid( cb ) {
		if ( window.mermaid ) {
			cb();
			return;
		}
		var src = ( window.lhMermaid && window.lhMermaid.src ) ? window.lhMermaid.src : '';
		if ( ! src ) {
			cb();
			return;
		}
		var existing = document.getElementById( 'lh-mermaid-lib' );
		if ( existing ) {
			existing.addEventListener( 'load', cb );
			return;
		}
		var s = document.createElement( 'script' );
		s.id = 'lh-mermaid-lib';
		s.src = src;
		s.onload = cb;
		document.head.appendChild( s );
	}

	// Metadata comes from blocks/mermaid/block.json; only edit and save live here.
	blocks.registerBlockType( 'living-handbook/mermaid', {
		edit: function ( props ) {
			var code = props.attributes.code || '';
			var title = props.attributes.title || '';
			var description = props.attributes.description || '';
			var blockProps = useBlockProps();
			var previewRef = useRef();

			useEffect( function () {
				var node = previewRef.current;
				if ( ! node ) {
					return;
				}
				if ( ! code ) {
					// Nothing to render yet, so do not load the ~3.5 MB library.
					node.innerHTML = '';
					return;
				}
				ensureMermaid( function () {
					if ( ! previewRef.current ) {
						return;
					}
					var target = previewRef.current;
					if ( ! window.mermaid ) {
						target.textContent = __( 'mermaid.min.js is missing in assets/js/.', 'living-handbook' );
						return;
					}
					target.innerHTML = '';
					if ( ! code ) {
						return;
					}
					try {
						window.mermaid.initialize( { startOnLoad: false } );
						window.mermaid.render( 'lhm' + Date.now(), code ).then( function ( r ) {
							if ( previewRef.current ) {
								previewRef.current.innerHTML = r.svg;
							}
						} ).catch( function ( e ) {
							if ( previewRef.current ) {
								previewRef.current.textContent = 'Mermaid: ' + ( e && e.message ? e.message : e );
							}
						} );
					} catch ( e ) {
						target.textContent = 'Mermaid: ' + ( e && e.message ? e.message : e );
					}
				} );
			}, [ code ] );

			return el(
				'div',
				blockProps,
				el( components.TextareaControl, {
					label: __( 'Mermaid code', 'living-handbook' ),
					value: code,
					rows: 8,
					onChange: function ( v ) {
						props.setAttributes( { code: v } );
					}
				} ),
				el( components.TextControl, {
					label: __( 'Diagram title (caption)', 'living-handbook' ),
					value: title,
					onChange: function ( v ) {
						props.setAttributes( { title: v } );
					}
				} ),
				el( components.TextareaControl, {
					label: __( 'Diagram description (for screen readers)', 'living-handbook' ),
					value: description,
					rows: 2,
					onChange: function ( v ) {
						props.setAttributes( { description: v } );
					}
				} ),
				el( 'div', {
					ref: previewRef,
					style: { background: '#fff', padding: '1em', border: '1px solid #ccd0d4', minHeight: '2em' }
				} )
			);
		},
		save: function () {
			return null;
		}
	} );
}( window.wp ) );
