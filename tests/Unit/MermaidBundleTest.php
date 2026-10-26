<?php
/**
 * The bundled mermaid.js is a known release, recorded, and ships its licenses.
 *
 * 0.75.0 shipped mermaid 11.16.0, whose bundled KaTeX carries a published
 * vulnerability (GHSA-238p-pmpm-9mq7, fixed in mermaid 11.17.2). Nothing in the
 * repository said which version the 3.5 MB file was, so nothing could notice:
 * the only way to tell was to search the minified code for a version string.
 * And the file compiles in some seventy other packages, among them Apache-2.0
 * ones, while the plugin shipped only mermaid's own MIT license.
 *
 * bin/update-mermaid.sh is now the one way the file changes. It records version
 * and hash in assets/js/mermaid.version.txt and writes the third-party licenses.
 * These tests make a hand-copied bundle, or one older than the fix, fail.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * assets/js/mermaid.*
 */
final class MermaidBundleTest extends TestCase {

	/**
	 * The first mermaid release with the KaTeX fix.
	 */
	private const MINIMUM = '11.17.2';

	/**
	 * Absolute path of a file in assets/js/.
	 *
	 * @param string $name File name.
	 * @return string
	 */
	private function asset( string $name ): string {
		$path = dirname( __DIR__, 2 ) . '/assets/js/' . $name;
		$this->assertFileExists( $path );

		return $path;
	}

	/**
	 * The record of what the bundle is.
	 *
	 * @return array{version: string, sha256: string}
	 */
	private function record(): array {
		$text = (string) file_get_contents( $this->asset( 'mermaid.version.txt' ) );

		$this->assertMatchesRegularExpression( '/^mermaid (\d+\.\d+\.\d+)$/m', $text );
		$this->assertMatchesRegularExpression( '/^sha256 of assets\/js\/mermaid\.min\.js: ([0-9a-f]{64})$/m', $text );
		preg_match( '/^mermaid (\d+\.\d+\.\d+)$/m', $text, $version );
		preg_match( '/: ([0-9a-f]{64})$/m', $text, $sha );

		return array(
			'version' => $version[1],
			'sha256'  => $sha[1],
		);
	}

	/**
	 * The file is the one the record describes, byte for byte.
	 *
	 * @return void
	 */
	public function test_the_bundle_is_the_recorded_release(): void {
		$record = $this->record();

		$this->assertSame(
			$record['sha256'],
			hash_file( 'sha256', $this->asset( 'mermaid.min.js' ) ),
			'assets/js/mermaid.min.js is not the file bin/update-mermaid.sh recorded. Update it with that script.'
		);

		// The version is also in the code itself; the two must agree, so the
		// record cannot describe a different file than the hash does.
		$this->assertStringContainsString( '"' . $record['version'] . '"', (string) file_get_contents( $this->asset( 'mermaid.min.js' ) ) );
	}

	/**
	 * The release carries the fix for the KaTeX advisory.
	 *
	 * @return void
	 */
	public function test_the_bundle_is_not_older_than_the_security_fix(): void {
		$version = $this->record()['version'];

		$this->assertTrue(
			version_compare( $version, self::MINIMUM, '>=' ),
			'mermaid ' . $version . ' is affected by GHSA-238p-pmpm-9mq7; ' . self::MINIMUM . ' is the first fixed release.'
		);
	}

	/**
	 * The third-party licenses ship next to the bundle, for the same release,
	 * with the full Apache text for the packages that need it.
	 *
	 * @return void
	 */
	public function test_the_third_party_licenses_ship_with_the_bundle(): void {
		$version = $this->record()['version'];
		$text    = (string) file_get_contents( $this->asset( 'mermaid.THIRD-PARTY.txt' ) );

		$this->assertStringContainsString( 'is mermaid ' . $version . ' ', $text, 'The license list is for another mermaid release.' );

		// KaTeX is the package the advisory was about, DOMPurify the one that
		// keeps diagrams from carrying script; both must be accounted for.
		foreach ( array( 'katex ', 'dompurify ', 'chevrotain ', 'd3 ' ) as $package ) {
			$this->assertMatchesRegularExpression( '/^' . preg_quote( $package, '/' ) . '\d/m', $text, $package . 'is missing.' );
		}

		$this->assertMatchesRegularExpression( '/^chevrotain \S+\s+Apache-2\.0$/m', $text );
		$this->assertStringContainsString( 'TERMS AND CONDITIONS FOR USE, REPRODUCTION, AND DISTRIBUTION', $text, 'Apache-2.0 asks for its full text.' );
		$this->assertStringContainsString( 'END OF TERMS AND CONDITIONS', $text );

		$this->assertStringStartsWith( 'The MIT License', trim( (string) file_get_contents( $this->asset( 'mermaid.LICENSE.txt' ) ) ) );
	}

	/**
	 * The build ships assets/ whole, so the license files reach the zip without
	 * a line of their own. If that ever changes to a list, they must be on it.
	 *
	 * @return void
	 */
	public function test_the_build_ships_the_license_files(): void {
		$build = (string) file_get_contents( dirname( __DIR__, 2 ) . '/bin/build.sh' );

		$this->assertStringContainsString( 'cp -R assets "$dest"/', $build );
	}
}
