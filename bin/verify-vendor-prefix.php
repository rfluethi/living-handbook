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

if ( array() !== $failures ) {
	fwrite( STDERR, "\nThe build is not shippable: " . count( $failures ) . " check(s) failed.\n" );
	exit( 1 );
}

echo "\nAll checks passed.\n";
exit( 0 );
