/*
 * Living Handbook frontend: live card filtering and search, server-side facet
 * submit, feedback, and the on-this-page table of contents with depth limit,
 * smooth scrolling and scroll-spy.
 */
( function () {
	'use strict';

	/* ---------- Overview and entry: live card filtering ---------- */

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
					h.scrollIntoView( { behavior: 'smooth', block: 'start' } );
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
		fetch( window.livingHandbook.rest, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': window.livingHandbook.nonce
			},
			body: JSON.stringify( { post_id: postId, value: value } )
		} ).then( function () {
			box.textContent = window.livingHandbook.thanks;
		} ).catch( function () {} );
	}

	/* ---------- Init ---------- */

	document.addEventListener( 'DOMContentLoaded', function () {
		applyFilter();
		buildToc();

		document.querySelectorAll( '.living-handbook-search__input' ).forEach( function ( input ) {
			input.addEventListener( 'input', applyFilter );
		} );

		document.querySelectorAll( '.living-handbook-facet__cb' ).forEach( function ( cb ) {
			cb.addEventListener( 'change', function () {
				var form = cb.closest( 'form' );
				if ( form ) {
					form.submit();
				}
			} );
		} );

		document.querySelectorAll( '.living-handbook-feedback' ).forEach( function ( box ) {
			box.querySelectorAll( 'button[data-value]' ).forEach( function ( button ) {
				button.addEventListener( 'click', function () {
					sendFeedback( box, button.getAttribute( 'data-value' ) );
				} );
			} );
		} );
	} );
}() );
