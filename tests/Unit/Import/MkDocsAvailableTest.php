<?php
/**
 * The MkDocs import finds its YAML reader on its own.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Unit\Import;

use PHPUnit\Framework\TestCase;

/**
 * MkDocsImport::available() in a process that has not loaded the bundled
 * libraries yet.
 *
 * The bundled libraries are loaded on demand, and this test process has
 * Composer's autoloader already, so the question cannot be asked here: it is
 * asked in a fresh PHP process that knows only the plugin's own classes.
 */
final class MkDocsAvailableTest extends TestCase {

	/**
	 * Without anybody having loaded vendor/ first, available() still says yes
	 * when the YAML reader is installed. Up to 0.75.1 it said yes only when the
	 * Markdown converter had happened to load vendor/ before it was asked.
	 *
	 * @return void
	 */
	public function test_available_loads_the_bundled_libraries_itself(): void {
		$root = dirname( __DIR__, 3 );
		$this->assertFileExists( $root . '/vendor/symfony/yaml/Yaml.php', 'precondition: the YAML reader is installed' );

		$script = (string) tempnam( sys_get_temp_dir(), 'lh-mkdocs-test' );
		$code   = '<?php' . "\n"
			. 'define( "ABSPATH", ' . var_export( $root . '/', true ) . ' );' . "\n" // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- Writes a literal into the generated script.
			. 'define( "LIVING_HANDBOOK_DIR", ' . var_export( $root . '/', true ) . ' );' . "\n" // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- See above.
			. 'spl_autoload_register( static function ( $name ) {' . "\n"
			. '	if ( 0 === strpos( $name, "LivingHandbook\\\\" ) ) {' . "\n"
			. '		$file = LIVING_HANDBOOK_DIR . "src/" . str_replace( "\\\\", "/", substr( $name, 15 ) ) . ".php";' . "\n"
			. '		if ( is_readable( $file ) ) { require $file; }' . "\n"
			. '	}' . "\n"
			. '} );' . "\n"
			. 'echo LivingHandbook\\Import\\MkDocsImport::available() ? "yes" : "no";' . "\n";
		file_put_contents( $script, $code ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- A throwaway script for the child process.

		$output = array();
		$status = 0;
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $script ) . ' 2>&1', $output, $status );
		unlink( $script ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit tests run without WordPress.

		$this->assertSame( 0, $status, implode( "\n", $output ) );
		$this->assertSame( 'yes', implode( '', $output ) );
	}
}
