/*
 * Living Handbook frontend: AJAX card filtering and search, the handbook
 * navigation accordion, feedback, the on-this-page table of contents with depth
 * limit, smooth scrolling and scroll-spy, and the mobile toggle for the
 * handbook menu. Selecting a taxonomy facet or submitting the search filters the
 * handbook through a REST request and swaps the result list in place, so no full
 * page reload is needed; without JavaScript the facet and search forms submit
 * normally with their own button. Typing in the search box re-runs the server
 * search after a short debounce.
 */
( function () {
	'use strict';

	function prefersReducedMotion() {
		return window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	}

	/* ---------- Small debounce helper for live search ---------- */

	function debounce( fn, wait ) {
		var timer = null;
		return function () {
			var context = this;
			var args = arguments;
			if ( timer ) {
				clearTimeout( timer );
			}
			timer = setTimeout( function () {
				fn.apply( context, args );
			}, wait );
		};
	}

	/* ---------- Entry: AJAX facet and search filtering ---------- */

	function canAjax() {
		return window.livingHandbook && window.livingHandbook.filter;
	}

	function updateUrl( params ) {
		if ( ! window.history || ! window.history.replaceState ) {
			return;
		}
		var qs = new URLSearchParams();
		params.forEach( function ( value, key ) {
			if ( 'term_id' !== key ) {
				qs.append( key, value );
			}
		} );
		var query = qs.toString();
		window.history.replaceState( null, '', window.location.pathname + ( query ? '?' + query : '' ) );
	}

	function showFilterError( main ) {
		if ( ! main || main.querySelector( '.living-handbook-filter-error' ) ) {
			return;
		}
		var p = document.createElement( 'p' );
		p.className = 'living-handbook-filter-error';
		p.setAttribute( 'role', 'alert' );
		p.textContent = ( window.livingHandbook && window.livingHandbook.filterError )
			? window.livingHandbook.filterError
			: 'The list could not be updated. Please reload the page.';
		main.insertBefore( p, main.firstChild );
	}

	// Say what the list says, in one sentence. The server already renders the
	// count ("%d pages found") and the empty message, both translated, so the
	// status line repeats that text rather than inventing a second wording.
	function announceCount( entry, main ) {
		var status = entry.querySelector( '.living-handbook-entry__status' )
			|| document.querySelector( '.living-handbook-entry__status' );
		if ( ! status ) {
			return;
		}
		var said = main.querySelector( '.living-handbook-empty' ) || main.querySelector( '.living-handbook-count' );
		status.textContent = said ? said.textContent : '';
	}

	function ajaxFilter( entry ) {
		if ( ! canAjax() ) {
			return;
		}
		var main = entry.querySelector( '.living-handbook-main' );
		var termId = entry.getAttribute( 'data-term-id' );
		if ( ! main || ! termId ) {
			return;
		}

		var params = new URLSearchParams();
		params.set( 'term_id', termId );

		// The controls are looked up in the document, not in the entry element.
		// Since 0.65.0 the search bar and the filter bar can be their own blocks
		// and sit anywhere on the page, and a term archive shows exactly one
		// handbook, so the document is the right scope.
		var input = document.querySelector( '.living-handbook-search__input' );
		if ( input && input.value.trim() ) {
			params.set( 'lh_s', input.value.trim() );
		}
		document.querySelectorAll( '.living-handbook-facet__cb:checked' ).forEach( function ( cb ) {
			params.append( cb.name, cb.value );
		} );

		// Cancel a request that is still in flight for this entry, so a quick
		// series of clicks does not race and land out of order.
		if ( entry.lhController ) {
			entry.lhController.abort();
		}
		var controller = ( 'AbortController' in window ) ? new AbortController() : null;
		entry.lhController = controller;

		main.setAttribute( 'aria-busy', 'true' );
		fetch( window.livingHandbook.filter + '?' + params.toString(), {
			headers: { 'X-WP-Nonce': window.livingHandbook.nonce },
			credentials: 'same-origin',
			signal: controller ? controller.signal : undefined
		} ).then( function ( response ) {
			if ( ! response.ok ) {
				throw new Error( 'HTTP ' + response.status );
			}
			return response.json();
		} ).then( function ( data ) {
			if ( data && typeof data.html === 'string' ) {
				main.innerHTML = data.html;
			}
			announceCount( entry, main );
			main.removeAttribute( 'aria-busy' );
			entry.lhController = null;
			updateUrl( params );
		} ).catch( function ( err ) {
			// A superseded request was aborted on purpose; leave it silent.
			if ( err && 'AbortError' === err.name ) {
				return;
			}
			main.removeAttribute( 'aria-busy' );
			entry.lhController = null;
			showFilterError( main );
		} );
	}

	// The entry element is the one that holds the result column and knows the
	// handbook. The controls may live inside it, or as their own blocks anywhere
	// else on the page; either way they drive this one.
	function entryElement() {
		return document.querySelector( '.living-handbook-entry[data-term-id]' );
	}

	function wireEntry( entry ) {
		if ( ! entry ) {
			return;
		}

		// With JavaScript the facets filter live on change, so the no-JS submit
		// button is hidden.
		document.querySelectorAll( '.living-handbook-filterform__submit' ).forEach( function ( button ) {
			button.hidden = true;
		} );

		// Listening on the document rather than on the entry element, because a
		// filter bar placed as its own block is not inside it. A no-JS page keeps
		// working either way: the forms submit to the handbook.
		document.addEventListener( 'change', function ( event ) {
			if ( event.target && event.target.classList && event.target.classList.contains( 'living-handbook-facet__cb' ) ) {
				ajaxFilter( entry );
			}
		} );

		document.querySelectorAll( '.living-handbook-start__search, .living-handbook-filterform' ).forEach( function ( form ) {
			if ( ! canAjax() ) {
				return;
			}
			form.addEventListener( 'submit', function ( event ) {
				event.preventDefault();
				ajaxFilter( entry );
			} );
		} );

		// Live search: re-run the server search after a short debounce, so the
		// result list (title and body matches) stays authoritative instead of
		// hiding cards by their visible text.
		var searchInput = document.querySelector( '.living-handbook-search__input' );
		if ( searchInput && canAjax() ) {
			searchInput.addEventListener( 'input', debounce( function () {
				ajaxFilter( entry );
			}, 150 ) );
		}
	}

	/* ---------- Handbook navigation: accordion toggles and mobile collapse ---------- */

	function wireNav() {
		document.querySelectorAll( '.living-handbook-nav__toggle' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var item = btn.closest( '.living-handbook-nav__item' );
				if ( ! item ) {
					return;
				}
				var open = item.classList.toggle( 'is-open' );
				btn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			} );
		} );

		// The title row's toggle opens and closes the whole navigation. It is the
		// same button as a branch toggle, one level up, so it is wired here
		// rather than through a second mechanism.
		document.querySelectorAll( '.living-handbook-nav__toggle--all' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var nav = btn.closest( '.living-handbook-nav' );
				if ( ! nav ) {
					return;
				}
				var collapsed = nav.classList.toggle( 'is-collapsed' );
				btn.setAttribute( 'aria-expanded', collapsed ? 'false' : 'true' );
			} );
		} );

		// Start the whole navigation collapsed on narrow screens; on wider
		// screens it stays open.
		if ( window.matchMedia && window.matchMedia( '(max-width: 781px)' ).matches ) {
			document.querySelectorAll( '.living-handbook-nav' ).forEach( function ( nav ) {
				nav.classList.add( 'is-collapsed' );
				var btn = nav.querySelector( '.living-handbook-nav__toggle--all' );
				if ( btn ) {
					btn.setAttribute( 'aria-expanded', 'false' );
				}
			} );
		}
	}

	/* ---------- Handbook menu: mobile toggle ---------- */

	function wireMenus() {
		document.querySelectorAll( '.living-handbook-menu__toggle' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var nav = btn.closest( '.living-handbook-menu' );
				if ( ! nav ) {
					return;
				}
				var open = nav.classList.toggle( 'is-open' );
				btn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			} );
		} );
	}

	/* ---------- Single page: on-page handbook search (typeahead) ---------- */

	function wirePageSearch( box ) {
		if ( ! window.livingHandbook || ! window.livingHandbook.search ) {
			return;
		}
		var input = box.querySelector( '.living-handbook-page-search__input' );
		var results = box.querySelector( '.living-handbook-page-search__results' );
		var termId = box.getAttribute( 'data-term-id' );
		if ( ! input || ! results || ! termId ) {
			return;
		}
		var controller = null;

		var status = box.querySelector( '[role="status"]' );

		function say( text ) {
			if ( status ) {
				status.textContent = text;
			}
		}

		function hide() {
			results.hidden = true;
			say( '' );
		}

		function render( items ) {
			results.innerHTML = '';
			if ( ! items.length ) {
				var empty = document.createElement( 'li' );
				empty.className = 'living-handbook-page-search__empty';
				empty.textContent = window.livingHandbook.searchEmpty || 'No matches.';
				results.appendChild( empty );
				say( empty.textContent );
			} else {
				items.forEach( function ( item ) {
					var li = document.createElement( 'li' );
					var a = document.createElement( 'a' );
					a.href = item.url;
					a.textContent = item.title;
					li.appendChild( a );

					// The sentence the words were found in, built from segments the
					// server marked. Every piece goes in as text, so nothing from the
					// page content is ever parsed as markup here.
					if ( item.snippet && item.snippet.length ) {
						var p = document.createElement( 'p' );
						p.className = 'living-handbook-page-search__snippet';
						item.snippet.forEach( function ( part ) {
							if ( part.mark ) {
								var mark = document.createElement( 'mark' );
								mark.textContent = part.text;
								p.appendChild( mark );
							} else {
								p.appendChild( document.createTextNode( part.text ) );
							}
						} );
						li.appendChild( p );
					}

					results.appendChild( li );
				} );
				// One short sentence, not the whole list: the list itself is right
				// there to be walked with the arrow keys.
				say( ( window.livingHandbook.searchCount || '%d matches' ).replace( '%d', items.length ) );
			}
			results.hidden = false;
		}

		// Walk the results with the arrow keys, from the input into the list and
		// back out at the top. The results are links, so Enter needs no handler.
		function links() {
			return Array.prototype.slice.call( results.querySelectorAll( 'a' ) );
		}

		function move( from, step ) {
			var all = links();
			if ( ! all.length ) {
				return;
			}
			var index = all.indexOf( from );
			var next = ( -1 === index ) ? ( step > 0 ? 0 : all.length - 1 ) : index + step;
			if ( next < 0 ) {
				input.focus();
				return;
			}
			if ( next >= all.length ) {
				next = all.length - 1;
			}
			all[ next ].focus();
		}

		function run() {
			var q = input.value.trim();
			if ( q.length < 2 ) {
				hide();
				return;
			}
			if ( controller ) {
				controller.abort();
			}
			controller = ( 'AbortController' in window ) ? new AbortController() : null;
			var params = new URLSearchParams();
			params.set( 'term_id', termId );
			params.set( 'q', q );
			fetch( window.livingHandbook.search + '?' + params.toString(), {
				headers: { 'X-WP-Nonce': window.livingHandbook.nonce },
				credentials: 'same-origin',
				signal: controller ? controller.signal : undefined
			} ).then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( 'HTTP ' + response.status );
				}
				return response.json();
			} ).then( function ( data ) {
				render( ( data && data.results ) ? data.results : [] );
			} ).catch( function ( err ) {
				if ( err && 'AbortError' === err.name ) {
					return;
				}
				hide();
			} );
		}

		input.addEventListener( 'input', debounce( run, 150 ) );
		input.addEventListener( 'focus', function () {
			if ( ! results.hidden || results.children.length ) {
				run();
			}
		} );
		input.addEventListener( 'keydown', function ( event ) {
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

		results.addEventListener( 'keydown', function ( event ) {
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
		box.addEventListener( 'focusout', function ( event ) {
			if ( ! box.contains( event.relatedTarget ) ) {
				hide();
			}
		} );
	}

	/* ---------- Single page: on-this-page table of contents ---------- */

	function level( heading ) {
		return parseInt( heading.tagName.substring( 1 ), 10 ) || 1;
	}

	function buildToc() {
		var boxes = document.querySelectorAll( '.living-handbook-toc' );
		if ( ! boxes.length ) {
			return;
		}
		var content = document.querySelector( '.wp-block-post-content' ) || document.querySelector( '.entry-content' );
		if ( ! content ) {
			return;
		}
		var heads = content.querySelectorAll( 'h1, h2, h3, h4, h5, h6' );
		if ( ! heads.length ) {
			return;
		}

		// h2 to h4 get their id from the server, from the heading text. What is
		// left here are h1, h5 and h6, and any heading on a page this filter did
		// not touch. Those keep the positional fallback: it is not an address to
		// pass on, but it is enough for the table of contents on this page.
		heads.forEach( function ( h, i ) {
			if ( ! h.id ) {
				h.id = 'lh-section-' + i;
			}
		} );

		// The heading text without the anchor link, so the entry does not read
		// "Installation #".
		function headingText( h ) {
			var copy = h.cloneNode( true );
			copy.querySelectorAll( '.living-handbook-anchor' ).forEach( function ( a ) {
				a.remove();
			} );
			return copy.textContent.trim();
		}

		var map = {};

		boxes.forEach( function ( box ) {
			var list = box.querySelector( '.living-handbook-toc__list' );
			if ( ! list ) {
				return;
			}
			var maxDepth = parseInt( box.getAttribute( 'data-max-depth' ), 10 ) || 6;
			var shown = false;

			heads.forEach( function ( h ) {
				if ( level( h ) > maxDepth ) {
					return;
				}
				shown = true;
				var li = document.createElement( 'li' );
				li.className = 'living-handbook-toc__item';
				li.style.paddingInlineStart = ( ( level( h ) - 1 ) * 0.8 ) + 'rem';
				var a = document.createElement( 'a' );
				a.href = '#' + h.id;
				a.textContent = headingText( h );
				a.addEventListener( 'click', function ( event ) {
					event.preventDefault();
					h.scrollIntoView( { behavior: prefersReducedMotion() ? 'auto' : 'smooth', block: 'start' } );
					// Move keyboard focus to the target heading so keyboard and
					// screen-reader users land at the section, not back at the top.
					if ( ! h.hasAttribute( 'tabindex' ) ) {
						h.setAttribute( 'tabindex', '-1' );
					}
					h.focus( { preventScroll: true } );
					if ( window.history && window.history.pushState ) {
						window.history.pushState( null, '', '#' + h.id );
					}
				} );
				li.appendChild( a );
				list.appendChild( li );
				map[ h.id ] = map[ h.id ] || [];
				map[ h.id ].push( a );
			} );

			if ( shown ) {
				box.hidden = false;
			}
		} );

		if ( 'IntersectionObserver' in window ) {
			var active = [];
			var observer = new IntersectionObserver( function ( entries ) {
				entries.forEach( function ( entry ) {
					if ( entry.isIntersecting && map[ entry.target.id ] ) {
						active.forEach( function ( a ) {
							a.classList.remove( 'is-active' );
						} );
						active = map[ entry.target.id ];
						active.forEach( function ( a ) {
							a.classList.add( 'is-active' );
						} );
					}
				} );
			}, { rootMargin: '0px 0px -65% 0px' } );
			heads.forEach( function ( h ) {
				if ( map[ h.id ] ) {
					observer.observe( h );
				}
			} );
		}
	}

	/* ---------- Single page: feedback ---------- */

	function sendFeedback( box, value ) {
		if ( ! window.livingHandbook || ! window.livingHandbook.rest ) {
			return;
		}
		var postId = parseInt( box.getAttribute( 'data-post' ), 10 );
		var buttons = box.querySelectorAll( 'button[data-value]' );
		// Prevent double counting: disable both buttons the moment one is used.
		buttons.forEach( function ( b ) {
			b.disabled = true;
		} );

		fetch( window.livingHandbook.rest, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': window.livingHandbook.nonce
			},
			body: JSON.stringify( { post_id: postId, value: value } )
		} ).then( function ( response ) {
			if ( ! response.ok ) {
				throw new Error( 'HTTP ' + response.status );
			}
			// Replace the prompt with the confirmation and move focus to it so
			// the aria-live region announces the change for screen-reader users.
			box.textContent = window.livingHandbook.thanks;
			box.setAttribute( 'tabindex', '-1' );
			box.focus();
		} ).catch( function () {
			// Re-enable the buttons and show a real error, not a false thanks.
			buttons.forEach( function ( b ) {
				b.disabled = false;
			} );
			var existing = box.querySelector( '.living-handbook-feedback__error' );
			if ( existing ) {
				existing.remove();
			}
			var msg = document.createElement( 'span' );
			msg.className = 'living-handbook-feedback__error';
			msg.textContent = window.livingHandbook.feedbackError || 'Please try again.';
			box.appendChild( msg );
		} );
	}

	/* ---------- Init ---------- */

	/* ---------- Content images: click to enlarge (lightbox) ---------- */

	// Handbook content images are plain <img>, not core Image blocks, so the
	// core "Enlarge on click" never reaches them. This gives them the same
	// behaviour: an image shown smaller than its real size becomes clickable and
	// opens in a dark overlay, closed by a click, the close button, or Escape.
	function initLightbox() {
		var scopes = document.querySelectorAll(
			'.living-handbook-page .wp-block-post-content, .living-handbook-page .entry-content'
		);
		if ( ! scopes.length ) {
			return;
		}

		var overlay = null;
		var lastFocus = null;

		function labels() {
			return ( window.livingHandbook && window.livingHandbook.lightboxClose )
				? window.livingHandbook.lightboxClose
				: 'Close';
		}

		function close() {
			if ( ! overlay ) {
				return;
			}
			overlay.parentNode.removeChild( overlay );
			overlay = null;
			document.removeEventListener( 'keydown', onKey );
			if ( lastFocus && lastFocus.focus ) {
				lastFocus.focus();
			}
		}

		function onKey( e ) {
			if ( 'Escape' === e.key || 'Esc' === e.key ) {
				close();
				return;
			}
			// aria-modal says the rest of the page is not there, so Tab must not
			// walk out of the overlay. The close button is the only thing in it.
			if ( 'Tab' === e.key && overlay ) {
				e.preventDefault();
				var btn = overlay.querySelector( '.living-handbook-lightbox__close' );
				if ( btn ) {
					btn.focus();
				}
			}
		}

		function buildOverlay( displayEl, ariaLabel ) {
			lastFocus = document.activeElement;

			overlay = document.createElement( 'div' );
			overlay.className = 'living-handbook-lightbox';
			overlay.setAttribute( 'role', 'dialog' );
			overlay.setAttribute( 'aria-modal', 'true' );
			if ( ariaLabel ) {
				overlay.setAttribute( 'aria-label', ariaLabel );
			}

			var btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'living-handbook-lightbox__close';
			btn.setAttribute( 'aria-label', labels() );
			btn.innerHTML = '&times;';

			overlay.appendChild( displayEl );
			overlay.appendChild( btn );

			// Close on the backdrop and on the button, not on the picture itself:
			// clicking what you just enlarged should not make it disappear.
			overlay.addEventListener( 'click', function ( event ) {
				if ( event.target === overlay || btn.contains( event.target ) ) {
					close();
				}
			} );

			document.body.appendChild( overlay );
			document.addEventListener( 'keydown', onKey );
			btn.focus();
		}

		function openImage( img ) {
			var big = document.createElement( 'img' );
			big.src = img.currentSrc || img.src;
			big.alt = img.alt || '';
			buildOverlay( big, img.alt );
		}

		// A Mermaid diagram is an inline <svg>, not an <img>. Show an enlarged
		// clone of it: drop the diagram's own width limit and give it a large base
		// width, so the stylesheet scales it down to fit the overlay.
		function openMermaid( box ) {
			var svg = box.querySelector( 'svg' );
			if ( ! svg ) {
				return;
			}
			var clone = svg.cloneNode( true );
			clone.removeAttribute( 'style' );
			clone.setAttribute( 'width', '1600' );
			clone.removeAttribute( 'height' );
			var label = ( window.livingHandbook && window.livingHandbook.lightboxDiagram )
				? window.livingHandbook.lightboxDiagram
				: 'Enlarged diagram';
			buildOverlay( clone, label );
		}

		function isSvg( img ) {
			var src = ( img.currentSrc || img.src || '' ).split( '?' )[ 0 ].split( '#' )[ 0 ];
			return /\.svg$/i.test( src );
		}

		// A click handler on an <img> or a <div> is reachable with a mouse and with
		// nothing else. Wrapping the thing in a real button brings focus, Enter,
		// Space and the announcement along, and costs one element.
		function enlargeLabel() {
			return ( window.livingHandbook && window.livingHandbook.lightboxOpen )
				? window.livingHandbook.lightboxOpen
				: 'Enlarge';
		}

		function wrapInButton( el, onOpen, label, modifier ) {
			if ( el.parentNode && el.parentNode.classList
				&& el.parentNode.classList.contains( 'living-handbook-zoom' ) ) {
				return el.parentNode;
			}
			var button = document.createElement( 'button' );
			button.type = 'button';
			// A diagram is drawn to the width it is given, an image brings its own.
			// The trigger has to behave accordingly, or the diagram collapses to
			// the button's shrink-to-fit width.
			button.className = 'living-handbook-zoom' + ( modifier ? ' ' + modifier : '' );
			button.setAttribute( 'aria-label', label );
			el.parentNode.insertBefore( button, el );
			button.appendChild( el );
			button.addEventListener( 'click', onOpen );
			return button;
		}

		function unwrap( el ) {
			var button = el.parentNode;
			if ( button && button.classList && button.classList.contains( 'living-handbook-zoom' ) ) {
				button.parentNode.insertBefore( el, button );
				button.parentNode.removeChild( button );
			}
		}

		function markZoomable( img ) {
			// An SVG is vector, so always let it enlarge for detail; a raster only
			// when it is shown smaller than its real size.
			if ( isSvg( img ) || ( img.naturalWidth && img.clientWidth && img.naturalWidth > img.clientWidth + 4 ) ) {
				img.classList.add( 'living-handbook-zoomable' );
				wrapInButton(
					img,
					function () {
						openImage( img );
					},
					img.alt ? enlargeLabel() + ': ' + img.alt : enlargeLabel()
				);
			} else {
				img.classList.remove( 'living-handbook-zoomable' );
				unwrap( img );
			}
		}

		scopes.forEach( function ( scope ) {
			scope.querySelectorAll( 'img' ).forEach( function ( img ) {
				if ( img.complete ) {
					markZoomable( img );
				} else {
					img.addEventListener( 'load', function () {
						markZoomable( img );
					} );
				}
			} );

			// Mermaid diagrams render into a .mermaid container after this runs, so
			// watch for the SVG to appear and mark the container clickable then.
			scope.querySelectorAll( '.mermaid' ).forEach( function ( box ) {
				function refresh() {
					if ( box.querySelector( 'svg' ) ) {
						box.classList.add( 'living-handbook-zoomable' );
						wrapInButton(
							box,
							function () {
								openMermaid( box );
							},
							enlargeLabel(),
							'living-handbook-zoom--diagram'
						);
					}
				}
				refresh();
				new MutationObserver( refresh ).observe( box, { childList: true, subtree: true } );
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

	function pathStorageKey( pathId ) {
		return 'living-handbook-path-' + pathId;
	}

	function readPath( pathId ) {
		try {
			var raw = window.localStorage.getItem( pathStorageKey( pathId ) );
			var parsed = raw ? JSON.parse( raw ) : [];
			return Array.isArray( parsed ) ? parsed.filter( function ( id ) {
				return 'number' === typeof id;
			} ) : [];
		} catch ( e ) {
			return [];
		}
	}

	function writePath( pathId, ids ) {
		try {
			window.localStorage.setItem( pathStorageKey( pathId ), JSON.stringify( ids ) );
		} catch ( e ) {
			// No storage, no ticks. Nothing else depends on this.
		}
	}

	function markLessonRead() {
		var bar = document.querySelector( '.living-handbook-pathbar' );
		if ( ! bar ) {
			return;
		}
		var pathId = parseInt( bar.getAttribute( 'data-path' ), 10 );
		var lessonId = parseInt( bar.getAttribute( 'data-lesson' ), 10 );
		if ( ! pathId || ! lessonId ) {
			return;
		}
		var done = readPath( pathId );
		if ( done.indexOf( lessonId ) === -1 ) {
			done.push( lessonId );
			writePath( pathId, done );
		}
	}

	function showPathProgress() {
		var path = document.querySelector( '.living-handbook-path' );
		if ( ! path ) {
			return;
		}
		var pathId = parseInt( path.getAttribute( 'data-path' ), 10 );
		var total = parseInt( path.getAttribute( 'data-total' ), 10 );
		if ( ! pathId || ! total ) {
			return;
		}

		var done = readPath( pathId );
		var read = 0;
		var next = null;

		path.querySelectorAll( '.living-handbook-path__item' ).forEach( function ( item ) {
			var lessonId = parseInt( item.getAttribute( 'data-lesson' ), 10 );
			var state = item.querySelector( '.living-handbook-path__state' );
			if ( done.indexOf( lessonId ) !== -1 ) {
				read += 1;
				item.classList.add( 'is-read' );
				if ( state ) {
					state.textContent = state.getAttribute( 'data-done-label' ) || '';
				}
				return;
			}
			if ( ! next ) {
				next = item.querySelector( '.living-handbook-path__link' );
			}
		} );

		var progress = path.querySelector( '.living-handbook-path__progress' );
		if ( progress && window.livingHandbook && livingHandbook.pathProgress ) {
			progress.textContent = livingHandbook.pathProgress
				.replace( '%1$d', read )
				.replace( '%2$d', total );
		}

		// The button says Start on an untouched path and Continue as soon as
		// something has been read, and it then points at the first lesson that
		// has not been: somebody coming back wants the next one, not the first.
		var start = path.querySelector( '.living-handbook-path__button' );
		if ( start && read > 0 && next && window.livingHandbook && livingHandbook.pathContinue ) {
			start.textContent = livingHandbook.pathContinue;
			start.setAttribute( 'href', next.getAttribute( 'href' ) );
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		buildToc();
		wireNav();
		wireMenus();
		initLightbox();
		markLessonRead();
		showPathProgress();

		wireEntry( entryElement() );
		document.querySelectorAll( '.living-handbook-page-search' ).forEach( wirePageSearch );

		document.querySelectorAll( '.living-handbook-feedback' ).forEach( function ( box ) {
			// Announce the confirmation to assistive technology when the buttons
			// are replaced by the thank-you text.
			box.setAttribute( 'aria-live', 'polite' );
			box.querySelectorAll( 'button[data-value]' ).forEach( function ( button ) {
				button.addEventListener( 'click', function () {
					sendFeedback( box, button.getAttribute( 'data-value' ) );
				} );
			} );
		} );
	} );
}() );
