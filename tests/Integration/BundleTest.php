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
	 * A vocabulary term travels with the page and is created on the target when it
	 * is missing there.
	 *
	 * @return void
	 */
	public function test_vocabulary_terms_travel_with_the_page(): void {
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
}
