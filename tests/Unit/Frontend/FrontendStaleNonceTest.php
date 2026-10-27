<?php
/**
 * A page served from a cache with someone else's nonce still filters and
 * searches.
 *
 * A page cache can hand the page of a signed-in reader to a guest, nonce and
 * all. Core refuses that nonce with 403 rest_cookie_invalid_nonce before the
 * endpoint runs, and up to 0.75.1 the filters failed on such a page and the
 * quick search closed without a word.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;

/**
 * Assets/frontend.js.
 */
final class FrontendStaleNonceTest extends TestCase {

	/**
	 * The script, as shipped.
	 *
	 * @return string Absolute path.
	 */
	private function script(): string {
		return dirname( __DIR__, 3 ) . '/assets/frontend.js';
	}

	/**
	 * Run the harness in node and decode what it prints.
	 *
	 * @param string $scenario Harness scenario.
	 * @return array<string, mixed>
	 */
	private function run_harness( string $scenario ): array {
		$node = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec -- test helper.
		if ( '' === $node ) {
			$this->markTestSkipped( 'node is not installed.' );
		}

		$harness = dirname( __DIR__, 2 ) . '/js/frontend-stale-nonce.js';
		$output  = (string) shell_exec( escapeshellarg( $node ) . ' ' . escapeshellarg( $harness ) . ' ' . escapeshellarg( $this->script() ) . ' ' . escapeshellarg( $scenario ) . ' 2>&1' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec -- test helper.
		$decoded = json_decode( $output, true );
		$this->assertIsArray( $decoded, 'Output: ' . $output );

		return $decoded;
	}

	/**
	 * Every request to the plugin's endpoints goes through the one function
	 * that knows how to recover from a refused nonce.
	 *
	 * @return void
	 */
	public function test_every_request_goes_through_rest_fetch(): void {
		$script = (string) file_get_contents( $this->script() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a plugin file.

		$this->assertSame( 1, preg_match_all( '/(?<![A-Za-z_])fetch\(/', $script ), 'Only restFetch() calls fetch().' );
		$this->assertSame( 3, substr_count( $script, 'restFetch( settings.' ), 'Filter, quick search and feedback use restFetch().' );
	}

	/**
	 * The refused request is asked again once without the nonce, and the page
	 * sends none from then on.
	 *
	 * @return void
	 */
	public function test_a_refused_nonce_is_dropped_and_the_request_asked_again(): void {
		$out = $this->run_harness( 'stale' );

		$this->assertSame(
			array(
				array(
					'path'  => 'search',
					'nonce' => 'stale',
				),
				array(
					'path'  => 'search',
					'nonce' => null,
				),
				array(
					'path'  => 'filter',
					'nonce' => null,
				),
			),
			$out['calls']
		);
		$this->assertSame( array( 'Linux' ), $out['quick'], 'The quick search shows the answer to the second request.' );
		$this->assertSame( '<p>filtered</p>', $out['list'], 'The filter works on the same page.' );
	}

	/**
	 * A quick search that fails says so, in the list and to a screen reader.
	 *
	 * @return void
	 */
	public function test_a_failed_quick_search_says_so(): void {
		$out = $this->run_harness( 'error' );

		$this->assertSame( array( 'Search failed.' ), $out['results'] );
		$this->assertSame( 'Search failed.', $out['status'] );
		$this->assertFalse( $out['hidden'] );
	}
}
