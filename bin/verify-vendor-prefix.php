<?php
// phpcs:ignoreFile -- Build tooling, run from the shell, never loaded by WordPress.
/**
 * Check that a built plugin's bundled libraries really are prefixed and still work.
 *
 * A prefix that silently did not take is worse than no prefix at all: the build
 * would look fine and the collision it was meant to prevent would still be
 * there. So the build runs this against the staged copy before it zips it, and
 * fails if anything here fails. Two questions are asked. Are the libraries
 * where PHP-Scoper was supposed to put them, and does the plain, unprefixed
 * name stay unclaimed. And do the three libraries actually do their job under
 * the new name: Markdown to HTML, YAML to an array, and a script stripped out
 * of an SVG.
 *
 * Usage: php bin/verify-vendor-prefix.php <path-to-staged-plugin>
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

$root = isset( $argv[1] ) ? rtrim( (string) $argv[1], '/' ) : '';
if ( '' === $root || ! is_dir( $root ) ) {
	fwrite( STDERR, "Usage: php bin/verify-vendor-prefix.php <path-to-staged-plugin>\n" );
	exit( 1 );
}

$autoload = $root . '/vendor/autoload.php';
if ( ! is_readable( $autoload ) ) {
	fwrite( STDERR, "No vendor/autoload.php in {$root}.\n" );
	exit( 1 );
}

// Pretend another plugin got there first. Composer loads each "files" autoload
// (deprecation-contracts' function.php, the polyfill bootstraps) once per
// request, under a key in $GLOBALS['__composer_autoload_files']. A plugin that
// ships the same package under its own name and loads earlier has set the key
// Composer derives from package and path. If this build still used those keys,
// its own scoped files would now be skipped, and the checks below would find
// the scoped functions missing, which is exactly what happened on sites with a
// second Symfony-based plugin.
$installed_json = json_decode( (string) @file_get_contents( $root . '/vendor/composer/installed.json' ), true );
foreach ( (array) ( $installed_json['packages'] ?? array() ) as $package ) {
	foreach ( (array) ( $package['autoload']['files'] ?? array() ) as $path ) {
		$GLOBALS['__composer_autoload_files'][ md5( $package['name'] . ':' . $path ) ] = true;
	}
}

require $autoload;

const PREFIX = 'LivingHandbook\\Vendor\\';

$failures = array();

/**
 * Record a failed check.
 *
 * @param bool   $ok      Whether the check passed.
 * @param string $message What was checked.
 * @return void
 */
function check( bool $ok, string $message ): void {
	global $failures;
	if ( $ok ) {
		echo "  ok    {$message}\n";
		return;
	}
	echo "  FAIL  {$message}\n";
	$failures[] = $message;
}

$converter_name = PREFIX . 'League\\CommonMark\\GithubFlavoredMarkdownConverter';
$rendered_name  = PREFIX . 'League\\CommonMark\\Output\\RenderedContentInterface';
$yaml_name      = PREFIX . 'Symfony\\Component\\Yaml\\Yaml';
$sanitizer_name = PREFIX . 'enshrined\\svgSanitize\\Sanitizer';

// The autoloader is where a half-done prefix shows up first: the files are on
// disk either way, but a classmap built against stale autoload rules holds a
// fraction of them. Report the numbers, so a failure below is one line, not an
// investigation.
$classmap_file = $root . '/vendor/composer/autoload_classmap.php';
$classmap      = is_readable( $classmap_file ) ? (string) file_get_contents( $classmap_file ) : '';
$entries       = substr_count( $classmap, '=>' );
// The classmap is PHP source, so every backslash in a class name is written
// twice. Count what is in the file, not what the name looks like here.
$under_prefix  = substr_count( $classmap, str_replace( '\\', '\\\\', PREFIX ) );

// Stated, not checked: there is no honest threshold for "enough classes", and
// the checks below decide the outcome. This line is what makes a failure
// readable, because a classmap that lost most of its entries says so here.
printf( "Classmap: %d entries, %d of them under %s\n\n", $entries, $under_prefix, PREFIX );

echo "Bundled libraries are prefixed:\n";
check( class_exists( $converter_name ), 'CommonMark converter is under ' . PREFIX );
check( interface_exists( $rendered_name ), 'CommonMark 2.x result interface is under ' . PREFIX );
check( class_exists( $yaml_name ), 'Symfony YAML is under ' . PREFIX );
check( class_exists( $sanitizer_name ), 'SVG sanitizer is under ' . PREFIX );

