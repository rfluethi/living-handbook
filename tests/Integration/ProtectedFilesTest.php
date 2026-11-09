<?php
/**
 * File protection: where the files of handbook pages lie (E-0076).
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Files\ProtectedFiles;
use LivingHandbook\Files\ServerRules;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use WP_UnitTestCase;

/**
 * Files of pages that not everyone may read move into the protected folder,
 * and out again when the handbook becomes public.
 */
final class ProtectedFilesTest extends WP_UnitTestCase {

	/**
	 * Attachments made by a test, removed with their files afterwards.
	 *
	 * @var array<int, int>
	 */
	private array $made = array();

	/**
	 * Remove the files and the rules.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		foreach ( $this->made as $id ) {
			wp_delete_attachment( $id, true );
		}
		$this->made = array();
		ServerRules::remove();
		delete_option( 'living_handbook_protect_queue' );
		delete_option( 'living_handbook_moved_files' );
		ProtectedFiles::unschedule();
		parent::tear_down();
	}

	/**
	 * A handbook with a visibility.
	 *
	 * @param string $visibility Visibility.
	 * @return int
	 */
	private function handbook( string $visibility ): int {
		$term_id = (int) self::factory()->term->create( array( 'taxonomy' => Handbooks::TAXONOMY ) );
		update_term_meta( $term_id, Handbooks::META_VISIBILITY, $visibility );
		return $term_id;
	}

	/**
	 * A published page in a handbook.
	 *
	 * @param int    $term_id Handbook.
	 * @param string $content Content.
	 * @return int
	 */
	private function page( int $term_id, string $content = '' ): int {
		$post_id = (int) self::factory()->post->create(
			array(
				'post_type'    => Handbook::POST_TYPE,
				'post_status'  => 'publish',
				'post_content' => $content,
			)
		);
		wp_set_object_terms( $post_id, array( $term_id ), Handbooks::TAXONOMY );
		return $post_id;
	}

	/**
	 * Uploads an image to a page, the way core does it.
	 *
	 * @param int    $parent Page.
	 * @param string $file   Test image.
	 * @return int
	 */
	private function upload( int $parent, string $file = 'canola.jpg' ): int {
		$id           = (int) self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/' . $file, $parent );
		$this->made[] = $id;
		ProtectedFiles::check_created();
		return $id;
	}

	/**
	 * Every file of an attachment, as absolute paths.
	 *
	 * @param int $id Attachment.
	 * @return array<int, string>
	 */
	private function paths( int $id ): array {
		$main  = (string) get_attached_file( $id, true );
		$paths = array( $main );
		$meta  = wp_get_attachment_metadata( $id, true );
		foreach ( (array) ( $meta['sizes'] ?? array() ) as $size ) {
			$paths[] = dirname( $main ) . '/' . $size['file'];
		}
		return $paths;
	}

	/**
	 * An image uploaded to a members page lies in the protected folder, with
	 * every size, and the metadata says so.
	 *
	 * @return void
	 */
	public function test_upload_to_members_page_lands_in_the_protected_folder(): void {
		$id = $this->upload( $this->page( $this->handbook( Handbooks::VISIBILITY_MEMBERS ) ) );

		$this->assertTrue( ProtectedFiles::is_protected( $id ) );
		$meta = wp_get_attachment_metadata( $id, true );
		$this->assertStringStartsWith( ProtectedFiles::DIR . '/', (string) $meta['file'] );
		$this->assertNotEmpty( $meta['sizes'], 'The test image has sizes.' );
		$folder = ProtectedFiles::folder();
		foreach ( $this->paths( $id ) as $path ) {
			$this->assertStringStartsWith( $folder['path'] . '/', $path );
			$this->assertFileExists( $path );
			$this->assertFileDoesNotExist( str_replace( '/' . ProtectedFiles::DIR . '/', '/', $path ) );
		}
		$this->assertStringContainsString( '/' . ProtectedFiles::DIR . '/', (string) wp_get_attachment_url( $id ) );
		$this->assertFileExists( $folder['path'] . '/.htaccess' );
	}

	/**
	 * The same name uploaded twice: the second gets another name, because the
	 * first lies in the protected folder where core does not look.
	 *
	 * @return void
	 */
	public function test_same_name_twice_does_not_collide(): void {
		$page  = $this->page( $this->handbook( Handbooks::VISIBILITY_MEMBERS ) );
		$first = $this->upload( $page );
		$again = $this->upload( $page );

		$this->assertTrue( ProtectedFiles::is_protected( $again ) );
		$this->assertNotSame( get_attached_file( $first ), get_attached_file( $again ) );
		$this->assertFileExists( (string) get_attached_file( $first ) );
		$this->assertFileExists( (string) get_attached_file( $again ) );
	}

	/**
	 * A file of a public handbook stays where core put it.
	 *
	 * @return void
	 */
	public function test_upload_to_public_page_stays(): void {
		$id = $this->upload( $this->page( $this->handbook( Handbooks::VISIBILITY_PUBLIC ) ) );

		$this->assertFalse( ProtectedFiles::is_protected( $id ) );
		foreach ( $this->paths( $id ) as $path ) {
			$this->assertFileExists( $path );
		}
	}

