/**
 * Prefills the handbook maintenance fields in the Quick Edit row.
 *
 * WordPress renders the custom Quick Edit fields empty; it does not know their
 * current values. This copies the values that the "Last reviewed" column carries
 * as data attributes into the fields when the Quick Edit form opens.
 */

/* global inlineEditPost */

( function ( $ ) {
	'use strict';

	if ( typeof inlineEditPost === 'undefined' ) {
		return;
	}

	const wpEdit = inlineEditPost.edit;

	/**
	 * Opens the Quick Edit row as core does, then fills in the handbook fields.
	 *
	 * Wraps core's inlineEditPost.edit(), so `this` is inlineEditPost and the
	 * arguments are passed on unchanged.
	 *
	 * @param {number|HTMLElement} id The post ID, or an element inside its row.
	 * @return {void}
	 */
	inlineEditPost.edit = function ( id ) {
		wpEdit.apply( this, arguments );

		let postId = 0;
		if ( typeof id === 'object' ) {
			postId = parseInt( this.getId( id ), 10 );
		}
		if ( ! postId ) {
			return;
		}

		const $data = $( '#post-' + postId ).find( '.living-handbook-qe-data' );
		if ( ! $data.length ) {
			return;
		}

		const $edit = $( '#edit-' + postId );
		$edit
			.find( 'input[name="living_handbook_reviewed"]' )
			.val( $data.attr( 'data-reviewed' ) || '' );
		$edit
			.find( 'input[name="living_handbook_interval"]' )
			.val( $data.attr( 'data-interval' ) || '' );
		$edit
			.find( 'select[name="living_handbook_reviewer"]' )
			.val( $data.attr( 'data-reviewer' ) || '0' );
	};
} )( window.jQuery );
