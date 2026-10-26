<?php
/**
 * The lesson search shows the answer to the latest search, not the slowest.
 *
 * The picker sends a search per pause in typing. The debounce spaces the
 * requests out but cannot order the answers, and up to 0.75.1 every answer
 * replaced the list: a slow answer for "onb" arriving after the quick one for
 * "onboarding" put the matches for words no longer in the field on screen, and
 * a click there added a lesson the editor had not looked for.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Unit\Training;

use PHPUnit\Framework\TestCase;

/**
 * assets/js/training-lessons.js.
 */
final class LessonSearchOrderTest extends TestCase {

	/**
	 * The script, as shipped.
	 *
	 * @return string Absolute path.
	 */
	private function script(): string {
		return dirname( __DIR__, 3 ) . '/assets/js/training-lessons.js';
	}

	/**
	 * Both answer paths, success and failure, ask whether they are still the
	 * latest before they touch the list.
	 *
	 * @return void
	 */
	public function test_every_answer_is_checked_against_the_latest_search(): void {
		$script = (string) file_get_contents( $this->script() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a plugin file.

		$this->assertStringContainsString( 'var request = ++latest;', $script );
		$this->assertSame( 2, substr_count( $script, 'if ( request !== latest ) {' ), 'The success and the error path both have to drop a stale answer.' );
	}

	/**
	 * Run the script in node against a fake DOM, answer two searches in the
	 * wrong order, and look at what the list shows.
	 *
	 * @return void
	 */
	public function test_a_late_answer_does_not_replace_a_newer_one(): void {
		$node = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec -- test helper.
		if ( '' === $node ) {
			$this->markTestSkipped( 'node is not installed.' );
		}

		$harness = dirname( __DIR__, 2 ) . '/js/training-lessons-race.js';
		$output  = (string) shell_exec( escapeshellarg( $node ) . ' ' . escapeshellarg( $harness ) . ' ' . escapeshellarg( $this->script() ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec -- test helper.

		$this->assertSame( array( 'Onboarding checklist' ), json_decode( $output, true ), 'Output: ' . $output );
	}
}
