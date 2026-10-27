<?php
/**
 * An import that loads no pages still shows the notes that say why.
 *
 * Reloading the app handbook and the GitHub import both answer with pages and
 * notes. When no page came back, both returned with "No pages were loaded."
 * or "No Markdown pages found." before the notes were shown, and up to 0.75.1
 * the one sentence that explained the empty result was dropped.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Unit\Import;

use PHPUnit\Framework\TestCase;

/**
 * Assets/js/markdown-import.js.
 */
final class ImportNotesShownTest extends TestCase {

	/**
	 * The two import sources and the status each reports for an empty result.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function sources(): array {
		return array(
			'app handbook reload' => array( 'app', 'No pages were loaded.' ),
			'GitHub import'       => array( 'github', 'No Markdown pages found.' ),
		);
	}

	/**
	 * Run the script in node, answer with no pages and one note, and look at
	 * what the screen shows.
	 *
	 * @dataProvider sources
	 *
	 * @param string $source Import source the harness presses the button of.
	 * @param string $status The status line for an empty result.
	 * @return void
	 */
	public function test_notes_are_shown_when_no_page_was_loaded( string $source, string $status ): void {
		$node = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec -- test helper.
		if ( '' === $node ) {
			$this->markTestSkipped( 'node is not installed.' );
		}

		$script  = dirname( __DIR__, 3 ) . '/assets/js/markdown-import.js';
		$harness = dirname( __DIR__, 2 ) . '/js/markdown-import-notes.js';
		$output  = (string) shell_exec( escapeshellarg( $node ) . ' ' . escapeshellarg( $harness ) . ' ' . escapeshellarg( $script ) . ' ' . escapeshellarg( $source ) . ' 2>&1' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec -- test helper.

		$this->assertSame(
			array(
				'status' => $status,
				'notes'  => array( 'The handbook folder holds no Markdown files.' ),
			),
			json_decode( $output, true ),
			'Output: ' . $output
		);
	}
}
