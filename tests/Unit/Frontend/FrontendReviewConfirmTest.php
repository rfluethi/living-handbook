<?php
/**
 * The review button of the page footer, in the browser (E-0079).
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;

/**
 * Assets/frontend.js: a click sends the review and puts the new entry in place.
 */
final class FrontendReviewConfirmTest extends TestCase {

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

		$harness = dirname( __DIR__, 2 ) . '/js/frontend-review-confirm.js';
		$script  = dirname( __DIR__, 3 ) . '/assets/frontend.js';
		$output  = (string) shell_exec( escapeshellarg( $node ) . ' ' . escapeshellarg( $harness ) . ' ' . escapeshellarg( $script ) . ' ' . escapeshellarg( $scenario ) . ' 2>&1' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec -- test helper.
		$decoded = json_decode( $output, true );
		$this->assertIsArray( $decoded, 'Output: ' . $output );

		return $decoded;
	}

	/**
	 * One POST with the nonce and the page, the entry replaced, the sentence
	 * in a status line that a screen reader announces.
	 *
	 * @return void
	 */
	public function test_a_click_sends_the_review_and_replaces_the_entry(): void {
		$out = $this->run_harness( 'ok' );

		$this->assertSame(
			array(
				array(
					'url'    => 'https://example.test/wp-json/living-handbook/v1/review',
					'method' => 'POST',
					'nonce'  => 'fresh',
					'body'   => array(
						'post_id' => 42,
						'people'  => true,
					),
				),
			),
			$out['calls']
		);
		$this->assertTrue( $out['disabledWhileSent'], 'A second click while the first is on its way would send twice.' );
		$this->assertStringContainsString( 'living-handbook-badge--ok', $out['entry'] );
		$this->assertSame( 'Review confirmed.', $out['status'] );
		$this->assertSame( 'status', $out['statusRole'] );
	}

	/**
	 * A refusal leaves the entry alone, says so and gives the button back.
	 *
	 * @return void
	 */
	public function test_a_refusal_says_so_and_gives_the_button_back(): void {
		$out = $this->run_harness( 'error' );

		$this->assertSame( '', $out['entry'] );
		$this->assertFalse( $out['disabledAfter'] );
		$this->assertSame( 'Review failed.', $out['status'] );
	}
}
