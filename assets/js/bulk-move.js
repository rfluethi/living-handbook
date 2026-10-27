/**
 * Places the "Move to handbook" target beside the bulk action dropdowns.
 *
 * WordPress gives a bulk action one dropdown and nothing else, so a plugin that
 * needs a target has two choices: put every target into the dropdown as its own
 * action, or add a control beside it. The first grows with every handbook a site
 * creates and reads as a list of unrelated actions; this is the second.
 *
 * The select is rendered by PHP into the filter row, which is inside the same
 * form, so it is submitted whether or not this script runs. What the script does
 * is placement: it moves the select next to the bulk dropdown, one copy per bar
 * (WordPress draws the bar above and below the table), and shows the copies only
 * while the move action is selected. If the script fails, the control stays
 * where PHP put it and still works.
 *
 * Since WordPress 5.7 the two bars are married (common.js, marryControls()):
 * changing one dropdown sets the other to the same value, and the lower Apply
 * button clicks the upper one. So both bars show the move action at once, and
 * the copies follow the same rule: both are shown, and a handbook chosen in one
 * is set in the other. Every enabled field of a form is submitted, so the two
 * copies must agree; this script keeps them equal instead of guessing which
 * button was pressed.
 *
 * Choosing nothing is refused by the browser's own validation, through the
 * required attribute on a placeholder option with an empty value, rather than by
 * a dialog of our own. The server still checks, because a submit can arrive
 * without any of this.
 */

/* global livingHandbookBulkMove */

( function () {
	'use strict';

	if (
		typeof livingHandbookBulkMove === 'undefined' ||
		! livingHandbookBulkMove.action ||
		! livingHandbookBulkMove.field
	) {
		return;
	}
	const config = livingHandbookBulkMove;

	document.addEventListener( 'DOMContentLoaded', () => {
		const source = document.querySelector( '.living-handbook-move-target' );
		if ( ! source ) {
			return;
		}

		const selectors = document.querySelectorAll(
			'#bulk-action-selector-top, #bulk-action-selector-bottom'
		);
		if ( ! selectors.length ) {
			return;
		}

		const controls = [];

		selectors.forEach( ( selector, index ) => {
			// The first bar takes the rendered element itself, any further bar a
			// copy.
			const control = 0 === index ? source : source.cloneNode( true );
			const field = control.querySelector( 'select' );
			const label = control.querySelector( 'label' );

			// A copy must not repeat the id of the original: two elements with one
			// id break the label association.
			if ( 0 !== index && field && label ) {
				field.id = config.field + '-' + index;
				label.setAttribute( 'for', field.id );
			}

			selector.parentNode.insertBefore( control, selector.nextSibling );
			control.hidden = true;
			controls.push( { control, field } );
		} );

		/**
		 * Shows the handbook fields while the move action is chosen.
		 *
		 * The value of the dropdown that changed decides for every bar. The
		 * other dropdown receives the same value from core, but possibly only
		 * after this handler has run, so it cannot be read here.
		 *
		 * @param {string} action The bulk action now selected.
		 * @return {void}
		 */
		function sync( action ) {
			const wanted = config.action === action;
			controls.forEach( ( { control, field } ) => {
				control.hidden = ! wanted;
				if ( field ) {
					// A hidden field must neither block the submit nor send a
					// value for an action that is not a move.
					field.required = wanted;
					field.disabled = ! wanted;
				}
			} );
		}

		/**
		 * Copies the handbook chosen in one bar into the other.
		 *
		 * @param {Event} event The change event of a handbook field.
		 * @return {void}
		 */
		function mirror( event ) {
			controls.forEach( ( { field } ) => {
				if ( field && field !== event.target ) {
					field.value = event.target.value;
				}
			} );
		}

		selectors.forEach( ( selector ) => {
			selector.addEventListener( 'change', ( event ) =>
				sync( event.target.value )
			);
		} );
		controls.forEach( ( { field } ) => {
			if ( field ) {
				field.addEventListener( 'change', mirror );
			}
		} );

		sync( selectors[ 0 ].value );
	} );
} )();
