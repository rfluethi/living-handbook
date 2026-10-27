<?php
/**
 * What the front-end script does with markup the server already prepared.
 *
 * assets/frontend.js runs on the site and in the static export, where it gets
 * pages the PHP side has finished more than on the site. These tests pin the
 * places where the script has to respect that, by reading the script itself,
 * as FrontendNonceTest does: there is no browser in this test suite.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use WP_UnitTestCase;

/**
 * Assets/frontend.js, read as a file.
 */
final class FrontendScriptTest extends WP_UnitTestCase {

	/**
	 * The script, as shipped.
	 *
	 * @return string
	 */
	private function script(): string {
		return (string) file_get_contents( LIVING_HANDBOOK_DIR . 'assets/frontend.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a plugin file.
	}

	/**
	 * The body of one named function in the script.
	 *
	 * @param string $name Function name.
	 * @return string
	 */
	private function function_body( string $name ): string {
		$script = $this->script();
		$start  = strpos( $script, 'function ' . $name . '(' );
		$this->assertNotFalse( $start, $name . '() is missing.' );
		$next = strpos( $script, "\n\tfunction ", (int) $start + 1 );

		return substr( $script, (int) $start, false === $next ? null : $next - (int) $start );
	}

	/**
	 * A table of contents the server filled is not filled a second time.
	 *
	 * The static export writes the list into the file (SiteRenderer::fill_toc())
	 * and ships this script with it. Up to 0.75.1 the script appended its own
	 * entries below the ones already there, so every exported page listed its
	 * headings twice. The check for existing entries has to come before the
	 * first entry is built.
	 *
	 * @return void
	 */
	public function test_a_filled_table_of_contents_is_left_as_it_is(): void {
		$body = $this->function_body( 'buildToc' );

		$guard = strpos( $body, "const filled = list.querySelectorAll( 'a[href^=\"#\"]' );\n\t\t\tif ( filled.length ) {" );
		$build = strpos( $body, 'tocItem( heading )' );

		$this->assertNotFalse( $guard, 'buildToc() does not look for entries the server wrote.' );
		$this->assertNotFalse( $build );
		$this->assertLessThan( $build, $guard, 'The check has to come before the first entry is built.' );
	}
}
