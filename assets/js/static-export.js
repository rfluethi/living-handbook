/**
 * The export screen.
 *
 * Fills the "What to export" lists of both forms on the screen, the bundle
 * export and the static website export, with the areas of the handbook chosen
 * above them. Without JavaScript each list keeps its single "whole handbook"
 * entry, so a whole-handbook export still works.
 *
 * Drives the static website export: rendering a handbook takes more than one
 * request, so this starts it and then asks for the next pass until the server
 * says it is done, showing how many pages are left. The link at the end is a
 * normal download; the file lives outside the uploads folder and is deleted
 * the moment it is fetched.
 *
 * Registered by Export\StaticSite with wp-api-fetch, wp-dom-ready and wp-i18n
 * as dependencies. The REST path arrives as lhStaticExport, the areas of every
 * handbook as lhExportAreas (Import\HandbookExport).
 */

/* global lhStaticExport, lhExportAreas */

( function () {
	'use strict';

	const { __, sprintf } = window.wp.i18n;

	/**
	 * How long after a click on the download link the screen says the file
	 * was downloaded, in milliseconds. The browser needs a moment to start the
	 * download; saying so at once would come before it.
	 */
	const DOWNLOADED_AFTER_MS = 1200;

	/** How long that sentence stays before the screen is cleared again. */
	const DOWNLOADED_FOR_MS = 6000;

	/**
	 * Fills an area list with the areas of the chosen handbook.
	 *
	 * @param {HTMLSelectElement} handbook The handbook choice.
	 * @param {HTMLSelectElement} area     The area list to fill.
	 * @return {void}
	 */
	function fillAreas( handbook, area ) {
		const areas = typeof lhExportAreas !== 'undefined' ? lhExportAreas : {};
		const list = areas[ handbook.value ] || [];
		area.innerHTML = '';
		const whole = document.createElement( 'option' );
		whole.value = '0';
		whole.textContent = area.getAttribute( 'data-whole' ) || '';
		area.appendChild( whole );
		list.forEach( ( page ) => {
			const option = document.createElement( 'option' );
			option.value = String( page.id );
			option.textContent = page.title;
			area.appendChild( option );
		} );
		// With no handbook chosen there is nothing to choose from.
		area.disabled = '0' === handbook.value;
	}

	/**
	 * Keeps an area list in step with its handbook choice.
	 *
	 * @param {string} handbookId Id of the handbook select.
	 * @param {string} areaId     Id of the area select.
	 * @return {void}
	 */
	function wireAreas( handbookId, areaId ) {
		const handbook = document.getElementById( handbookId );
		const area = document.getElementById( areaId );
		if ( ! handbook || ! area ) {
			return;
		}
		handbook.addEventListener( 'change', () =>
			fillAreas( handbook, area )
		);
		fillAreas( handbook, area );
	}

	/**
	 * Connects the static website form.
	 *
	 * @return {void}
	 */
	function wireStaticExport() {
		const handbook = document.getElementById( 'lh-site-handbook' );
		const area = document.getElementById( 'lh-site-area' );
		const theme = document.getElementById( 'lh-site-theme' );
		const button = document.getElementById( 'lh-site-start' );
		const status = document.getElementById( 'lh-site-status' );
		if ( ! handbook || ! area || ! theme || ! button || ! status ) {
			return;
		}

		/**
		 * Reports a failed export and lets the user try again.
		 *
		 * @param {string} message What went wrong.
		 * @return {void}
		 */
		function fail( message ) {
			status.textContent = message;
			button.disabled = false;
		}

		/**
		 * Replaces the status with the download link of the finished file.
		 *
		 * @param {{url: string, size: string}} result The server's last answer.
		 * @return {void}
		 */
		function offerDownload( result ) {
			status.innerHTML = '';
			const link = document.createElement( 'a' );
			link.href = result.url;
			link.className = 'button button-primary';
			link.textContent = sprintf(
				/* translators: %s: file size, for example "4 MB". */
				__( 'Download the website (%s)', 'living-handbook' ),
				result.size
			);

			// The file is handed over once and deleted in the same request, so
			// this link works exactly once. Leaving it on screen afterwards
			// offers a button whose only possible answer is an error message.
			// The screen goes back to how it started instead, ready for the
			// next export.
			link.addEventListener( 'click', () => {
				window.setTimeout( () => {
					status.textContent = __(
						'Downloaded. Check your downloads folder.',
						'living-handbook'
					);
					window.setTimeout( () => {
						status.textContent = '';
					}, DOWNLOADED_FOR_MS );
				}, DOWNLOADED_AFTER_MS );
			} );

			status.appendChild( link );
			button.disabled = false;
		}

		/**
		 * Runs one pass of the export and, while the server has more, the
		 * next.
		 *
		 * @param {Object} body The start parameters, or the job to continue.
		 * @return {void}
		 */
		function pass( body ) {
			window.wp
				.apiFetch( {
					path: lhStaticExport.path,
					method: 'POST',
					data: body,
				} )
				.then( ( result ) => {
					if ( result.error ) {
						fail( result.error );
						return;
					}
					if ( result.done ) {
						offerDownload( result );
						return;
					}
					status.textContent = sprintf(
						/* translators: 1: pages already rendered, 2: pages in total. */
						__(
							'Rendering page %1$d of %2$d …',
							'living-handbook'
						),
						result.total - result.remaining,
						result.total
					);
					pass( { job: result.job } );
				} )
				.catch( ( error ) => {
					fail(
						( error && error.message ) ||
							__( 'The export failed.', 'living-handbook' )
					);
				} );
		}

		button.addEventListener( 'click', () => {
			if ( '0' === handbook.value || '' === handbook.value ) {
				status.textContent = __(
					'Choose a handbook to export.',
					'living-handbook'
				);
				return;
			}
			button.disabled = true;
			status.textContent = __( 'Starting …', 'living-handbook' );
			pass( {
				handbook: handbook.value,
				area: area.value,
				theme: theme.value,
			} );
		} );
	}

	window.wp.domReady( () => {
		wireAreas( 'lh-export-handbook', 'lh-export-area' );
		wireAreas( 'lh-site-handbook', 'lh-site-area' );
		wireAreas( 'lh-md-handbook', 'lh-md-area' );
		wireStaticExport();
	} );
} )();
