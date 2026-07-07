( function () {
	'use strict';

	function submit( box, value ) {
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

	document.querySelectorAll( '.living-handbook-feedback' ).forEach( function ( box ) {
		box.querySelectorAll( 'button[data-value]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				submit( box, button.getAttribute( 'data-value' ) );
			} );
		} );
	} );
}() );
