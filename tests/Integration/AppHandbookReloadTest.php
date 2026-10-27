<?php
/**
 * Loading the shipped handbook again tells what it left behind, and leaves
 * pages that were handed over alone.
 *
 * Two things went wrong silently up to 0.75.1. A page whose file was removed
 * or renamed in a new version stayed published, locked and in the navigation,
 * and nobody was told. And a page loaded without the connection to the source,
 * an ordinary page that somebody may have edited since, was found again by its
 * path and overwritten with the shipped text. Decided on 2026-10-27: name the
 * left-over pages and delete nothing, and leave the handed-over pages alone.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Git\GitSync;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use WP_UnitTestCase;

/**
 * GitSync::import_local_folder() run twice on a folder that changed.
 */
final class AppHandbookReloadTest extends WP_UnitTestCase {

	/**
	 * The folder standing in for the shipped handbook.
	 *
	 * @var string
	 */
	private string $dir = '';

	/**
	 * A folder with two pages, and an administrator to load it.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->dir = get_temp_dir() . 'lh-reload-' . wp_generate_password( 8, false );
		wp_mkdir_p( $this->dir );
		$this->write( 'alpha.md', "# Alpha\n\nShipped text of alpha.\n" );
		$this->write( 'beta.md', "# Beta\n\nShipped text of beta.\n" );

		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Remove the folder.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		foreach ( (array) glob( $this->dir . '/*' ) as $file ) {
			if ( is_string( $file ) ) {
				wp_delete_file( $file );
			}
		}
		if ( is_dir( $this->dir ) ) {
			rmdir( $this->dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- removing a test fixture.
		}

		parent::tear_down();
	}

	/**
	 * Write a file into the folder.
	 *
	 * @param string $name    File name.
	 * @param string $content Content.
	 * @return void
	 */
	private function write( string $name, string $content ): void {
		file_put_contents( $this->dir . '/' . $name, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- writing a test fixture.
	}

	/**
	 * The pages of a handbook, by title.
	 *
	 * @param int $term Handbook.
	 * @return array<string, int>
	 */
	private function pages( int $term ): array {
		$posts = get_posts(
			array(
				'post_type'      => Handbook::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => Handbooks::TAXONOMY,
						'field'    => 'term_id',
						'terms'    => $term,
					),
				),
			)
		);
		$out = array();
		foreach ( $posts as $post ) {
			$out[ $post->post_title ] = $post->ID;
		}
		return $out;
	}

	/**
	 * A page whose file is gone is named in the result and kept.
	 *
	 * @return void
	 */
	public function test_a_page_whose_file_is_gone_is_named_and_kept(): void {
		$term = (int) self::factory()->term->create( array( 'taxonomy' => Handbooks::TAXONOMY ) );
		$sync = new GitSync();

		$sync->import_local_folder( $this->dir, $term, true, true );
		$this->assertCount( 2, $this->pages( $term ) );

		wp_delete_file( $this->dir . '/beta.md' );
		$result = $sync->import_local_folder( $this->dir, $term, true, true );

		$this->assertIsArray( $result );
		$notes = implode( "\n", (array) ( $result['notes'] ?? array() ) );
		$this->assertStringContainsString( '"Beta" is no longer part of the shipped handbook', $notes );
		$this->assertStringNotContainsString( '"Alpha"', $notes, 'A page that was loaded again is not named.' );
		$this->assertArrayHasKey( 'Beta', $this->pages( $term ), 'Nothing is deleted.' );
	}

	/**
	 * A page loaded without the connection keeps its edits on a later load, no
	 * second copy appears, and the result says why.
	 *
	 * @return void
	 */
	public function test_a_page_loaded_without_the_connection_is_left_alone(): void {
		$term = (int) self::factory()->term->create( array( 'taxonomy' => Handbooks::TAXONOMY ) );
		$sync = new GitSync();

		$sync->import_local_folder( $this->dir, $term, true, false );
		$alpha = $this->pages( $term )['Alpha'];
		wp_update_post(
			array(
				'ID'           => $alpha,
				'post_content' => '<p>Our own words.</p>',
			)
		);

		$result = $sync->import_local_folder( $this->dir, $term, true, true );

		$this->assertSame( '<p>Our own words.</p>', get_post_field( 'post_content', $alpha ), 'The edit survives the load.' );
		$this->assertCount( 2, $this->pages( $term ), 'No second copy.' );
		$notes = implode( "\n", (array) ( $result['notes'] ?? array() ) );
		$this->assertStringContainsString( '"Alpha" was loaded without the connection to the source', $notes );
	}

	/**
	 * A page still tied to the shipped copy is refreshed as before.
	 *
	 * @return void
	 */
	public function test_a_tied_page_is_still_refreshed(): void {
		$term = (int) self::factory()->term->create( array( 'taxonomy' => Handbooks::TAXONOMY ) );
		$sync = new GitSync();

		$sync->import_local_folder( $this->dir, $term, true, true );
		$this->write( 'alpha.md', "# Alpha\n\nThe new shipped text.\n" );
		$sync->import_local_folder( $this->dir, $term, true, true );

		$this->assertStringContainsString( 'The new shipped text.', (string) get_post_field( 'post_content', $this->pages( $term )['Alpha'] ) );
	}
}
