<?php
/**
 * Every shipped PHP file stops when it is called directly, the way Plugin
 * Check can see it.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Plugin Check reads the top-level statements of a file for the guard. In a
 * file with a namespace every statement sits inside the namespace, so it
 * falls back to the first 50 lines. src/Plugin.php grew past that with its
 * imports in 0.81.0 and failed the check in CI, though the guard was there.
 */
final class DirectAccessGuardTest extends TestCase {

	/**
	 * The guard is in the first 50 lines of every PHP file the ZIP ships.
	 *
	 * @return void
	 */
	public function test_every_file_has_the_guard_early(): void {
		$root  = dirname( __DIR__, 2 );
		$files = array( $root . '/living-handbook.php', $root . '/uninstall.php' );
		foreach ( array( 'src', 'blocks', 'templates' ) as $dir ) {
			if ( ! is_dir( $root . '/' . $dir ) ) {
				continue;
			}
			$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root . '/' . $dir, \FilesystemIterator::SKIP_DOTS ) );
			foreach ( $iterator as $file ) {
				if ( $file instanceof \SplFileInfo && 'php' === $file->getExtension() ) {
					$files[] = $file->getPathname();
				}
			}
		}
		$this->assertGreaterThan( 50, count( $files ), 'The scan found the source.' );

		$late = array();
		foreach ( $files as $path ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A unit test runs without WordPress.
			$head = implode( "\n", array_slice( explode( "\n", (string) file_get_contents( $path ) ), 0, 50 ) );
			if ( 1 !== preg_match( "/defined\\(\\s*'(ABSPATH|WPINC|WP_UNINSTALL_PLUGIN)'\\s*\\)/", $head ) ) {
				$late[] = substr( $path, strlen( $root ) + 1 );
			}
		}
		$this->assertSame( array(), $late, 'These files have no guard in their first 50 lines.' );
	}
}
