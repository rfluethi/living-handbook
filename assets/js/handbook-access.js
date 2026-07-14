/*
 * Handbook access fields: show the roles and users rows only when the frontend
 * visibility is set to "restricted". Enqueued on the handbook_set term add and
 * edit screens by HandbookAdmin.
 */
( function () {
	'use strict';

	var sel = document.getElementById( 'living_handbook_visibility' );
	if ( ! sel ) {
		return;
	}
	var rows = document.querySelectorAll( '.js-lh-restricted' );

	function update() {
		var restricted = 'restricted' === sel.value;
		rows.forEach( function ( row ) {
			row.style.display = restricted ? '' : 'none';
		} );
	}

	sel.addEventListener( 'change', update );
	update();
}() );
