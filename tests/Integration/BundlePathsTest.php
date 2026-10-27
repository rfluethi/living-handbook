<?php
/**
 * Learning paths travel in a handbook bundle.
 *
 * A path is an ordered list of page IDs, and IDs mean nothing on another site.
 * These tests hold the bundle to carrying the order as page keys and turning it
 * back into this site's IDs on the way in, so a path arrives pointing at the
 * pages that arrived with it, not at whatever carries the old numbers here.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\Import\HandbookExport;
use LivingHandbook\Import\HandbookImport;
use LivingHandbook\Import\MarkdownImportPage;
use LivingHandbook\PostType\Handbook;
use LivingHandbook\Training\Lessons;
use LivingHandbook\Training\Training;
use WP_Term;
use WP_UnitTestCase;

/**
 * Export and import of learning paths with HandbookExport and HandbookImport.
 */
final class BundlePathsTest extends WP_UnitTestCase {

	/**
	 * Run as a content manager, who may export and import (see BundleTest).
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Leave the module switch and the access filter as the suite expects them.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		delete_option( Training::OPTION_ENABLED );
		remove_all_filters( 'living_handbook_can_view_post' );
		parent::tear_down();
	}

	/**
	 * A members-only handbook.
	 *
	 * @param string $name Handbook name.
	 * @return int Term ID.
	 */
	private function handbook( string $name ): int {
		$term_id = (int) self::factory()->term->create(
			array(
				'taxonomy' => Handbooks::TAXONOMY,
				'name'     => $name,
			)
		);
		update_term_meta( $term_id, Handbooks::META_VISIBILITY, Handbooks::VISIBILITY_MEMBERS );
		return $term_id;
	}

	/**
	 * A published handbook page.
	 *
	 * @param int    $handbook Handbook term ID.
	 * @param string $title    Title, which also gives the slug.
	 * @param int    $parent   Parent page ID.
	 * @return int
	 */
	private function page( int $handbook, string $title, int $parent = 0 ): int {
		$id = (int) self::factory()->post->create(
			array(
				'post_type'   => Handbook::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_parent' => $parent,
			)
		);
		wp_set_object_terms( $id, array( $handbook ), Handbooks::TAXONOMY );
		return $id;
	}

	/**
	 * A learning path in a handbook with its lessons.
	 *
	 * @param int        $handbook Handbook term ID.
	 * @param string     $title    Title, which also gives the slug.
	 * @param array<int> $lessons  Lesson page IDs in order.
	 * @param string     $status   Post status.
	 * @return int
	 */
	private function path( int $handbook, string $title, array $lessons, string $status = 'publish' ): int {
		$id = (int) self::factory()->post->create(
			array(
				'post_type'    => Training::POST_TYPE,
				'post_status'  => $status,
				'post_title'   => $title,
				'post_excerpt' => 'Why ' . $title,
				'post_content' => '<!-- wp:paragraph --><p>Intro of ' . $title . '</p><!-- /wp:paragraph -->',
			)
		);
		wp_set_object_terms( $id, array( $handbook ), Handbooks::TAXONOMY );
		Lessons::store( $id, $lessons );
		return $id;
	}

