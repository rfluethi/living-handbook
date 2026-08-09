<?php
/**
 * The hook documentation and the code say the same thing.
 *
 * Both directions of drift cost somebody an afternoon. A hook the docs promise
 * and the code does not have sends a reader looking for something that was never
 * built; a hook the code has and the docs do not mention is an extension point
 * nobody can find, which is the same as not having it. Seven of the fifteen
 * filters were in the second state until 0.60.0, and three announcements were in
 * the first.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * docs/technical/en/hooks.md against src/.
 */
final class HookDocumentationTest extends TestCase {

	/**
	 * The plugin root.
	 *
	 * @return string
	 */
	private function root(): string {
		return dirname( __DIR__, 2 );
	}

	/**
	 * Every hook name the plugin fires.
	 *
	 * Matched across line breaks, because a call whose name sits on its own line
	 * is exactly as real as one that fits on a single line, and an earlier
	 * version of this search missed those and reported a clean result.
	 *
	 * @return array<int, string>
	 */
	private function hooks_in_code(): array {
		$found = array();
		$files = array( $this->root() . '/uninstall.php' );

		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->root() . '/src' ) );
		foreach ( $iterator as $file ) {
			if ( is_object( $file ) && method_exists( $file, 'getExtension' ) && 'php' === $file->getExtension() ) {
				$files[] = (string) $file->getPathname();
			}
		}

		foreach ( $files as $path ) {
			if ( ! is_readable( $path ) ) {
				continue;
			}

			$code = (string) file_get_contents( $path );
			if ( preg_match_all( "/(?:apply_filters|do_action)\s*\(\s*'(living_handbook_[a-z_]+)'/s", $code, $matches ) ) {
				$found = array_merge( $found, $matches[1] );
			}
		}

		$found = array_values( array_unique( $found ) );
		sort( $found );

		return $found;
	}

	/**
	 * Every hook name docs/technical/en/hooks.md mentions.
	 *
	 * @return array<int, string>
	 */
	private function hooks_in_docs(): array {
		$path = $this->root() . '/docs/technical/en/hooks.md';
		$this->assertFileExists( $path );

		preg_match_all( '/living_handbook_[a-z_]+/', (string) file_get_contents( $path ), $matches );

		$found = array_values( array_unique( $matches[0] ) );
		sort( $found );

		return $found;
	}

	/**
	 * Every hook the plugin fires is documented.
	 *
	 * @return void
	 */
	public function test_every_hook_in_the_code_is_documented(): void {
		$missing = array_values( array_diff( $this->hooks_in_code(), $this->hooks_in_docs() ) );

		$this->assertSame(
			array(),
			$missing,
			"These hooks exist but docs/technical/en/hooks.md does not mention them:\n  " . implode( "\n  ", $missing )
		);
	}

	/**
	 * And nothing is documented that the plugin does not fire.
	 *
	 * @return void
	 */
	public function test_nothing_is_documented_that_does_not_exist(): void {
		$phantom = array_values( array_diff( $this->hooks_in_docs(), $this->hooks_in_code() ) );

		$this->assertSame(
			array(),
			$phantom,
			"docs/technical/en/hooks.md names these, and the code does not fire them:\n  " . implode( "\n  ", $phantom )
		);
	}

	/**
	 * Each one has its own section, not just a passing mention somewhere in the
	 * prose. A name inside a sentence is not documentation of that hook.
	 *
	 * @return void
	 */
	public function test_every_hook_has_a_heading_of_its_own(): void {
		$doc      = (string) file_get_contents( $this->root() . '/docs/technical/en/hooks.md' );
		$headless = array();

		foreach ( $this->hooks_in_code() as $hook ) {
			if ( ! str_contains( $doc, '### `' . $hook . '`' ) ) {
				$headless[] = $hook;
			}
		}

		$this->assertSame(
			array(),
			$headless,
			"These hooks are mentioned but have no section of their own:\n  " . implode( "\n  ", $headless )
		);
	}
}
