<?php
/**
 * While an import runs, every button that starts one is locked.
 *
 * The import screen locks its buttons for the length of an import, so a second
 * click cannot create the same pages twice. Up to 0.75.1 the "Load handbook"
 * button of the app handbook tab was missing from that list: it stayed active
 * while the handbook was loading and started a second load when clicked again.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Unit\Import;

use PHPUnit\Framework\TestCase;

/**
 * Assets/js/markdown-import.js.
 */
final class ImportButtonsLockedTest extends TestCase {

	/**
	 * Press "Load handbook", keep the answer waiting, and look at the buttons.
	 *
	 * @return void
	 */
	public function test_the_app_handbook_button_is_locked_while_it_loads(): void {
		$node = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec -- test helper.
		if ( '' === $node ) {
			$this->markTestSkipped( 'node is not installed.' );
		}

		$script  = dirname( __DIR__, 3 ) . '/assets/js/markdown-import.js';
		$harness = dirname( __DIR__, 2 ) . '/js/markdown-import-busy.js';
		$output  = (string) shell_exec( escapeshellarg( $node ) . ' ' . escapeshellarg( $harness ) . ' ' . escapeshellarg( $script ) . ' 2>&1' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec -- test helper.
		$decoded = json_decode( $output, true );
		$this->assertIsArray( $decoded, 'Output: ' . $output );

		$this->assertSame( array( 'paste', 'github', 'app' ), $decoded['during'], 'Every import button is locked while the app handbook loads.' );
		$this->assertSame( array(), $decoded['after'], 'All of them are free again once it has finished.' );
	}
}
