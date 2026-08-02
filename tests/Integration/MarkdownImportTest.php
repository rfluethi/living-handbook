<?php
/**
 * The import screen's two dangerous jobs: reading a ZIP, and writing a page.
 *
 * Reading a ZIP means reading a file a user uploaded, so the bounds around it
 * are the only thing between a prepared archive and the server's memory. Writing
 * a page means deciding whether to create one or overwrite an existing one, and
 * a wrong decision costs somebody their text. Both had no test.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\Import\MarkdownImportPage;
use LivingHandbook\Import\Postprocessor;
use LivingHandbook\PostType\Handbook;
use WP_Error;
use WP_REST_Request;
use WP_UnitTestCase;
use ZipArchive;

/**
 * ZIP reading and page writing on the import screen.
 */
final class MarkdownImportTest extends WP_UnitTestCase {

	/**
	 * ZIP files built for a test.
	 *
	 * @var array<int, string>
	 */
	private array $archives = array();

	/**
	 * Remove the built archives.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		foreach ( $this->archives as $path ) {
			if ( file_exists( $path ) ) {
				wp_delete_file( $path );
			}
		}
		$this->archives = array();
		parent::tear_down();
	}

	/**
	 * Build a ZIP from a path to contents map.
	 *
	 * @param array<string, string> $entries Entry path to contents.
	 * @return string Path of the archive.
	 */
	private function make_zip( array $entries ): string {
		$path = (string) tempnam( sys_get_temp_dir(), 'lh-import-zip' );
		$zip  = new ZipArchive();
		$this->assertTrue( true === $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) );
		foreach ( $entries as $name => $contents ) {
			$zip->addFromString( $name, $contents );
		}
		$zip->close();

