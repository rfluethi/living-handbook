<?php
/**
 * Export and import of handbook bundles.
 *
 * These cover the rules that decide whether existing content is touched, which
 * is the part of the feature that can destroy work if it is wrong.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Import\HandbookExport;
use LivingHandbook\Import\HandbookImport;
use LivingHandbook\Import\MarkdownImportPage;
use WP_Term;
use WP_UnitTestCase;

/**
 * Round trip of a handbook bundle, and the conflict rules on import.
 */
final class BundleTest extends WP_UnitTestCase {

	/**
	 * Run as a content manager, which is who may export and import.
	 *
	 * Without a user the frontend access layer applies to the queries the export
	 * makes: a guest may not view a members-only handbook, so every page would be
	 * filtered out and the export would come back empty. In the backend, where the
	 * export actually runs, that layer steps aside.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Create a handbook (grouping term).
	 *
	 * @param string $name Handbook name.
	 * @return int Term ID.
	 */
	private function make_handbook( string $name ): int {
		$term_id = self::factory()->term->create(
			array(
				'taxonomy' => 'handbook_set',
				'name'     => $name,
			)
		);
		update_term_meta( $term_id, 'living_handbook_visibility', 'members' );
		return (int) $term_id;
	}

	/**
	 * Create a published handbook page in a handbook.
	 *
	 * @param int    $term_id Handbook term ID.
	 * @param string $title   Page title.
	 * @param int    $parent  Parent page ID.
	 * @return int Post ID.
	 */
	private function make_page( int $term_id, string $title, int $parent = 0 ): int {
		$post_id = self::factory()->post->create(
			array(
				'post_type'    => 'handbook',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_parent'  => $parent,
				'post_content' => '<!-- wp:paragraph --><p>Body of ' . $title . '</p><!-- /wp:paragraph -->',
			)
		);
		wp_set_object_terms( $post_id, array( $term_id ), 'handbook_set' );
		return (int) $post_id;
	}

	/**
	 * Export a handbook to a manifest.
	 *
	 * @param int $term_id Handbook term ID.
	 * @param int $root    Optional area root page ID.
	 * @return array<string, mixed>
	 */
	private function export( int $term_id, int $root = 0 ): array {
		$term = get_term( $term_id, 'handbook_set' );
		$this->assertInstanceOf( WP_Term::class, $term );
		$media = array();
		return ( new HandbookExport() )->build_manifest( $term, $media, $root );
	}

	/**
	 * Import a manifest without media.
	 *
	 * @param array<string, mixed> $manifest Manifest.
	 * @param string               $rule     Run rule.
	 * @param int                  $chosen   Target handbook term ID, or 0.
	 * @return array<string, mixed>
	 */
	private function import( array $manifest, string $rule, int $chosen = 0 ): array {
		return ( new HandbookImport() )->import_manifest(
			$manifest,
			static function ( string $file ) {
				unset( $file );
				return false;
			},
			$rule,
			$chosen
		);
	}

