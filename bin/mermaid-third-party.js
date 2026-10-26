#!/usr/bin/env node
/*
 * List every package compiled into the bundled mermaid.min.js, with its version
 * and license, and print the license texts. Called by bin/update-mermaid.sh,
 * which writes the output to assets/js/mermaid.THIRD-PARTY.txt.
 *
 * mermaid.min.js is not one library but about seventy: d3, cytoscape, KaTeX,
 * DOMPurify, lodash, chevrotain and their relatives, compiled into one file.
 * The plugin redistributes all of them, and until 0.76.0 it shipped only
 * mermaid's own MIT license. Some of the bundled packages are Apache-2.0, whose
 * section 4 asks for the license text to travel with the code; the MIT, ISC and
 * BSD packages ask for their copyright notice to do the same.
 *
 * The list is read from the release itself, not typed by hand: the source map
 * of mermaid.min.js names the file of every package it compiled in, with the
 * version in the path. mermaid also bundles its own parser package, which
 * brings a second set (chevrotain, langium) that is read from that package's
 * build the same way. Each package is then fetched with `npm pack` (only read,
 * never run) for its license field and its license file.
 *
 * Usage: node bin/mermaid-third-party.js <unpacked mermaid package> <work dir>
 */

'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const { execFileSync } = require( 'child_process' );

const [ packageDir, workDir ] = process.argv.slice( 2 );
if ( ! packageDir || ! workDir ) {
	process.stderr.write( 'Usage: node bin/mermaid-third-party.js <package dir> <work dir>\n' );
	process.exit( 1 );
}

const npm = ( args, cwd ) =>
	execFileSync( 'npm', args, { cwd, encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'pipe' ] } ).trim();

// "node_modules/.pnpm/<name>@<version>[_peer...]/node_modules/<name>/", the
// layout pnpm builds mermaid with. The name is taken from the second part,
// because a scope's slash is written as "+" in the first.
const PNPM = /node_modules\/\.pnpm\/([^/]+)\/node_modules\/((?:@[^/]+\/)?[^/]+)\//;

/**
 * Packages named in a list of source paths.
 *
 * @param {string[]} sources Paths from a source map or from build comments.
 * @param {Set<string>} into Set of "name@version".
 */
function collect( sources, into ) {
	for ( const source of sources ) {
		const match = source.match( PNPM );
		if ( ! match ) {
			continue;
		}
		const store = match[ 1 ].split( '_' )[ 0 ];
		into.add( match[ 2 ] + '@' + store.slice( store.lastIndexOf( '@' ) + 1 ) );
	}
}

/**
 * Every file under a directory.
 *
 * @param {string} dir Directory.
 * @return {string[]} Paths.
 */
function walk( dir ) {
	return fs.readdirSync( dir, { withFileTypes: true } ).flatMap( ( entry ) => {
		const full = path.join( dir, entry.name );
		return entry.isDirectory() ? walk( full ) : [ full ];
	} );
}

/**
 * Fetch and unpack a package, read-only.
 *
 * @param {string} spec "name@version".
 * @return {string} Directory of the unpacked package.
 */
function unpack( spec ) {
	const dir = path.join( workDir, 'third-party', spec.replace( /[@/]/g, '_' ) );
	const unpacked = path.join( dir, 'package' );
	if ( fs.existsSync( unpacked ) ) {
		return unpacked;
	}
	fs.mkdirSync( dir, { recursive: true } );
	const file = npm( [ 'pack', spec, '--silent' ], dir ).split( '\n' ).pop();
	execFileSync( 'tar', [ '-xzf', path.join( dir, file ), '-C', dir ] );
	return unpacked;
}

const manifest = JSON.parse( fs.readFileSync( path.join( packageDir, 'package.json' ), 'utf8' ) );
const packages = new Set();

// 1. mermaid's own bundle.
const map = JSON.parse( fs.readFileSync( path.join( packageDir, 'dist', 'mermaid.min.js.map' ), 'utf8' ) );
collect( map.sources, packages );

// 2. The parser package is a workspace sibling in mermaid's repository, so the
// map names it by a relative path without a version. The version is the one
// mermaid's package.json requires; its own bundled packages are read from its
// build, the source maps and the "// node_modules/..." comments esbuild leaves
// in the unminified chunks.
if ( map.sources.some( ( s ) => s.includes( '/parser/dist/' ) ) ) {
	const range = ( manifest.dependencies || {} )[ '@mermaid-js/parser' ];
	const version = npm( [ 'view', '@mermaid-js/parser@' + range, 'version', '--json' ] );
	const parsed = JSON.parse( version );
	const parserVersion = Array.isArray( parsed ) ? parsed[ 0 ] : parsed;
	packages.add( '@mermaid-js/parser@' + parserVersion );

	const parserDir = unpack( '@mermaid-js/parser@' + parserVersion );
	for ( const file of walk( path.join( parserDir, 'dist', 'chunks', 'mermaid-parser.core' ) ) ) {
		const text = fs.readFileSync( file, 'utf8' );
		if ( file.endsWith( '.map' ) ) {
			collect( JSON.parse( text ).sources, packages );
		} else {
			collect( text.split( '\n' ).filter( ( l ) => l.startsWith( '//' ) ), packages );
		}
	}
}

