/**
 * The Markdown import screen.
 *
 * Imports pasted Markdown, a ZIP file (flat, or an MkDocs project with its
 * navigation), a GitHub folder or one of the handbooks that ship with the
 * plugin. The server converts Markdown to HTML; this script turns that HTML
 * into block markup with the block editor's own paste handler, creates the
 * draft pages through the plugin's REST routes and finally asks the server to
 * resolve the internal links between them.
 *
 * Registered by Import\MarkdownImportPage with wp-blocks, wp-block-library,
 * wp-api-fetch, wp-dom-ready and wp-i18n as dependencies, and the REST paths
 * as lhImport.
 */

/* global lhImport */

( function () {
	'use strict';

	const { __, _n, sprintf } = window.wp.i18n;

	/**
	 * Composes the "Done" summary from already-pluralised parts, so each count
	 * (pages, images, converted links) gets its own singular or plural form.
	 *
	 * @param {number} pages  Pages created.
	 * @param {number} images Images imported.
	 * @param {number} links  Internal links converted.
	 * @return {string} The sentence.
	 */
	function doneSummary( pages, images, links ) {
		const pagesText = sprintf(
			/* translators: %d is a number of pages. */
			_n( '%d page', '%d pages', pages, 'living-handbook' ),
			pages
		);
		const imagesText = sprintf(
			/* translators: %d is a number of images. */
			_n( '%d image', '%d images', images, 'living-handbook' ),
			images
		);
		const linksText = sprintf(
			/* translators: %d is a number of converted internal links. */
			_n(
				'%d link converted',
				'%d links converted',
				links,
				'living-handbook'
			),
			links
		);
		return sprintf(
			/* translators: %1$s pages, %2$s images, %3$s converted links, each already pluralised. */
			__( 'Done: %1$s, %2$s, %3$s.', 'living-handbook' ),
			pagesText,
			imagesText,
			linksText
		);
	}

	/**
	 * Registers the core blocks if the screen has not done so.
	 *
	 * The paste handler can only produce blocks it knows; on this screen, which
	 * is not an editor, nobody else registers them.
	 *
	 * @return {void}
	 */
	function ensureCoreBlocks() {
		const { blocks, blockLibrary } = window.wp;
		if ( ! blocks || ! blockLibrary || ! blockLibrary.registerCoreBlocks ) {
			return;
		}
		if ( blocks.getBlockType && blocks.getBlockType( 'core/paragraph' ) ) {
			return;
		}
		try {
			blockLibrary.registerCoreBlocks();
		} catch ( error ) {
			// The import still works for the blocks that did register; the
			// console tells a developer why the others are missing.
			// eslint-disable-next-line no-console -- see the comment above.
			console.error( error );
		}
	}

	/**
	 * Escapes text for use inside HTML.
	 *
	 * @param {unknown} value Anything; null and undefined become an empty string.
	 * @return {string} The escaped text.
	 */
	function escapeHtml( value ) {
		return String( value === null || value === undefined ? '' : value )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' );
	}

	/**
	 * Returns the block markup of a Mermaid block.
	 *
	 * @param {string} code Mermaid source.
	 * @return {string} Serialised block.
	 */
	function mermaidBlock( code ) {
		// Encode <, > and & like WordPress does for block attributes, so that a
		// Mermaid arrow (-->) inside the code cannot close the HTML comment.
		const json = JSON.stringify( { code } )
			.replace( /</g, '\\u003c' )
			.replace( />/g, '\\u003e' )
			.replace( /&/g, '\\u0026' );
		return '<!-- wp:living-handbook/mermaid ' + json + ' /-->';
	}

	/**
	 * Returns the block markup of a core Details block for a <details> element.
	 *
	 * @param {HTMLDetailsElement} node The element, from the converted HTML.
	 * @return {string} Serialised block.
	 */
	function detailsBlock( node ) {
		const summaryEl = node.querySelector( 'summary' );
		const summary = summaryEl ? summaryEl.textContent : '';
		const clone = node.cloneNode( true );
		const cloneSummary = clone.querySelector( 'summary' );
		if ( cloneSummary ) {
			cloneSummary.remove();
		}
		// Mutual recursion: the content of a <details> is converted like the page.
		const inner = libraryHtmlToMarkup( clone.innerHTML );
		return (
			'<!-- wp:details --><details class="wp-block-details"><summary>' +
			escapeHtml( summary ) +
			'</summary>' +
			inner +
			'</details><!-- /wp:details -->'
		);
	}

	/**
	 * Turns converted HTML into block markup.
	 *
	 * Most of it goes through the block editor's paste handler. Two things are
	 * taken out first, because the paste handler would lose them: <details>
	 * becomes a core Details block (its content converted the same way), and a
	 * code block in the mermaid language becomes a Mermaid block.
	 *
	 * @param {string} html HTML from the server's Markdown conversion.
	 * @return {string} Serialised blocks.
	 */
	function libraryHtmlToMarkup( html ) {
		// GitHub task-list checkboxes are <input> elements, which the block
		// paste handler drops. Turn them into ballot symbols first, so the
		// checkbox survives as visible text in the imported page.
		const source = String( html ).replace(
			/<input\b[^>]*type=["']?checkbox["']?[^>]*>/gi,
			( match ) => ( /\bchecked\b/i.test( match ) ? '☑ ' : '☐ ' )
		);

		const doc = new window.DOMParser().parseFromString(
			'<body>' + source + '</body>',
			'text/html'
		);
		const nodes = doc.body ? doc.body.childNodes : [];
		const parts = [];
		let buffer = '';

		/**
		 * Converts what has been collected so far through the paste handler.
		 *
		 * @return {void}
		 */
		function flush() {
			if ( buffer.replace( /\s+/g, '' ) !== '' ) {
				const converted = window.wp.blocks.pasteHandler( {
					HTML: buffer,
					plainText: '',
					mode: 'BLOCKS',
				} );
				parts.push(
					typeof converted === 'string'
						? converted
						: window.wp.blocks.serialize( converted )
				);
			}
			buffer = '';
		}

		Array.from( nodes ).forEach( ( node ) => {
			if ( node.nodeType === 1 && node.tagName === 'DETAILS' ) {
				flush();
				parts.push( detailsBlock( node ) );
				return;
			}
			if ( node.nodeType === 1 && node.tagName === 'PRE' ) {
				const codeEl = node.querySelector( 'code.language-mermaid' );
				if ( codeEl ) {
					flush();
					parts.push( mermaidBlock( codeEl.textContent ) );
					return;
				}
			}
			buffer +=
				node.nodeType === 1 ? node.outerHTML : node.textContent || '';
		} );
		flush();
		return parts.join( '\n\n' );
	}

	/**
	 * Connects the import tabs as an ARIA tab list.
	 *
	 * Which tabs exist depends on the user's capabilities, so the handling
	 * stays generic over whatever is rendered.
	 *
	 * @return {void}
	 */
	function setupSourceTabs() {
		const tablist = document.querySelector(
			'.living-handbook-import__tablist'
		);
		if ( ! tablist ) {
			return;
		}
		const tabs = Array.from( tablist.querySelectorAll( '[role="tab"]' ) );

		/**
		 * Selects a tab and shows its panel.
		 *
		 * @param {HTMLElement} tab The tab to select.
		 * @return {void}
		 */
		function select( tab ) {
			tabs.forEach( ( candidate ) => {
				const on = candidate === tab;
				candidate.setAttribute(
					'aria-selected',
					on ? 'true' : 'false'
				);
				candidate.tabIndex = on ? 0 : -1;
				const panel = document.getElementById(
					candidate.getAttribute( 'aria-controls' )
				);
				if ( panel ) {
					panel.hidden = ! on;
				}
			} );
		}

		tabs.forEach( ( tab, index ) => {
			tab.addEventListener( 'click', () => {
				select( tab );
				tab.focus();
			} );
			tab.addEventListener( 'keydown', ( event ) => {
				let next;
				if ( event.key === 'ArrowRight' || event.key === 'ArrowDown' ) {
					next = ( index + 1 ) % tabs.length;
				} else if (
					event.key === 'ArrowLeft' ||
					event.key === 'ArrowUp'
				) {
					next = ( index - 1 + tabs.length ) % tabs.length;
				} else if ( event.key === 'Home' ) {
					next = 0;
				} else if ( event.key === 'End' ) {
					next = tabs.length - 1;
				} else {
					return;
				}
				event.preventDefault();
				select( tabs[ next ] );
				tabs[ next ].focus();
			} );
		} );

		// Show the panel of the tab the markup marks as selected and hide the
		// others, so the first paint is consistent.
		const current = tablist.querySelector( '[aria-selected="true"]' );
		if ( current ) {
			select( current );
		}
	}

	window.wp.domReady( () => {
		const pasteBtn = document.getElementById( 'lh-import-run-paste' );
		const zipBtn = document.getElementById( 'lh-import-run-zip' );
		const githubBtn = document.getElementById( 'lh-import-run-github' );
		const runButtons = [ pasteBtn, zipBtn, githubBtn ].filter( Boolean );
		if ( ! runButtons.length ) {
			return;
		}

		const mdField = document.getElementById( 'lh-import-md' );
		const zipField = document.getElementById( 'lh-import-zip' );
		const githubField = document.getElementById( 'lh-import-github' );
		const statusEl = document.getElementById( 'lh-import-status' );
		const results = document.getElementById( 'lh-import-results' );
		const notesEl = document.getElementById( 'lh-import-notes' );

		ensureCoreBlocks();
		setupSourceTabs();

		/**
		 * Renders the import notes as one highlighted warning block, or clears
		 * it.
		 *
		 * @param {string[]} notes What the server reports as worth knowing.
		 * @return {void}
		 */
		function showNotes( notes ) {
			if ( ! notesEl ) {
				return;
			}
			notesEl.innerHTML = '';
			if ( ! notes || ! notes.length ) {
				return;
			}
			const box = document.createElement( 'div' );
			box.className = 'notice notice-warning';
			box.style.margin = '0 0 1rem';
			box.style.padding = '0.5rem 0.75rem';
			const head = document.createElement( 'p' );
			head.style.margin = '0 0 0.25rem';
			const strong = document.createElement( 'strong' );
			strong.textContent = __(
				'Worth knowing about this import:',
				'living-handbook'
			);
			head.appendChild( strong );
			box.appendChild( head );
			const list = document.createElement( 'ul' );
			list.style.margin = '0';
			list.style.listStyle = 'disc';
			list.style.marginInlineStart = '1.5em';
			notes.forEach( ( note ) => {
				const item = document.createElement( 'li' );
				item.textContent = note;
				list.appendChild( item );
			} );
			box.appendChild( list );
			notesEl.appendChild( box );
		}

		/**
		 * Shows a progress or result sentence.
		 *
		 * @param {string} message The sentence.
		 * @return {void}
		 */
		function setStatus( message ) {
			if ( statusEl ) {
				statusEl.textContent = message;
			}
		}

		/**
		 * Locks or unlocks every import button.
		 *
		 * @param {boolean} on True while an import runs.
		 * @return {void}
		 */
		function setBusy( on ) {
			runButtons.forEach( ( button ) => {
				button.disabled = on;
			} );
		}

		/**
		 * Returns the sentence for a failed request.
		 *
		 * @param {Error|Object} error What apiFetch rejected with.
		 * @return {string} The sentence.
		 */
		function errorMessage( error ) {
			return sprintf(
				/* translators: %s is the error message. */
				__( 'Error: %s', 'living-handbook' ),
				error && error.message
					? error.message
					: __( 'unknown', 'living-handbook' )
			);
		}

		/**
		 * Returns the trimmed value of a field.
		 *
		 * @param {HTMLInputElement|null} field The field.
		 * @return {string} The value, or an empty string without the field.
		 */
		function trimVal( field ) {
			return field ? field.value.trim() : '';
		}

		/**
		 * Returns a field of the open tab panel.
		 *
		 * Each source tab carries its own copy of the shared options, so the
		 * values are read from the panel that is currently open.
		 *
		 * @param {string} selector CSS selector of the field.
		 * @return {HTMLElement|null} The field.
		 */
		function panelField( selector ) {
			const panel = document.querySelector(
				'.living-handbook-import__panel:not([hidden])'
			);
			return panel ? panel.querySelector( selector ) : null;
		}

		/**
		 * Returns the target handbook chosen in the open panel.
		 *
		 * @return {number} Term ID, 0 for none.
		 */
		function handbookId() {
			const field = panelField( '.lh-import-handbook' );
			return field ? parseInt( field.value, 10 ) || 0 : 0;
		}

		/**
		 * Returns the title typed in the open panel.
		 *
		 * @return {string} The title, possibly empty.
		 */
		function titleValue() {
			return trimVal( panelField( '.lh-import-title' ) );
		}

		/**
		 * Lists a created page, linked to its editor.
		 *
		 * @param {{id: number, editUrl: string}} created What the server created.
		 * @param {string}                        title   Title to show.
		 * @return {void}
		 */
		function addResult( created, title ) {
			if ( ! results || ! created || ! created.id ) {
				return;
			}
			const item = document.createElement( 'li' );
			const link = document.createElement( 'a' );
			link.href = created.editUrl || '#';
			link.textContent =
				title ||
				sprintf(
					/* translators: %d is the new page's numeric ID, used as a fallback title. */
					__( 'Page %d', 'living-handbook' ),
					created.id
				);
			item.appendChild( link );
			results.appendChild( item );
		}

		/**
		 * Lists a page that failed to import, with the reason, instead of
		 * letting the total silently count fewer pages.
		 *
		 * @param {string} title  Page title.
		 * @param {string} reason What the server said.
		 * @return {void}
		 */
		function addFailure( title, reason ) {
			if ( ! results ) {
				return;
			}
			const item = document.createElement( 'li' );
			item.className = 'lh-import-failed';
			item.style.color = '#b32d2e';
			item.textContent = sprintf(
				/* translators: 1: title of the page that failed to import, 2: the reason. */
				__( '%1$s: %2$s', 'living-handbook' ),
				title || __( 'Imported page', 'living-handbook' ),
				reason || __( 'unknown', 'living-handbook' )
			);
			results.appendChild( item );
		}

		/**
		 * Creates one draft page.
		 *
		 * @param {Object} page             The page.
		 * @param {string} page.title       Title.
		 * @param {string} page.content     Block markup.
		 * @param {Object} [page.transport] Transport block from the source file.
		 * @param {string} [page.slug]      Slug.
		 * @param {Object} [extra]          Further fields for the route, such
		 *                                  as parent, sourcePath and order.
		 * @return {Promise<Object>} What the server created, or its error.
		 */
		function createPage( page, extra ) {
			return window.wp.apiFetch( {
				path: lhImport.createPath,
				method: 'POST',
				data: {
					title: page.title,
					content: page.content,
					slug: page.slug || '',
					handbook: handbookId(),
					transport: page.transport || {},
					...extra,
				},
			} );
		}

		/**
		 * Asks the server to resolve the internal links of the created pages.
		 *
		 * @param {number[]} ids Post IDs of the created pages.
		 * @return {Promise<{converted: number}>} How many links were converted.
		 */
		function finalize( ids ) {
			if ( ! ids.length ) {
				return Promise.resolve( { converted: 0 } );
			}
			return window.wp.apiFetch( {
				path: lhImport.finalizePath,
				method: 'POST',
				data: { ids },
			} );
		}

		/**
		 * Imports the pasted Markdown as one draft page.
		 *
		 * @return {Promise<void>} Settles when the import is done.
		 */
		function importPaste() {
			const markdown = mdField ? mdField.value : '';
			if ( ! markdown.replace( /\s+/g, '' ) ) {
				setStatus(
					__(
						'Nothing to import: paste Markdown, choose a ZIP, or enter a GitHub URL.',
						'living-handbook'
					)
				);
				return Promise.resolve();
			}
			setStatus( __( 'Converting…', 'living-handbook' ) );
			return window.wp
				.apiFetch( {
					path: lhImport.convertPath,
					method: 'POST',
					data: { markdown },
				} )
				.then( ( response ) => {
					const markup = libraryHtmlToMarkup( response.html || '' );
					const title =
						titleValue() ||
						response.title ||
						__( 'Imported page', 'living-handbook' );
					setStatus( __( 'Creating draft…', 'living-handbook' ) );
					return createPage( {
						title,
						content: markup,
						transport: response.transport,
					} ).then( ( created ) => {
						if ( created && created.error ) {
							addFailure( title, created.error );
							setStatus(
								sprintf(
									/* translators: %s is the error message. */
									__( 'Error: %s', 'living-handbook' ),
									created.error
								)
							);
							return;
						}
						addResult( created, title );
						return finalize( [ created.id ] ).then( ( fin ) => {
							const converted = ( fin && fin.converted ) || 0;
							setStatus(
								sprintf(
									/* translators: %d is the number of internal links that were converted. */
									_n(
										'Done: 1 page, %d link converted.',
										'Done: 1 page, %d links converted.',
										converted,
										'living-handbook'
									),
									converted
								)
							);
						} );
					} );
				} )
				.catch( ( error ) => setStatus( errorMessage( error ) ) );
		}

		/**
		 * Creates the pages of an MkDocs project in navigation order, each
		 * under its parent, then resolves the links between them.
		 *
		 * @param {Array<Object>} pages  Page specs from the ZIP route.
		 * @param {number}        images Images the server imported.
		 * @return {Promise<void>} Settles when the import is done.
		 */
		function importMkdocs( pages, images ) {
			if ( ! pages.length ) {
				setStatus(
					__( 'No pages found in the mkdocs.yml.', 'living-handbook' )
				);
				return Promise.resolve();
			}
			setStatus(
				sprintf(
					/* translators: %d is the number of pages being created. */
					_n(
						'Creating %d page…',
						'Creating %d pages…',
						pages.length,
						'living-handbook'
					),
					pages.length
				)
			);
			const byPath = {};
			const ids = [];
			let done = 0;
			const total = pages.length;
			let chain = Promise.resolve();
			pages.forEach( ( spec ) => {
				chain = chain.then( () => {
					const parentId =
						spec.parentPath && byPath[ spec.parentPath ]
							? byPath[ spec.parentPath ]
							: 0;
					return createPage(
						{
							title: spec.navTitle,
							content: spec.synthetic
								? ''
								: libraryHtmlToMarkup( spec.html || '' ),
							slug: spec.slug,
							// The server reads the page's transport block and
							// hands it along.
							transport: spec.transport,
						},
						{
							parent: parentId,
							sourcePath: spec.sourcePath,
							order: spec.order,
						}
					).then( ( created ) => {
						++done;
						setStatus(
							sprintf(
								/* translators: %1$d is the current page number, %2$d the total number of pages. */
								__(
									'Importing page %1$d of %2$d …',
									'living-handbook'
								),
								done,
								total
							)
						);
						if ( created && created.error ) {
							addFailure( spec.navTitle, created.error );
							return;
						}
						if ( created && created.id ) {
							byPath[ spec.sourcePath ] = created.id;
							ids.push( created.id );
							addResult( created, spec.navTitle );
						}
					} );
				} );
			} );
			return chain.then( () => {
				setStatus( __( 'Linking…', 'living-handbook' ) );
				return finalize( ids ).then( ( fin ) => {
					setStatus(
						doneSummary(
							ids.length,
							images || 0,
							( fin && fin.converted ) || 0
						)
					);
				} );
			} );
		}

		/**
		 * Uploads a ZIP and imports what it holds.
		 *
		 * @param {File} file The ZIP file.
		 * @return {Promise<void>} Settles when the import is done.
		 */
		function importZip( file ) {
			setStatus( __( 'Uploading ZIP…', 'living-handbook' ) );
			const body = new window.FormData();
			body.append( 'zip', file );
			return window.wp
				.apiFetch( { path: lhImport.zipPath, method: 'POST', body } )
				.then( ( response ) => {
					// A ZIP with a mkdocs.yml that could not be read is imported
					// flat, which looks like a successful import of the wrong
					// thing. The server says why; show it rather than let it
					// pass unnoticed.
					showNotes(
						response && response.notes ? response.notes : []
					);
					if ( response && response.mode === 'mkdocs' ) {
						return importMkdocs(
							response.pages || [],
							response.images || 0
						);
					}
					const files =
						response && response.files ? response.files : [];
					if ( ! files.length ) {
						setStatus(
							__( 'No pages in the ZIP.', 'living-handbook' )
						);
						return;
					}
					setStatus(
						sprintf(
							/* translators: %d is the number of draft pages being created. */
							_n(
								'Creating %d draft…',
								'Creating %d drafts…',
								files.length,
								'living-handbook'
							),
							files.length
						)
					);
					const ids = [];
					let done = 0;
					const total = files.length;
					let chain = Promise.resolve();
					files.forEach( ( entry ) => {
						chain = chain.then( () =>
							createPage( {
								title: entry.title,
								content: libraryHtmlToMarkup(
									entry.html || ''
								),
								transport: entry.transport,
								slug: entry.slug,
							} ).then( ( created ) => {
								++done;
								setStatus(
									sprintf(
										/* translators: %1$d is the current page number, %2$d the total number of pages. */
										__(
											'Importing page %1$d of %2$d …',
											'living-handbook'
										),
										done,
										total
									)
								);
								if ( created && created.error ) {
									addFailure( entry.title, created.error );
									return;
								}
								addResult( created, entry.title );
								if ( created && created.id ) {
									ids.push( created.id );
								}
							} )
						);
					} );
					return chain.then( () => {
						setStatus( __( 'Linking…', 'living-handbook' ) );
						return finalize( ids ).then( ( fin ) => {
							setStatus(
								doneSummary(
									ids.length,
									response.images || 0,
									( fin && fin.converted ) || 0
								)
							);
						} );
					} );
				} )
				.catch( ( error ) => setStatus( errorMessage( error ) ) );
		}

		/**
		 * Imports a GitHub folder, in as many passes as the server needs.
		 *
		 * A large folder does not fit in one request, so the server hands back
		 * a job id when it runs out of time and this asks for the rest. Pages
		 * appear as they are created, which is also the progress display.
		 *
		 * @param {string} url GitHub URL of the folder or file.
		 * @return {Promise<void>} Settles when the import is done or stopped.
		 */
		function importGithub( url ) {
			let created = 0;
			let notes = [];

			/**
			 * Runs one pass and, while the server has more, the next.
			 *
			 * @param {Object} data Request body: the start parameters or a job.
			 * @return {Promise<void>} Settles after the last pass.
			 */
			function pass( data ) {
				return window.wp
					.apiFetch( {
						path: lhImport.githubPath,
						method: 'POST',
						data,
					} )
					.then( ( response ) => {
						const pages =
							response && response.pages ? response.pages : [];
						pages.forEach( ( page ) =>
							addResult( page, page.title )
						);
						created += pages.length;
						if ( response && response.notes ) {
							notes = notes.concat( response.notes );
						}

						// GitHub's hourly limit is used up. Asking again right away
						// only spends another refusal, so the loop stops here and
						// the note says what was imported and when it can be
						// continued.
						if ( response && response.retry_after ) {
							showNotes( notes );
							setStatus(
								sprintf(
									/* translators: %d is the number of pages created so far. */
									_n(
										'Stopped after %d page.',
										'Stopped after %d pages.',
										created,
										'living-handbook'
									),
									created
								)
							);
							return;
						}

						if ( response && response.job ) {
							// An import runs in two phases: the pages first, then
							// their internal links, which can only be resolved
							// once every page exists. Both pause when their time is
							// up, so both report.
							if ( 'links' === response.phase ) {
								setStatus(
									sprintf(
										/* translators: 1: pages created so far, 2: pages whose links still have to be checked. */
										__(
											'Imported %1$d pages, checking the links on %2$d…',
											'living-handbook'
										),
										created,
										response.remaining || 0
									)
								);
							} else {
								setStatus(
									sprintf(
										/* translators: 1: pages created so far, 2: pages still to go. */
										__(
											'Imported %1$d pages, %2$d to go…',
											'living-handbook'
										),
										created,
										response.remaining || 0
									)
								);
							}
							return pass( { job: response.job } );
						}

						// Notes report what needs attention: links that resolved to
						// no page, or an import cut short by the file or tree
						// limit. They go in their own highlighted block above the
						// page list, not lost at the end of it, and before the
						// check for an empty result, since then they are the
						// reason.
						showNotes( notes );
						if ( ! created ) {
							setStatus(
								__(
									'No Markdown pages found.',
									'living-handbook'
								)
							);
							return;
						}
						setStatus(
							sprintf(
								/* translators: %d is the number of pages created from GitHub. */
								_n(
									'Done: created %d GitHub page.',
									'Done: created %d GitHub pages.',
									created,
									'living-handbook'
								),
								created
							)
						);
					} );
			}

			setStatus( __( 'Fetching pages from GitHub…', 'living-handbook' ) );
			return pass( {
				url,
				title: titleValue(),
				handbook: handbookId(),
			} ).catch( ( error ) => setStatus( errorMessage( error ) ) );
		}

		/**
		 * Loads one of the handbooks that ship with the plugin.
		 *
		 * The server loads the chosen one from its bundled folder (or from a
		 * GitHub override, if the living_handbook_app_handbook_url filter sets
		 * one). The button asks for it by key, no URL from the browser.
		 *
		 * @return {Promise<void>} Settles when the import is done.
		 */
		function importApp() {
			const entry = document.getElementById( 'lh-app-entry' );
			const keep = document.getElementById( 'lh-app-keep' );

			setStatus( __( 'Loading the handbook…', 'living-handbook' ) );
			return window.wp
				.apiFetch( {
					path: lhImport.githubPath,
					method: 'POST',
					data: {
						app_handbook: 1,
						handbook: handbookId(),
						app_entry: entry ? entry.value : '',
						// Sent as 0 or 1 rather than a boolean: an unchecked box
						// has to arrive as "untie", not as "nothing said".
						keep_source: ! keep || keep.checked ? 1 : 0,
					},
				} )
				.then( ( response ) => {
					const pages =
						response && response.pages ? response.pages : [];
					// The notes first: when nothing was loaded they are the
					// reason why, and up to 0.75.1 they were dropped in exactly
					// that case.
					showNotes(
						response && response.notes ? response.notes : []
					);
					if ( ! pages.length ) {
						setStatus(
							__( 'No pages were loaded.', 'living-handbook' )
						);
						return;
					}
					pages.forEach( ( page ) => addResult( page, page.title ) );
					setStatus(
						sprintf(
							/* translators: %d is the number of pages loaded. */
							_n(
								'Done: loaded %d page.',
								'Done: loaded %d pages.',
								pages.length,
								'living-handbook'
							),
							pages.length
						)
					);
				} )
				.catch( ( error ) => setStatus( errorMessage( error ) ) );
		}

		/**
		 * Prepares an import: clears the old results, asks for confirmation
		 * when no handbook is chosen, and locks the buttons.
		 *
		 * @return {boolean} False when the import should not start.
		 */
		function begin() {
			if ( results ) {
				results.innerHTML = '';
			}
			if ( notesEl ) {
				notesEl.innerHTML = '';
			}
			if ( ! window.wp.blocks ) {
				setStatus(
					__( 'wp.blocks is not loaded.', 'living-handbook' )
				);
				return false;
			}
			if (
				handbookId() === 0 &&
				// A native dialog on purpose: it is one yes-or-no question that
				// must be answered before anything is created.
				// eslint-disable-next-line no-alert -- see the comment above.
				! window.confirm(
					__(
						'No target handbook is selected. Imported pages stay invisible until you assign them to a handbook. Import anyway?',
						'living-handbook'
					)
				)
			) {
				return false;
			}
			ensureCoreBlocks();
			setBusy( true );
			return true;
		}

		/**
		 * Unlocks the buttons once an import has settled.
		 *
		 * @param {Promise<void>} promise The running import.
		 * @return {void}
		 */
		function run( promise ) {
			Promise.resolve( promise )
				.then( () => setBusy( false ) )
				.catch( ( error ) => {
					setStatus( errorMessage( error ) );
					setBusy( false );
				} );
		}

		if ( pasteBtn ) {
			pasteBtn.addEventListener( 'click', () => {
				if ( begin() ) {
					run( importPaste() );
				}
			} );
		}
		if ( zipBtn ) {
			zipBtn.addEventListener( 'click', () => {
				const file =
					zipField && zipField.files && zipField.files.length
						? zipField.files[ 0 ]
						: null;
				if ( ! file ) {
					setStatus(
						__( 'No ZIP file received.', 'living-handbook' )
					);
					return;
				}
				if ( begin() ) {
					run( importZip( file ) );
				}
			} );
		}
		if ( githubBtn ) {
			githubBtn.addEventListener( 'click', () => {
				const url = trimVal( githubField );
				if ( ! url ) {
					setStatus(
						__( 'No GitHub URL given.', 'living-handbook' )
					);
					return;
				}
				if ( begin() ) {
					run( importGithub( url ) );
				}
			} );
		}
		const appBtn = document.getElementById( 'lh-app-btn' );
		if ( appBtn ) {
			appBtn.addEventListener( 'click', () => {
				if ( begin() ) {
					run( importApp() );
				}
			} );
		}
	} );
} )();
