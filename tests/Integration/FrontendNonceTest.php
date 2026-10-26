<?php
/**
 * A guest gets no REST nonce in the page.
 *
 * Core checks a nonce whenever one is sent, for a guest too. A page cache
 * hands the guest's nonce out long after it expired, and from then on every
 * search, filter and feedback request of a guest on a cached public handbook
 * was refused with 403 rest_cookie_invalid_nonce (0.75.0). A guest needs no
 * nonce: the endpoints decide by themselves what a guest may see.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Frontend\FrontendRenderer;
use WP_UnitTestCase;

/**
 * FrontendRenderer::register_assets() and the data it attaches to the script.
 */
final class FrontendNonceTest extends WP_UnitTestCase {

	/**
	 * The data attached to the front-end script, registered afresh for the
	 * current user.
	 *
	 * @return array<string, mixed>
	 */
	private function localized(): array {
		wp_deregister_script( 'living-handbook' );
		( new FrontendRenderer() )->register_assets();

		$data = (string) wp_scripts()->get_data( 'living-handbook', 'data' );
		$this->assertSame( 1, preg_match( '/var livingHandbook = (\{.*\});/s', $data, $match ), 'The script carries its data.' );

		return (array) json_decode( $match[1], true );
	}

	/**
	 * A guest's page carries no nonce, so the script sends none.
	 *
	 * @return void
	 */
	public function test_a_guest_gets_no_nonce(): void {
		wp_set_current_user( 0 );

		$this->assertSame( '', $this->localized()['nonce'] ?? null );
	}

	/**
	 * A signed-in reader still gets one, which is what makes REST know who is
	 * asking.
	 *
	 * @return void
	 */
	public function test_a_signed_in_reader_gets_a_nonce(): void {
		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertNotSame( '', (string) ( $this->localized()['nonce'] ?? '' ) );
	}

	/**
	 * The script sends the header only when there is a nonce to send.
	 *
	 * @return void
	 */
	public function test_the_script_sends_the_header_only_with_a_nonce(): void {
		$script = (string) file_get_contents( LIVING_HANDBOOK_DIR . 'assets/frontend.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a plugin file.

		$this->assertSame( 1, substr_count( $script, "'X-WP-Nonce'" ), 'One place sets the header, behind the check.' );
		$this->assertStringContainsString( 'window.livingHandbook.nonce ) {', $script );
	}
}