// 3. vscode-uri arrives prebuilt with webpack and carries path-browserify
// inside; its own map names that one by a webpack path without a version. The
// version is the newest that vscode-uri's declared range allows.
for ( const spec of [ ...packages ] ) {
	if ( ! spec.startsWith( 'vscode-uri@' ) ) {
		continue;
	}
	const dir = unpack( spec );
	const inner = walk( dir ).filter( ( f ) => f.endsWith( '.map' ) ).map( ( f ) => fs.readFileSync( f, 'utf8' ) ).join( '\n' );
	if ( inner.includes( 'node_modules/path-browserify/' ) ) {
		const pkg = JSON.parse( fs.readFileSync( path.join( dir, 'package.json' ), 'utf8' ) );
		const range = ( pkg.devDependencies || {} )[ 'path-browserify' ] || ( pkg.dependencies || {} )[ 'path-browserify' ] || '*';
		const found = JSON.parse( npm( [ 'view', 'path-browserify@' + range, 'version', '--json' ] ) );
		packages.add( 'path-browserify@' + ( Array.isArray( found ) ? found[ found.length - 1 ] : found ) );
	}
}

const LICENSE_FILE = /^(licen[cs]e|copying)(\.(md|txt))?$/i;

const rows = [];
const texts = new Map();
for ( const spec of [ ...packages ].sort( ( a, b ) => a.localeCompare( b ) ) ) {
	const at = spec.lastIndexOf( '@' );
	const name = spec.slice( 0, at );
	const version = spec.slice( at + 1 );
	const dir = unpack( spec );
	const pkg = JSON.parse( fs.readFileSync( path.join( dir, 'package.json' ), 'utf8' ) );
	const files = fs.readdirSync( dir ).filter( ( f ) => LICENSE_FILE.test( f ) ).sort();

	let license = typeof pkg.license === 'string' ? pkg.license : ( pkg.license && pkg.license.type ) || '';
	let text = files.length ? fs.readFileSync( path.join( dir, files[ 0 ] ), 'utf8' ).trim() : '';

	// A package without a license field but with an MIT license file (khroma).
	if ( '' === license && /^(the )?mit license/i.test( text ) ) {
		license = 'MIT';
	}
	// A dual license: the plugin redistributes under the Apache option, which is
	// the one compatible with the plugin's GPL, and ships that text.
	if ( /\bOR\b/.test( license ) && /Apache-2\.0/.test( license ) ) {
		const apache = files.find( ( f ) => /apache/i.test( fs.readFileSync( path.join( dir, f ), 'utf8' ).slice( 0, 200 ) ) );
		if ( apache ) {
			text = fs.readFileSync( path.join( dir, apache ), 'utf8' ).trim();
		}
		license += ', used under Apache-2.0';
	}
	if ( '' === text ) {
		text = 'No license file in the package. License per its package.json: ' + ( license || 'not stated' ) + '.';
	}

	rows.push( { name, version, license: license || 'not stated' } );
	if ( ! texts.has( text ) ) {
		texts.set( text, [] );
	}
	texts.get( text ).push( name + ' ' + version );
}

const out = [];
out.push( 'Third-party software in assets/js/mermaid.min.js' );
out.push( '=================================================' );
out.push( '' );
out.push( 'assets/js/mermaid.min.js is mermaid ' + manifest.version + ' (' + manifest.license + ', see mermaid.LICENSE.txt),' );
out.push( 'taken unchanged from the npm package. It compiles in the packages listed below.' );
out.push( 'Generated by bin/update-mermaid.sh from the source maps of that release; do not' );
out.push( 'edit by hand.' );
out.push( '' );
out.push( 'Packages' );
out.push( '--------' );
out.push( '' );
const width = Math.max( ...rows.map( ( r ) => ( r.name + ' ' + r.version ).length ) ) + 2;
for ( const row of rows ) {
	out.push( ( row.name + ' ' + row.version ).padEnd( width ) + row.license );
}
out.push( '' );
out.push( 'License texts' );
out.push( '-------------' );
for ( const [ text, users ] of texts ) {
	out.push( '' );
	out.push( '=== ' + users.join( ', ' ) );
	out.push( '' );
	out.push( text );
}
out.push( '' );

process.stdout.write( out.join( '\n' ) );
