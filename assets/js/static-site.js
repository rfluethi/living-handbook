/**
 * The script of a handbook exported as a static website.
 *
 * Shipped inside the export ZIP as assets/site.js (Export\StaticSite), not
 * loaded by WordPress. It opens the navigation on small screens and runs the
 * search over the page index in assets/search-index.js (LH_PAGES). The labels
 * come translated from the server in assets/site-strings.js (LH_STRINGS), with
 * the results heading prepared for every count the search can show, so each
 * has the plural form its language gives it.
 */

/* global LH_PAGES, LH_STRINGS */

( function () {
	'use strict';

	/** Characters of page text shown when the search term is not in it. */
	const SNIPPET_LENGTH = 160;

	/** Characters shown before the match in a snippet. */
	const SNIPPET_BEFORE = 60;

	/** Characters shown after the match in a snippet. */
	const SNIPPET_AFTER = 100;

	/** Delay before a typed search runs, in milliseconds. */
	const SEARCH_DEBOUNCE_MS = 150;

	const pages = typeof LH_PAGES !== 'undefined' ? LH_PAGES : [];
	const strings =
		typeof LH_STRINGS !== 'undefined'
			? LH_STRINGS
			: { results: [], max: 50, noResults: '' };
	const results = document.getElementById( 'lh-results' );
	const body = document.getElementById( 'lh-body' );
	const field = document.getElementById( 'lh-search' );
	const toggle = document.querySelector( '.lh-menu-toggle' );
	const nav = document.getElementById( 'lh-nav' );

	if ( toggle && nav ) {
		toggle.addEventListener( 'click', () => {
			const open = nav.classList.toggle( 'is-open' );
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		} );
	}

	if ( ! field || ! results || ! body ) {
		return;
	}

	// Every page links the stylesheet relative to itself, so its href tells
	// how far this page is from the root of the export.
	const stylesheet = document.querySelector( 'link[rel="stylesheet"]' );
	const depth = (
		( stylesheet && stylesheet.getAttribute( 'href' ) ) ||
		''
	).replace( 'assets/style.css', '' );

	/**
	 * Escapes text for use inside HTML.
	 *
	 * @param {string} text Plain text.
	 * @return {string} The escaped text.
	 */
	function escapeHtml( text ) {
		return text.replace(
			/[&<>"']/g,
			( character ) =>
				( {
					'&': '&amp;',
					'<': '&lt;',
					'>': '&gt;',
					'"': '&quot;',
					"'": '&#39;',
				} )[ character ]
		);
	}

	/**
	 * Returns the part of a page's text around the first match, with the
	 * match marked.
	 *
	 * @param {string} text   The page's plain text.
	 * @param {string} needle The search term, lowercase.
	 * @return {string} HTML: escaped text with one <mark>.
	 */
	function snippet( text, needle ) {
		const at = text.toLowerCase().indexOf( needle );
		if ( at < 0 ) {
			return escapeHtml( text.slice( 0, SNIPPET_LENGTH ) );
		}
		const from = Math.max( 0, at - SNIPPET_BEFORE );
		const part = text.slice( from, at + needle.length + SNIPPET_AFTER );
		const mark = part.toLowerCase().indexOf( needle );
		return (
			( from > 0 ? '… ' : '' ) +
			escapeHtml( part.slice( 0, mark ) ) +
			'<mark>' +
			escapeHtml( part.slice( mark, mark + needle.length ) ) +
			'</mark>' +
			escapeHtml( part.slice( mark + needle.length ) ) +
			' …'
		);
	}

	/**
	 * Shows the pages that contain a term in place of the page content, or
	 * the content again for a term shorter than two characters.
	 *
	 * @param {string} term What was typed.
	 * @return {void}
	 */
	function search( term ) {
		const needle = term.trim().toLowerCase();
		if ( needle.length < 2 ) {
			results.hidden = true;
			results.innerHTML = '';
			body.hidden = false;
			return;
		}

		const hits = pages
			.filter(
				( page ) =>
					page.t.toLowerCase().indexOf( needle ) >= 0 ||
					page.x.toLowerCase().indexOf( needle ) >= 0
			)
			.slice( 0, strings.max );

		let html =
			'<h2>' +
			escapeHtml(
				strings.results[ hits.length ] || String( hits.length )
			) +
			'</h2>';
		if ( ! hits.length ) {
			html += '<p>' + escapeHtml( strings.noResults ) + '</p>';
		} else {
			html += '<ol>';
			hits.forEach( ( page ) => {
				html +=
					'<li><a href="' +
					depth +
					page.u +
					'">' +
					escapeHtml( page.t ) +
					'</a>' +
					'<p>' +
					snippet( page.x, needle ) +
					'</p></li>';
			} );
			html += '</ol>';
		}

		results.innerHTML = html;
		results.hidden = false;
		body.hidden = true;
	}

	let timer = null;
	field.addEventListener( 'input', () => {
		window.clearTimeout( timer );
		timer = window.setTimeout(
			() => search( field.value ),
			SEARCH_DEBOUNCE_MS
		);
	} );
	field.addEventListener( 'keydown', ( event ) => {
		if ( 'Escape' === event.key ) {
			field.value = '';
			search( '' );
		}
	} );
} )();
