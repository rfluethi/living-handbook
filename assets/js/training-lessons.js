/*
 * The lesson picker of a learning path.
 *
 * A search field that loads matches from the plugin's REST route on demand, and
 * the chosen lessons as an ordered list underneath. The order is changed with
 * Move up and Move down, which work with a pointer, a keyboard and a screen
 * reader alike; there is deliberately no dragging, because a drag would need a
 * keyboard alternative anyway.
 *
 * The whole list travels in one hidden comma separated field, so the order is
 * part of the value instead of something rebuilt from the order of form fields.
 */
( function () {
	'use strict';

	var i18n = ( window.wp && window.wp.i18n ) ? window.wp.i18n : null;
	var __ = i18n ? i18n.__ : function ( s ) { return s; };
	var sprintf = i18n ? i18n.sprintf : function ( fmt ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var i = 0;
		return String( fmt ).replace( /%(\d+\$)?[ds]/g, function () { return args[ i++ ]; } );
	};

	function ready( fn ) {
		if ( window.wp && wp.domReady ) {
			wp.domReady( fn );
		} else if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

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

	function setup( root ) {
		var trainingId = parseInt( root.getAttribute( 'data-training' ), 10 );
		var value = root.querySelector( '.living-handbook-lessons__value' );
		var list = root.querySelector( '.living-handbook-lessons__list' );
		var empty = root.querySelector( '.living-handbook-lessons__empty' );
		var search = root.querySelector( '.living-handbook-lessons__search' );
		var results = root.querySelector( '.living-handbook-lessons__results' );

		if ( ! value || ! list ) {
			return;
		}

		function ids() {
			return Array.prototype.map.call( list.children, function ( item ) {
				return parseInt( item.getAttribute( 'data-id' ), 10 );
			} ).filter( function ( id ) {
				return id > 0;
			} );
		}

		// One place writes the field and the empty note, so the two cannot drift
		// apart after a move, a removal or an addition.
		function sync() {
			var current = ids();
			value.value = current.join( ',' );
			if ( empty ) {
				if ( current.length ) {
					empty.setAttribute( 'hidden', 'hidden' );
				} else {
					empty.removeAttribute( 'hidden' );
				}
			}
			Array.prototype.forEach.call( list.children, function ( item, index ) {
				var up = item.querySelector( '.living-handbook-lessons__up' );
				var down = item.querySelector( '.living-handbook-lessons__down' );
				if ( up ) {
					up.disabled = 0 === index;
				}
				if ( down ) {
					down.disabled = index === list.children.length - 1;
				}
			} );
		}

		function button( className, label ) {
			var element = document.createElement( 'button' );
			element.type = 'button';
			element.className = 'button-link ' + className;
			element.textContent = label;
			return element;
		}

		// Keeping the focus on the button that was pressed is the whole point of
		// having buttons: without it a keyboard user loses their place on every
		// move and has to tab back into the list.
		function move( item, direction ) {
			if ( direction < 0 && item.previousElementSibling ) {
				list.insertBefore( item, item.previousElementSibling );
			} else if ( direction > 0 && item.nextElementSibling ) {
				list.insertBefore( item.nextElementSibling, item );
			}
			sync();
		}

		function decorate( item ) {
			if ( item.querySelector( '.living-handbook-lessons__up' ) ) {
				return;
			}
			var title = item.querySelector( '.living-handbook-lessons__title' );
			var name = title ? title.textContent : '';

			var controls = document.createElement( 'span' );
			controls.className = 'living-handbook-lessons__controls';

			var up = button( 'living-handbook-lessons__up', __( 'Move up', 'living-handbook' ) );
			var down = button( 'living-handbook-lessons__down', __( 'Move down', 'living-handbook' ) );
			var remove = button( 'living-handbook-lessons__remove', __( 'Remove', 'living-handbook' ) );

			// translators: %s is the title of a lesson.
			up.setAttribute( 'aria-label', sprintf( __( 'Move %s up', 'living-handbook' ), name ) );
			// translators: %s is the title of a lesson.
			down.setAttribute( 'aria-label', sprintf( __( 'Move %s down', 'living-handbook' ), name ) );
			// translators: %s is the title of a lesson.
			remove.setAttribute( 'aria-label', sprintf( __( 'Remove %s', 'living-handbook' ), name ) );

			up.addEventListener( 'click', function () {
				move( item, -1 );
				up.focus();
			} );
			down.addEventListener( 'click', function () {
				move( item, 1 );
				down.focus();
			} );
			remove.addEventListener( 'click', function () {
				var next = item.nextElementSibling || item.previousElementSibling;
				item.parentNode.removeChild( item );
				sync();
				var target = next ? next.querySelector( '.living-handbook-lessons__remove' ) : search;
				if ( target ) {
					target.focus();
				}
			} );

			controls.appendChild( up );
			controls.appendChild( down );
			controls.appendChild( remove );
			item.appendChild( controls );
		}

		function add( id, title ) {
			if ( ids().indexOf( id ) !== -1 ) {
				return;
			}
			var item = document.createElement( 'li' );
			item.setAttribute( 'data-id', String( id ) );
			var name = document.createElement( 'span' );
			name.className = 'living-handbook-lessons__title';
			name.textContent = title;
			item.appendChild( name );
			list.appendChild( item );
			decorate( item );
			sync();
		}

		Array.prototype.forEach.call( list.children, decorate );
		sync();

		if ( ! search || ! results || ! window.wp || ! wp.apiFetch || ! window.lhTraining ) {
			return;
		}

		function render( items ) {
			results.textContent = '';
			if ( ! items.length ) {
				var none = document.createElement( 'li' );
				none.className = 'living-handbook-lessons__none';
				none.textContent = __( 'No matching pages.', 'living-handbook' );
				results.appendChild( none );
				return;
			}
			items.forEach( function ( hit ) {
				var entry = document.createElement( 'li' );
				var choose = document.createElement( 'button' );
				choose.type = 'button';
				choose.className = 'button-link';
				choose.textContent = hit.title;
				choose.addEventListener( 'click', function () {
					add( hit.id, hit.title );
					search.value = '';
					results.textContent = '';
					search.focus();
				} );
				entry.appendChild( choose );
				results.appendChild( entry );
			} );
		}

		var run = debounce( function () {
			var term = search.value.trim();
			if ( term.length < 2 ) {
				results.textContent = '';
				return;
			}
			wp.apiFetch( {
				path: window.lhTraining.searchPath
					+ '?training_id=' + encodeURIComponent( trainingId )
					+ '&q=' + encodeURIComponent( term )
			} ).then( function ( response ) {
				render( ( response && response.results ) || [] );
			} ).catch( function () {
				results.textContent = '';
				var failed = document.createElement( 'li' );
				failed.className = 'living-handbook-lessons__none';
				failed.textContent = __( 'The search failed. Please try again.', 'living-handbook' );
				results.appendChild( failed );
			} );
		}, 250 );

		search.addEventListener( 'input', run );
		// Enter in a search field submits the post form in the classic editor,
		// which would save the path in the middle of choosing lessons.
		search.addEventListener( 'keydown', function ( event ) {
			if ( 'Enter' === event.key ) {
				event.preventDefault();
				run();
			}
		} );
	}

	ready( function () {
		Array.prototype.forEach.call(
			document.querySelectorAll( '.living-handbook-lessons' ),
			setup
		);
	} );
}() );
