<?php
/**
 * Moving a lesson keeps the keyboard focus in its row.
 *
 * Move up and Move down keep the focus on the button that was pressed. The
 * move disables the button at the end of the list, and a disabled button
 * cannot hold the focus: up to 0.75.1 the last Move up of a row sent a
 * keyboard user to the page body. The other button of the row takes the
 * focus now, and the disabled one stays disabled.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Unit\Training;

use PHPUnit\Framework\TestCase;

/**
 * Assets/js/training-lessons.js.
 */
final class LessonMoveFocusTest extends TestCase {

	/**
	 * Run the script in node against a fake DOM and press the buttons that
	 * have the focus, as a keyboard user does.
	 *
	 * @return void
	 */
	public function test_the_focus_stays_in_the_row_when_a_button_is_disabled(): void {
		$node = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec -- test helper.
		if ( '' === $node ) {
			$this->markTestSkipped( 'node is not installed.' );
		}

		$script  = dirname( __DIR__, 3 ) . '/assets/js/training-lessons.js';
		$harness = dirname( __DIR__, 2 ) . '/js/training-lessons-focus.js';
		$output  = (string) shell_exec( escapeshellarg( $node ) . ' ' . escapeshellarg( $harness ) . ' ' . escapeshellarg( $script ) . ' 2>&1' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec -- test helper.

		$this->assertSame(
			array(
				array( '1,3,2', '3:up' ),
				array( '3,1,2', '3:down' ),
				array( '3,2,1', '1:up' ),
				array( 'up disabled on first', true ),
				array( 'down disabled on last', true ),
			),
			json_decode( $output, true ),
			'Output: ' . $output
		);
	}
}
