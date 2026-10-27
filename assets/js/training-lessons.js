/**
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
 * Registered by Training\LessonPicker with wp-api-fetch, wp-dom-ready and
 * wp-i18n as dependencies, and the REST path as lhTraining.
 */

/* global lhTraining */

( function () {
	'use strict';

	const { __, sprintf } = window.wp.i18n;

	/**
	 * Delay before a typed search is sent, in milliseconds. A little longer
	 * than on the front end: an editor types full titles.
	 */
	const SEARCH_DEBOUNCE_MS = 250;

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

	/**
	 * Creates one of the small text buttons of a lesson row.
	 *
	 * @param {string} className Class that identifies the button's job.
	 * @param {string} label     Visible label.
	 * @return {HTMLButtonElement} The button.
	 */
	function rowButton( className, label ) {
		const element = document.createElement( 'button' );
		element.type = 'button';
		element.className = 'button-link ' + className;
		element.textContent = label;
		return element;
	}

	/**
	 * Keeps the focus on the button that was pressed.
	 *
	 * That is the whole point of having buttons: without it a keyboard user
	 * loses their place on every move and has to tab back into the list. A
	 * button the move has just disabled cannot take the focus, though: up to
	 * 0.75.1 the last Move up of a row sent it to the page body. Then the other
	 * button of the same row takes it, the one that has something left to do.
	 * The disabled button stays disabled, so the list works the same for
	 * everybody.
	 *
	 * @param {HTMLButtonElement} pressed The button that was used.
	 * @param {HTMLButtonElement} other   The opposite button of the same row.
	 * @return {void}
	 */
	function keepFocus( pressed, other ) {
		const target = pressed.disabled ? other : pressed;
		if ( target && ! target.disabled ) {
			target.focus();
		}
	}

	/**
	 * Turns one lesson picker into a working control.
	 *
	 * @param {HTMLElement} root The picker, carrying data-training.
	 * @return {void}
	 */
	function setup( root ) {
		const value = root.querySelector( '.living-handbook-lessons__value' );
		const list = root.querySelector( '.living-handbook-lessons__list' );
		if ( ! value || ! list ) {
			return;
		}
		const empty = root.querySelector( '.living-handbook-lessons__empty' );
		const search = root.querySelector( '.living-handbook-lessons__search' );

		/**
		 * Returns the lesson IDs in their current order.
		 *
		 * @return {number[]} Post IDs.
		 */
		function ids() {
			return Array.from( list.children )
				.map( ( item ) =>
					parseInt( item.getAttribute( 'data-id' ), 10 )
				)
				.filter( ( id ) => id > 0 );
		}

		/**
		 * Writes the hidden field, the empty note and the button states.
		 *
		 * One place writes them all, so they cannot drift apart after a move,
		 * a removal or an addition.
		 *
		 * @return {void}
		 */
		function sync() {
			const current = ids();
			value.value = current.join( ',' );
			if ( empty ) {
				if ( current.length ) {
					empty.setAttribute( 'hidden', 'hidden' );
				} else {
					empty.removeAttribute( 'hidden' );
				}
			}
			Array.from( list.children ).forEach( ( item, index ) => {
				const up = item.querySelector( '.living-handbook-lessons__up' );
				const down = item.querySelector(
					'.living-handbook-lessons__down'
				);
				if ( up ) {
					up.disabled = 0 === index;
				}
				if ( down ) {
					down.disabled = index === list.children.length - 1;
				}
			} );
		}

		/**
		 * Moves a lesson one place up or down.
		 *
		 * @param {HTMLLIElement} item      The lesson row.
		 * @param {number}        direction -1 for up, 1 for down.
		 * @return {void}
		 */
		function move( item, direction ) {
			if ( direction < 0 && item.previousElementSibling ) {
				list.insertBefore( item, item.previousElementSibling );
			} else if ( direction > 0 && item.nextElementSibling ) {
				list.insertBefore( item.nextElementSibling, item );
			}
			sync();
		}

		/**
		 * Adds the Move up, Move down and Remove buttons to a lesson row.
		 *
		 * @param {HTMLLIElement} item The lesson row.
		 * @return {void}
		 */
		function decorate( item ) {
			if ( item.querySelector( '.living-handbook-lessons__up' ) ) {
				return;
			}
			const title = item.querySelector(
				'.living-handbook-lessons__title'
			);
			const name = title ? title.textContent : '';

			const controls = document.createElement( 'span' );
			controls.className = 'living-handbook-lessons__controls';

			const up = rowButton(
				'living-handbook-lessons__up',
				__( 'Move up', 'living-handbook' )
			);
			const down = rowButton(
				'living-handbook-lessons__down',
				__( 'Move down', 'living-handbook' )
			);
			const remove = rowButton(
				'living-handbook-lessons__remove',
				__( 'Remove', 'living-handbook' )
			);

			up.setAttribute(
				'aria-label',
				sprintf(
					/* translators: %s is the title of a lesson. */
					__( 'Move %s up', 'living-handbook' ),
					name
				)
			);
			down.setAttribute(
				'aria-label',
				sprintf(
					/* translators: %s is the title of a lesson. */
					__( 'Move %s down', 'living-handbook' ),
					name
				)
			);
			remove.setAttribute(
				'aria-label',
				sprintf(
					/* translators: %s is the title of a lesson. */
					__( 'Remove %s', 'living-handbook' ),
					name
				)
			);

			up.addEventListener( 'click', () => {
				move( item, -1 );
				keepFocus( up, down );
			} );
			down.addEventListener( 'click', () => {
				move( item, 1 );
				keepFocus( down, up );
			} );
			remove.addEventListener( 'click', () => {
				const next =
					item.nextElementSibling || item.previousElementSibling;
				item.remove();
				sync();
				const target = next
					? next.querySelector( '.living-handbook-lessons__remove' )
					: search;
				if ( target ) {
					target.focus();
				}
			} );

			controls.appendChild( up );
			controls.appendChild( down );
			controls.appendChild( remove );
			item.appendChild( controls );
		}

		/**
		 * Appends a lesson to the list, unless it is already in it.
		 *
		 * @param {number} id    Post ID of the page.
		 * @param {string} title Page title.
		 * @return {void}
		 */
		function add( id, title ) {
			if ( -1 !== ids().indexOf( id ) ) {
				return;
			}
			const item = document.createElement( 'li' );
			item.setAttribute( 'data-id', String( id ) );
			const label = document.createElement( 'span' );
			label.className = 'living-handbook-lessons__title';
			label.textContent = title;
			item.appendChild( label );
			list.appendChild( item );
			decorate( item );
			sync();
		}

		Array.from( list.children ).forEach( decorate );
		sync();

		const results = root.querySelector(
			'.living-handbook-lessons__results'
		);
		if ( ! search || ! results || typeof lhTraining === 'undefined' ) {
			return;
		}
		const trainingId = parseInt( root.getAttribute( 'data-training' ), 10 );

		/**
		 * Shows the pages a search found, each as a button that adds it.
		 *
		 * @param {Array<{id: number, title: string}>} items Matching pages.
		 * @return {void}
		 */
		function render( items ) {
			results.textContent = '';
			if ( ! items.length ) {
				const none = document.createElement( 'li' );
				none.className = 'living-handbook-lessons__none';
				none.textContent = __(
					'No matching pages.',
					'living-handbook'
				);
				results.appendChild( none );
				return;
			}
			items.forEach( ( hit ) => {
				const entry = document.createElement( 'li' );
				const choose = document.createElement( 'button' );
				choose.type = 'button';
				choose.className = 'button-link';
				choose.textContent = hit.title;
				choose.addEventListener( 'click', () => {
					add( hit.id, hit.title );
					search.value = '';
					results.textContent = '';
					search.focus();
				} );
				entry.appendChild( choose );
				results.appendChild( entry );
			} );
		}

		// Every search gets a number, and only the answer to the latest one is
		// shown. The debounce spaces the requests out but cannot order the
		// answers: a slow answer for "onb" could land after the quick one for
		// "onboarding" and replace it, so the list showed matches for words no
		// longer in the field. A field emptied below two characters counts as a
		// search of its own, so an answer still in flight cannot refill it.
		let latest = 0;

		const run = debounce( () => {
			const term = search.value.trim();
			const request = ++latest;
			if ( term.length < 2 ) {
				results.textContent = '';
				return;
			}
			window.wp
				.apiFetch( {
					path:
						lhTraining.searchPath +
						'?training_id=' +
						encodeURIComponent( trainingId ) +
						'&q=' +
						encodeURIComponent( term ),
				} )
				.then( ( response ) => {
					if ( request !== latest ) {
						return;
					}
					render( ( response && response.results ) || [] );
				} )
				.catch( () => {
					if ( request !== latest ) {
						return;
					}
					results.textContent = '';
					const failed = document.createElement( 'li' );
					failed.className = 'living-handbook-lessons__none';
					failed.textContent = __(
						'The search failed. Please try again.',
						'living-handbook'
					);
					results.appendChild( failed );
				} );
		}, SEARCH_DEBOUNCE_MS );

		search.addEventListener( 'input', run );
		// Enter in a search field submits the post form in the classic editor,
		// which would save the path in the middle of choosing lessons.
		search.addEventListener( 'keydown', ( event ) => {
			if ( 'Enter' === event.key ) {
				event.preventDefault();
				run();
			}
		} );
	}

	window.wp.domReady( () => {
		document
			.querySelectorAll( '.living-handbook-lessons' )
			.forEach( setup );
	} );
} )();
