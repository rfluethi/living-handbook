<?php
/**
 * The handbook field of "Move to handbook" works in both bulk action bars.
 *
 * WordPress draws the bulk actions above and below the list, and since 5.7
 * core marries the two dropdowns: choosing an action in one sets the other.
 * Up to 0.75.1 the script read each dropdown on its own, at a moment when
 * core had not updated the other one yet, so the lower bar said "Move to
 * handbook" with no handbook field beside it. Both bars show the field now,
 * and a handbook chosen in one is set in the other, because every enabled
 * field of the form is submitted.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;

/**
 * Assets/js/bulk-move.js.
 */
final class BulkMoveBarsTest extends TestCase {

	/**
	 * Choose the move action above, pick a handbook below, and look at both
	 * bars.
	 *
	 * @return void
	 */
	public function test_both_bars_offer_the_field_and_agree_on_the_handbook(): void {
		$node = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec -- test helper.
		if ( '' === $node ) {
			$this->markTestSkipped( 'node is not installed.' );
		}

		$script  = dirname( __DIR__, 3 ) . '/assets/js/bulk-move.js';
		$harness = dirname( __DIR__, 2 ) . '/js/bulk-move.js';
		$output  = (string) shell_exec( escapeshellarg( $node ) . ' ' . escapeshellarg( $harness ) . ' ' . escapeshellarg( $script ) . ' 2>&1' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec -- test helper.
		$decoded = json_decode( $output, true );
		$this->assertIsArray( $decoded, 'Output: ' . $output );

		$expected = array(
			'shown'    => true,
			'enabled'  => true,
			'required' => true,
			'value'    => '15',
		);
		$this->assertSame( $expected, $decoded[0], 'The upper bar shows the field, with the handbook chosen below.' );
		$this->assertSame( $expected, $decoded[1], 'The lower bar shows the field as well.' );
	}
}
