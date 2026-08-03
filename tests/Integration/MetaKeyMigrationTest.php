<?php
/**
 * Renaming the custom fields of an older installation.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Feedback\Feedback;
use LivingHandbook\Git\GitSync;
use LivingHandbook\Plugin;
use WP_UnitTestCase;

/**
 * The upgrade brings old meta keys to their current names.
 *
 * The plugin writes two kinds of custom field: the editorial ones a person fills
 * in, under living_handbook_, and the bookkeeping it keeps about a page, under
 * the protected _lh_. Three keys were on neither side of that line. The two
 * source keys were the expensive kind of wrong: without the underscore they sat
 * in the Custom Fields box of every handbook page, where a wrong edit stops the
 * sync without a word.
 */
final class MetaKeyMigrationTest extends WP_UnitTestCase {

	/**
	 * Create a handbook page.
	 *
	 * @return int
	 */
	private function page(): int {
		return (int) self::factory()->post->create(
			array(
				'post_type'   => 'handbook',
				'post_status' => 'publish',
			)
		);
	}

	/**
	 * Run the upgrade the way a plugin update does.
	 *
	 * @return void
	 */
	private function upgrade(): void {
		update_option( Plugin::DB_VERSION_OPTION, '0.15.0' );
		Plugin::instance()->maybe_upgrade();
	}

	/**
	 * The two source keys are renamed, values intact.
	 *
	 * @return void
	 */
	public function test_the_source_keys_move_under_the_protected_prefix(): void {
		$post_id = $this->page();
		update_post_meta( $post_id, 'living_handbook_source', 'github' );
		update_post_meta( $post_id, 'living_handbook_markdown_source', 'https://raw.githubusercontent.com/a/b/main/x.md' );

		$this->upgrade();

		$this->assertSame( 'github', get_post_meta( $post_id, GitSync::META_SOURCE, true ) );
		$this->assertSame( 'https://raw.githubusercontent.com/a/b/main/x.md', get_post_meta( $post_id, GitSync::META_URL, true ) );
		$this->assertSame( '', get_post_meta( $post_id, 'living_handbook_source', true ), 'The old key must be gone, not duplicated.' );
		$this->assertSame( '', get_post_meta( $post_id, 'living_handbook_markdown_source', true ) );
	}

	/**
	 * An installation from before 0.16.0 arrives at the current name in two
	 * steps, in one upgrade run.
	 *
	 * @return void
	 */
	public function test_the_feedback_keys_are_renamed_across_two_generations(): void {
		$old    = $this->page();
		$middle = $this->page();

		update_post_meta( $old, 'living_handbook_feedback_yes', 3 );
		update_post_meta( $old, 'living_handbook_feedback_voters', array( 7 ) );
		update_post_meta( $middle, '_living_handbook_feedback_no', 5 );

		$this->upgrade();

		$this->assertSame( '3', (string) get_post_meta( $old, Feedback::YES, true ) );
		$this->assertSame( array( 7 ), get_post_meta( $old, Feedback::VOTERS, true ) );
		$this->assertSame( '5', (string) get_post_meta( $middle, Feedback::NO, true ) );
		$this->assertSame( '', get_post_meta( $old, 'living_handbook_feedback_yes', true ) );
		$this->assertSame( '', get_post_meta( $middle, '_living_handbook_feedback_no', true ) );
	}

	/**
	 * Running it twice must not undo or duplicate anything, and it must leave
	 * everything else alone.
	 *
	 * @return void
	 */
	public function test_the_upgrade_is_idempotent_and_minds_its_own_keys(): void {
		$post_id = $this->page();
		update_post_meta( $post_id, 'living_handbook_source', 'github' );
		update_post_meta( $post_id, 'living_handbook_last_reviewed', '2026-01-01' );
		update_post_meta( $post_id, 'other_plugin_source', 'keep me' );

		$this->upgrade();
		$this->upgrade();

		$this->assertSame( array( 'github' ), get_post_meta( $post_id, GitSync::META_SOURCE ), 'Exactly one row, not two.' );
		$this->assertSame( '2026-01-01', get_post_meta( $post_id, 'living_handbook_last_reviewed', true ), 'An editorial field keeps its name.' );
		$this->assertSame( 'keep me', get_post_meta( $post_id, 'other_plugin_source', true ), 'Another plugin key is none of our business.' );
	}

	/**
	 * The two source keys must not be editable as plain custom fields any more.
	 *
	 * @return void
	 */
	public function test_the_source_keys_are_protected(): void {
		$this->assertTrue( is_protected_meta( GitSync::META_SOURCE, 'post' ) );
		$this->assertTrue( is_protected_meta( GitSync::META_URL, 'post' ) );
		$this->assertTrue( is_protected_meta( Feedback::VOTERS, 'post' ) );
		$this->assertFalse( is_protected_meta( 'living_handbook_last_reviewed', 'post' ), 'The editorial fields stay visible on purpose.' );
	}
}