	/**
	 * Export a handbook, or one of its areas, to a manifest.
	 *
	 * @param int $handbook Handbook term ID.
	 * @param int $root     Area root page ID, or 0.
	 * @return array<string, mixed>
	 */
	private function export( int $handbook, int $root = 0 ): array {
		$term = get_term( $handbook, Handbooks::TAXONOMY );
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
	 * @param bool                 $as_pages Import as ordinary pages.
	 * @return array<string, mixed>
	 */
	private function import( array $manifest, string $rule, int $chosen = 0, bool $as_pages = false ): array {
		return ( new HandbookImport() )->import_manifest(
			$manifest,
			static function ( string $file ) {
				unset( $file );
				return false;
			},
			$rule,
			$chosen,
			$as_pages
		);
	}

	/**
	 * The posts of one type in a handbook.
	 *
	 * @param string $type     Post type.
	 * @param int    $handbook Handbook term ID.
	 * @return array<int, int>
	 */
	private function in_handbook( string $type, int $handbook ): array {
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'      => $type,
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'no_found_rows'  => true,
					'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						array(
							'taxonomy' => Handbooks::TAXONOMY,
							'field'    => 'term_id',
							'terms'    => $handbook,
						),
					),
				)
			)
		);
	}

	/**
	 * The path entries of a manifest, keyed by title.
	 *
	 * @param array<string, mixed> $manifest Manifest.
	 * @return array<string, array<string, mixed>>
	 */
	private function paths_by_title( array $manifest ): array {
		$this->assertArrayHasKey( 'paths', $manifest, 'The manifest carries the learning paths.' );
		$out = array();
		foreach ( $manifest['paths'] as $path ) {
			$out[ (string) $path['title'] ] = $path;
		}
		return $out;
	}

	/**
	 * Find a post by title among IDs.
	 *
	 * @param array<int, int> $ids   Post IDs.
	 * @param string          $title Title.
	 * @return int
	 */
	private function by_title( array $ids, string $title ): int {
		foreach ( $ids as $id ) {
			if ( get_the_title( $id ) === $title ) {
				return $id;
			}
		}
		return 0;
	}

	/**
	 * Two handbooks with pages and paths, the shape most tests start from.
	 *
	 * Basics and Tools are top-level, Hammer sits under Tools. "Start" reads
	 * Hammer, then Basics, then a page of another handbook; "Tooling" reads
	 * Tools and Hammer; "Elsewhere" reads only Basics.
	 *
	 * @return array<string, int>
	 */
	private function fixture(): array {
		$source = $this->handbook( 'Source' );
		$other  = $this->handbook( 'Other' );

		$basics  = $this->page( $source, 'Basics' );
		$tools   = $this->page( $source, 'Tools' );
		$hammer  = $this->page( $source, 'Hammer', $tools );
		$foreign = $this->page( $other, 'Foreign' );

		return array(
			'source'    => $source,
			'basics'    => $basics,
			'tools'     => $tools,
			'hammer'    => $hammer,
			'start'     => $this->path( $source, 'Start', array( $hammer, $basics, $foreign ) ),
			'tooling'   => $this->path( $source, 'Tooling', array( $tools, $hammer ), 'draft' ),
			'elsewhere' => $this->path( $source, 'Elsewhere', array( $basics ) ),
			'foreign'   => $this->path( $other, 'Not this handbook', array( $foreign ) ),
		);
	}

	/**
	 * A whole-handbook export carries every path of the handbook, with its
	 * lessons as page keys in the stored order, and a lesson that is not among
	 * the exported pages left out.
	 *
	 * @return void
	 */
	public function test_whole_handbook_export_carries_paths_with_page_keys_in_order(): void {
		$f        = $this->fixture();
		$manifest = $this->export( $f['source'] );
		$paths    = $this->paths_by_title( $manifest );

		$this->assertSame( array( 'Elsewhere', 'Start', 'Tooling' ), array_keys( $paths ), 'Only this handbook\'s paths, drafts included.' );

		$page_keys = array_column( $manifest['pages'], 'key' );
		$this->assertContains( 'tools/hammer', $page_keys );

		$this->assertSame( array( 'tools/hammer', 'basics' ), $paths['Start']['lessons'], 'Order kept, the page of another handbook dropped.' );
		$this->assertSame( array( 'tools', 'tools/hammer' ), $paths['Tooling']['lessons'] );

		$start = $paths['Start'];
		$this->assertSame( 'path:start', $start['key'] );
		$this->assertSame( home_url() . '#' . $f['start'], $start['origin_id'] );
		$this->assertSame( 'start', $start['slug'] );
		$this->assertSame( 'publish', $start['status'] );
		$this->assertSame( 'Why Start', $start['excerpt'] );
		$this->assertStringContainsString( 'Intro of Start', $start['content'] );
		$this->assertSame( 'draft', $paths['Tooling']['status'] );
		$this->assertSame( 1, $manifest['version'], 'The paths are an optional addition to version 1.' );
	}

	/**
	 * An area export keeps only the lessons inside the area, and a path with no
	 * lesson left there is not exported at all.
	 *
	 * @return void
	 */
	public function test_area_export_keeps_only_lessons_inside_the_area(): void {
		$f        = $this->fixture();
		$manifest = $this->export( $f['source'], $f['tools'] );
		$paths    = $this->paths_by_title( $manifest );

		$this->assertSame( 'area', $manifest['scope'] );
		$this->assertArrayNotHasKey( 'Elsewhere', $paths, 'A path whose lessons are all outside the area stays behind.' );
		$this->assertSame( array( 'tools/hammer' ), $paths['Start']['lessons'] );
		$this->assertSame( array( 'tools', 'tools/hammer' ), $paths['Tooling']['lessons'] );
	}

	/**
	 * A path the exporting user may not view stays out of the bundle, decided by
	 * the same rule as everywhere else.
	 *
	 * @return void
	 */
	public function test_export_leaves_out_a_path_the_user_may_not_view(): void {
		$f = $this->fixture();
		add_filter(
			'living_handbook_can_view_post',
			static function ( bool $allowed, int $post_id ) use ( $f ): bool {
				return $post_id === $f['tooling'] ? false : $allowed;
			},
			10,
			2
		);

		$paths = $this->paths_by_title( $this->export( $f['source'] ) );

		$this->assertArrayNotHasKey( 'Tooling', $paths );
		$this->assertArrayHasKey( 'Start', $paths );
	}

	/**
	 * The pages of a bundle are asked the same question as its paths: a page
	 * the filter hides stays out, and a path that had it as a lesson loses it.
	 * Up to 0.75.1 the bundle carried the page the site had just hidden.
	 *
	 * @return void
	 */
	public function test_export_leaves_out_a_page_the_user_may_not_view(): void {
		$f = $this->fixture();
		add_filter(
			'living_handbook_can_view_post',
			static function ( bool $allowed, int $post_id ) use ( $f ): bool {
				return $post_id === $f['hammer'] ? false : $allowed;
			},
			10,
			2
		);

		$manifest = $this->export( $f['source'] );
		$titles   = wp_list_pluck( (array) $manifest['pages'], 'title' );

		$this->assertNotContains( 'Hammer', $titles );
		$this->assertContains( 'Basics', $titles );
	}

	/**
	 * A path has an editor of its own, so an image in its introduction travels
	 * like an image on a page.
	 *
	 * @return void
	 */
	public function test_an_image_in_a_path_travels_with_the_bundle(): void {
		$image = imagecreatetruecolor( 20, 20 );
		ob_start();
		imagejpeg( $image );
		$upload = wp_upload_bits( 'intro-' . wp_generate_password( 6, false, false ) . '.jpg', null, (string) ob_get_clean() );
		$this->assertEmpty( $upload['error'] );
		$attachment = (int) wp_insert_attachment(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Intro',
				'post_status'    => 'inherit',
			),
			(string) $upload['file']
		);
		$url = (string) wp_get_attachment_url( $attachment );

		$source = $this->handbook( 'Source' );
		$path   = $this->path( $source, 'Pictured', array( $this->page( $source, 'Basics' ) ) );
		wp_update_post(
			array(
				'ID'           => $path,
				'post_content' => '<!-- wp:image --><figure class="wp-block-image"><img src="' . esc_url( $url ) . '" alt=""/></figure><!-- /wp:image -->',
			)
		);

		$manifest = $this->export( $source );
		$paths    = $this->paths_by_title( $manifest );

		$this->assertSame( array( $url ), array_column( $manifest['media'], 'original_url' ) );
		$this->assertSame( array_column( $manifest['media'], 'file' ), $paths['Pictured']['media'] );

		wp_delete_file( (string) $upload['file'] );
	}

	/**
	 * Export, import into another handbook: the path exists there, in that
	 * handbook, and its lessons are the new pages, in order.
	 *
	 * @return void
	 */
	public function test_round_trip_points_the_path_at_the_new_pages(): void {
		$f        = $this->fixture();
		$manifest = $this->export( $f['source'] );
		$target   = $this->handbook( 'Target' );

		$report = $this->import( $manifest, HandbookImport::RULE_SKIP, $target );

		$this->assertSame( 3, $report['created'], 'The page counter counts pages only.' );
		$this->assertSame( 3, $report['paths']['created'] );

		$pages = $this->in_handbook( Handbook::POST_TYPE, $target );
		$paths = $this->in_handbook( Training::POST_TYPE, $target );
		$this->assertCount( 3, $paths );

		$start = $this->by_title( $paths, 'Start' );
		$this->assertGreaterThan( 0, $start );
		$this->assertNotSame( $f['start'], $start );
		$this->assertSame( $target, Handbooks::for_post( $start ) );

		$new_hammer = $this->by_title( $pages, 'Hammer' );
		$new_basics = $this->by_title( $pages, 'Basics' );
		$this->assertNotSame( $f['hammer'], $new_hammer );
		$this->assertSame( array( $new_hammer, $new_basics ), Lessons::stored( $start ) );
		$this->assertSame( 'Why Start', get_post_field( 'post_excerpt', $start ) );
		$this->assertSame( 'publish', get_post_status( $start ) );
		$this->assertSame( 'draft', get_post_status( $this->by_title( $paths, 'Tooling' ) ) );
	}

	/**
	 * Importing the same bundle again finds the path it created and updates it,
	 * instead of adding a second one.
	 *
	 * @return void
	 */
	public function test_reimport_updates_the_path_instead_of_duplicating_it(): void {
		$f        = $this->fixture();
		$manifest = $this->export( $f['source'] );
		$target   = $this->handbook( 'Target' );
		$this->import( $manifest, HandbookImport::RULE_SKIP, $target );

		$paths = $this->in_handbook( Training::POST_TYPE, $target );
		$start = $this->by_title( $paths, 'Start' );
		wp_update_post(
			array(
				'ID'         => $start,
				'post_title' => 'Changed here',
			)
		);

		$skipped = $this->import( $manifest, HandbookImport::RULE_SKIP, $target );
		$this->assertSame( 3, $skipped['paths']['skipped'] );
		$this->assertSame( 'Changed here', get_the_title( $start ) );

		$updated = $this->import( $manifest, HandbookImport::RULE_UPDATE, $target );
		$this->assertSame( 3, $updated['paths']['updated'] );
		$this->assertSame( 0, $updated['paths']['created'] );
		$this->assertSame( $paths, $this->in_handbook( Training::POST_TYPE, $target ), 'No second copy of any path.' );
		$this->assertSame( 'Start', get_the_title( $start ) );
	}

	/**
	 * An update run into a parent handbook matches only what sits in the parent
	 * itself. The lookup used to include the child handbooks, so the copies an
	 * earlier import had put into a child handbook were found by origin,
	 * overwritten and moved into the parent: pages and paths alike.
	 *
	 * @return void
	 */
	public function test_update_into_a_parent_handbook_leaves_a_child_handbooks_copies_alone(): void {
		$f        = $this->fixture();
		$manifest = $this->export( $f['source'] );
		$parent   = $this->handbook( 'Parent' );
		$child    = (int) self::factory()->term->create(
			array(
				'taxonomy' => Handbooks::TAXONOMY,
				'name'     => 'Child',
				'parent'   => $parent,
			)
		);
		$this->import( $manifest, HandbookImport::RULE_UPDATE, $child );

		$child_page = $this->by_title( $this->in_handbook( Handbook::POST_TYPE, $child ), 'Basics' );
		$child_path = $this->by_title( $this->in_handbook( Training::POST_TYPE, $child ), 'Start' );
		$this->assertGreaterThan( 0, $child_page );
		$this->assertGreaterThan( 0, $child_path );
		wp_update_post(
			array(
				'ID'         => $child_page,
				'post_title' => 'Child page edited',
			)
		);
		wp_update_post(
			array(
				'ID'         => $child_path,
				'post_title' => 'Child path edited',
			)
		);

		$report = $this->import( $manifest, HandbookImport::RULE_UPDATE, $parent );

		$this->assertSame( 0, $report['updated'], 'No page of the child handbook is taken over.' );
		$this->assertSame( 0, $report['paths']['updated'], 'No path of the child handbook is taken over.' );
		foreach ( array( $child_page, $child_path ) as $id ) {
			$terms = wp_get_object_terms( $id, Handbooks::TAXONOMY, array( 'fields' => 'ids' ) );
			$this->assertSame( array( $child ), array_map( 'intval', (array) $terms ), 'The copy stays in the child handbook.' );
		}
		$this->assertSame( 'Child page edited', get_the_title( $child_page ) );
		$this->assertSame( 'Child path edited', get_the_title( $child_path ) );
	}

	/**
	 * A path pinned as protected is not overwritten by an update run.
	 *
	 * @return void
	 */
	public function test_protected_path_is_not_overwritten(): void {
		$f        = $this->fixture();
		$manifest = $this->export( $f['source'] );
		wp_update_post(
			array(
				'ID'         => $f['start'],
				'post_title' => 'Pinned here',
			)
		);
		update_post_meta( $f['start'], HandbookImport::META_PROTECTED, '1' );

		$report = $this->import( $manifest, HandbookImport::RULE_UPDATE );

		$this->assertSame( 1, $report['paths']['protected'] );
		$this->assertSame( 'Pinned here', get_the_title( $f['start'] ) );
	}

	/**
	 * Imported as ordinary pages there is nothing a path could become: no path
	 * is created, and the report says so.
	 *
	 * @return void
	 */
	public function test_import_as_pages_brings_no_paths_and_says_so(): void {
		$f        = $this->fixture();
		$manifest = $this->export( $f['source'] );
		$before   = wp_count_posts( Training::POST_TYPE );

		$report = $this->import( $manifest, HandbookImport::RULE_CREATE, 0, true );

		$this->assertSame( 0, $report['paths']['created'] );
		$this->assertEquals( $before, wp_count_posts( Training::POST_TYPE ) );
		$this->assertNotEmpty(
			array_filter(
				$report['notes'],
				static function ( $note ) {
					return false !== strpos( (string) $note, 'learning paths in the bundle were not imported' );
				}
			)
		);
	}

	/**
	 * A lesson whose key is not among the bundle's pages drops out of the path,
	 * and the report names the path.
	 *
	 * @return void
	 */
	public function test_unknown_lesson_key_drops_out_with_a_note(): void {
		$f        = $this->fixture();
		$manifest = $this->export( $f['source'] );
		foreach ( $manifest['paths'] as $i => $path ) {
			if ( 'Start' === $path['title'] ) {
				$manifest['paths'][ $i ]['lessons'] = array( 'basics', 'no/such-page', 'tools/hammer' );
			}
		}
		$target = $this->handbook( 'Target' );

		$report = $this->import( $manifest, HandbookImport::RULE_SKIP, $target );

		$pages = $this->in_handbook( Handbook::POST_TYPE, $target );
		$start = $this->by_title( $this->in_handbook( Training::POST_TYPE, $target ), 'Start' );
		$this->assertSame(
			array( $this->by_title( $pages, 'Basics' ), $this->by_title( $pages, 'Hammer' ) ),
			Lessons::stored( $start )
		);
		$this->assertContains( 'Learning path "Start": 1 lesson is not among the pages of the bundle and was left out.', $report['notes'] );
	}

	/**
	 * Path content is external content and is cleaned like a page's.
	 *
	 * @return void
	 */
	public function test_path_content_is_sanitised_on_import(): void {
		$f        = $this->fixture();
		$manifest = $this->export( $f['source'] );
		foreach ( $manifest['paths'] as $i => $path ) {
			$manifest['paths'][ $i ]['content'] = '<!-- wp:paragraph --><p>Kept<script>alert(1)</script></p><!-- /wp:paragraph -->';
		}
		$target = $this->handbook( 'Target' );
		$this->import( $manifest, HandbookImport::RULE_SKIP, $target );

		$start   = $this->by_title( $this->in_handbook( Training::POST_TYPE, $target ), 'Start' );
		$content = (string) get_post_field( 'post_content', $start );
		$this->assertStringNotContainsString( '<script', $content );
		$this->assertStringContainsString( 'Kept', $content );
		$this->assertStringContainsString( 'wp:paragraph', $content );
	}

	/**
	 * With the module switched off the paths are still imported, and the report
	 * says where they will show up; with it on, that note is not given.
	 *
	 * @return void
	 */
	public function test_paths_import_with_the_module_off_and_a_note_says_so(): void {
		$f        = $this->fixture();
		$manifest = $this->export( $f['source'] );
		$note     = 'Learning paths are switched off on this site. The imported paths are kept and appear once learning paths are switched on in the settings.';

		delete_option( Training::OPTION_ENABLED );
		$off = $this->import( $manifest, HandbookImport::RULE_SKIP, $this->handbook( 'Off' ) );
		$this->assertSame( 3, $off['paths']['created'] );
		$this->assertContains( $note, $off['notes'] );

		update_option( Training::OPTION_ENABLED, 1 );
		$on = $this->import( $manifest, HandbookImport::RULE_SKIP, $this->handbook( 'On' ) );
		$this->assertSame( 3, $on['paths']['created'] );
		$this->assertNotContains( $note, $on['notes'] );
	}

	/**
	 * The result screen names the paths when the bundle had any, and stays as it
	 * was when it had none.
	 *
	 * @return void
	 */
	public function test_result_notice_names_the_paths(): void {
		set_current_screen( 'handbook_page_' . MarkdownImportPage::MENU_SLUG );
		$key    = 'living_handbook_bundle_report_' . get_current_user_id();
		$import = new HandbookImport();

		$report = array(
			'created'   => 2,
			'updated'   => 0,
			'skipped'   => 0,
			'protected' => 0,
			'paths'     => array(
				'created'   => 2,
				'updated'   => 0,
				'skipped'   => 1,
				'protected' => 0,
			),
			'notes'     => array(),
		);
		set_transient( $key, $report );
		ob_start();
		$import->result_notice();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( '3 learning paths: 2 created, 0 updated, 1 skipped, 0 protected.', $html );

		unset( $report['paths'] );
		set_transient( $key, $report );
		ob_start();
		$import->result_notice();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'Bundle imported: 2 created', $html );
		$this->assertStringNotContainsString( 'learning path', $html );

		set_current_screen( 'front' );
	}
}