	/**
	 * The IDs of the pages in a handbook.
	 *
	 * @param int $term_id Handbook term ID.
	 * @return int[]
	 */
	private function pages_in( int $term_id ): array {
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'      => 'handbook',
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						array(
							'taxonomy' => 'handbook_set',
							'field'    => 'term_id',
							'terms'    => $term_id,
						),
					),
				)
			)
		);
	}

	/**
	 * Find a page by title among a set of IDs.
	 *
	 * @param int[]  $ids   Post IDs.
	 * @param string $title Title to find.
	 * @return int Post ID, or 0.
	 */
	private function find_by_title( array $ids, string $title ): int {
		foreach ( $ids as $id ) {
			if ( get_the_title( $id ) === $title ) {
				return (int) $id;
			}
		}
		return 0;
	}

	/**
	 * A bundle imported into a different handbook recreates the pages and keeps
	 * the parent-child structure.
	 *
	 * @return void
	 */
	public function test_import_into_another_handbook_recreates_hierarchy(): void {
		$source = $this->make_handbook( 'Source' );
		$parent = $this->make_page( $source, 'Parent page' );
		$this->make_page( $source, 'Child page', $parent );

		$manifest = $this->export( $source );
		$this->assertCount( 2, $manifest['pages'], 'the export should carry both pages' );

		$target = $this->make_handbook( 'Target' );

		$report = $this->import( $manifest, HandbookImport::RULE_SKIP, $target );

		$this->assertSame( 2, $report['created'], 'report: ' . (string) wp_json_encode( $report ) );

		$ids = $this->pages_in( $target );
		$this->assertCount( 2, $ids, 'report: ' . (string) wp_json_encode( $report ) );

		$new_parent = $this->find_by_title( $ids, 'Parent page' );
		$new_child  = $this->find_by_title( $ids, 'Child page' );
		$this->assertGreaterThan( 0, $new_parent );
		$this->assertGreaterThan( 0, $new_child );

		$child_post = get_post( $new_child );
		$this->assertNotNull( $child_post );
		$this->assertSame( $new_parent, (int) $child_post->post_parent );
	}

	/**
	 * The default rule never overwrites: a page that already exists is left as it
	 * is, even when the bundle carries a different version of it.
	 *
	 * @return void
	 */
	public function test_skip_rule_leaves_an_existing_page_untouched(): void {
		$handbook = $this->make_handbook( 'Source' );
		$page     = $this->make_page( $handbook, 'From the bundle' );

		$manifest = $this->export( $handbook );
		wp_update_post(
			array(
				'ID'         => $page,
				'post_title' => 'Changed here',
			)
		);

		$report = $this->import( $manifest, HandbookImport::RULE_SKIP );

		$this->assertSame( 1, $report['skipped'] );
		$this->assertSame( 0, $report['created'] );
		$this->assertSame( 'Changed here', get_the_title( $page ) );
	}

	/**
	 * The update rule refreshes the content, but leaves this site's own review
	 * data alone, because that is local upkeep.
	 *
	 * @return void
	 */
	public function test_update_rule_refreshes_content_but_keeps_local_review_data(): void {
		$handbook = $this->make_handbook( 'Source' );
		$page     = $this->make_page( $handbook, 'From the bundle' );

		$manifest = $this->export( $handbook );
		wp_update_post(
			array(
				'ID'         => $page,
				'post_title' => 'Changed here',
			)
		);
		update_post_meta( $page, 'living_handbook_last_reviewed', '2026-01-01' );

		$report = $this->import( $manifest, HandbookImport::RULE_UPDATE );

		$this->assertSame( 1, $report['updated'] );
		$this->assertSame( 'From the bundle', get_the_title( $page ) );
		$this->assertSame( '2026-01-01', get_post_meta( $page, 'living_handbook_last_reviewed', true ) );
	}

	/**
	 * A page pinned with the protected flag is never overwritten, even when the
	 * run rule says update.
	 *
	 * @return void
	 */
	public function test_protected_page_is_never_overwritten(): void {
		$handbook = $this->make_handbook( 'Source' );
		$page     = $this->make_page( $handbook, 'From the bundle' );

		$manifest = $this->export( $handbook );
		wp_update_post(
			array(
				'ID'         => $page,
				'post_title' => 'Pinned here',
			)
		);
		update_post_meta( $page, HandbookImport::META_PROTECTED, '1' );

		$report = $this->import( $manifest, HandbookImport::RULE_UPDATE );

		$this->assertSame( 1, $report['protected'] );
		$this->assertSame( 0, $report['updated'] );
		$this->assertSame( 'Pinned here', get_the_title( $page ) );
	}

	/**
	 * The create rule always adds new pages, so a bundle can be cloned next to the
	 * pages it came from.
	 *
	 * @return void
	 */
	public function test_create_rule_always_adds_new_pages(): void {
		$handbook = $this->make_handbook( 'Source' );
		$this->make_page( $handbook, 'Only page' );

		$manifest = $this->export( $handbook );
		$report   = $this->import( $manifest, HandbookImport::RULE_CREATE );

		$this->assertSame( 1, $report['created'] );
		$this->assertCount( 2, $this->pages_in( $handbook ) );
	}

	/**
	 * A taxonomy term travels with the page and is created on the target when it
	 * is missing there.
	 *
	 * @return void
	 */
	public function test_taxonomy_terms_travel_with_the_page(): void {
		$handbook = $this->make_handbook( 'Source' );
		$page     = $this->make_page( $handbook, 'Tagged page' );

		$created = wp_insert_term( 'Guide', 'handbook_type', array( 'slug' => 'guide' ) );
		$this->assertIsArray( $created );
		wp_set_object_terms( $page, array( (int) $created['term_id'] ), 'handbook_type' );

		$manifest = $this->export( $handbook );
		$target   = $this->make_handbook( 'Target' );
		$report   = $this->import( $manifest, HandbookImport::RULE_SKIP, $target );

		$ids = $this->pages_in( $target );
		$this->assertCount( 1, $ids, 'report: ' . (string) wp_json_encode( $report ) );

		$terms = wp_get_object_terms( $ids[0], 'handbook_type', array( 'fields' => 'slugs' ) );
		$this->assertIsArray( $terms );
		$this->assertContains( 'guide', $terms );
	}

	/**
	 * A password-protected page stays protected on the target: the bundle
	 * carries the password, and the import sets it on a new page and on an
	 * updated one. Up to 0.75.1 the page arrived without it and stood open.
	 *
	 * @return void
	 */
	public function test_a_protected_page_keeps_its_password(): void {
		$handbook = $this->make_handbook( 'Source' );
		$secret   = $this->make_page( $handbook, 'Secret page' );
		$this->make_page( $handbook, 'Open page' );
		wp_update_post(
			array(
				'ID'            => $secret,
				'post_password' => wp_slash( 'pa\\ss "word"' ),
			)
		);

		$manifest = $this->export( $handbook );
		$entries  = array();
		foreach ( $manifest['pages'] as $entry ) {
			$entries[ (string) $entry['title'] ] = $entry;
		}
		$this->assertSame( 'pa\\ss "word"', $entries['Secret page']['password'] ?? null );
		$this->assertArrayNotHasKey( 'password', $entries['Open page'], 'An open page carries no password key, as in older bundles.' );

		$target = $this->make_handbook( 'Target' );
		$this->import( $manifest, HandbookImport::RULE_SKIP, $target );
		$ids  = $this->pages_in( $target );
		$copy = 0;
		$open = 0;
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			$this->assertNotNull( $post );
			if ( 'Secret page' === $post->post_title ) {
				$copy = $id;
			} elseif ( 'Open page' === $post->post_title ) {
				$open = $id;
			}
		}
		$this->assertGreaterThan( 0, $copy );
		$this->assertSame( 'pa\\ss "word"', get_post( $copy )->post_password ?? null, 'A new page gets the password.' );
		$this->assertSame( '', get_post( $open )->post_password ?? null );

		// The update rule: the existing page had lost its password here.
		wp_update_post(
			array(
				'ID'            => $secret,
				'post_password' => '',
			)
		);
		$report = $this->import( $manifest, HandbookImport::RULE_UPDATE, $handbook );
		$this->assertSame( 2, $report['updated'], 'report: ' . (string) wp_json_encode( $report ) );
		$this->assertSame( 'pa\\ss "word"', get_post( $secret )->post_password ?? null, 'An updated page gets the password.' );
	}

	/**
	 * A bundle without the password key, as every bundle up to 0.75.1, leaves
	 * the password of an existing page alone on update.
	 *
	 * @return void
	 */
	public function test_a_bundle_without_password_leaves_an_existing_one_alone(): void {
		$handbook = $this->make_handbook( 'Source' );
		$page     = $this->make_page( $handbook, 'Page' );

		$manifest = $this->export( $handbook );
		$this->assertArrayNotHasKey( 'password', $manifest['pages'][0] );
		wp_update_post(
			array(
				'ID'            => $page,
				'post_password' => 'local',
			)
		);

		$this->import( $manifest, HandbookImport::RULE_UPDATE, $handbook );

		$this->assertSame( 'local', get_post( $page )->post_password ?? null );
	}

	/**
	 * The export carries no personal data: the per-user allowlist of a restricted
	 * handbook stays behind, because a bundle is a file that gets passed around.
	 *
	 * @return void
	 */
	public function test_export_carries_no_user_addresses(): void {
		$handbook = $this->make_handbook( 'Restricted' );
		$user     = self::factory()->user->create(
			array(
				'role'       => 'subscriber',
				'user_email' => 'person@example.com',
			)
		);
		update_term_meta( $handbook, 'living_handbook_visibility', 'restricted' );
		update_term_meta( $handbook, 'living_handbook_users', array( (int) $user ) );
		$this->make_page( $handbook, 'A page' );

		$manifest = $this->export( $handbook );
		$encoded  = (string) wp_json_encode( $manifest );

		$this->assertArrayNotHasKey( 'users', $manifest['handbook'] );
		$this->assertStringNotContainsString( 'person@example.com', $encoded );
	}

	/**
	 * A bundle is a file from another site, so its content is sanitised on the way
	 * in: active markup is removed, while the block delimiters survive, which is the
	 * whole point of cleaning block by block instead of running kses over the lot.
	 *
	 * @return void
	 */
	public function test_bundle_content_is_sanitised_on_import(): void {
		$handbook = $this->make_handbook( 'Source' );
		$this->make_page( $handbook, 'Harmless page' );

		$manifest = $this->export( $handbook );
		$this->assertCount( 1, $manifest['pages'] );

		// Stand in for a prepared bundle: active markup inside a normal block.
		$manifest['pages'][0]['content'] = '<!-- wp:paragraph --><p>Kept text'
			. '<script>alert(1)</script>'
			. '<img src="x" onerror="alert(2)">'
			. '</p><!-- /wp:paragraph -->';

		$target = $this->make_handbook( 'Target' );
		$this->import( $manifest, HandbookImport::RULE_SKIP, $target );

		$ids = $this->pages_in( $target );
		$this->assertCount( 1, $ids );

		$content = (string) get_post_field( 'post_content', $ids[0] );
		$this->assertStringNotContainsString( '<script', $content );
		$this->assertStringNotContainsString( 'onerror', $content );
		$this->assertStringContainsString( 'Kept text', $content );
		$this->assertStringContainsString( 'wp:paragraph', $content );
	}

	/**
	 * An area export carries only the chosen page and its descendants, and the
	 * area root has no parent inside the bundle.
	 *
	 * @return void
	 */
	public function test_area_export_carries_only_the_subtree(): void {
		$handbook = $this->make_handbook( 'Source' );
		$area     = $this->make_page( $handbook, 'Area root' );
		$this->make_page( $handbook, 'Inside the area', $area );
		$this->make_page( $handbook, 'Outside the area' );

		$manifest = $this->export( $handbook, $area );

		$this->assertSame( 'area', $manifest['scope'] );
		$this->assertCount( 2, $manifest['pages'] );

		$titles = array_map(
			static function ( $page ) {
				return $page['title'];
			},
			$manifest['pages']
		);
		$this->assertContains( 'Area root', $titles );
		$this->assertContains( 'Inside the area', $titles );
		$this->assertNotContains( 'Outside the area', $titles );

		foreach ( $manifest['pages'] as $page ) {
			if ( 'Area root' === $page['title'] ) {
				$this->assertNull( $page['parent_key'] );
			}
		}
	}

	/**
	 * An area the exporting user may not view is refused, rather than the
	 * whole handbook going out under a file name that names the area.
	 *
	 * @return void
	 */
	public function test_an_area_the_user_may_not_view_is_not_exported(): void {
		$handbook = $this->make_handbook( 'Source' );
		$area     = $this->make_page( $handbook, 'Hidden area' );
		$this->make_page( $handbook, 'Elsewhere' );

		add_filter(
			'living_handbook_can_view_post',
			static function ( $allowed, $post_id ) use ( $area ) {
				return $area === (int) $post_id ? false : $allowed;
			},
			10,
			2
		);
		// Reaching the page query means the export went ahead.
		add_action(
			'pre_get_posts',
			static function (): void {
				throw new \RuntimeException( 'The export went ahead.' );
			}
		);

		$_POST['handbook']     = (string) $handbook;
		$_POST['area']         = (string) $area;
		$_REQUEST['_wpnonce']  = wp_create_nonce( 'living_handbook_export' );
		$_POST['_wpnonce']     = $_REQUEST['_wpnonce'];

		try {
			( new HandbookExport() )->download();
			$this->fail( 'The download did not stop.' );
		} catch ( \WPDieException $e ) {
			$this->assertStringContainsString( 'cannot be exported', $e->getMessage() );
		} finally {
			unset( $_POST['handbook'], $_POST['area'], $_POST['_wpnonce'], $_REQUEST['_wpnonce'] );
		}
	}

	/**
	 * A bundle is checked against its own limits before it is read. Up to
	 * 0.75.0 it had none, so a small prepared archive could unpack to whatever
	 * its entries claimed; the first limits were the Markdown ZIP import's,
	 * sized for text, which refused bundles with videos that the export had
	 * written without complaint. The refusal names the filter that changes it.
	 *
	 * @return void
	 */
	public function test_a_bundle_beyond_the_bundle_limits_is_refused(): void {
		$path = (string) tempnam( sys_get_temp_dir(), 'lh-test-bundle' );
		$zip  = new \ZipArchive();
		$zip->open( $path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE );
		$zip->addFromString( 'manifest.json', '{"format":"living-handbook-bundle"}' );
		$zip->addFromString( 'media/1-big.pdf', str_repeat( 'x', 8192 ) );
		$zip->close();
		$zip->open( $path );

		$small = static function (): int {
			return 4096;
		};
		add_filter( 'living_handbook_zip_max_bytes', $small );
		$past_zip_limit = HandbookImport::check_bounds( $zip );
		remove_filter( 'living_handbook_zip_max_bytes', $small );

		add_filter( 'living_handbook_bundle_max_bytes', $small );
		$too_large = HandbookImport::check_bounds( $zip );
		remove_filter( 'living_handbook_bundle_max_bytes', $small );

		$one = static function (): int {
			return 1;
		};
		add_filter( 'living_handbook_bundle_max_entries', $one );
		$too_many = HandbookImport::check_bounds( $zip );
		remove_filter( 'living_handbook_bundle_max_entries', $one );

		$within = HandbookImport::check_bounds( $zip );
		$zip->close();
		wp_delete_file( $path );

		$this->assertSame( '', $past_zip_limit, 'The Markdown ZIP limit does not apply to a bundle.' );
		$this->assertStringContainsString( 'living_handbook_bundle_max_bytes', $too_large, 'Past the uncompressed total the bundle is refused, naming the filter.' );
		$this->assertStringContainsString( 'living_handbook_bundle_max_entries', $too_many, 'Past the entry count the bundle is refused, naming the filter.' );
		$this->assertSame( '', $within, 'Under the default limits the same bundle passes.' );
		$this->assertGreaterThan( MarkdownImportPage::ZIP_MAX_TOTAL_BYTES, HandbookImport::BUNDLE_MAX_TOTAL_BYTES );
		$this->assertGreaterThan( MarkdownImportPage::ZIP_MAX_ENTRIES, HandbookImport::BUNDLE_MAX_ENTRIES );
	}

	/**
	 * A file of the bundle that does not arrive is named in the report: one
	 * that is refused, and one the manifest lists but the archive lacks. Up to
	 * 0.75.1 both were dropped without a word, and the page kept pointing at
	 * the old site.
	 *
	 * @return void
	 */
	public function test_a_file_that_does_not_arrive_is_named_in_the_report(): void {
		$target   = $this->make_handbook( 'Media target' );
		$manifest = array(
			'format'   => 'living-handbook-bundle',
			'handbook' => array( 'name' => 'Media source' ),
			'pages'    => array(),
			'media'    => array(
				array(
					'file'         => 'media/1-page.html',
					'original_url' => 'https://old.example/wp-content/uploads/page.html',
				),
				array(
					'file'         => 'media/2-gone.pdf',
					'original_url' => 'https://old.example/wp-content/uploads/gone.pdf',
				),
			),
		);

		$report = ( new HandbookImport() )->import_manifest(
			$manifest,
			static function ( string $file ) {
				return 'media/1-page.html' === $file ? '<script>alert(1)</script>' : false;
			},
			HandbookImport::RULE_SKIP,
			$target
		);

		$notes = implode( "\n", (array) $report['notes'] );
		$this->assertStringContainsString( '1-page.html', $notes, 'A refused file is named.' );
		$this->assertStringContainsString( '2-gone.pdf', $notes, 'A missing file is named.' );
		$this->assertStringContainsString( 'This file from the bundle was not imported', $notes, 'One file is one file.' );
		$this->assertStringContainsString( 'This file is listed in the bundle but missing from it: 2-gone.pdf.', $notes );
	}

	/**
	 * A file of the bundle that is too large to hold in memory is not read, and
	 * the report names it; the rest of the bundle is imported. Up to 0.75.1
	 * every file was unpacked into memory whatever its size, and one video
	 * past memory_limit ended the import in a fatal error half-way.
	 *
	 * @return void
	 */
	public function test_a_file_too_large_to_read_is_left_out_and_named(): void {
		$path = (string) tempnam( sys_get_temp_dir(), 'lh-test-bundle' );
		$zip  = new \ZipArchive();
		$zip->open( $path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE );
		$zip->addFromString( 'media/1-small.pdf', "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n" );
		$zip->addFromString( 'media/2-video.pdf', "%PDF-1.4\n" . str_repeat( 'x', 8192 ) . "\n%%EOF\n" );
		$zip->close();
		$zip->open( $path );

		$target   = $this->make_handbook( 'Large target' );
		$manifest = array(
			'format'   => 'living-handbook-bundle',
			'handbook' => array( 'name' => 'Large source' ),
			'pages'    => array(),
			'media'    => array(
				array(
					'file'         => 'media/1-small.pdf',
					'original_url' => 'https://old.example/wp-content/uploads/small.pdf',
				),
				array(
					'file'         => 'media/2-video.pdf',
					'original_url' => 'https://old.example/wp-content/uploads/video.pdf',
				),
			),
		);

		$limit = static function (): int {
			return 4096;
		};
		add_filter( 'living_handbook_bundle_max_file_bytes', $limit );
		$import = new \ReflectionMethod( HandbookImport::class, 'import_bundle' );
		$import->setAccessible( true );
		$report = $import->invoke( new HandbookImport(), $manifest, $zip, HandbookImport::RULE_SKIP, $target );
		remove_filter( 'living_handbook_bundle_max_file_bytes', $limit );
		$zip->close();
		wp_delete_file( $path );

		$notes = implode( "\n", (array) $report['notes'] );
		$this->assertStringContainsString( 'too large', $notes );
		$this->assertStringContainsString( '2-video.pdf', $notes, 'The file left out is named.' );
		$this->assertStringNotContainsString( '1-small.pdf', $notes, 'The small file is imported.' );
		$this->assertLessThanOrEqual( 128 * MB_IN_BYTES, HandbookImport::max_file_bytes(), 'Never more than 128 MB by default.' );
	}

	/**
	 * The per-file limit is the smaller of 128 MB and a quarter of the free
	 * memory, and 128 MB without a memory limit.
	 *
	 * @return void
	 */
	public function test_the_file_limit_follows_the_free_memory(): void {
		$before = (string) ini_get( 'memory_limit' );

		ini_set( 'memory_limit', '-1' ); // phpcs:ignore WordPress.PHP.IniSet.memory_limit_Disallowed -- the setting under test.
		$unlimited = HandbookImport::max_file_bytes();
		ini_set( 'memory_limit', (string) ( memory_get_usage() + 40 * MB_IN_BYTES ) ); // phpcs:ignore WordPress.PHP.IniSet.memory_limit_Disallowed -- the setting under test.
		$tight = HandbookImport::max_file_bytes();
		ini_set( 'memory_limit', $before ); // phpcs:ignore WordPress.PHP.IniSet.memory_limit_Disallowed -- restoring it.

		$this->assertSame( 128 * MB_IN_BYTES, $unlimited );
		$this->assertLessThanOrEqual( 10 * MB_IN_BYTES, $tight, 'A quarter of about 40 MB free.' );
		$this->assertGreaterThan( 9 * MB_IN_BYTES, $tight );
	}
}
