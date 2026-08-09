/**
 * The static website export on the export screen.
 *
 * Rendering a handbook takes more than one request, so this drives the passes:
 * start, then ask for the next one until the server says it is done, showing
 * how many pages are left. The link at the end is a normal download; the file
 * lives outside the uploads folder and is deleted the moment it is fetched.
 */
( function ( wp ) {
	'use strict';

	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;

	wp.domReady( function () {
		var handbook = document.getElementById( 'lh-site-handbook' );
		var area = document.getElementById( 'lh-site-area' );
		var theme = document.getElementById( 'lh-site-theme' );
		var button = document.getElementById( 'lh-site-start' );
		var status = document.getElementById( 'lh-site-status' );

		if ( ! handbook || ! area || ! theme || ! button || ! status ) {
			return;
		}

		// The area list of the bundle form above, printed by HandbookExport.
		var areas = window.lhExportAreas || {};

		function fillAreas() {
			var list = areas[ handbook.value ] || [];
			area.innerHTML = '';
			var whole = document.createElement( 'option' );
			whole.value = '0';
			whole.textContent = area.getAttribute( 'data-whole' ) || '';
			area.appendChild( whole );
			list.forEach( function ( page ) {
				var option = document.createElement( 'option' );
				option.value = String( page.id );
				option.textContent = page.title;
				area.appendChild( option );
			} );
		}

		handbook.addEventListener( 'change', fillAreas );
		fillAreas();

		function fail( message ) {
			status.textContent = message;
			button.disabled = false;
		}

		function pass( body ) {
			wp.apiFetch( {
				path: window.lhStaticExport.path,
				method: 'POST',
				data: body
			} ).then( function ( result ) {
				if ( result.error ) {
					fail( result.error );
					return;
				}
				if ( ! result.done ) {
					status.textContent = sprintf(
						/* translators: 1: pages already rendered, 2: pages in total. */
						__( 'Rendering page %1$d of %2$d …', 'living-handbook' ),
						result.total - result.remaining,
						result.total
					);
					pass( { job: result.job } );
					return;
				}

				status.innerHTML = '';
				var link = document.createElement( 'a' );
				link.href = result.url;
				link.className = 'button button-primary';
				link.textContent = sprintf(
					/* translators: %s: file size, for example "4 MB". */
					__( 'Download the website (%s)', 'living-handbook' ),
					result.size
				);
				status.appendChild( link );
				button.disabled = false;
			} ).catch( function ( error ) {
				fail( ( error && error.message ) || __( 'The export failed.', 'living-handbook' ) );
			} );
		}

		button.addEventListener( 'click', function () {
			if ( '0' === handbook.value || '' === handbook.value ) {
				status.textContent = __( 'Choose a handbook to export.', 'living-handbook' );
				return;
			}
			button.disabled = true;
			status.textContent = __( 'Starting …', 'living-handbook' );
			pass( { handbook: handbook.value, area: area.value, theme: theme.value } );
		} );
	} );
}( window.wp ) );
