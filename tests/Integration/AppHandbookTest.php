<?php
/**
 * The app handbook shipped with the plugin.
 *
 * The app handbook is content, and content shipped in a file rots differently from
 * code: nothing in the build fails when a page loses its parent, a term token is
 * misspelt, or the two language files drift apart. These tests are that missing
 * check. They also pin the two things the loader does beyond a plain import, the
 * relative review dates and the language-independent term tokens, because both
 * are only visible after an import has run.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Frontend\FreshnessStatus;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\Import\AppHandbook;
use LivingHandbook\Import\HandbookImport;
use LivingHandbook\PostType\Handbook;
use LivingHandbook\Setup\Seeder;
use LivingHandbook\Taxonomy\Taxonomies;
use WP_Term;
use WP_UnitTestCase;

/**
 * The shipped handbook must be loadable, complete, and idempotent.
 */
final class AppHandbookTest extends WP_UnitTestCase {

	/**
	 * Act as an administrator. The frontend access layer is fail-closed, so a
	 * test without a user would find no pages afterwards and pass for the wrong
	 * reason.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Decode one shipped language file directly, without the loader.
	 *
	 * @param string $language Language code.
	 * @return array<string, mixed>
	 */
	private function raw( string $language ): array {
		$path = LIVING_HANDBOOK_DIR . 'assets/app-handbook/app-handbook-' . $language . '.json';
		$this->assertFileExists( $path );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$decoded = json_decode( (string) file_get_contents( $path ), true );
		$this->assertIsArray( $decoded );
		return $decoded;
	}

	/**
	 * Load the app handbook the way the admin action does.
	 *
	 * @return array<string, mixed> The import report.
	 */
	private function load( int $chosen = 0 ): array {
		$manifest = AppHandbook::manifest();
		$this->assertIsArray( $manifest );

		return ( new HandbookImport() )->import_manifest(
			$manifest,
			static function ( string $file ) {
				return AppHandbook::read_media( $file );
			},
			HandbookImport::RULE_SKIP,
			$chosen
		);
	}

