/**
 * Persists the dismissal of the first-run setup notice.
 *
 * WordPress adds the dismiss button to a notice carrying "is-dismissible" and
 * hides it on click, but only for the current page view. This tells the server
 * about it, so the notice stays gone. Enqueued by Setup\Onboarding, and only on
 * the screens where the notice can appear.
 */

/* global livingHandbookSetup */

( function () {
	'use strict';

	if ( typeof livingHandbookSetup === 'undefined' ) {
		return;
	}

	// The dismiss button is added by core after this script runs, so listen on
	// the document rather than on the button itself.
	document.addEventListener( 'click', ( event ) => {
		if ( ! ( event.target instanceof window.Element ) ) {
			return;
		}
		if (
			! event.target.closest(
				'#living-handbook-setup-notice .notice-dismiss'
			)
		) {
			return;
		}

		const body = new URLSearchParams();
		body.set( 'action', livingHandbookSetup.action );
		body.set( 'nonce', livingHandbookSetup.nonce );

		// Fire and forget: the notice is already hidden for this view, and a
		// failed request only means it comes back on the next one.
		window
			.fetch( livingHandbookSetup.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: body.toString(),
			} )
			.catch( () => {} );
	} );
} )();
