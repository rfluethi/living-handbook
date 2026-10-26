<?php
/**
 * What a sync does to the order and the excerpt of a page.
 *
 * Every sync applied the transport block as if it carried every field. A page
 * without "Reihenfolge" was set back to position 0 and its excerpt emptied, so
 * the order a folder import had given its pages was gone after the first
 * scheduled sync. A field is now written when the block carries it, and taken
 * back only when the block had set it and no longer does.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Git\GitSync;
use LivingHandbook\PostType\Handbook;
use WP_UnitTestCase;

/**
 * GitSync and Postprocessor::apply_transport() on order and excerpt.
 */
final class SyncTransportTest extends WP_UnitTestCase {

	/**
	 * What the fake repository answers with.
	 *
	 * @var string
	 */
	private string $markdown = '';

	/**
	 * Answer every request with the current Markdown.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		add_filter(
			'pre_http_request',
			function () {
				return array(
					'headers'  => array(),
					'body'     => $this->markdown,
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
					'filename' => null,
				);
			}
		);
	}

	/**
	 * A GitHub page as a folder import leaves it: placed, with an excerpt.
	 *
	 * @return int Post ID.
	 */
	private function github_page(): int {
		$id = (int) self::factory()->post->create(
			array(
				'post_type'    => Handbook::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'Synced',
				'menu_order'   => 30,
				'post_excerpt' => 'Written by hand.',
			)
		);
		update_post_meta( $id, GitSync::META_SOURCE, GitSync::SOURCE_GITHUB );
		update_post_meta( $id, GitSync::META_URL, 'https://raw.githubusercontent.com/example/repo/main/synced.md' );
		return $id;
	}

	/**
	 * A sync of a file without the two lines keeps what the page has.
	 *
	 * @return void
	 */
	public function test_a_sync_without_order_and_excerpt_keeps_them(): void {
		$id             = $this->github_page();
		$this->markdown = "# Synced\n\nBody.\n";

		wp_set_current_user( 0 );
		( new GitSync() )->run_sync();

		$post = get_post( $id );
		$this->assertSame( 30, (int) $post->menu_order, 'The position the import gave the page survives a sync.' );
		$this->assertSame( 'Written by hand.', $post->post_excerpt );
	}

	/**
	 * The block sets both, and when the lines leave the file the values go too.
	 *
	 * @return void
	 */
	public function test_values_from_the_block_follow_the_block(): void {
		$id = $this->github_page();

		$this->markdown = "# Synced\n\nBody.\n\n## Transport-Metadaten\n* Reihenfolge: 5\n* Textauszug: From the file.\n";
		( new GitSync() )->run_sync();
		$post = get_post( $id );
		$this->assertSame( 5, (int) $post->menu_order );
		$this->assertSame( 'From the file.', $post->post_excerpt );

		$this->markdown = "# Synced\n\nBody.\n";
		( new GitSync() )->run_sync();
		$post = get_post( $id );
		$this->assertSame( 0, (int) $post->menu_order, 'A number the file took back is taken back here too.' );
		$this->assertSame( '', $post->post_excerpt );
	}
}