	/**
	 * All pages of the app handbook, keyed by their bundle key.
	 *
	 * @return array<string, int>
	 */
	private function pages_by_key(): array {
		$posts = get_posts(
			array(
				'post_type'      => Handbook::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		$map = array();
		foreach ( $posts as $post_id ) {
			$key = (string) get_post_meta( (int) $post_id, HandbookImport::META_BUNDLE_KEY, true );
			if ( '' !== $key ) {
				$map[ $key ] = (int) $post_id;
			}
		}
		return $map;
	}

	/**
	 * The two language files must describe the same handbook, or the app handbook is a
	 * different thing depending on the site language.
	 *
	 * @return void
	 */
	public function test_both_languages_have_the_same_structure(): void {
		$en = $this->raw( 'en' );
		$de = $this->raw( 'de' );

		$shape = static function ( array $manifest ): array {
			$out = array();
			foreach ( $manifest['pages'] as $page ) {
				$out[ $page['key'] ] = array(
					'parent' => $page['parent_key'],
					'order'  => $page['order'],
					'terms'  => $page['terms'],
					'meta'   => $page['meta'],
				);
			}
			ksort( $out );
			return $out;
		};

		$this->assertSame( $shape( $en ), $shape( $de ) );
		$this->assertNotSame(
			$en['handbook']['slug'],
			$de['handbook']['slug'],
			'The two language versions should be separate handbooks.'
		);
	}

	/**
	 * A load creates the whole handbook, with its hierarchy intact.
	 *
	 * @return void
	 */
	public function test_loading_creates_the_app_handbook_with_its_hierarchy(): void {
		$report = $this->load();

		$this->assertArrayNotHasKey( 'error', $report );
		$this->assertSame( 9, $report['created'] );

		$term = AppHandbook::existing_handbook();
		$this->assertInstanceOf( WP_Term::class, $term );
		$this->assertSame(
			Handbooks::VISIBILITY_MEMBERS,
			get_term_meta( $term->term_id, Handbooks::META_VISIBILITY, true ),
			'A newly created handbook must never start public.'
		);

		$pages = $this->pages_by_key();
		$this->assertCount( 9, $pages );

		// Three areas without a parent, and every other page inside one of them.
		foreach ( $pages as $key => $post_id ) {
			$parent = (int) get_post( $post_id )->post_parent;
			if ( false === strpos( $key, '/' ) ) {
				$this->assertSame( 0, $parent, $key . ' is an area and must have no parent.' );
				continue;
			}
			$expected = substr( $key, 0, (int) strrpos( $key, '/' ) );
			$this->assertArrayHasKey( $expected, $pages );
			$this->assertSame( $pages[ $expected ], $parent, $key . ' must hang under ' . $expected . '.' );
		}
	}

	/**
	 * Loaded into a handbook that already exists, the pages land there and no
	 * handbook of their own is created. The target's access configuration must be
	 * left alone: someone who picks a public handbook has decided that.
	 *
	 * @return void
	 */
	public function test_it_can_be_loaded_into_an_existing_handbook(): void {
		$target = self::factory()->term->create(
			array(
				'taxonomy' => Handbooks::TAXONOMY,
				'name'     => 'Our own handbook',
			)
		);
		update_term_meta( $target, Handbooks::META_VISIBILITY, Handbooks::VISIBILITY_PUBLIC );

		$report = $this->load( (int) $target );

		$this->assertSame( 9, $report['created'] );
		$this->assertNull( AppHandbook::existing_handbook(), 'No separate handbook should have been created.' );

		foreach ( $this->pages_by_key() as $key => $post_id ) {
			$terms = wp_get_object_terms( $post_id, Handbooks::TAXONOMY, array( 'fields' => 'ids' ) );
			$this->assertSame( array( (int) $target ), $terms, $key . ' must sit in the chosen handbook.' );
		}

		$this->assertSame(
			Handbooks::VISIBILITY_PUBLIC,
			get_term_meta( (int) $target, Handbooks::META_VISIBILITY, true ),
			'The chosen handbook must keep its own visibility.'
		);
	}

	/**
	 * The review dates are relative to the load, so the pages show every
	 * freshness state instead of turning entirely overdue as the release ages.
	 *
	 * @return void
	 */
	public function test_the_review_states_are_relative_to_the_load(): void {
		$this->load();
		$pages = $this->pages_by_key();

		$states = array();
		foreach ( $pages as $key => $post_id ) {
			$states[ $key ] = FreshnessStatus::for_post( $post_id );
		}

		$this->assertContains( FreshnessStatus::OK, $states );
		$this->assertContains( FreshnessStatus::DUE, $states );
		$this->assertContains( FreshnessStatus::OVERDUE, $states );
		$this->assertContains( FreshnessStatus::NONE, $states );
	}

	/**
	 * A term token resolves to the seeded term instead of creating a second one
	 * next to it. This is what breaks first on a translated site.
	 *
	 * @return void
	 */
	public function test_terms_attach_to_the_seeded_vocabulary(): void {
		Seeder::seed();
		$before = wp_count_terms( array( 'taxonomy' => Taxonomies::PAGE_TYPE ) );

		$this->load();
		$pages = $this->pages_by_key();

		$this->assertEquals(
			$before,
			wp_count_terms( array( 'taxonomy' => Taxonomies::PAGE_TYPE ) ),
			'The app handbook must not add page types next to the seeded ones.'
		);

		$types = wp_get_object_terms( $pages['getting-started/writing-a-good-page'], Taxonomies::PAGE_TYPE, array( 'fields' => 'names' ) );
		$this->assertSame( array( 'Guide' ), $types );

		// The topic vocabulary is not seeded, so the app handbook fills it.
		$topics = wp_get_object_terms( $pages['getting-started/writing-a-good-page'], Taxonomies::TOPIC, array( 'fields' => 'slugs' ) );
		$this->assertNotEmpty( $topics );
	}

	/**
	 * Loading twice must not duplicate anything, and must not overwrite an edit
	 * someone made while trying it out.
	 *
	 * @return void
	 */
	public function test_loading_twice_keeps_the_edits(): void {
		$this->load();
		$pages = $this->pages_by_key();
		$page  = $pages['reference/frequently-asked-questions'];

		wp_update_post(
			array(
				'ID'         => $page,
				'post_title' => 'Edited by hand',
			)
		);

		$report = $this->load();

		$this->assertSame( 0, $report['created'] );
		$this->assertSame( 9, $report['skipped'] );
		$this->assertCount( 9, $this->pages_by_key() );
		$this->assertSame( 'Edited by hand', get_the_title( $page ) );
	}

	/**
	 * Images named in the manifest are read from the plugin's own folder, and
	 * nothing else is. The manifest is shipped, so the path is not user input
	 * today, but a reader that hands back any path it is given is the kind of
	 * thing that turns into a hole the moment someone reuses it.
	 *
	 * @return void
	 */
	public function test_media_is_read_only_from_the_app_handbook_folder(): void {
		$this->assertIsString(
			AppHandbook::read_media( 'media/README.md' ),
			'A file inside the folder must be readable.'
		);

		foreach ( array( '', '../../living-handbook.php', 'media/../../../wp-config.php', 'media/nope.png' ) as $path ) {
			$this->assertFalse( AppHandbook::read_media( $path ), $path . ' must not be readable.' );
		}
	}

	/**
	 * Every image the manifest names must actually be in the plugin. A missing
	 * file would leave the placeholder URL in the content, and the sanitiser
	 * would then strip the image source, so the page would ship a blank image.
	 *
	 * @return void
	 */
	public function test_every_named_image_exists(): void {
		foreach ( array( 'en', 'de' ) as $language ) {
			$manifest = $this->raw( $language );
			foreach ( $manifest['media'] as $item ) {
				$this->assertIsString(
					AppHandbook::read_media( (string) $item['file'] ),
					$item['file'] . ' is named in the ' . $language . ' manifest but not shipped.'
				);
			}
		}
	}

	/**
	 * The shipped content passes through the same sanitisation as any other
	 * import, so the block delimiters must survive it.
	 *
	 * @return void
	 */
	public function test_the_content_survives_sanitisation(): void {
		$this->load();
		$pages   = $this->pages_by_key();
		$content = (string) get_post( $pages['keeping-content-current/the-review-cycle'] )->post_content;

		$this->assertStringContainsString( 'wp:living-handbook/mermaid', $content );
		$this->assertStringContainsString( 'graph TD', $content );
		$this->assertStringContainsString( 'wp:heading', $content );
	}
}
