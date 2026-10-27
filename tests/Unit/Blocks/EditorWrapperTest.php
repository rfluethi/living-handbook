<?php
/**
 * Every server-rendered block is a selectable block in the editor.
 *
 * The blocks declare apiVersion 3, so the editor leaves the wrapper element to
 * the block: the root its edit() returns has to carry useBlockProps(). Up to
 * 0.75.1 the fourteen blocks in assets/blocks.js returned their note or
 * preview bare, and a placed block could not be clicked to select it.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Unit\Blocks;

use PHPUnit\Framework\TestCase;

/**
 * Assets/blocks.js.
 */
final class EditorWrapperTest extends TestCase {

	/**
	 * Render every block's editor view and look at its root element.
	 *
	 * @return void
	 */
	public function test_every_block_renders_inside_its_wrapper(): void {
		$node = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec -- test helper.
		if ( '' === $node ) {
			$this->markTestSkipped( 'node is not installed.' );
		}

		$script  = dirname( __DIR__, 3 ) . '/assets/blocks.js';
		$harness = dirname( __DIR__, 2 ) . '/js/blocks-wrapper.js';
		$output  = (string) shell_exec( escapeshellarg( $node ) . ' ' . escapeshellarg( $harness ) . ' ' . escapeshellarg( $script ) . ' 2>&1' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec -- test helper.
		$decoded = json_decode( $output, true );
		$this->assertIsArray( $decoded, 'Output: ' . $output );

		$this->assertCount( 14, $decoded, 'blocks.js registers the fourteen server-rendered blocks.' );
		$this->assertSame( array(), array_keys( array_filter( $decoded, static fn( $wrapped ) => true !== $wrapped ) ), 'Blocks whose editor view is not inside the block wrapper.' );
	}
}