	/**
	 * Files of ordinary posts and of the media library are not touched.
	 *
	 * @return void
	 */
	public function test_files_outside_the_handbooks_stay(): void {
		$post = (int) self::factory()->post->create();
		$this->assertFalse( ProtectedFiles::is_protected( $this->upload( $post ) ) );
		$this->assertFalse( ProtectedFiles::is_protected( $this->upload( 0 ) ) );
	}

	/**
	 * A page without a handbook does not yet say who may read it, so its
	 * files are protected.
	 *
	 * @return void
	 */
	public function test_page_without_handbook_protects(): void {
		$page = (int) self::factory()->post->create( array( 'post_type' => Handbook::POST_TYPE ) );
		$this->assertTrue( ProtectedFiles::protects( $page ) );
	}

	/**
	 * A file created without metadata (no image) is checked at the end of the
	 * request.
	 *
	 * @return void
	 */
	public function test_file_without_metadata_moves_at_the_end_of_the_request(): void {
		$page    = $this->page( $this->handbook( Handbooks::VISIBILITY_MEMBERS ) );
		$uploads = wp_upload_dir();
		$path    = $uploads['path'] . '/liste-' . wp_generate_password( 6, false ) . '.txt';
		file_put_contents( $path, 'Telefonliste' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture.
		$id           = (int) wp_insert_attachment(
			array(
				'post_mime_type' => 'text/plain',
				'post_title'     => 'Liste',
			),
			$path,
			$page
		);
		$this->made[] = $id;

		$this->assertFalse( ProtectedFiles::is_protected( $id ), 'Nothing moves before the request ends.' );
		ProtectedFiles::check_created();
		$this->assertTrue( ProtectedFiles::is_protected( $id ) );
		$this->assertFileExists( (string) get_attached_file( $id ) );
		$this->assertFileDoesNotExist( $path );
	}

	/**
	 * When a handbook becomes public, the queue brings its files back, and the
	 * addresses in the page content follow both ways without a revision.
	 *
	 * @return void
	 */
	public function test_visibility_change_moves_back_and_rewrites_content(): void {
		$term_id = $this->handbook( Handbooks::VISIBILITY_PUBLIC );
		$page    = $this->page( $term_id );
		$id      = $this->upload( $page );
		$url     = (string) wp_get_attachment_url( $id );
		$srcset  = (string) wp_get_attachment_image_srcset( $id, 'full' );
		$this->assertNotSame( '', $srcset );
		$relative = (string) wp_parse_url( $url, PHP_URL_PATH );
		wp_update_post(
			array(
				'ID'           => $page,
				'post_content' => '<!-- wp:image --><figure><img src="' . $url . '" srcset="' . esc_attr( $srcset ) . '"/></figure><!-- /wp:image --><p><a href="' . $relative . '">Datei</a></p>',
			)
		);
		$revisions = count( wp_get_post_revisions( $page ) );

		update_term_meta( $term_id, Handbooks::META_VISIBILITY, Handbooks::VISIBILITY_MEMBERS );
		$this->assertNotFalse( wp_next_scheduled( ProtectedFiles::CRON_HOOK ) );
		$result = ProtectedFiles::run_queue( 30.0 );

		$this->assertSame( 1, $result['moved'] );
		$this->assertTrue( ProtectedFiles::is_protected( $id ) );
		$content = (string) get_post_field( 'post_content', $page, 'raw' );
		$this->assertStringNotContainsString( $url, $content );
		$this->assertStringContainsString( (string) wp_get_attachment_url( $id ), $content );
		$this->assertStringContainsString( 'href="' . (string) wp_parse_url( (string) wp_get_attachment_url( $id ), PHP_URL_PATH ) . '"', $content );
		$this->assertStringNotContainsString( '/uploads/' . gmdate( 'Y' ), str_replace( '/' . ProtectedFiles::DIR . '/', '/X/', $content ), 'The srcset followed as well.' );
		$this->assertSame( $revisions, count( wp_get_post_revisions( $page ) ), 'No revision for a changed address.' );

		update_term_meta( $term_id, Handbooks::META_VISIBILITY, Handbooks::VISIBILITY_PUBLIC );
		ProtectedFiles::run_queue( 30.0 );
		$this->assertFalse( ProtectedFiles::is_protected( $id ) );
		$this->assertStringContainsString( $url, (string) get_post_field( 'post_content', $page, 'raw' ) );
		$this->assertSame( array(), get_option( 'living_handbook_moved_files' ), 'Moving back forgets the address.' );
	}

	/**
	 * Content outside the handbooks keeps working: the moved address is
	 * corrected on output.
	 *
	 * @return void
	 */
	public function test_moved_address_is_corrected_in_other_content(): void {
		$term_id = $this->handbook( Handbooks::VISIBILITY_PUBLIC );
		$id      = $this->upload( $this->page( $term_id ) );
		$url     = (string) wp_get_attachment_url( $id );
		$post    = (int) self::factory()->post->create( array( 'post_content' => '<p><img src="' . $url . '"/></p>' ) );

		update_term_meta( $term_id, Handbooks::META_VISIBILITY, Handbooks::VISIBILITY_RESTRICTED );
		ProtectedFiles::run_queue( 30.0 );

		$this->assertStringContainsString( $url, (string) get_post_field( 'post_content', $post, 'raw' ), 'The stored post is not changed.' );
		$shown = (string) apply_filters( 'the_content', (string) get_post_field( 'post_content', $post, 'raw' ) );
		$this->assertStringContainsString( (string) wp_get_attachment_url( $id ), $shown );
	}

	/**
	 * A page moving to another handbook takes its files along.
	 *
	 * @return void
	 */
	public function test_page_changing_handbook_moves_its_files(): void {
		$page = $this->page( $this->handbook( Handbooks::VISIBILITY_MEMBERS ) );
		$id   = $this->upload( $page );
		$this->assertTrue( ProtectedFiles::is_protected( $id ) );

		wp_set_object_terms( $page, array( $this->handbook( Handbooks::VISIBILITY_PUBLIC ) ), Handbooks::TAXONOMY );
		$this->assertFalse( ProtectedFiles::is_protected( $id ) );
		foreach ( $this->paths( $id ) as $path ) {
			$this->assertFileExists( $path );
		}
	}

	/**
	 * A file attached to another page follows the new page.
	 *
	 * @return void
	 */
	public function test_reattached_file_follows_its_new_page(): void {
		$id = $this->upload( $this->page( $this->handbook( Handbooks::VISIBILITY_PUBLIC ) ) );
		wp_update_post(
			array(
				'ID'          => $id,
				'post_parent' => $this->page( $this->handbook( Handbooks::VISIBILITY_MEMBERS ) ),
			)
		);
		$this->assertTrue( ProtectedFiles::is_protected( $id ) );
	}

	/**
	 * When one rename fails, the others are undone and nothing is recorded.
	 *
	 * @return void
	 */
	public function test_failed_move_is_undone(): void {
		$term_id = $this->handbook( Handbooks::VISIBILITY_PUBLIC );
		$id      = $this->upload( $this->page( $term_id ) );
		$before  = $this->paths( $id );
		$meta    = wp_get_attachment_metadata( $id, true );

		// A file already there for the last size blocks the move.
		$folder  = ProtectedFiles::folder();
		$sizes   = array_values( (array) $meta['sizes'] );
		$blocker = $folder['path'] . '/' . dirname( (string) $meta['file'] ) . '/' . $sizes[ count( $sizes ) - 1 ]['file'];
		wp_mkdir_p( dirname( $blocker ) );
		file_put_contents( $blocker, 'x' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture.

		$this->assertFalse( ProtectedFiles::move( $id, true ) );
		$this->assertFalse( ProtectedFiles::is_protected( $id ) );
		$this->assertSame( $meta, wp_get_attachment_metadata( $id, true ) );
		foreach ( $before as $path ) {
			$this->assertFileExists( $path );
		}
		wp_delete_file( $blocker );
	}

	/**
	 * Many files are moved through the queue, a few at once.
	 *
	 * @return void
	 */
	public function test_update_queues_all_and_lists_the_misplaced(): void {
		$term_id = $this->handbook( Handbooks::VISIBILITY_PUBLIC );
		$id      = $this->upload( $this->page( $term_id ) );
		// The visibility changes without the hooks, as in a version before 0.79.0.
		global $wpdb;
		$wpdb->update( $wpdb->termmeta, array( 'meta_value' => Handbooks::VISIBILITY_MEMBERS ), array( 'term_id' => $term_id, 'meta_key' => Handbooks::META_VISIBILITY ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- test setup.
		wp_cache_flush();

		$this->assertSame( array( $id ), ProtectedFiles::misplaced() );
		ProtectedFiles::enqueue( 'all' );
		$this->assertSame( 'all', get_option( 'living_handbook_protect_queue' ) );
		ProtectedFiles::run_queue( 30.0 );
		$this->assertSame( array(), ProtectedFiles::misplaced() );
		$this->assertFalse( get_option( 'living_handbook_protect_queue' ) );
	}

	/**
	 * The uninstall brings every file back.
	 *
	 * @return void
	 */
	public function test_release_all_brings_every_file_back(): void {
		$a = $this->upload( $this->page( $this->handbook( Handbooks::VISIBILITY_MEMBERS ) ) );
		$b = $this->upload( $this->page( $this->handbook( Handbooks::VISIBILITY_RESTRICTED ) ), 'test-image.jpg' );

		$this->assertSame( 2, ProtectedFiles::release_all() );
		foreach ( array( $a, $b ) as $id ) {
			$this->assertFalse( ProtectedFiles::is_protected( $id ) );
			foreach ( $this->paths( $id ) as $path ) {
				$this->assertFileExists( $path );
			}
		}
	}
}
