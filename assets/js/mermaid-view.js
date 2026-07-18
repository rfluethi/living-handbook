( function () {
	// Build the accessible name for a diagram from its title and description,
	// falling back to the diagram source captured before mermaid replaces it.
	function label( pre ) {
		var t = pre.getAttribute( 'data-title' ) || '';
		var d = pre.getAttribute( 'data-description' ) || '';
		var text = ( t + ( t && d ? '. ' : '' ) + d ).trim();
		if ( text ) {
			return text;
		}
		return ( pre.getAttribute( 'data-code' ) || '' ).trim();
	}

	// Give each rendered diagram a text alternative (WCAG 1.1.1): the SVG gets
	// role="img" and an aria-label, so it is not an unlabelled image.
	function annotate() {
		document.querySelectorAll( 'pre.mermaid' ).forEach( function ( pre ) {
			var svg = pre.querySelector( 'svg' );
			if ( ! svg ) {
				return;
			}
			svg.setAttribute( 'role', 'img' );
			var text = label( pre );
			if ( text ) {
				svg.setAttribute( 'aria-label', text );
			}
		} );
	}

	function run() {
		if ( ! window.mermaid ) {
			return;
		}
		// Capture the diagram source before mermaid replaces the element content,
		// so it can serve as a last-resort text alternative.
		document.querySelectorAll( 'pre.mermaid' ).forEach( function ( pre ) {
			if ( ! pre.hasAttribute( 'data-code' ) ) {
				pre.setAttribute( 'data-code', pre.textContent || '' );
			}
		} );

		try {
			window.mermaid.initialize( { startOnLoad: false } );
			var pending;
			if ( typeof window.mermaid.run === 'function' ) {
				pending = window.mermaid.run( { querySelector: 'pre.mermaid' } );
			} else if ( typeof window.mermaid.init === 'function' ) {
				window.mermaid.init( undefined, document.querySelectorAll( 'pre.mermaid' ) );
			}
			if ( pending && typeof pending.then === 'function' ) {
				pending.then( annotate ).catch( annotate );
			} else {
				annotate();
			}
		} catch ( e ) {
			if ( window.console ) {
				console.error( e );
			}
		}
	}

	if ( document.readyState !== 'loading' ) {
		run();
	} else {
		document.addEventListener( 'DOMContentLoaded', run );
	}
}() );
