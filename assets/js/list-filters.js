/**
 * Keeps the filter bar of the handbook list in step with the column checkboxes.
 *
 * WordPress applies the "Screen Options" checkboxes immediately: tick a column
 * off and it disappears from the table, no reload. The filters above the list
 * are drawn by PHP, so without this they would keep the state the page was
 * loaded with, and a person would tidy up their columns and still face a filter
 * bar for columns that are no longer there.
 *
 * PHP renders every filter and hides the ones whose column is off, so this
 * script only flips the `hidden` attribute. Without it the bar is still correct
 * for the columns the page was loaded with.
 *
 * One filter is never hidden: the one currently narrowing the list, marked
 * data-active by PHP. Its value sits in the URL and stays in effect whatever
 * the columns say, so taking the control away would leave a filtered list with
 * no way to clear it.
 */

( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', () => {
		const filters = document.querySelectorAll(
			'.living-handbook-list-filter[data-column]'
		);
		if ( ! filters.length ) {
			return;
		}

		// One filter per column, so the lookup is a map rather than a scan per
		// checkbox change.
		const byColumn = {};
		filters.forEach( ( filter ) => {
			byColumn[ filter.getAttribute( 'data-column' ) ] = filter;
		} );

		/**
		 * Shows or hides the filter of the column a checkbox controls.
		 *
		 * @param {HTMLInputElement} checkbox A "Screen Options" column checkbox.
		 * @return {void}
		 */
		function sync( checkbox ) {
			const filter = byColumn[ checkbox.value ];
			if ( ! filter || '1' === filter.getAttribute( 'data-active' ) ) {
				return;
			}
			filter.hidden = ! checkbox.checked;
		}

		document
			.querySelectorAll( '.hide-column-tog' )
			.forEach( ( checkbox ) => {
				checkbox.addEventListener( 'change', () => sync( checkbox ) );
			} );
	} );
} )();
