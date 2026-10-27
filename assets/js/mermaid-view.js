/**
 * Draws the Mermaid diagrams of a page and gives each a text alternative.
 *
 * Loaded wherever a Mermaid block is rendered, after mermaid.min.js. The
 * diagrams arrive as <pre class="mermaid"> with their source as text, plus the
 * title and description of the block as data attributes.
 */

( function () {
	'use strict';

	/**
	 * Picks the Mermaid colour scheme that fits the page.
	 *
	 * Before 0.57.0 the plugin started Mermaid with nothing but startOnLoad, so
	 * it always drew the light default: connecting lines in #333333, which on a
	 * dark theme are all but invisible. The page background decides instead,
	 * because that is what the diagram is drawn on. It uses the WCAG channel
	 * weights without gamma linearisation, which is enough to tell light from
	 * dark. The editor preview (mermaid-block.js) has the same function; the
	 * two scripts load in different places and share no handle.
	 *
	 * @return {string} "dark" or "default".
	 */
	function scheme() {
		let node = document.body;
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
	 * Builds the accessible name of a diagram from its title and description,
	 * falling back to the diagram source captured before Mermaid replaced it.
	 *
	 * @param {HTMLElement} pre The diagram's <pre class="mermaid">.
	 * @return {string} The text alternative, possibly empty.
	 */
	function label( pre ) {
		const title = pre.getAttribute( 'data-title' ) || '';
		const description = pre.getAttribute( 'data-description' ) || '';
		const text = (
			title +
			( title && description ? '. ' : '' ) +
			description
		).trim();
		if ( text ) {
			return text;
		}
		return ( pre.getAttribute( 'data-code' ) || '' ).trim();
	}

	/**
	 * Gives each rendered diagram a text alternative (WCAG 1.1.1): the SVG gets
	 * role="img" and an aria-label, so it is not an unlabelled image.
	 *
	 * @return {void}
	 */
	function annotate() {
		document.querySelectorAll( 'pre.mermaid' ).forEach( ( pre ) => {
			const svg = pre.querySelector( 'svg' );
			if ( ! svg ) {
				return;
			}
			svg.setAttribute( 'role', 'img' );
			const text = label( pre );
			if ( text ) {
				svg.setAttribute( 'aria-label', text );
			}
		} );
	}

	/**
	 * Renders every diagram on the page.
	 *
	 * @return {void}
	 */
	function run() {
		if ( ! window.mermaid ) {
			return;
		}
		// Capture the diagram source before Mermaid replaces the element
		// content, so it can serve as a last-resort text alternative.
		document.querySelectorAll( 'pre.mermaid' ).forEach( ( pre ) => {
			if ( ! pre.hasAttribute( 'data-code' ) ) {
				pre.setAttribute( 'data-code', pre.textContent || '' );
			}
		} );

		try {
			window.mermaid.initialize( {
				startOnLoad: false,
				theme: scheme(),
			} );
			let pending;
			if ( typeof window.mermaid.run === 'function' ) {
				pending = window.mermaid.run( {
					querySelector: 'pre.mermaid',
				} );
			} else if ( typeof window.mermaid.init === 'function' ) {
				// Mermaid before 10 has no run().
				window.mermaid.init(
					undefined,
					document.querySelectorAll( 'pre.mermaid' )
				);
			}
			if ( pending && typeof pending.then === 'function' ) {
				pending.then( annotate ).catch( annotate );
			} else {
				annotate();
			}
		} catch ( error ) {
			// A broken diagram must not stop the rest of the page; the console is
			// where an author looks for the reason.
			// eslint-disable-next-line no-console -- see the comment above.
			console.error( error );
		}
	}

	if ( document.readyState !== 'loading' ) {
		run();
	} else {
		document.addEventListener( 'DOMContentLoaded', run );
	}
} )();
