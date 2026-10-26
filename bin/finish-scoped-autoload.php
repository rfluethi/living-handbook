<?php
// phpcs:ignoreFile -- Build tooling, run from the shell, never loaded by WordPress.
/**
 * Make the regenerated Composer autoloader of a scoped vendor tree its own.
 *
 * `composer dump-autoload` writes two things into a scoped tree that PHP-Scoper
 * never sees, because they are generated after it ran, and both of them shipped
 * in 0.75.0 under names every other Composer project uses as well.
 *
 * 1. Composer always adds 'Composer\InstalledVersions' to the classmap, pointing
 *    at vendor/composer/InstalledVersions.php. PHP-Scoper had already moved the
 *    class in that file to LivingHandbook\Vendor\Composer\InstalledVersions. So
 *    the classmap promised the global name and the file declared the prefixed
 *    one: the first class_exists('Composer\InstalledVersions') from any plugin
 *    loaded the file and still found no such class, and the second loaded it
 *    again and ended in "Cannot declare class". This autoloader is registered
 *    first and authoritative, so it was asked before everyone else's. Nothing in
 *    the bundled libraries uses InstalledVersions, so the entry and the file are
 *    removed rather than renamed.
 *
 * 2. Composer loads a package's "files" (deprecation-contracts' function.php,
 *    the polyfill bootstraps) once per request, keyed by md5(package:path) in
 *    $GLOBALS['__composer_autoload_files']. That key is the same in every
 *    project. When another plugin with symfony/deprecation-contracts loaded
 *    first, its key was already set, and this plugin's scoped function.php was
 *    skipped as "already loaded": LivingHandbook\Vendor\trigger_deprecation()
 *    was never defined. The keys are rewritten to ones derived from the prefix,
 *    so the bookkeeping only ever matches this plugin's own copy.
 *
 * Runs after dump-autoload and before verify-vendor-prefix.php, which checks
 * both. Running it twice changes nothing the second time.
 *
 * Usage: php bin/finish-scoped-autoload.php <path-to-staged-plugin> <prefix>
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

$root   = isset( $argv[1] ) ? rtrim( (string) $argv[1], '/' ) : '';
$prefix = isset( $argv[2] ) ? trim( (string) $argv[2], '\\' ) : '';

if ( '' === $root || '' === $prefix ) {
	fwrite( STDERR, "Usage: php bin/finish-scoped-autoload.php <path-to-staged-plugin> <prefix>\n" );
	exit( 1 );
}

$composer = $root . '/vendor/composer';
$classmap = $composer . '/autoload_classmap.php';
$static   = $composer . '/autoload_static.php';
$files    = $composer . '/autoload_files.php';

foreach ( array( $classmap, $static ) as $required ) {
	if ( ! is_readable( $required ) || ! is_writable( $required ) ) {
		fwrite( STDERR, "Cannot read and write {$required}.\n" );
		exit( 1 );
	}
}

/**
 * Write a generated file back, or stop the build.
 *
 * @param string $file     Path.
 * @param string $contents New contents.
 * @return void
 */
function living_handbook_write( string $file, string $contents ): void {
	if ( false === file_put_contents( $file, $contents ) ) {
		fwrite( STDERR, "Could not write {$file}.\n" );
		exit( 1 );
	}
}

// 1. The unprefixed InstalledVersions entry, in both places Composer lists the
// classmap. The line is matched whole, so a class that only contains the name
// is not touched.
$installed_line = '/^\s*\'Composer\\\\\\\\InstalledVersions\'\s*=>.*\n/m';
$removed        = 0;
foreach ( array( $classmap, $static ) as $file ) {
	$before   = (string) file_get_contents( $file );
	$after    = (string) preg_replace( $installed_line, '', $before, -1, $count );
	$removed += $count;
	if ( $after !== $before ) {
		living_handbook_write( $file, $after );
	}
}
if ( is_file( $composer . '/InstalledVersions.php' ) ) {
	unlink( $composer . '/InstalledVersions.php' );
}

// 2. The "files" identifiers. Composer writes the same 32-character keys into
// autoload_files.php and into the $files array of autoload_static.php; both are
// rewritten with the same mapping, so whichever one the loader reads agrees.
$rewritten = 0;
$static_src = (string) file_get_contents( $static );
if ( 1 === preg_match( '/public static \$files = array \((.*?)\);/s', $static_src, $block ) ) {
	// The new key is derived from the prefix and the file's path, not from the
	// old key, so a second run computes the same key again and changes nothing.
	preg_match_all( '/\'([0-9a-f]{32})\'\s*=>\s*__DIR__\s*\.\s*\'\/\.\.\'\s*\.\s*\'\/([^\']+)\'/', $block[1], $entries, PREG_SET_ORDER );
	$map = array();
	foreach ( $entries as $entry ) {
		$own = md5( $prefix . ':' . $entry[2] );
		if ( $own !== $entry[1] ) {
			$map[ "'" . $entry[1] . "'" ] = "'" . $own . "'";
		}
	}

	if ( array() !== $map ) {
		$targets = array( $static );
		if ( is_readable( $files ) ) {
			$targets[] = $files;
		}
		foreach ( $targets as $file ) {
			$before = (string) file_get_contents( $file );
			$after  = strtr( $before, $map );
			if ( $after !== $before ) {
				living_handbook_write( $file, $after );
			}
		}
		$rewritten = count( $map );
	}
}

printf(
	"Composer runtime entries: %d unprefixed InstalledVersions entr%s removed, %d files identifier(s) rewritten.%s",
	$removed,
	1 === $removed ? 'y' : 'ies',
	$rewritten,
	PHP_EOL
);
exit( 0 );
