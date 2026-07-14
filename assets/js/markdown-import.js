( function () {
	var i18n = ( window.wp && window.wp.i18n ) ? window.wp.i18n : null;
	var __ = i18n ? i18n.__ : function ( s ) { return s; };
	var sprintf = i18n ? i18n.sprintf : function ( fmt ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var i = 0;
		return String( fmt ).replace( /%(\d+\$)?[ds]/g, function () { return args[ i++ ]; } );
	};

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
		var runBtn = document.getElementById( 'lh-import-run' );
		var mdField = document.getElementById( 'lh-import-md' );
		var zipField = document.getElementById( 'lh-import-zip' );
		var titleField = document.getElementById( 'lh-import-title' );
		var handbookField = document.getElementById( 'lh-import-handbook' );
		var githubField = document.getElementById( 'lh-import-github' );
		var statusEl = document.getElementById( 'lh-import-status' );
		var results = document.getElementById( 'lh-import-results' );
		if ( ! runBtn ) {
			return;
		}

		ensureCoreBlocks();

		function setStatus( msg ) {
			if ( statusEl ) {
				statusEl.textContent = msg;
			}
		}

		function errorMessage( err ) {
			return sprintf( __( 'Error: %s', 'living-handbook' ), ( err && err.message ) ? err.message : __( 'unknown', 'living-handbook' ) );
		}

		function trimVal( field ) {
			return field ? field.value.replace( /^\s+|\s+$/g, '' ) : '';
		}

		function handbookId() {
			return handbookField ? ( parseInt( handbookField.value, 10 ) || 0 ) : 0;
		}

		function addResult( created, title ) {
			if ( ! results || ! created || ! created.id ) {
				return;
			}
			var li = document.createElement( 'li' );
			var a = document.createElement( 'a' );
			a.href = created.editUrl || '#';
			a.textContent = title || sprintf( __( 'Page %d', 'living-handbook' ), created.id );
			li.appendChild( a );
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
				return;
			}
			setStatus( __( 'Converting…', 'living-handbook' ) );
			wp.apiFetch( { path: lhImport.convertPath, method: 'POST', data: { markdown: md } } ).then( function ( res ) {
				if ( res && res.error ) {
					setStatus( res.error );
					return;
				}
				var markup = libraryHtmlToMarkup( res.html || '' );
				var userTitle = trimVal( titleField );
				var title = userTitle || res.title || __( 'Imported page', 'living-handbook' );
				setStatus( __( 'Creating draft…', 'living-handbook' ) );
				return createPage( title, markup, res.transport, '' ).then( function ( created ) {
					if ( created && created.error ) {
						setStatus( sprintf( __( 'Error: %s', 'living-handbook' ), created.error ) );
						return;
					}
					addResult( created, title );
					return finalize( [ created.id ] ).then( function ( fin ) {
						setStatus( sprintf( __( 'Done: 1 page, %d links converted.', 'living-handbook' ), ( fin && fin.converted ) || 0 ) );
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
			setStatus( sprintf( __( 'Creating %d page(s)…', 'living-handbook' ), pages.length ) );
			var byPath = {};
			var ids = [];
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
						if ( created && created.error ) {
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
					setStatus( sprintf( __( 'Done: %1$d page(s), %2$d image(s), %3$d links converted.', 'living-handbook' ), ids.length, images || 0, ( fin && fin.converted ) || 0 ) );
				} );
			} );
		}

		function importZip( file ) {
			setStatus( __( 'Uploading ZIP…', 'living-handbook' ) );
			var fd = new FormData();
			fd.append( 'zip', file );
			wp.apiFetch( { path: lhImport.zipPath, method: 'POST', body: fd } ).then( function ( res ) {
				if ( res && res.error ) {
					setStatus( res.error );
					return;
				}
				if ( res && res.mode === 'mkdocs' ) {
					return importMkdocs( res.pages || [], res.images || 0 );
				}
				var files = ( res && res.files ) ? res.files : [];
				if ( ! files.length ) {
					setStatus( __( 'No pages in the ZIP.', 'living-handbook' ) );
					return;
				}
				setStatus( sprintf( __( 'Creating %d drafts…', 'living-handbook' ), files.length ) );
				var ids = [];
				var chain = Promise.resolve();
				files.forEach( function ( f ) {
					chain = chain.then( function () {
						var markup = libraryHtmlToMarkup( f.html || '' );
						return createPage( f.title, markup, f.transport, f.slug ).then( function ( created ) {
							if ( created && created.error ) {
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
						setStatus( sprintf( __( 'Done: %1$d page(s), %2$d image(s), %3$d links converted.', 'living-handbook' ), ids.length, res.images || 0, ( fin && fin.converted ) || 0 ) );
					} );
				} );
			} ).catch( function ( err ) {
				setStatus( errorMessage( err ) );
			} );
		}

		function importGithub( url ) {
			setStatus( __( 'Fetching page(s) from GitHub…', 'living-handbook' ) );
			wp.apiFetch( {
				path: lhImport.githubPath,
				method: 'POST',
				data: { url: url, title: trimVal( titleField ), handbook: handbookId() }
			} ).then( function ( res ) {
				if ( res && res.error ) {
					setStatus( res.error );
					return;
				}
				var pages = ( res && res.pages ) ? res.pages : [];
				if ( ! pages.length ) {
					setStatus( __( 'No Markdown pages found.', 'living-handbook' ) );
					return;
				}
				pages.forEach( function ( p ) {
					addResult( p, p.title );
				} );
				setStatus( sprintf( __( 'Done: created %d GitHub page(s).', 'living-handbook' ), pages.length ) );
			} ).catch( function ( err ) {
				setStatus( errorMessage( err ) );
			} );
		}

		runBtn.addEventListener( 'click', function () {
			if ( results ) {
				results.innerHTML = '';
			}
			if ( ! window.wp || ! wp.blocks ) {
				setStatus( __( 'wp.blocks is not loaded.', 'living-handbook' ) );
				return;
			}
			ensureCoreBlocks();
			var githubUrl = trimVal( githubField );
			var file = ( zipField && zipField.files && zipField.files.length ) ? zipField.files[0] : null;
			if ( githubUrl ) {
				importGithub( githubUrl );
			} else if ( file ) {
				importZip( file );
			} else {
				importPaste();
			}
		} );
	} );
}() );
