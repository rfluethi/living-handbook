/*
 * Living Handbook frontend: AJAX card filtering and search, the handbook
 * navigation accordion, feedback, the on-this-page table of contents with depth
 * limit, smooth scrolling and scroll-spy, and the mobile toggle for the
 * handbook menu. Selecting a taxonomy facet or submitting the search filters the
 * handbook through a REST request and swaps the result list in place, so no full
 * page reload is needed; without JavaScript the facet and search forms submit
 * normally with their own button. Typing in the search box also narrows the
 * shown cards live for instant feedback.
 */
( function () {
	'use strict';

	function prefersReducedMotion() {
		return window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	}

	/* ---------- Overview and entry: live card narrowing while typing ---------- */

	function cards() {
		return document.querySelectorAll( '.living-handbook-card' );
	}

	function applyFilter() {
		var list = cards();
		if ( ! list.length ) {
			return;
		}

		var query = '';
		document.querySelectorAll( '.living-handbook-search__input' ).forEach( function ( input ) {
			if ( ! query ) {
				query = input.value.trim().toLowerCase();
			}
		} );

		list.forEach( function ( card ) {
			var show = true;
			if ( query ) {
				var text = ( ( card.getAttribute( 'data-title' ) || '' ) + ' ' + ( card.textContent || '' ) ).toLowerCase();
				if ( text.indexOf( query ) === -1 ) {
					show = false;
				}
			}
			card.style.display = show ? '' : 'none';
		} );
	}

	/* ---------- Overview and entry: AJAX facet and search filtering ---------- */

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

		var input = entry.querySelector( '.living-handbook-search__input' );
		if ( input && input.value.trim() ) {
			params.set( 'lh_s', input.value.trim() );
		}
		entry.querySelectorAll( '.living-handbook-facet__cb:checked' ).forEach( function ( cb ) {
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

	function wireEntry( entry ) {
		// With JavaScript the facets filter live on change, so the no-JS submit
		// button is hidden.
		entry.querySelectorAll( '.living-handbook-filterform__submit' ).forEach( function ( button ) {
			button.hidden = true;
		} );

		entry.addEventListener( 'change', function ( event ) {
			if ( event.target && event.target.classList && event.target.classList.contains( 'living-handbook-facet__cb' ) ) {
				ajaxFilter( entry );
			}
		} );

		var searchForm = entry.querySelector( '.living-handbook-start__search' );
		if ( searchForm && canAjax() ) {
			searchForm.addEventListener( 'submit', function ( event ) {
				event.preventDefault();
				ajaxFilter( entry );
			} );
		}

		var filterForm = entry.querySelector( '.living-handbook-filterform' );
		if ( filterForm && canAjax() ) {
			filterForm.addEventListener( 'submit', function ( event ) {
				event.preventDefault();
				ajaxFilter( entry );
			} );
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

		// Start the whole navigation collapsed on narrow screens; on wider
		// screens it stays open. The title (summary) opens or closes it either
		// way, natively.
		if ( window.matchMedia && window.matchMedia( '(max-width: 781px)' ).matches ) {
			document.querySelectorAll( 'details.living-handbook-nav[open]' ).forEach( function ( nav ) {
				nav.open = false;
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

		heads.forEach( function ( h, i ) {
			if ( ! h.id ) {
				h.id = 'lh-section-' + i;
			}
		} );

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
				a.textContent = h.textContent;
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

	document.addEventListener( 'DOMContentLoaded', function () {
		applyFilter();
		buildToc();
		wireNav();
		wireMenus();

		document.querySelectorAll( '.living-handbook-search__input' ).forEach( function ( input ) {
			input.addEventListener( 'input', applyFilter );
		} );

		document.querySelectorAll( '.living-handbook-entry' ).forEach( wireEntry );

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
