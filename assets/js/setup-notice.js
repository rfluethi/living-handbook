/*
 * Persist the dismissal of the first-run setup notice.
 *
 * WordPress adds the dismiss button to a notice carrying "is-dismissible" and
 * hides it on click, but only for the current page view. This tells the server
 * about it, so the notice stays gone. Enqueued by Setup\Onboarding, and only on
 * the screens where the notice can appear.
 */
( function () {
	'use strict';

	if ( ! window.livingHandbookSetup ) {
		return;
	}

	// The dismiss button is added by core after this script runs, so listen on
	// the document rather than on the button itself.
	document.addEventListener( 'click', function ( event ) {
		if ( ! ( event.target instanceof Element ) ) {
			return;
		}
		if ( ! event.target.closest( '#living-handbook-setup-notice .notice-dismiss' ) ) {
			return;
		}

		var body = new URLSearchParams();
		body.set( 'action', window.livingHandbookSetup.action );
		body.set( 'nonce', window.livingHandbookSetup.nonce );

		fetch( window.livingHandbookSetup.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		} ).catch( function () {} );
	} );
}() );
