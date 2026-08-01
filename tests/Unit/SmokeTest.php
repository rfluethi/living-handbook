<?php
/**
 * Unit test that proves the toolchain runs without WordPress and keeps the
 * version number identical in every place that declares it.
 *
 * The previous version of this test compared a string literal against a regular
 * expression, so it could never fail. The check now reads the real files, which
 * makes the version consistency that bin/check-and-build.sh enforces part of the
 * test suite as well.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Version consistency across the plugin header, the constant, readme.txt and the changelog.
 */
final class SmokeTest extends TestCase {

	/**
	 * Read a file from the plugin root.
	 *
	 * @param string $relative Path relative to the plugin root.
	 * @return string
	 */
	private function read_plugin_file( string $relative ): string {
		$path = dirname( __DIR__, 2 ) . '/' . $relative;
		$this->assertFileExists( $path, $relative . ' is missing.' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A unit test runs without WordPress, so WP_Filesystem is not available.
		$contents = file_get_contents( $path );
		$this->assertIsString( $contents, $relative . ' could not be read.' );
		return (string) $contents;
	}

	/**
	 * Return the first capture group of a pattern, failing when it does not match.
	 *
	 * @param string $pattern Regular expression with one capture group.
	 * @param string $subject Text to search.
	 * @param string $label   What is being looked for, used in the failure message.
	 * @return string
	 */
	private function capture( string $pattern, string $subject, string $label ): string {
		$matches = array();
		$this->assertSame( 1, preg_match( $pattern, $subject, $matches ), 'Could not find ' . $label . '.' );
		return (string) $matches[1];
	}

	/**
	 * The plugin header carries a semantic version number.
	 *
	 * @return void
	 */
	public function test_version_is_semver(): void {
		$version = $this->capture(
			'/^\s*\*\s*Version:\s*([0-9][0-9.]*)/m',
			$this->read_plugin_file( 'living-handbook.php' ),
			'the Version header'
		);
		$this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', $version );
	}

	/**
	 * Header, constant, readme.txt and changelog all declare the same version.
	 *
	 * @return void
	 */
	public function test_version_is_the_same_everywhere(): void {
		$plugin_file = $this->read_plugin_file( 'living-handbook.php' );

		$header   = $this->capture( '/^\s*\*\s*Version:\s*([0-9][0-9.]*)/m', $plugin_file, 'the Version header' );
		$constant = $this->capture(
			"/define\(\s*'LIVING_HANDBOOK_VERSION',\s*'([0-9][0-9.]*)'\s*\)/",
			$plugin_file,
			'the LIVING_HANDBOOK_VERSION constant'
		);
		$stable    = $this->capture( '/^Stable tag:\s*([0-9][0-9.]*)/m', $this->read_plugin_file( 'readme.txt' ), 'the stable tag' );
		$changelog = $this->capture( '/^=\s*([0-9][0-9.]*)\s*=/m', $this->read_plugin_file( 'CHANGELOG.md' ), 'the newest changelog entry' );

		$this->assertSame( $header, $constant, 'The constant does not match the plugin header.' );
		$this->assertSame( $header, $stable, 'The stable tag does not match the plugin header.' );
		$this->assertSame( $header, $changelog, 'The newest changelog entry does not match the plugin header.' );
	}
}
