( function ( wp ) {
	if ( ! wp || ! wp.blocks ) {
		return;
	}
	var blocks = wp.blocks;
	var blockEditor = wp.blockEditor;
	var element = wp.element;
	var components = wp.components;
	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;
	var useEffect = element.useEffect;
	var useRef = element.useRef;

	blocks.registerBlockType( 'living-handbook/mermaid', {
		apiVersion: 3,
		title: 'Mermaid',
		icon: 'chart-area',
		category: 'living-handbook',
		attributes: { code: { type: 'string', default: '' } },
		edit: function ( props ) {
			var code = props.attributes.code || '';
			var blockProps = useBlockProps();
			var previewRef = useRef();

			useEffect( function () {
				var node = previewRef.current;
				if ( ! node ) {
					return;
				}
				if ( ! window.mermaid ) {
					node.textContent = 'mermaid.min.js fehlt in assets/js/.';
					return;
				}
				node.innerHTML = '';
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
					node.textContent = 'Mermaid: ' + ( e && e.message ? e.message : e );
				}
			}, [ code ] );

			return el(
				'div',
				blockProps,
				el( components.TextareaControl, {
					label: 'Mermaid-Code',
					value: code,
					rows: 8,
					onChange: function ( v ) {
						props.setAttributes( { code: v } );
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
