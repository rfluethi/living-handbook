/**
 * Move pages into a handbook: the second control beside the bulk dropdown.
 *
 * WordPress gives a bulk action one dropdown and nothing else, so a plugin that
 * needs a target has two choices: put every target into the dropdown as its own
 * action, or add a control beside it. The first grows with every handbook a site
 * creates and reads as a list of unrelated actions; this is the second.
 *
 * The select is rendered by PHP into the filter row, which is inside the same
 * form, so it is submitted whether or not this script runs. What the script does
 * is placement: it moves the select next to the bulk dropdown, one copy per bar
 * (WordPress draws the bar above and below the table), and shows it only while
 * the move action is selected. If the script fails, the control stays where PHP
 * put it and still works.
 *
 * Choosing nothing is refused by the browser's own validation, through the
 * required attribute on a placeholder option with an empty value, rather than by
 * a dialog of our own. The server still checks, because a submit can arrive
 * without any of this.
 */
( function () {
	'use strict';

	var config = window.livingHandbookBulkMove;
	if ( ! config || ! config.action || ! config.field ) {
		return;
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var source = document.querySelector( '.living-handbook-move-target' );
		if ( ! source ) {
			return;
		}

		var selectors = document.querySelectorAll( '#bulk-action-selector-top, #bulk-action-selector-bottom' );
		if ( ! selectors.length ) {
			return;
		}

		var pairs = [];

		selectors.forEach( function ( selector, index ) {
			// The first bar takes the rendered element itself, any further bar a
			// copy, so the field is present once per bar and the browser submits
			// the one belonging to the button that was pressed.
			var control = 0 === index ? source : source.cloneNode( true );
			var field = control.querySelector( 'select' );
			var label = control.querySelector( 'label' );

			// A copy must not repeat the id of the original: two elements with one
			// id break the label association.
			if ( 0 !== index && field && label ) {
				field.id = config.field + '-' + index;
				label.setAttribute( 'for', field.id );
			}

			selector.parentNode.insertBefore( control, selector.nextSibling );
			control.hidden = true;
			pairs.push( { selector: selector, control: control, field: field } );
		} );

		function sync() {
			pairs.forEach( function ( pair ) {
				var wanted = config.action === pair.selector.value;
				pair.control.hidden = ! wanted;
				if ( pair.field ) {
					// Only the visible one may block the submit, and only while
					// the move is what was asked for.
					pair.field.required = wanted;
					pair.field.disabled = ! wanted;
				}
			} );
		}

		selectors.forEach( function ( selector ) {
			selector.addEventListener( 'change', sync );
		} );

		sync();
	} );
}() );
