<?php
// phpcs:ignoreFile -- Build tooling, run from the shell, never loaded by WordPress.
/**
 * Move the Composer autoload rules of a scoped vendor tree under the prefix.
 *
 * PHP-Scoper rewrites the classes; the rules that say where those classes live
 * are in vendor/composer/installed.json, and they still name the old namespace.
 * Composer refuses to put a class in the classmap when it does not match the
 * PSR-4 rule of its package, so regenerating the autoloader over a scoped tree
 * with stale rules quietly drops nearly everything: the classes are on disk,
 * the classmap has a fraction of them, and nothing loads. Observed with
 * Composer 2.9.7 on PHP 8.5, where 413 classes became 68.
 *
 * PHP-Scoper does patch this file in some versions and environments. Rather
 * than depend on that, the build states the requirement itself: every psr-4 and
 * psr-0 rule carries the prefix afterwards, whoever put it there. Running this
 * twice changes nothing the second time.
 *
 * Usage: php bin/prefix-autoload-rules.php <path-to-staged-plugin> <prefix>
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
	fwrite( STDERR, "Usage: php bin/prefix-autoload-rules.php <path-to-staged-plugin> <prefix>\n" );
	exit( 1 );
}

$file = $root . '/vendor/composer/installed.json';
if ( ! is_readable( $file ) || ! is_writable( $file ) ) {
	fwrite( STDERR, "Cannot read and write {$file}.\n" );
	exit( 1 );
}

$installed = json_decode( (string) file_get_contents( $file ), true );
if ( ! is_array( $installed ) || ! isset( $installed['packages'] ) || ! is_array( $installed['packages'] ) ) {
	fwrite( STDERR, "{$file} is not a package list this build understands.\n" );
	exit( 1 );
}

$with     = $prefix . '\\';
$changed  = 0;
$already  = 0;
$packages = $installed['packages'];

foreach ( $packages as $index => $package ) {
	if ( ! isset( $package['autoload'] ) || ! is_array( $package['autoload'] ) ) {
		continue;
	}

	foreach ( array( 'psr-4', 'psr-0' ) as $standard ) {
		if ( ! isset( $package['autoload'][ $standard ] ) || ! is_array( $package['autoload'][ $standard ] ) ) {
			continue;
		}

		$moved = array();
		foreach ( $package['autoload'][ $standard ] as $namespace => $paths ) {
			$namespace = (string) $namespace;

			// A rule for the global namespace has no name to prefix.
			if ( '' === $namespace ) {
				$moved[ $namespace ] = $paths;
				continue;
			}

			if ( str_starts_with( $namespace, $with ) ) {
				$moved[ $namespace ] = $paths;
				++$already;
				continue;
			}

			$moved[ $with . $namespace ] = $paths;
			++$changed;
		}

		$packages[ $index ]['autoload'][ $standard ] = $moved;
	}
}

$installed['packages'] = $packages;

$json = json_encode( $installed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
if ( ! is_string( $json ) ) {
	fwrite( STDERR, "Could not write the package list back.\n" );
	exit( 1 );
}

if ( false === file_put_contents( $file, $json . "\n" ) ) {
	fwrite( STDERR, "Could not write {$file}.\n" );
	exit( 1 );
}

if ( 0 === $changed && 0 === $already ) {
	fwrite( STDERR, "No autoload rules found at all. That is not a vendor tree this build can ship.\n" );
	exit( 1 );
}

printf(
	"Autoload rules under %s: %d moved, %d already there.%s",
	$with,
	$changed,
	$already,
	PHP_EOL
);
exit( 0 );
