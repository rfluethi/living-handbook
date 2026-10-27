/**
 * Handbook access fields on the handbook_set term screens.
 *
 * Shows the roles and users rows only while the frontend visibility is set to
 * "restricted", because they have no effect for any other setting. Enqueued on
 * the term add and edit screens by HandbookAdmin.
 */

( function () {
	'use strict';

	const select = document.getElementById( 'living_handbook_visibility' );
	if ( ! select ) {
		return;
	}
	const rows = document.querySelectorAll( '.js-lh-restricted' );

	/**
	 * Shows or hides the restricted-only rows for the current visibility.
	 *
	 * @return {void}
	 */
	function update() {
		const restricted = 'restricted' === select.value;
		rows.forEach( ( row ) => {
			row.style.display = restricted ? '' : 'none';
		} );
	}

	select.addEventListener( 'change', update );
	update();
} )();
