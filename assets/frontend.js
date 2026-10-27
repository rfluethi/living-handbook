/**
 * Living Handbook frontend behaviour.
 *
 * Covers the AJAX card filtering and search of the entry page, the handbook
 * navigation accordion and the mobile toggle of the handbook menu, the quick
 * search (typeahead) of a single page, the on-this-page table of contents with
 * depth limit, smooth scrolling and scroll-spy, the feedback prompt, the
 * lightbox for content images and Mermaid diagrams, and the learning path
 * progress kept in this browser's local storage.
 *
 * Selecting a taxonomy facet or submitting the search filters the handbook
 * through a REST request and swaps the result list in place, so no full page
 * reload is needed; without JavaScript the facet and search forms submit
 * normally with their own button. Typing in the search box re-runs the server
 * search after a short debounce.
 *
 * The endpoints and labels arrive as the livingHandbook object
 * (FrontendRenderer::register_assets()). Every label has an English fallback
 * here, used only if that object is missing.
 */

/* global livingHandbook */

( function () {
	'use strict';

	/**
	 * Data printed by wp_localize_script(), or an empty object without it.
	 *
	 * @type {Object}
	 */
	const settings =
		typeof livingHandbook !== 'undefined' && livingHandbook
			? livingHandbook
			: {};

	/**
	 * Delay before a typed search is sent, in milliseconds. Long enough to
	 * skip the keystrokes of one word, short enough to feel live.
	 */
	const SEARCH_DEBOUNCE_MS = 150;

	/**
	 * Widest viewport that counts as narrow. Matches the 781px breakpoint of
	 * frontend.css, which is the WordPress mobile breakpoint.
	 */
	const NARROW_QUERY = '(max-width: 781px)';

	/**
	 * Scroll-spy band: a heading counts as current while it is in the upper
	 * 35% of the viewport, so the entry switches as a section starts, not when
	 * its heading has nearly left the screen.
	 */
	const SCROLL_SPY_MARGIN = '0px 0px -65% 0px';

	/**
	 * How much wider than shown a raster image must really be before it is
	 * worth enlarging, in CSS pixels. Rounding of responsive images leaves a
	 * pixel or two that are not a real enlargement.
	 */
	const ZOOM_TOLERANCE_PX = 4;

	/**
	 * Base width of an enlarged diagram in the lightbox. Large on purpose: the
	 * stylesheet scales it down to the overlay, so it is sharp at any size.
	 */
	const DIAGRAM_ZOOM_WIDTH = '1600';

	/**
	 * Tells whether the visitor asked for reduced motion.
	 *
	 * @return {boolean} True when animations should be avoided.
	 */
	function prefersReducedMotion() {
		return (
			!! window.matchMedia &&
			window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches
		);
	}

	/* ---------- REST requests ---------- */

	// Set once core has refused the nonce of this page; see restFetch().
	let nonceRejected = false;

	/**
	 * Builds the headers of a request to the plugin's endpoints.
	 *
	 * The nonce is only there for a signed-in reader. Sending an empty or stale
	 * one makes core refuse the request, so a guest sends none.
	 *
	 * @param {Object} [extra] Headers to send in addition.
	 * @return {Object} The headers, with X-WP-Nonce where it belongs.
	 */
	function restHeaders( extra ) {
		const headers = extra || {};
		if ( ! nonceRejected && settings.nonce ) {
			headers[ 'X-WP-Nonce' ] = settings.nonce;
		}
		return headers;
	}

	/**
	 * Fetches a plugin endpoint, retrying once without the nonce if core
	 * refuses it.
	 *
	 * A page cache can hand the page of a signed-in reader, nonce and all, to a
	 * guest. Core checks that nonce against the cookies of whoever asks and
	 * refuses with 403 rest_cookie_invalid_nonce before the endpoint runs. Up to
	 * 0.75.1 the filters failed on such a page and the quick search said
	 * nothing at all. Without the header the request runs as a guest, which is
	 * who the reader then is, and the endpoints decide what a guest may see. So
	 * that answer is asked again once without the nonce, and the nonce stays
	 * off for the rest of the page. The refused request never reached the
	 * endpoint, so asking again counts no feedback twice.
	 *
	 * @param {string} url     Endpoint URL, query string included.
	 * @param {Object} options Request options: method, headers, body, signal.
	 * @return {Promise<Response>} The response of the last attempt.
	 */
	function restFetch( url, options ) {
		const extra = options.headers || {};
		const withNonce = ! nonceRejected && !! settings.nonce;

		/**
		 * Sends the request with the headers that apply right now.
		 *
		 * @return {Promise<Response>} The response.
		 */
		function attempt() {
			return window.fetch( url, {
				...options,
				headers: restHeaders( { ...extra } ),
			} );
		}

		return attempt().then( ( response ) => {
			if ( ! withNonce || 403 !== response.status ) {
				return response;
			}
			return response
				.clone()
				.json()
				.then(
					( body ) => {
						if (
							! body ||
							'rest_cookie_invalid_nonce' !== body.code
						) {
							return response;
						}
						nonceRejected = true;
						return attempt();
					},
					() => response
				);
		} );
	}

	/**
	 * Returns a debounced version of a function.
	 *
	 * @param {(...args: unknown[]) => void} fn   Function to call once the calls
	 *                                            have paused.
	 * @param {number}                       wait Pause in milliseconds.
	 * @return {(...args: unknown[]) => void} The debounced function.
	 */
	function debounce( fn, wait ) {
		let timer = null;
		return function ( ...args ) {
			if ( timer ) {
				clearTimeout( timer );
			}
			timer = setTimeout( () => fn.apply( this, args ), wait );
		};
	}

	/* ---------- Entry page: AJAX facet and search filtering ---------- */

	/**
	 * Tells whether the filter endpoint is known, so filtering can run in place.
	 *
	 * @return {boolean} True when AJAX filtering is available.
	 */
	function canAjax() {
		return !! settings.filter;
	}

	/**
	 * Writes the current filter state into the address bar, so a reload or a
	 * shared link shows the same list.
	 *
	 * @param {URLSearchParams} params The parameters of the filter request.
	 * @return {void}
	 */
	function updateUrl( params ) {
		if ( ! window.history || ! window.history.replaceState ) {
			return;
		}
		const query = new URLSearchParams();
		params.forEach( ( value, key ) => {
			if ( 'term_id' !== key ) {
				query.append( key, value );
			}
		} );
		const search = query.toString();
		window.history.replaceState(
			null,
			'',
			window.location.pathname + ( search ? '?' + search : '' )
		);
	}

	/**
	 * Shows, once, that the result list could not be updated.
	 *
	 * @param {HTMLElement} main The result column.
	 * @return {void}
	 */
	function showFilterError( main ) {
		if ( ! main || main.querySelector( '.living-handbook-filter-error' ) ) {
			return;
		}
		const message = document.createElement( 'p' );
		message.className = 'living-handbook-filter-error';
		message.setAttribute( 'role', 'alert' );
		message.textContent =
			settings.filterError ||
			'The list could not be updated. Please reload the page.';
		main.insertBefore( message, main.firstChild );
	}

	/**
	 * Announces the new result count through the entry's status line.
	 *
	 * The server already renders the count ("%d pages found") and the empty
	 * message, both translated, so the status line repeats that text rather
	 * than inventing a second wording.
	 *
	 * @param {HTMLElement} entry The entry element.
	 * @param {HTMLElement} main  The result column.
	 * @return {void}
	 */
	function announceCount( entry, main ) {
		const status =
			entry.querySelector( '.living-handbook-entry__status' ) ||
			document.querySelector( '.living-handbook-entry__status' );
		if ( ! status ) {
			return;
		}
		const said =
			main.querySelector( '.living-handbook-empty' ) ||
			main.querySelector( '.living-handbook-count' );
		status.textContent = said ? said.textContent : '';
	}

	/**
	 * Filters the result list in place from the current search and facets.
	 *
	 * @param {HTMLElement} entry The entry element that holds the result column.
	 * @return {void}
	 */
	function ajaxFilter( entry ) {
		if ( ! canAjax() ) {
			return;
		}
		const main = entry.querySelector( '.living-handbook-main' );
		const termId = entry.getAttribute( 'data-term-id' );
		if ( ! main || ! termId ) {
			return;
		}

		const params = new URLSearchParams();
		params.set( 'term_id', termId );

		// The controls are looked up in the document, not in the entry element.
		// The search bar and the filter bar are blocks of their own and may sit
		// anywhere on the page, and a term archive shows exactly one handbook, so
		// the document is the right scope.
		const input = document.querySelector(
			'.living-handbook-search__input'
		);
		if ( input && input.value.trim() ) {
			params.set( 'lh_s', input.value.trim() );
		}
		document
			.querySelectorAll( '.living-handbook-facet__cb:checked' )
			.forEach( ( checkbox ) => {
				params.append( checkbox.name, checkbox.value );
			} );

		// Cancel a request that is still in flight for this entry, so a quick
		// series of clicks does not race and land out of order.
		if ( entry.lhController ) {
			entry.lhController.abort();
		}
		const controller =
			'AbortController' in window ? new window.AbortController() : null;
		entry.lhController = controller;

		main.setAttribute( 'aria-busy', 'true' );
		restFetch( settings.filter + '?' + params.toString(), {
			credentials: 'same-origin',
			signal: controller ? controller.signal : undefined,
		} )
			.then( ( response ) => {
				if ( ! response.ok ) {
					throw new Error( 'HTTP ' + response.status );
				}
				return response.json();
			} )
			.then( ( data ) => {
				if ( data && typeof data.html === 'string' ) {
					main.innerHTML = data.html;
				}
				announceCount( entry, main );
				main.removeAttribute( 'aria-busy' );
				entry.lhController = null;
				updateUrl( params );
			} )
			.catch( ( error ) => {
				// A superseded request was aborted on purpose; leave it silent.
				if ( error && 'AbortError' === error.name ) {
					return;
				}
				main.removeAttribute( 'aria-busy' );
				entry.lhController = null;
				showFilterError( main );
			} );
	}

	/**
	 * Finds the entry element of the page.
	 *
	 * The entry element is the one that holds the result column and knows the
	 * handbook. The controls may live inside it, or as their own blocks
	 * anywhere else on the page; either way they drive this one.
	 *
	 * @return {HTMLElement|null} The entry element, or null on other pages.
	 */
	function entryElement() {
		return document.querySelector( '.living-handbook-entry[data-term-id]' );
	}

	/**
	 * Connects the search bar and the filter bar to the result list.
	 *
	 * @param {HTMLElement|null} entry The entry element, see entryElement().
	 * @return {void}
	 */
	function wireEntry( entry ) {
		if ( ! entry ) {
			return;
		}

		// With JavaScript the facets filter live on change, so the no-JS submit
		// button is hidden.
		document
			.querySelectorAll( '.living-handbook-filterform__submit' )
			.forEach( ( button ) => {
				button.hidden = true;
			} );

		// Listening on the document rather than on the entry element, because a
		// filter bar placed as its own block is not inside it. A no-JS page keeps
		// working either way: the forms submit to the handbook.
		document.addEventListener( 'change', ( event ) => {
			if (
				event.target &&
				event.target.classList &&
				event.target.classList.contains( 'living-handbook-facet__cb' )
			) {
				ajaxFilter( entry );
			}
		} );

		if ( ! canAjax() ) {
			return;
		}

		document
			.querySelectorAll(
				'.living-handbook-start__search, .living-handbook-filterform'
			)
			.forEach( ( form ) => {
				form.addEventListener( 'submit', ( event ) => {
					event.preventDefault();
					ajaxFilter( entry );
				} );
			} );

		// Live search: re-run the server search after a short debounce, so the
		// result list (title and body matches) stays authoritative instead of
		// hiding cards by their visible text.
		const searchInput = document.querySelector(
			'.living-handbook-search__input'
		);
		if ( searchInput ) {
			searchInput.addEventListener(
				'input',
				debounce( () => ajaxFilter( entry ), SEARCH_DEBOUNCE_MS )
			);
		}
	}

	/* ---------- Handbook navigation: accordion and mobile collapse ---------- */

	/**
	 * Marks every handbook navigation as driven by this script.
	 *
	 * The stylesheet collapses closed branches and shows the toggles only once
	 * it is known that the script runs, because without it the toggles do
	 * nothing. Collapsing before the first paint is the job of the inline line
	 * in the head that sets living-handbook-js on the root element; this marker
	 * is the fallback for a page where that line did not run. It is set as
	 * soon as this file runs and again from wireNav(), for the case where core
	 * loads the file without defer and the navigation did not exist yet.
	 *
	 * @return {void}
	 */
	function markNav() {
		document
			.querySelectorAll( '.living-handbook-nav' )
			.forEach( ( nav ) => {
				nav.classList.add( 'living-handbook-nav--js' );
			} );
	}

	markNav();

	/**
	 * Connects the branch toggles and the title toggle of every navigation.
	 *
	 * @return {void}
	 */
	function wireNav() {
		markNav();

		document
			.querySelectorAll( '.living-handbook-nav__toggle' )
			.forEach( ( button ) => {
				button.addEventListener( 'click', () => {
					const item = button.closest( '.living-handbook-nav__item' );
					if ( ! item ) {
						return;
					}
					const open = item.classList.toggle( 'is-open' );
					button.setAttribute(
						'aria-expanded',
						open ? 'true' : 'false'
					);
				} );
			} );

		// The title row's toggle opens and closes the whole navigation. It is the
		// same button as a branch toggle, one level up, so it is wired here
		// rather than through a second mechanism.
		document
			.querySelectorAll( '.living-handbook-nav__toggle--all' )
			.forEach( ( button ) => {
				button.addEventListener( 'click', () => {
					const nav = button.closest( '.living-handbook-nav' );
					if ( ! nav ) {
						return;
					}
					const collapsed = nav.classList.toggle( 'is-collapsed' );
					button.setAttribute(
						'aria-expanded',
						collapsed ? 'false' : 'true'
					);
				} );
			} );

		// Start the whole navigation collapsed on narrow screens; on wider
		// screens it stays open.
		if ( window.matchMedia && window.matchMedia( NARROW_QUERY ).matches ) {
			document
				.querySelectorAll( '.living-handbook-nav' )
				.forEach( ( nav ) => {
					nav.classList.add( 'is-collapsed' );
					const button = nav.querySelector(
						'.living-handbook-nav__toggle--all'
					);
					if ( button ) {
						button.setAttribute( 'aria-expanded', 'false' );
					}
				} );
		}
	}

	/* ---------- Handbook menu: mobile toggle ---------- */

	/**
	 * Connects the toggle that opens the handbook menu on narrow screens.
	 *
	 * @return {void}
	 */
	function wireMenus() {
		document
			.querySelectorAll( '.living-handbook-menu__toggle' )
			.forEach( ( button ) => {
				button.addEventListener( 'click', () => {
					const nav = button.closest( '.living-handbook-menu' );
					if ( ! nav ) {
						return;
					}
					const open = nav.classList.toggle( 'is-open' );
					button.setAttribute(
						'aria-expanded',
						open ? 'true' : 'false'
					);
				} );
			} );
	}

	/* ---------- Single page: quick search (typeahead) ---------- */

	/**
	 * Turns a quick search block into a typeahead over the handbook.
	 *
	 * @param {HTMLElement} box The block root, carrying data-term-id.
	 * @return {void}
	 */
	function wirePageSearch( box ) {
		if ( ! settings.search ) {
			return;
		}
		const input = box.querySelector(
			'.living-handbook-page-search__input'
		);
		const results = box.querySelector(
			'.living-handbook-page-search__results'
		);
		const termId = box.getAttribute( 'data-term-id' );
		if ( ! input || ! results || ! termId ) {
			return;
		}
		const status = box.querySelector( '[role="status"]' );
		let controller = null;

		/**
		 * Puts a sentence into the live region.
		 *
		 * @param {string} text What screen readers should hear.
		 * @return {void}
		 */
		function say( text ) {
			if ( status ) {
				status.textContent = text;
			}
		}

		/**
		 * Closes the result list.
		 *
		 * @return {void}
		 */
		function hide() {
			results.hidden = true;
			say( '' );
		}

		/**
		 * Says, where the results would be, that the search failed. Up to
		 * 0.75.1 the list closed without a word, and the field looked as if it
		 * did nothing.
		 *
		 * @return {void}
		 */
		function fail() {
			results.innerHTML = '';
			const error = document.createElement( 'li' );
			error.className =
				'living-handbook-page-search__empty living-handbook-page-search__error';
			error.textContent =
				settings.searchError ||
				'The search failed. Please reload the page.';
			results.appendChild( error );
			say( error.textContent );
			results.hidden = false;
		}

		/**
		 * Builds the snippet paragraph of one result.
		 *
		 * The sentence the words were found in arrives as segments the server
		 * marked. Every piece goes in as text, so nothing from the page content
		 * is ever parsed as markup here.
		 *
		 * @param {Array<{text: string, mark: boolean}>} snippet Text segments.
		 * @return {HTMLElement} The paragraph.
		 */
		function snippetElement( snippet ) {
			const paragraph = document.createElement( 'p' );
			paragraph.className = 'living-handbook-page-search__snippet';
			snippet.forEach( ( part ) => {
				if ( part.mark ) {
					const mark = document.createElement( 'mark' );
					mark.textContent = part.text;
					paragraph.appendChild( mark );
				} else {
					paragraph.appendChild(
						document.createTextNode( part.text )
					);
				}
			} );
			return paragraph;
		}

		/**
		 * Shows the results of a search.
		 *
		 * @param {Array<Object>} items   Results with url, title and snippet.
		 * @param {string}        summary Sentence for the live region, with the
		 *                                plural form chosen by the server.
		 * @return {void}
		 */
		function render( items, summary ) {
			results.innerHTML = '';
			if ( ! items.length ) {
				const empty = document.createElement( 'li' );
				empty.className = 'living-handbook-page-search__empty';
				empty.textContent = settings.searchEmpty || 'No matches.';
				results.appendChild( empty );
				say( empty.textContent );
				results.hidden = false;
				return;
			}
			items.forEach( ( item ) => {
				const entry = document.createElement( 'li' );
				const link = document.createElement( 'a' );
				link.href = item.url;
				link.textContent = item.title;
				entry.appendChild( link );
				if ( item.snippet && item.snippet.length ) {
					entry.appendChild( snippetElement( item.snippet ) );
				}
				results.appendChild( entry );
			} );
			// One short sentence, not the whole list: the list itself is right
			// there to be walked with the arrow keys. The server sends it with
			// the right plural form; the script has no plural rules of its own.
			say( summary || String( items.length ) );
			results.hidden = false;
		}

		/**
		 * Returns the result links, in order.
		 *
		 * @return {HTMLAnchorElement[]} The links.
		 */
		function links() {
			return Array.from( results.querySelectorAll( 'a' ) );
		}

		/**
		 * Moves the focus through the results with the arrow keys, from the
		 * input into the list and back out at the top. The results are links,
		 * so Enter needs no handler.
		 *
		 * @param {Element|null} from The element that has the focus now, or null
		 *                            when coming from the input.
		 * @param {number}       step 1 for down, -1 for up.
		 * @return {void}
		 */
		function move( from, step ) {
			const all = links();
			if ( ! all.length ) {
				return;
			}
			const index = all.indexOf( from );
			let next;
			if ( -1 !== index ) {
				next = index + step;
			} else {
				next = step > 0 ? 0 : all.length - 1;
			}
			if ( next < 0 ) {
				input.focus();
				return;
			}
			all[ Math.min( next, all.length - 1 ) ].focus();
		}

		/**
		 * Sends the search for what is in the field, cancelling an older one.
		 *
		 * @return {void}
		 */
		function run() {
			const query = input.value.trim();
			if ( query.length < 2 ) {
				hide();
				return;
			}
			if ( controller ) {
				controller.abort();
			}
			controller =
				'AbortController' in window
					? new window.AbortController()
					: null;
			const params = new URLSearchParams();
			params.set( 'term_id', termId );
			params.set( 'q', query );
			restFetch( settings.search + '?' + params.toString(), {
				credentials: 'same-origin',
				signal: controller ? controller.signal : undefined,
			} )
				.then( ( response ) => {
					if ( ! response.ok ) {
						throw new Error( 'HTTP ' + response.status );
					}
					return response.json();
				} )
				.then( ( data ) => {
					render(
						data && data.results ? data.results : [],
						data && data.summary ? data.summary : ''
					);
				} )
				.catch( ( error ) => {
					if ( error && 'AbortError' === error.name ) {
						return;
					}
					fail();
				} );
		}

		input.addEventListener( 'input', debounce( run, SEARCH_DEBOUNCE_MS ) );
		input.addEventListener( 'focus', () => {
			if ( ! results.hidden || results.children.length ) {
				run();
			}
		} );
		input.addEventListener( 'keydown', ( event ) => {
			if ( 'Escape' === event.key ) {
				input.value = '';
				hide();
				return;
			}
			if ( 'ArrowDown' === event.key && ! results.hidden ) {
				event.preventDefault();
				move( null, 1 );
			}
		} );

		results.addEventListener( 'keydown', ( event ) => {
			if ( 'Escape' === event.key ) {
				hide();
				input.focus();
				return;
			}
			if ( 'ArrowDown' === event.key ) {
				event.preventDefault();
				move( event.target, 1 );
			}
			if ( 'ArrowUp' === event.key ) {
				event.preventDefault();
				move( event.target, -1 );
			}
		} );

		// Leaving the field and the list altogether closes it, so a stale list
		// does not sit under the next thing the visitor does.
		box.addEventListener( 'focusout', ( event ) => {
			if ( ! box.contains( event.relatedTarget ) ) {
				hide();
			}
		} );
	}

	/* ---------- Single page: on-this-page table of contents ---------- */

	/**
	 * Returns the level of a heading element.
	 *
	 * @param {HTMLElement} heading An h1 to h6 element.
	 * @return {number} 1 to 6.
	 */
	function level( heading ) {
		return parseInt( heading.tagName.substring( 1 ), 10 ) || 1;
	}

	/**
	 * Returns the text of a heading without its anchor link, so the table of
	 * contents entry does not read "Installation #".
	 *
	 * @param {HTMLElement} heading The heading.
	 * @return {string} The heading text.
	 */
	function headingText( heading ) {
		const copy = heading.cloneNode( true );
		copy.querySelectorAll( '.living-handbook-anchor' ).forEach(
			( anchor ) => {
				anchor.remove();
			}
		);
		return copy.textContent.trim();
	}

	/**
	 * Builds one table of contents entry that scrolls to its heading.
	 *
	 * @param {HTMLElement} heading The heading the entry points to.
	 * @return {HTMLLIElement} The list item.
	 */
	function tocItem( heading ) {
		const item = document.createElement( 'li' );
		item.className = 'living-handbook-toc__item';
		item.style.paddingInlineStart = ( level( heading ) - 1 ) * 0.8 + 'rem';
		const link = document.createElement( 'a' );
		link.href = '#' + heading.id;
		link.textContent = headingText( heading );
		link.addEventListener( 'click', ( event ) => {
			event.preventDefault();
			heading.scrollIntoView( {
				behavior: prefersReducedMotion() ? 'auto' : 'smooth',
				block: 'start',
			} );
			// Move keyboard focus to the target heading so keyboard and
			// screen-reader users land at the section, not back at the top.
			if ( ! heading.hasAttribute( 'tabindex' ) ) {
				heading.setAttribute( 'tabindex', '-1' );
			}
			heading.focus( { preventScroll: true } );
			if ( window.history && window.history.pushState ) {
				window.history.pushState( null, '', '#' + heading.id );
			}
		} );
		item.appendChild( link );
		return item;
	}

	/**
	 * Fills every table of contents box and starts the scroll-spy.
	 *
	 * @return {void}
	 */
	function buildToc() {
		const boxes = document.querySelectorAll( '.living-handbook-toc' );
		if ( ! boxes.length ) {
			return;
		}
		const content =
			document.querySelector( '.wp-block-post-content' ) ||
			document.querySelector( '.entry-content' );
		if ( ! content ) {
			return;
		}
		const heads = content.querySelectorAll( 'h1, h2, h3, h4, h5, h6' );
		if ( ! heads.length ) {
			return;
		}

		// h2 to h4 get their id from the server (Frontend\Headings), from the
		// heading text. What is left here are h1, h5 and h6, and any heading
		// that filter did not touch. Those keep the positional fallback: it is
		// not an address to pass on, but it is enough for the table of contents
		// on this page.
		heads.forEach( ( heading, index ) => {
			if ( ! heading.id ) {
				heading.id = 'lh-section-' + index;
			}
		} );

		// Heading id => the entries that point to it, across all boxes.
		const map = {};

		/**
		 * Records that a table of contents link points to a heading.
		 *
		 * @param {string}            id   Heading id.
		 * @param {HTMLAnchorElement} link The entry's link.
		 * @return {void}
		 */
		function remember( id, link ) {
			map[ id ] = map[ id ] || [];
			map[ id ].push( link );
		}

		boxes.forEach( ( box ) => {
			const list = box.querySelector( '.living-handbook-toc__list' );
			if ( ! list ) {
				return;
			}

			// A list that arrives with entries was filled on the server: the static
			// export writes it into the file, so it works without scripting and in
			// print. Up to 0.75.1 this function appended its own entries below
			// those, and every exported page showed its table of contents twice.
			// The entries already there are adopted for the scroll-spy instead.
			const filled = list.querySelectorAll( 'a[href^="#"]' );
			if ( filled.length ) {
				filled.forEach( ( link ) => {
					remember(
						decodeURIComponent(
							link.getAttribute( 'href' ).substring( 1 )
						),
						link
					);
				} );
				return;
			}

			const maxDepth =
				parseInt( box.getAttribute( 'data-max-depth' ), 10 ) || 6;
			let shown = false;

			heads.forEach( ( heading ) => {
				if ( level( heading ) > maxDepth ) {
					return;
				}
				shown = true;
				const item = tocItem( heading );
				list.appendChild( item );
				remember( heading.id, item.firstChild );
			} );

			if ( shown ) {
				box.hidden = false;
			}
		} );

		if ( ! ( 'IntersectionObserver' in window ) ) {
			return;
		}
		let active = [];
		const observer = new window.IntersectionObserver(
			( entries ) => {
				entries.forEach( ( entry ) => {
					if ( ! entry.isIntersecting || ! map[ entry.target.id ] ) {
						return;
					}
					active.forEach( ( link ) =>
						link.classList.remove( 'is-active' )
					);
					active = map[ entry.target.id ];
					active.forEach( ( link ) =>
						link.classList.add( 'is-active' )
					);
				} );
			},
			{ rootMargin: SCROLL_SPY_MARGIN }
		);
		heads.forEach( ( heading ) => {
			if ( map[ heading.id ] ) {
				observer.observe( heading );
			}
		} );
	}

	/* ---------- Single page: feedback ---------- */

	/**
	 * Sends a "Was this helpful?" answer and replaces the prompt with thanks.
	 *
	 * @param {HTMLElement} box   The feedback block, carrying data-post.
	 * @param {string}      value The answer, as the button's data-value.
	 * @return {void}
	 */
	function sendFeedback( box, value ) {
		if ( ! settings.rest ) {
			return;
		}
		const postId = parseInt( box.getAttribute( 'data-post' ), 10 );
		const buttons = box.querySelectorAll( 'button[data-value]' );
		// Prevent double counting: disable both buttons the moment one is used.
		buttons.forEach( ( button ) => {
			button.disabled = true;
		} );

		restFetch( settings.rest, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify( { post_id: postId, value } ),
		} )
			.then( ( response ) => {
				if ( ! response.ok ) {
					throw new Error( 'HTTP ' + response.status );
				}
				// Replace the prompt with the confirmation and move focus to it so
				// the aria-live region announces the change for screen-reader users.
				box.textContent = settings.thanks;
				box.setAttribute( 'tabindex', '-1' );
				box.focus();
			} )
			.catch( () => {
				// Re-enable the buttons and show a real error, not a false thanks.
				buttons.forEach( ( button ) => {
					button.disabled = false;
				} );
				const existing = box.querySelector(
					'.living-handbook-feedback__error'
				);
				if ( existing ) {
					existing.remove();
				}
				const message = document.createElement( 'span' );
				message.className = 'living-handbook-feedback__error';
				message.textContent =
					settings.feedbackError || 'Please try again.';
				box.appendChild( message );
			} );
	}

	/**
	 * Connects the answer buttons of every feedback block.
	 *
	 * @return {void}
	 */
	function wireFeedback() {
		document
			.querySelectorAll( '.living-handbook-feedback' )
			.forEach( ( box ) => {
				// Announce the confirmation to assistive technology when the
				// buttons are replaced by the thank-you text.
				box.setAttribute( 'aria-live', 'polite' );
				box.querySelectorAll( 'button[data-value]' ).forEach(
					( button ) => {
						button.addEventListener( 'click', () => {
							sendFeedback(
								box,
								button.getAttribute( 'data-value' )
							);
						} );
					}
				);
			} );
	}

	/* ---------- Content images and diagrams: click to enlarge ---------- */

	/**
	 * Makes content images and Mermaid diagrams open in a lightbox.
	 *
	 * Handbook content images are plain <img>, not core Image blocks, so the
	 * core "Enlarge on click" never reaches them. This gives them the same
	 * behaviour: an image shown smaller than its real size, and any SVG image,
	 * becomes a button that opens it in a dark overlay, closed by a click, the
	 * close button, or Escape.
	 *
	 * @return {void}
	 */
	function initLightbox() {
		const scopes = document.querySelectorAll(
			'.living-handbook-page .wp-block-post-content, .living-handbook-page .entry-content'
		);
		if ( ! scopes.length ) {
			return;
		}

		let overlay = null;
		let lastFocus = null;

		/**
		 * Closes the overlay and gives the focus back.
		 *
		 * @return {void}
		 */
		function close() {
			if ( ! overlay ) {
				return;
			}
			overlay.remove();
			overlay = null;
			document.removeEventListener( 'keydown', onKey );
			if ( lastFocus && lastFocus.focus ) {
				lastFocus.focus();
			}
		}

		/**
		 * Handles the keyboard while the overlay is open.
		 *
		 * @param {KeyboardEvent} event The key event.
		 * @return {void}
		 */
		function onKey( event ) {
			if ( 'Escape' === event.key || 'Esc' === event.key ) {
				close();
				return;
			}
			// aria-modal says the rest of the page is not there, so Tab must not
			// walk out of the overlay. The close button is the only thing in it.
			if ( 'Tab' === event.key && overlay ) {
				event.preventDefault();
				const button = overlay.querySelector(
					'.living-handbook-lightbox__close'
				);
				if ( button ) {
					button.focus();
				}
			}
		}

		/**
		 * Opens the overlay with an element in it.
		 *
		 * @param {Element} displayEl The enlarged image or diagram.
		 * @param {string}  ariaLabel Accessible name of the dialog.
		 * @return {void}
		 */
		function buildOverlay( displayEl, ariaLabel ) {
			lastFocus = displayEl.ownerDocument.activeElement;

			overlay = document.createElement( 'div' );
			overlay.className = 'living-handbook-lightbox';
			overlay.setAttribute( 'role', 'dialog' );
			overlay.setAttribute( 'aria-modal', 'true' );
			if ( ariaLabel ) {
				overlay.setAttribute( 'aria-label', ariaLabel );
			}

			const button = document.createElement( 'button' );
			button.type = 'button';
			button.className = 'living-handbook-lightbox__close';
			button.setAttribute(
				'aria-label',
				settings.lightboxClose || 'Close'
			);
			button.textContent = '×';

			overlay.appendChild( displayEl );
			overlay.appendChild( button );

			// Close on the backdrop and on the button, not on the picture itself:
			// clicking what you just enlarged should not make it disappear.
			overlay.addEventListener( 'click', ( event ) => {
				if (
					event.target === overlay ||
					button.contains( event.target )
				) {
					close();
				}
			} );

			document.body.appendChild( overlay );
			document.addEventListener( 'keydown', onKey );
			button.focus();
		}

		/**
		 * Opens an image in the overlay.
		 *
		 * @param {HTMLImageElement} img The content image.
		 * @return {void}
		 */
		function openImage( img ) {
			const big = document.createElement( 'img' );
			big.src = img.currentSrc || img.src;
			big.alt = img.alt || '';
			buildOverlay( big, img.alt );
		}

		/**
		 * Opens a Mermaid diagram in the overlay.
		 *
		 * A diagram is an inline <svg>, not an <img>. The overlay shows a clone
		 * without the diagram's own width limit and with a large base width, so
		 * the stylesheet scales it down to fit.
		 *
		 * @param {HTMLElement} box The .mermaid container.
		 * @return {void}
		 */
		function openMermaid( box ) {
			const svg = box.querySelector( 'svg' );
			if ( ! svg ) {
				return;
			}
			const clone = svg.cloneNode( true );
			clone.removeAttribute( 'style' );
			clone.setAttribute( 'width', DIAGRAM_ZOOM_WIDTH );
			clone.removeAttribute( 'height' );
			buildOverlay(
				clone,
				settings.lightboxDiagram || 'Enlarged diagram'
			);
		}

		/**
		 * Tells whether an image is an SVG file.
		 *
		 * @param {HTMLImageElement} img The image.
		 * @return {boolean} True for an .svg source.
		 */
		function isSvg( img ) {
			const src = ( img.currentSrc || img.src || '' )
				.split( '?' )[ 0 ]
				.split( '#' )[ 0 ];
			return /\.svg$/i.test( src );
		}

		/**
		 * Returns the label of the enlarge button.
		 *
		 * @return {string} The translated label.
		 */
		function enlargeLabel() {
			return settings.lightboxOpen || 'Enlarge';
		}

		/**
		 * Wraps an image or diagram in a button that opens the overlay.
		 *
		 * A click handler on an <img> or a <div> is reachable with a mouse and
		 * with nothing else. Wrapping the thing in a real button brings focus,
		 * Enter, Space and the announcement along, and costs one element.
		 *
		 * @param {Element}    el         The image or diagram container.
		 * @param {() => void} onOpen     Opens the overlay.
		 * @param {string}     label      Accessible name of the button.
		 * @param {string}     [modifier] Extra class, see the zoom--diagram rule.
		 * @return {HTMLElement} The button, new or already there.
		 */
		function wrapInButton( el, onOpen, label, modifier ) {
			if (
				el.parentNode &&
				el.parentNode.classList &&
				el.parentNode.classList.contains( 'living-handbook-zoom' )
			) {
				return el.parentNode;
			}
			const button = document.createElement( 'button' );
			button.type = 'button';
			// A diagram is drawn to the width it is given, an image brings its own.
			// The trigger has to behave accordingly, or the diagram collapses to
			// the button's shrink-to-fit width.
			button.className =
				'living-handbook-zoom' + ( modifier ? ' ' + modifier : '' );
			button.setAttribute( 'aria-label', label );
			el.parentNode.insertBefore( button, el );
			button.appendChild( el );
			button.addEventListener( 'click', onOpen );
			return button;
		}

		/**
		 * Takes an image back out of its zoom button.
		 *
		 * @param {Element} el The image.
		 * @return {void}
		 */
		function unwrap( el ) {
			const button = el.parentNode;
			if (
				button &&
				button.classList &&
				button.classList.contains( 'living-handbook-zoom' )
			) {
				button.parentNode.insertBefore( el, button );
				button.remove();
			}
		}

		/**
		 * Offers an image for enlarging when that shows more of it.
		 *
		 * An SVG is vector, so it always enlarges for detail; a raster image
		 * only when it is shown smaller than its real size.
		 *
		 * @param {HTMLImageElement} img The content image, loaded.
		 * @return {void}
		 */
		function markZoomable( img ) {
			const shownSmaller =
				img.naturalWidth &&
				img.clientWidth &&
				img.naturalWidth > img.clientWidth + ZOOM_TOLERANCE_PX;
			if ( ! isSvg( img ) && ! shownSmaller ) {
				img.classList.remove( 'living-handbook-zoomable' );
				unwrap( img );
				return;
			}
			img.classList.add( 'living-handbook-zoomable' );
			wrapInButton(
				img,
				() => openImage( img ),
				img.alt ? enlargeLabel() + ': ' + img.alt : enlargeLabel()
			);
		}

		/**
		 * Offers a Mermaid diagram for enlarging once it has been drawn.
		 *
		 * @param {HTMLElement} box The .mermaid container.
		 * @return {void}
		 */
		function markDiagram( box ) {
			if ( ! box.querySelector( 'svg' ) ) {
				return;
			}
			box.classList.add( 'living-handbook-zoomable' );
			wrapInButton(
				box,
				() => openMermaid( box ),
				enlargeLabel(),
				'living-handbook-zoom--diagram'
			);
		}

		scopes.forEach( ( scope ) => {
			scope.querySelectorAll( 'img' ).forEach( ( img ) => {
				if ( img.complete ) {
					markZoomable( img );
				} else {
					img.addEventListener( 'load', () => markZoomable( img ) );
				}
			} );

			// Mermaid diagrams render into a .mermaid container after this runs, so
			// watch for the SVG to appear and mark the container clickable then.
			scope.querySelectorAll( '.mermaid' ).forEach( ( box ) => {
				markDiagram( box );
				new window.MutationObserver( () => markDiagram( box ) ).observe(
					box,
					{ childList: true, subtree: true }
				);
			} );
		} );
	}

	/* ---------- Learning paths: how far this browser has read ---------- */

	/*
	 * The first stage of the learning paths keeps no progress on the server, so
	 * there is nothing personal to store, export or delete. What it does keep is
	 * a list of lesson ids per path in this browser's local storage, which is
	 * enough to tick off a list and to say "3 of 8", and honest about the price:
	 * clearing the browser data or switching device starts over. The page says
	 * so, and this code does not pretend otherwise.
	 *
	 * Storage can be unavailable (a locked-down profile, private mode in some
	 * browsers), so every access is wrapped: a failure means no ticks, never a
	 * broken page.
	 */

	/**
	 * Returns the local storage key of a learning path.
	 *
	 * @param {number} pathId Post ID of the learning path.
	 * @return {string} The storage key.
	 */
	function pathStorageKey( pathId ) {
		return 'living-handbook-path-' + pathId;
	}

	/**
	 * Reads the lessons of a path this browser has read.
	 *
	 * @param {number} pathId Post ID of the learning path.
	 * @return {number[]} Lesson post IDs, empty when nothing is stored.
	 */
	function readPath( pathId ) {
		try {
			const raw = window.localStorage.getItem( pathStorageKey( pathId ) );
			const parsed = raw ? JSON.parse( raw ) : [];
			return Array.isArray( parsed )
				? parsed.filter( ( id ) => 'number' === typeof id )
				: [];
		} catch {
			return [];
		}
	}

	/**
	 * Stores the lessons of a path this browser has read.
	 *
	 * @param {number}   pathId Post ID of the learning path.
	 * @param {number[]} ids    Lesson post IDs.
	 * @return {void}
	 */
	function writePath( pathId, ids ) {
		try {
			window.localStorage.setItem(
				pathStorageKey( pathId ),
				JSON.stringify( ids )
			);
		} catch {
			// No storage, no ticks. Nothing else depends on this.
		}
	}

	/**
	 * Records the lesson on screen as read, when it was opened from a path.
	 *
	 * @return {void}
	 */
	function markLessonRead() {
		const bar = document.querySelector( '.living-handbook-pathbar' );
		if ( ! bar ) {
			return;
		}
		const pathId = parseInt( bar.getAttribute( 'data-path' ), 10 );
		const lessonId = parseInt( bar.getAttribute( 'data-lesson' ), 10 );
		if ( ! pathId || ! lessonId ) {
			return;
		}
		const done = readPath( pathId );
		if ( -1 === done.indexOf( lessonId ) ) {
			done.push( lessonId );
			writePath( pathId, done );
		}
	}

	/**
	 * Ticks off the read lessons on a learning path page and updates the
	 * progress sentence and the start button.
	 *
	 * @return {void}
	 */
	function showPathProgress() {
		const path = document.querySelector( '.living-handbook-path' );
		if ( ! path ) {
			return;
		}
		const pathId = parseInt( path.getAttribute( 'data-path' ), 10 );
		const total = parseInt( path.getAttribute( 'data-total' ), 10 );
		if ( ! pathId || ! total ) {
			return;
		}

		const done = readPath( pathId );
		let read = 0;
		let next = null;

		path.querySelectorAll( '.living-handbook-path__item' ).forEach(
			( item ) => {
				const lessonId = parseInt(
					item.getAttribute( 'data-lesson' ),
					10
				);
				const state = item.querySelector(
					'.living-handbook-path__state'
				);
				if ( -1 !== done.indexOf( lessonId ) ) {
					read += 1;
					item.classList.add( 'is-read' );
					if ( state ) {
						state.textContent =
							state.getAttribute( 'data-done-label' ) || '';
					}
					return;
				}
				if ( ! next ) {
					next = item.querySelector( '.living-handbook-path__link' );
				}
			}
		);

		// The sentence arrives from the server with the total and its plural
		// form already chosen; only the read count is filled in here.
		const progress = path.querySelector(
			'.living-handbook-path__progress'
		);
		const sentence = path.getAttribute( 'data-progress' );
		if ( progress && sentence ) {
			progress.textContent = sentence.replace( '%1$d', String( read ) );
		}

		// The button says Start on an untouched path and Continue as soon as
		// something has been read, and it then points at the first lesson that
		// has not been: somebody coming back wants the next one, not the first.
		const start = path.querySelector( '.living-handbook-path__button' );
		if ( start && read > 0 && next && settings.pathContinue ) {
			start.textContent = settings.pathContinue;
			start.setAttribute( 'href', next.getAttribute( 'href' ) );
		}
	}

	/* ---------- Init ---------- */

	document.addEventListener( 'DOMContentLoaded', () => {
		buildToc();
		wireNav();
		wireMenus();
		initLightbox();
		markLessonRead();
		showPathProgress();
		wireEntry( entryElement() );
		document
			.querySelectorAll( '.living-handbook-page-search' )
			.forEach( wirePageSearch );
		wireFeedback();
	} );
} )();
