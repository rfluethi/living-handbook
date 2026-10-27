<?php
/**
 * The build removes the libraries' command-line tools, and the check after it
 * insists on that.
 *
 * Three of them live outside vendor/bin and so stayed in the zip up to 0.76.0:
 * the SVG sanitizer's svg-scanner.php, a PHP file without an ABSPATH guard and
 * reachable over the web inside wp-content/plugins, and Symfony YAML's lint
 * script with its command class. Plugin Check reported the first. The build
 * cannot run in CI, so this holds the two scripts to each other.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * bin/build.sh and bin/verify-vendor-prefix.php.
 */
final class BuildVendorCleanupTest extends TestCase {

	private const TOOLS = array(
		'vendor/enshrined/svg-sanitize/src/svg-scanner.php',
		'vendor/symfony/yaml/Resources/bin',
		'vendor/symfony/yaml/Command',
	);

	/**
	 * A file of the repository.
	 *
	 * @param string $path Path from the plugin root.
	 * @return string
	 */
	private function read( string $path ): string {
		$file = dirname( __DIR__, 2 ) . '/' . $path;
		$this->assertFileExists( $file );
		return (string) file_get_contents( $file );
	}

	/**
	 * Each tool is removed by the build and refused by the check.
	 *
	 * @return void
	 */
	public function test_the_build_removes_the_tools_and_the_check_refuses_them(): void {
		$build  = $this->read( 'bin/build.sh' );
		$verify = $this->read( 'bin/verify-vendor-prefix.php' );

		foreach ( self::TOOLS as $tool ) {
			$this->assertMatchesRegularExpression( '#rm -(?:rf|f) [^\n]*"\$\{dest\}/' . preg_quote( $tool, '#' ) . '"#', $build, $tool . ' is not removed by the build.' );
			$this->assertStringContainsString( "'" . $tool . "'", $verify, $tool . ' is not checked after the build.' );
		}
	}
}
