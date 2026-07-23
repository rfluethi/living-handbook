( function () {
	var i18n = ( window.wp && window.wp.i18n ) ? window.wp.i18n : null;
	var __ = i18n ? i18n.__ : function ( s ) { return s; };
	var _n = i18n ? i18n._n : function ( s, p, n ) { return 1 === n ? s : p; };
	var sprintf = i18n ? i18n.sprintf : function ( fmt ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var i = 0;
		return String( fmt ).replace( /%(\d+\$)?[ds]/g, function () { return args[ i++ ]; } );
	};

	// Compose the "Done" summary from already-pluralised parts, so each count
	// (pages, images, converted links) gets its own singular or plural form.
	function doneSummary( pages, images, links ) {
		// translators: %d is a number of pages.
		var pagesText = sprintf( _n( '%d page', '%d pages', pages, 'living-handbook' ), pages );
		// translators: %d is a number of images.
		var imagesText = sprintf( _n( '%d image', '%d images', images, 'living-handbook' ), images );
		// translators: %d is a number of converted internal links.
		var linksText = sprintf( _n( '%d link converted', '%d links converted', links, 'living-handbook' ), links );
		// translators: %1$s pages, %2$s images, %3$s converted links, each already pluralised.
		return sprintf( __( 'Done: %1$s, %2$s, %3$s.', 'living-handbook' ), pagesText, imagesText, linksText );
	}

	function ready( fn ) {
		if ( window.wp && wp.domReady ) {
			wp.domReady( fn );
		} else if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	function ensureCoreBlocks() {
		if ( ! window.wp || ! wp.blocks || ! wp.blockLibrary || ! wp.blockLibrary.registerCoreBlocks ) {
			return;
		}
		if ( wp.blocks.getBlockType && wp.blocks.getBlockType( 'core/paragraph' ) ) {
			return;
		}
		try {
			wp.blockLibrary.registerCoreBlocks();
		} catch ( e ) {
			if ( window.console ) {
				console.error( e );
			}
		}
	}

	function escapeHtml( value ) {
		return String( ( value === null || value === undefined ) ? '' : value )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' );
	}

	function mermaidBlock( code ) {
		// Encode <, > and & like WordPress does for block attributes, so that a
		// Mermaid arrow (-->) inside the code cannot close the HTML comment.
		var json = JSON.stringify( { code: code } )
			.replace( /</g, '\\u003c' )
			.replace( />/g, '\\u003e' )
			.replace( /&/g, '\\u0026' );
		return '<!-- wp:living-handbook/mermaid ' + json + ' /-->';
	}

	function detailsBlock( node ) {
		var summaryEl = node.querySelector( 'summary' );
		var summary = summaryEl ? summaryEl.textContent : '';
		var clone = node.cloneNode( true );
		var s = clone.querySelector( 'summary' );
		if ( s && s.parentNode ) {
			s.parentNode.removeChild( s );
		}
		var inner = libraryHtmlToMarkup( clone.innerHTML );
		return '<!-- wp:details --><details class="wp-block-details"><summary>' + escapeHtml( summary ) + '</summary>' + inner + '</details><!-- /wp:details -->';
	}

	function libraryHtmlToMarkup( html ) {
		// GitHub task-list checkboxes are <input> elements, which the block
		// paste handler drops. Turn them into ballot symbols first, so the
		// checkbox survives as visible text in the imported page.
		html = String( html ).replace( /<input\b[^>]*type=["']?checkbox["']?[^>]*>/gi, function ( match ) {
			return /\bchecked\b/i.test( match ) ? '☑ ' : '☐ ';
		} );

		var doc = new DOMParser().parseFromString( '<body>' + html + '</body>', 'text/html' );
		var nodes = doc.body ? doc.body.childNodes : [];
		var parts = [];
		var buffer = '';
		function flush() {
			if ( buffer.replace( /\s+/g, '' ) !== '' ) {
				var b = wp.blocks.pasteHandler( { HTML: buffer, plainText: '', mode: 'BLOCKS' } );
				parts.push( ( typeof b === 'string' ) ? b : wp.blocks.serialize( b ) );
			}
			buffer = '';
		}
		Array.prototype.forEach.call( nodes, function ( node ) {
			if ( node.nodeType === 1 && node.tagName === 'DETAILS' ) {
				flush();
				parts.push( detailsBlock( node ) );
				return;
			}
			if ( node.nodeType === 1 && node.tagName === 'PRE' ) {
				var codeEl = node.querySelector( 'code.language-mermaid' );
				if ( codeEl ) {
					flush();
					parts.push( mermaidBlock( codeEl.textContent ) );
					return;
				}
			}
			buffer += ( node.nodeType === 1 ) ? node.outerHTML : ( node.textContent || '' );
		} );
		flush();
		return parts.join( '\n\n' );
	}

	ready( function () {
		var mdField = document.getElementById( 'lh-import-md' );
		var zipField = document.getElementById( 'lh-import-zip' );
		var githubField = document.getElementById( 'lh-import-github' );
		var statusEl = document.getElementById( 'lh-import-status' );
		var results = document.getElementById( 'lh-import-results' );

		var pasteBtn = document.getElementById( 'lh-import-run-paste' );
		var zipBtn = document.getElementById( 'lh-import-run-zip' );
		var githubBtn = document.getElementById( 'lh-import-run-github' );
		var runButtons = [ pasteBtn, zipBtn, githubBtn ];
		if ( ! pasteBtn && ! zipBtn && ! githubBtn ) {
			return;
		}

		ensureCoreBlocks();

		// ARIA tabs for the import source. Which tabs exist depends on the user's
		// capabilities, so the handling stays generic over whatever is rendered.
		( function setupSourceTabs() {
			var tablist = document.querySelector( '.living-handbook-import__tablist' );
			if ( ! tablist ) {
				return;
			}
			var tabs = Array.prototype.slice.call( tablist.querySelectorAll( '[role="tab"]' ) );

			function select( tab ) {
				tabs.forEach( function ( t ) {
					var on = t === tab;
					t.setAttribute( 'aria-selected', on ? 'true' : 'false' );
					t.tabIndex = on ? 0 : -1;
					var panel = document.getElementById( t.getAttribute( 'aria-controls' ) );
					if ( panel ) {
						panel.hidden = ! on;
					}
				} );
			}
			tabs.forEach( function ( tab, idx ) {
				tab.addEventListener( 'click', function () {
					select( tab );
					tab.focus();
				} );
				tab.addEventListener( 'keydown', function ( e ) {
					var i = idx;
					if ( e.key === 'ArrowRight' || e.key === 'ArrowDown' ) {
						i = ( idx + 1 ) % tabs.length;
					} else if ( e.key === 'ArrowLeft' || e.key === 'ArrowUp' ) {
						i = ( idx - 1 + tabs.length ) % tabs.length;
					} else if ( e.key === 'Home' ) {
						i = 0;
					} else if ( e.key === 'End' ) {
						i = tabs.length - 1;
					} else {
						return;
					}
					e.preventDefault();
					select( tabs[ i ] );
					tabs[ i ].focus();
				} );
			} );

			// Bring options and button in step with the tab the markup marks as
			// selected, so the first paint is consistent.
			var current = tablist.querySelector( '[aria-selected="true"]' );
			if ( current ) {
				select( current );
			}
		}() );

		function setStatus( msg ) {
			if ( statusEl ) {
				statusEl.textContent = msg;
			}
		}

		function setBusy( on ) {
			runButtons.forEach( function ( b ) {
				if ( b ) {
					b.disabled = on;
				}
			} );
		}

		function errorMessage( err ) {
			// translators: %s is the error message.
			return sprintf( __( 'Error: %s', 'living-handbook' ), ( err && err.message ) ? err.message : __( 'unknown', 'living-handbook' ) );
		}

		function trimVal( field ) {
			return field ? field.value.replace( /^\s+|\s+$/g, '' ) : '';
		}

		// Each source tab carries its own copy of the shared options, so the values
		// are read from the panel that is currently open.
		function openPanel() {
			return document.querySelector( '.living-handbook-import__panel:not([hidden])' );
		}

		function panelField( selector ) {
			var panel = openPanel();
			return panel ? panel.querySelector( selector ) : null;
		}

		function handbookId() {
			var field = panelField( '.lh-import-handbook' );
			return field ? ( parseInt( field.value, 10 ) || 0 ) : 0;
		}

		function titleValue() {
			return trimVal( panelField( '.lh-import-title' ) );
		}

		function addResult( created, title ) {
			if ( ! results || ! created || ! created.id ) {
				return;
			}
			var li = document.createElement( 'li' );
			var a = document.createElement( 'a' );
			a.href = created.editUrl || '#';
			// translators: %d is the new page's numeric ID, used as a fallback title.
			a.textContent = title || sprintf( __( 'Page %d', 'living-handbook' ), created.id );
			li.appendChild( a );
			results.appendChild( li );
		}

		// Show a page that failed to import, with the reason, instead of letting
		// the total silently count fewer pages.
		function addFailure( title, reason ) {
			if ( ! results ) {
				return;
			}
			var li = document.createElement( 'li' );
			li.className = 'lh-import-failed';
			li.style.color = '#b32d2e';
			li.textContent = ( title || __( 'Imported page', 'living-handbook' ) ) + ' — ' + ( reason || __( 'unknown', 'living-handbook' ) );
			results.appendChild( li );
		}

		function createPage( title, content, transport, slug ) {
			return wp.apiFetch( {
				path: lhImport.createPath,
				method: 'POST',
				data: {
					title: title,
					content: content,
					slug: slug || '',
					handbook: handbookId(),
					transport: transport || {}
				}
			} );
		}

		function finalize( ids ) {
			if ( ! ids.length ) {
				return Promise.resolve( { converted: 0 } );
			}
			return wp.apiFetch( { path: lhImport.finalizePath, method: 'POST', data: { ids: ids } } );
		}

		function importPaste() {
			var md = mdField ? mdField.value : '';
			if ( ! md.replace( /\s+/g, '' ) ) {
				setStatus( __( 'Nothing to import: paste Markdown, choose a ZIP, or enter a GitHub URL.', 'living-handbook' ) );
				return Promise.resolve();
			}
			setStatus( __( 'Converting…', 'living-handbook' ) );
			return wp.apiFetch( { path: lhImport.convertPath, method: 'POST', data: { markdown: md } } ).then( function ( res ) {
				var markup = libraryHtmlToMarkup( res.html || '' );
				var userTitle = titleValue();
				var title = userTitle || res.title || __( 'Imported page', 'living-handbook' );
				setStatus( __( 'Creating draft…', 'living-handbook' ) );
				return createPage( title, markup, res.transport, '' ).then( function ( created ) {
					if ( created && created.error ) {
						addFailure( title, created.error );
						// translators: %s is the error message.
						setStatus( sprintf( __( 'Error: %s', 'living-handbook' ), created.error ) );
						return;
					}
					addResult( created, title );
					return finalize( [ created.id ] ).then( function ( fin ) {
						var conv = ( fin && fin.converted ) || 0;
						// translators: %d is the number of internal links that were converted.
						setStatus( sprintf( _n( 'Done: 1 page, %d link converted.', 'Done: 1 page, %d links converted.', conv, 'living-handbook' ), conv ) );
					} );
				} );
			} ).catch( function ( err ) {
				setStatus( errorMessage( err ) );
			} );
		}

		function importMkdocs( pages, images ) {
			if ( ! pages.length ) {
				setStatus( __( 'No pages found in the mkdocs.yml.', 'living-handbook' ) );
				return Promise.resolve();
			}
			// translators: %d is the number of pages being created.
			setStatus( sprintf( _n( 'Creating %d page…', 'Creating %d pages…', pages.length, 'living-handbook' ), pages.length ) );
			var byPath = {};
			var ids = [];
			var done = 0;
			var total = pages.length;
			var chain = Promise.resolve();
			pages.forEach( function ( spec ) {
				chain = chain.then( function () {
					var markup = spec.synthetic ? '' : libraryHtmlToMarkup( spec.html || '' );
					var parentId = ( spec.parentPath && byPath[ spec.parentPath ] ) ? byPath[ spec.parentPath ] : 0;
					return wp.apiFetch( {
						path: lhImport.createPath,
						method: 'POST',
						data: {
							title: spec.navTitle,
							content: markup,
							slug: spec.slug || '',
							handbook: handbookId(),
							transport: {},
							parent: parentId,
							sourcePath: spec.sourcePath,
							order: spec.order
						}
					} ).then( function ( created ) {
						++done;
						// translators: %1$d is the current page number, %2$d the total number of pages.
						setStatus( sprintf( __( 'Importing page %1$d of %2$d …', 'living-handbook' ), done, total ) );
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
			return chain.then( function () {
				setStatus( __( 'Linking…', 'living-handbook' ) );
				return finalize( ids ).then( function ( fin ) {
					setStatus( doneSummary( ids.length, images || 0, ( fin && fin.converted ) || 0 ) );
				} );
			} );
		}

		function importZip( file ) {
			setStatus( __( 'Uploading ZIP…', 'living-handbook' ) );
			var fd = new FormData();
			fd.append( 'zip', file );
			return wp.apiFetch( { path: lhImport.zipPath, method: 'POST', body: fd } ).then( function ( res ) {
				if ( res && res.mode === 'mkdocs' ) {
					return importMkdocs( res.pages || [], res.images || 0 );
				}
				var files = ( res && res.files ) ? res.files : [];
				if ( ! files.length ) {
					setStatus( __( 'No pages in the ZIP.', 'living-handbook' ) );
					return;
				}
				// translators: %d is the number of draft pages being created.
				setStatus( sprintf( _n( 'Creating %d draft…', 'Creating %d drafts…', files.length, 'living-handbook' ), files.length ) );
				var ids = [];
				var done = 0;
				var total = files.length;
				var chain = Promise.resolve();
				files.forEach( function ( f ) {
					chain = chain.then( function () {
						var markup = libraryHtmlToMarkup( f.html || '' );
						return createPage( f.title, markup, f.transport, f.slug ).then( function ( created ) {
							++done;
							// translators: %1$d is the current page number, %2$d the total number of pages.
							setStatus( sprintf( __( 'Importing page %1$d of %2$d …', 'living-handbook' ), done, total ) );
							if ( created && created.error ) {
								addFailure( f.title, created.error );
								return;
							}
							addResult( created, f.title );
							if ( created && created.id ) {
								ids.push( created.id );
							}
						} );
					} );
				} );
				return chain.then( function () {
					setStatus( __( 'Linking…', 'living-handbook' ) );
					return finalize( ids ).then( function ( fin ) {
						setStatus( doneSummary( ids.length, res.images || 0, ( fin && fin.converted ) || 0 ) );
					} );
				} );
			} ).catch( function ( err ) {
				setStatus( errorMessage( err ) );
			} );
		}

		function importGithub( url ) {
			setStatus( __( 'Fetching pages from GitHub…', 'living-handbook' ) );
			return wp.apiFetch( {
				path: lhImport.githubPath,
				method: 'POST',
				data: { url: url, title: titleValue(), handbook: handbookId() }
			} ).then( function ( res ) {
				var pages = ( res && res.pages ) ? res.pages : [];
				if ( ! pages.length ) {
					setStatus( __( 'No Markdown pages found.', 'living-handbook' ) );
					return;
				}
				pages.forEach( function ( p ) {
					addResult( p, p.title );
				} );
				// A folder import can succeed and still be incomplete, when the
				// repository is too large for one tree response or the file limit
				// was reached. Saying so is the whole point of the note.
				( ( res && res.notes ) ? res.notes : [] ).forEach( function ( note ) {
					var item = document.createElement( 'li' );
					item.textContent = note;
					if ( results ) {
						results.appendChild( item );
					}
				} );
				// translators: %d is the number of pages created from GitHub.
				setStatus( sprintf( _n( 'Done: created %d GitHub page.', 'Done: created %d GitHub pages.', pages.length, 'living-handbook' ), pages.length ) );
			} ).catch( function ( err ) {
				setStatus( errorMessage( err ) );
			} );
		}

		function begin() {
			if ( results ) {
				results.innerHTML = '';
			}
			if ( ! window.wp || ! wp.blocks ) {
				setStatus( __( 'wp.blocks is not loaded.', 'living-handbook' ) );
				return false;
			}
			if ( handbookId() === 0 && ! window.confirm( __( 'No target handbook is selected. Imported pages stay invisible until you assign them to a handbook. Import anyway?', 'living-handbook' ) ) ) {
				return false;
			}
			ensureCoreBlocks();
			setBusy( true );
			return true;
		}

		function run( promise ) {
			Promise.resolve( promise ).then( function () {
				setBusy( false );
			} ).catch( function ( err ) {
				setStatus( errorMessage( err ) );
				setBusy( false );
			} );
		}

		if ( pasteBtn ) {
			pasteBtn.addEventListener( 'click', function () {
				if ( ! begin() ) {
					return;
				}
				run( importPaste() );
			} );
		}
		if ( zipBtn ) {
			zipBtn.addEventListener( 'click', function () {
				var file = ( zipField && zipField.files && zipField.files.length ) ? zipField.files[0] : null;
				if ( ! file ) {
					setStatus( __( 'No ZIP file received.', 'living-handbook' ) );
					return;
				}
				if ( ! begin() ) {
					return;
				}
				run( importZip( file ) );
			} );
		}
		if ( githubBtn ) {
			githubBtn.addEventListener( 'click', function () {
				var url = trimVal( githubField );
				if ( ! url ) {
					setStatus( __( 'No GitHub URL given.', 'living-handbook' ) );
					return;
				}
				if ( ! begin() ) {
					return;
				}
				run( importGithub( url ) );
			} );
		}
		// The app handbook is a GitHub folder import against a fixed URL the
		// server picks by admin language; the button just runs the same path.
		var appBtn = document.getElementById( 'lh-app-btn' );
		if ( appBtn ) {
			appBtn.addEventListener( 'click', function () {
				var url = ( window.lhImport && lhImport.appHandbookUrl ) ? lhImport.appHandbookUrl : '';
				if ( ! url ) {
					setStatus( __( 'The app handbook URL is not configured.', 'living-handbook' ) );
					return;
				}
				if ( ! begin() ) {
					return;
				}
				run( importGithub( url ) );
			} );
		}
	} );
}() );