echo "The plain names stay free for other plugins:\n";
check( ! class_exists( 'League\\CommonMark\\GithubFlavoredMarkdownConverter', false ), 'League\\CommonMark is not claimed' );
check( ! class_exists( 'Symfony\\Component\\Yaml\\Yaml', false ), 'Symfony\\Component\\Yaml is not claimed' );
check( ! class_exists( 'enshrined\\svgSanitize\\Sanitizer', false ), 'enshrined\\svgSanitize is not claimed' );

echo "The libraries still work under the new name:\n";

if ( class_exists( $converter_name ) ) {
	$converter = new $converter_name(
		array(
			'html_input'         => 'allow',
			'allow_unsafe_links' => false,
		)
	);
	$html      = (string) $converter->convert( "# Title\n\n| a | b |\n|---|---|\n| 1 | 2 |\n" )->getContent();
	check(
		str_contains( $html, '<h1>' ) && str_contains( $html, '<table' ),
		'Markdown becomes HTML, GitHub tables included'
	);
}

if ( class_exists( $yaml_name ) ) {
	$parsed = $yaml_name::parse( "nav:\n  - Home: index.md\n" );
	check(
		is_array( $parsed ) && isset( $parsed['nav'][0]['Home'] ),
		'A mkdocs.yml nav block parses'
	);
}

if ( class_exists( $sanitizer_name ) ) {
	$sanitizer = new $sanitizer_name();
	$clean     = $sanitizer->sanitize( '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><rect width="1" height="1"/></svg>' );
	check(
		is_string( $clean ) && ! str_contains( $clean, 'script' ) && str_contains( $clean, 'rect' ),
		'An SVG keeps its shapes and loses its script'
	);
}

// Everything above passed in 0.75.0, and the zip still collided in three
// places. Each was a name the build writes or leaves global after PHP-Scoper had
// done its part, so each gets a check of its own.
echo "Nothing Composer generates claims a shared name:\n";

// The loader's own data, as it is in memory now: the autoloader was required
// above, so its static initializer class is declared. Read from there rather
// than parsed out of the PHP source, so a formatting change in a later Composer
// cannot make the check pass by not matching.
$static_class = '';
foreach ( get_declared_classes() as $declared ) {
	if ( str_starts_with( $declared, 'Composer\\Autoload\\ComposerStaticInit' ) ) {
		$static_class = $declared;
	}
}
check( '' !== $static_class, 'The Composer autoloader is the static, authoritative one' );
$static_classmap = '' !== $static_class && isset( $static_class::$classMap ) ? (array) $static_class::$classMap : array();
$static_files    = '' !== $static_class && isset( $static_class::$files ) ? (array) $static_class::$files : array();
$file_classmap   = is_readable( $classmap_file ) ? (array) require $classmap_file : array();

// The classmap entry Composer adds for its own runtime class. Under the plain
// name it pointed at a file declaring the prefixed class, so another plugin's
// class_exists() loaded the file twice and the second time was fatal.
$unprefixed = array();
foreach ( array_merge( array_keys( $static_classmap ), array_keys( $file_classmap ) ) as $class_name ) {
	if ( ! str_starts_with( (string) $class_name, PREFIX ) ) {
		$unprefixed[] = (string) $class_name;
	}
}
$unprefixed = array_values( array_unique( $unprefixed ) );
check( array() === $unprefixed, 'Every classmap entry is under ' . PREFIX . ( $unprefixed ? ' (not: ' . implode( ', ', $unprefixed ) . ')' : '' ) );
// Asked twice, the way two plugins would. With a stale entry the second ask is
// a fatal "Cannot declare class" that no handler can catch, so it is only put
// when the entry above is gone; otherwise the failure is already on record.
if ( array() === $unprefixed ) {
	check(
		! class_exists( 'Composer\\InstalledVersions' ) && ! class_exists( 'Composer\\InstalledVersions' ),
		'Asking for Composer\\InstalledVersions twice finds nothing and breaks nothing'
	);
}

