<?php
/**
 * The notice about failed syncs counts every failed page.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Git\GitSync;
use LivingHandbook\PostType\Handbook;
use WP_UnitTestCase;

/**
 * GitSync::sync_error_notice().
 */
final class SyncErrorNoticeTest extends WP_UnitTestCase {

	/**
	 * Reset the admin screen.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		unset( $GLOBALS['current_screen'] );
		parent::tear_down();
	}

	/**
	 * GitHub pages whose last sync failed.
	 *
	 * @param int $count How many.
	 * @return array<int, int> Post ids.
	 */
	private function failed_pages( int $count ): array {
		$ids = self::factory()->post->create_many(
			$count,
			array(
				'post_type'   => Handbook::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		foreach ( $ids as $id ) {
			update_post_meta( (int) $id, GitSync::META_SOURCE, GitSync::SOURCE_GITHUB );
			update_post_meta( (int) $id, '_lh_sync_error', '1' );
		}
		return array_map( 'intval', $ids );
	}

	/**
	 * The notice as an administrator sees it on the page list.
	 *
	 * @return string
	 */
	private function notice(): string {
		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'edit-' . Handbook::POST_TYPE );

		ob_start();
		( new GitSync() )->sync_error_notice();
		return (string) ob_get_clean();
	}

	/**
	 * Fifty-five failed pages are reported as fifty-five. Up to 0.75.1 the
	 * query stopped at fifty and the notice said fifty.
	 *
	 * @return void
	 */
	public function test_more_than_fifty_failed_pages_are_counted(): void {
		$this->failed_pages( 55 );

		$html = $this->notice();

		$this->assertStringContainsString( '55 GitHub pages could not be synced', $html );
		$this->assertSame( 10, substr_count( $html, '<li>' ), 'Ten are named.' );
		$this->assertStringContainsString( '45 more', $html, 'The rest are counted.' );
	}

	/**
	 * A handful are all named, and nothing more is promised.
	 *
	 * @return void
	 */
	public function test_a_few_failed_pages_are_all_named(): void {
		$this->failed_pages( 3 );

		$html = $this->notice();

		$this->assertStringContainsString( '3 GitHub pages could not be synced', $html );
		$this->assertSame( 3, substr_count( $html, '<li>' ) );
		$this->assertStringNotContainsString( 'more', $html );
	}
}
