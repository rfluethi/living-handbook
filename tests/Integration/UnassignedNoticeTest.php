<?php
/**
 * The warning about pages without a handbook counts them all.
 *
 * Up to 0.75.1 the lookup behind the warning stopped at 50 pages, so a site
 * with more than that was told "50 handbook pages" however many there were.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\PostType\Handbook;
use LivingHandbook\Setup\Onboarding;
use WP_UnitTestCase;

/**
 * Onboarding::unassigned_notice().
 */
final class UnassignedNoticeTest extends WP_UnitTestCase {

	/**
	 * An administrator on the handbook list screen.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'edit-' . Handbook::POST_TYPE );
	}

	/**
	 * Back to the screen the suite expects.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		set_current_screen( 'front' );
		parent::tear_down();
	}

	/**
	 * Fifty-five pages without a handbook are reported as 55, with ten of them
	 * linked and the rest summed up.
	 *
	 * @return void
	 */
	public function test_more_than_fifty_pages_are_counted_in_full(): void {
		self::factory()->post->create_many(
			55,
			array(
				'post_type'   => Handbook::POST_TYPE,
				'post_status' => 'draft',
			)
		);

		ob_start();
		( new Onboarding() )->unassigned_notice();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( '55 handbook pages are not assigned', $html );
		$this->assertSame( 10, substr_count( $html, '<li>' ) );
		$this->assertStringContainsString( '45 more', $html );
	}
}