// The once-per-request keys of the "files" autoloads. Composer derives them
// from package and path only, md5("vendor/package:path"), so they are the same
// in every project, and the first plugin to load a file under such a key
// decides for everybody.
$shared_keys = array();
$vendor_dir  = realpath( $root . '/vendor' );
foreach ( $static_files as $identifier => $path ) {
	$real = realpath( (string) $path );
	if ( false === $real || false === $vendor_dir ) {
		continue;
	}
	$parts = explode( '/', substr( $real, strlen( $vendor_dir ) + 1 ), 3 );
	if ( 3 === count( $parts ) && md5( $parts[0] . '/' . $parts[1] . ':' . $parts[2] ) === (string) $identifier ) {
		$shared_keys[] = $parts[0] . '/' . $parts[1] . '/' . $parts[2];
	}
}
check( array() !== $static_files, 'The libraries\' "files" autoloads are registered' );
check( array() === $shared_keys, 'No "files" autoload uses the identifier every Composer project shares' . ( $shared_keys ? ' (' . implode( ', ', $shared_keys ) . ')' : '' ) );
check(
	function_exists( PREFIX . 'trigger_deprecation' ),
	'The scoped trigger_deprecation() is defined'
);

// The calls PHP-Scoper cannot rewrite: unqualified, inside a namespace. PHP
// looks for them in the current namespace and then in the global one, and the
// prefixed function is in neither.
$unqualified = array();
$iterator    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/vendor', FilesystemIterator::SKIP_DOTS ) );
foreach ( $iterator as $file ) {
	if ( ! $file->isFile() || 'php' !== $file->getExtension() ) {
		continue;
	}
	$source = (string) file_get_contents( $file->getPathname() );
	if ( str_contains( $source, 'trigger_deprecation' ) && 1 === preg_match( '/(?<![\\\\\w>:$\'"])(?<!function )trigger_deprecation\s*\(/', $source ) ) {
		$unqualified[] = substr( $file->getPathname(), strlen( $root ) + 1 );
	}
}
check( array() === $unqualified, 'No library calls trigger_deprecation() unqualified' . ( $unqualified ? ' (' . implode( ', ', $unqualified ) . ')' : '' ) );

if ( class_exists( $yaml_name ) ) {
	// The input that reaches that call in symfony/yaml: the deprecated
	// "!php/const:" key syntax. The notice itself is the library's business;
	// what must not happen is an Error for a function that is not there.
	$error = '';
	set_error_handler( static fn(): bool => true, E_USER_DEPRECATED );
	try {
		$yaml_name::parse( "!php/const:PHP_INT_SIZE: x\n" );
	} catch ( \Error $e ) {
		$error = $e->getMessage();
	} catch ( \Throwable $e ) {
		// A parse exception is the library answering, not a scoping fault.
		unset( $e );
	}
	restore_error_handler();
	check( '' === $error, 'Symfony YAML reaches its deprecation notice without an Error' . ( '' !== $error ? ' (' . $error . ')' : '' ) );
}

echo "Only what runs ships:\n";

// The libraries' own development files. They never run inside WordPress, but
// each one is a file Plugin Check and a host's scanner read, and the test
// fixtures of an SVG sanitizer look like attacks because that is their job.
$dev_files = array();
if ( is_dir( $root . '/vendor/bin' ) ) {
	$dev_files[] = 'vendor/bin';
}
$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $root . '/vendor', FilesystemIterator::SKIP_DOTS ),
	RecursiveIteratorIterator::SELF_FIRST
);
foreach ( $iterator as $item ) {
	$name = $item->getFilename();
	$rel  = substr( $item->getPathname(), strlen( $root ) + 1 );
	if ( $item->isDir() && in_array( $name, array( 'tests', 'Tests', 'docs', '.git', '.github' ), true ) ) {
		$dev_files[] = $rel;
	} elseif ( $item->isFile() && 1 === preg_match( '/\.md$/i', $name ) && 1 !== preg_match( '/^licen[sc]e/i', $name ) ) {
		$dev_files[] = $rel;
	}
}
check( array() === $dev_files, 'No tests, docs, tool binaries or READMEs of the libraries' . ( $dev_files ? ' (' . count( $dev_files ) . ', first: ' . $dev_files[0] . ')' : '' ) );

if ( array() !== $failures ) {
	fwrite( STDERR, "\nThe build is not shippable: " . count( $failures ) . " check(s) failed.\n" );
	exit( 1 );
}

echo "\nAll checks passed.\n";
exit( 0 );