		$this->archives[] = $path;
		return $path;
	}

	/**
	 * A ZIP with Markdown, an image and a mkdocs.yml is sorted into three piles.
	 *
	 * @return void
	 */
	public function test_a_zip_is_sorted_into_markdown_images_and_config(): void {
		$png  = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- A one pixel PNG for the test archive.
		$path = $this->make_zip(
			array(
				'docs/index.md'       => "# Start\n",
				'docs/second.md'      => "# Second\n",
				'docs/img/plan.png'   => (string) $png,
				'mkdocs.yml'          => "nav:\n  - Home: index.md\n",
				'docs/notes.txt'      => 'ignored',
			)
		);

		$read = MarkdownImportPage::read_zip( $path );

		$this->assertIsArray( $read );
		$this->assertSame( array( 'docs/index.md', 'docs/second.md' ), array_keys( $read['markdown'] ) );
		$this->assertSame( array( 'plan.png' ), array_keys( $read['images'] ), 'Images are keyed by file name, not by path.' );
		$this->assertStringContainsString( 'nav:', $read['mkdocs'] );
	}

	/**
	 * Hidden files and the folder macOS packs into every ZIP are skipped, so a
	 * "._page.md" does not become a page.
	 *
	 * @return void
	 */
	public function test_hidden_and_macos_entries_are_skipped(): void {
		$path = $this->make_zip(
			array(
				'docs/real.md'            => "# Real\n",
				'docs/.hidden.md'         => "# Hidden\n",
				'__MACOSX/docs/._real.md' => 'resource fork',
				'.DS_Store'               => 'junk',
			)
		);

		$read = MarkdownImportPage::read_zip( $path );

		$this->assertSame( array( 'docs/real.md' ), array_keys( $read['markdown'] ) );
	}

	/**
	 * A single oversized file is skipped, the rest of the archive is still
	 * imported: one huge file is not a reason to lose the other pages.
	 *
	 * @return void
	 */
	public function test_an_oversized_file_is_skipped_and_the_rest_survives(): void {
		$path = $this->make_zip(
			array(
				'docs/small.md' => "# Small\n",
				'docs/huge.md'  => str_repeat( 'x', 5242880 + 1024 ),
			)
		);

		$read = MarkdownImportPage::read_zip( $path );

		$this->assertSame( array( 'docs/small.md' ), array_keys( $read['markdown'] ), 'The 5 MB per-file limit holds.' );
	}

	/**
	 * Past the total size the reading stops with an error rather than filling
	 * memory. The limit is lowered through its filter, so the test does not have
	 * to build a hundred megabytes to prove it.
	 *
	 * @return void
	 */
	public function test_the_total_size_limit_stops_the_import(): void {
		add_filter(
			'living_handbook_zip_max_bytes',
			static function (): int {
				return 4096;
			}
		);

		$path = $this->make_zip(
			array(
				'docs/a.md' => str_repeat( 'a', 3000 ),
				'docs/b.md' => str_repeat( 'b', 3000 ),
			)
		);

		$read = MarkdownImportPage::read_zip( $path );

		$this->assertInstanceOf( WP_Error::class, $read );
		$this->assertSame( 400, $read->get_error_data()['status'] ?? 0 );
	}

	/**
	 * A file that is not a ZIP is refused, not half read.
	 *
	 * @return void
	 */
	public function test_something_that_is_not_a_zip_is_refused(): void {
		$path = (string) tempnam( sys_get_temp_dir(), 'lh-not-a-zip' );
		file_put_contents( $path, 'this is not an archive' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- A test fixture on the local disk.
		$this->archives[] = $path;

		$read = MarkdownImportPage::read_zip( $path );

		$this->assertInstanceOf( WP_Error::class, $read );
	}

	/**
	 * Build a REST request for the page-creating endpoint.
	 *
	 * @param array<string, mixed> $params Request parameters.
	 * @return WP_REST_Request
	 */
	private function create_request( array $params ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST', '/living-handbook/v1/create' );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return $request;
	}

	/**
	 * A second import of the same source path refreshes the page instead of
	 * creating a second one. This is what keeps a repeated folder or MkDocs
	 * import from doubling a handbook.
	 *
	 * @return void
	 */
	public function test_a_re_import_by_source_path_updates_instead_of_duplicating(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$page = new MarkdownImportPage();

		$first = $page->create_callback(
			$this->create_request(
				array(
					'title'      => 'First',
					'content'    => '<!-- wp:paragraph --><p>One</p><!-- /wp:paragraph -->',
					'sourcePath' => 'docs/guide.md',
				)
			)
		);
		$this->assertArrayHasKey( 'id', $first );

		$second = $page->create_callback(
			$this->create_request(
				array(
					'title'      => 'Second',
					'content'    => '<!-- wp:paragraph --><p>Two</p><!-- /wp:paragraph -->',
					'sourcePath' => 'docs/guide.md',
				)
			)
		);

		$this->assertSame( $first['id'], $second['id'], 'The same source path is the same page.' );
		$this->assertStringContainsString( 'Two', (string) get_post_field( 'post_content', (int) $second['id'] ) );
		$this->assertSame( 'docs/guide.md', get_post_meta( (int) $second['id'], Postprocessor::META_SOURCE_PATH, true ) );

		$all = get_posts(
			array(
				'post_type'      => Handbook::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		$this->assertCount( 1, $all, 'A re-import must not leave two pages behind.' );
	}

	/**
	 * A different source path is a different page, even with the same title.
	 *
	 * @return void
	 */
	public function test_a_different_source_path_creates_its_own_page(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$page = new MarkdownImportPage();

		$first  = $page->create_callback( $this->create_request( array( 'title' => 'Guide', 'sourcePath' => 'docs/a.md' ) ) );
		$second = $page->create_callback( $this->create_request( array( 'title' => 'Guide', 'sourcePath' => 'docs/b.md' ) ) );

		$this->assertNotSame( $first['id'], $second['id'] );
	}

	/**
	 * A pasted draft carries neither a source path nor an explicit slug, so it
	 * always becomes a new page: two pastes of the same text are two drafts, not
	 * one silently overwritten.
	 *
	 * @return void
	 */
	public function test_a_pasted_draft_never_overwrites_anything(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$page = new MarkdownImportPage();

		$first  = $page->create_callback( $this->create_request( array( 'title' => 'Pasted' ) ) );
		$second = $page->create_callback( $this->create_request( array( 'title' => 'Pasted' ) ) );

		$this->assertNotSame( $first['id'], $second['id'] );
	}

	/**
	 * A re-import may not overwrite a page the current user cannot edit. The
	 * endpoint only requires edit_posts, which a contributor has, and a match on
	 * somebody else's published page must become a new page instead.
	 *
	 * @return void
	 */
	public function test_a_contributor_cannot_overwrite_another_authors_page(): void {
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $author );
		$page = new MarkdownImportPage();

		$owned = $page->create_callback(
			$this->create_request(
				array(
					'title'      => 'Owned',
					'content'    => '<!-- wp:paragraph --><p>Mine</p><!-- /wp:paragraph -->',
					'sourcePath' => 'docs/owned.md',
				)
			)
		);
		wp_update_post(
			array(
				'ID'          => (int) $owned['id'],
				'post_status' => 'publish',
			)
		);

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );
		$attempt = $page->create_callback(
			$this->create_request(
				array(
					'title'      => 'Taken over',
					'content'    => '<!-- wp:paragraph --><p>Theirs</p><!-- /wp:paragraph -->',
					'sourcePath' => 'docs/owned.md',
				)
			)
		);

		$this->assertNotSame( $owned['id'], $attempt['id'], 'A page somebody else published must not be overwritten.' );
		$this->assertStringContainsString( 'Mine', (string) get_post_field( 'post_content', (int) $owned['id'] ) );
	}

	/**
	 * The written content is sanitized here, not only on the way in through the
	 * conversion endpoint: this endpoint can be called directly.
	 *
	 * @return void
	 */
	public function test_the_written_content_is_sanitized(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$page = new MarkdownImportPage();

		$result = $page->create_callback(
			$this->create_request(
				array(
					'title'   => 'Hostile',
					'content' => '<!-- wp:paragraph --><p>Text<script>alert(1)</script><a href="javascript:alert(2)">link</a></p><!-- /wp:paragraph -->',
				)
			)
		);

		$content = (string) get_post_field( 'post_content', (int) $result['id'] );
		$this->assertStringNotContainsString( '<script', $content );
		$this->assertStringNotContainsString( 'javascript:', $content );
		$this->assertStringContainsString( 'Text', $content );
	}

	/**
	 * A page is created as a draft and a re-import keeps the status it has, so a
	 * published page does not silently return to draft.
	 *
	 * @return void
	 */
	public function test_a_re_import_keeps_the_publication_status(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$page = new MarkdownImportPage();

		$first = $page->create_callback( $this->create_request( array( 'title' => 'Draft first', 'sourcePath' => 'docs/status.md' ) ) );
		$this->assertSame( 'draft', get_post_status( (int) $first['id'] ), 'An import lands as a draft for review.' );

		wp_update_post(
			array(
				'ID'          => (int) $first['id'],
				'post_status' => 'publish',
			)
		);

		$page->create_callback( $this->create_request( array( 'title' => 'Draft first', 'sourcePath' => 'docs/status.md' ) ) );

		$this->assertSame( 'publish', get_post_status( (int) $first['id'] ), 'A re-import must not unpublish a page.' );
	}

	/**
	 * A slug match only applies within the chosen handbook, so the same file
	 * name in two handbooks stays two pages.
	 *
	 * @return void
	 */
	public function test_a_slug_match_stays_inside_its_handbook(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$page = new MarkdownImportPage();

		$one = wp_insert_term( 'Handbook one', Handbooks::TAXONOMY );
		$two = wp_insert_term( 'Handbook two', Handbooks::TAXONOMY );

		$first = $page->create_callback(
			$this->create_request(
				array(
					'title'    => 'Onboarding',
					'slug'     => 'onboarding',
					'handbook' => (int) $one['term_id'],
				)
			)
		);
		$second = $page->create_callback(
			$this->create_request(
				array(
					'title'    => 'Onboarding',
					'slug'     => 'onboarding',
					'handbook' => (int) $two['term_id'],
				)
			)
		);

		$this->assertNotSame( $first['id'], $second['id'], 'The same slug in another handbook is another page.' );

		$again = $page->create_callback(
			$this->create_request(
				array(
					'title'    => 'Onboarding',
					'slug'     => 'onboarding',
					'handbook' => (int) $one['term_id'],
				)
			)
		);
		$this->assertSame( $first['id'], $again['id'], 'Inside the same handbook the slug matches.' );
	}
}
