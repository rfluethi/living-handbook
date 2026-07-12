( function () {
	function run() {
		if ( ! window.mermaid ) {
			return;
		}
		try {
			window.mermaid.initialize( { startOnLoad: false } );
			if ( typeof window.mermaid.run === 'function' ) {
				window.mermaid.run( { querySelector: '.mermaid' } );
			} else if ( typeof window.mermaid.init === 'function' ) {
				window.mermaid.init( undefined, document.querySelectorAll( '.mermaid' ) );
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
