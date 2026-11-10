<?php
/**
 * The version of each handbook (E-0084).
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Abilities\ListHandbooks;
use LivingHandbook\Export\MarkdownExport;
use LivingHandbook\Frontend\Entry;
use LivingHandbook\Frontend\PageMeta;
use LivingHandbook\Git\GitSync;
use LivingHandbook\Handbook\HandbookAdmin;
use LivingHandbook\Handbook\HandbookVersion;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\Import\HandbookExport;
use LivingHandbook\Import\HandbookImport;
use LivingHandbook\Meta\Metadata;
use LivingHandbook\PostType\Handbook;
use WP_Term;
use WP_UnitTestCase;

/**
 * Edition and count: what counts, where it shows, and the GitHub tag.
 */
final class HandbookVersionTest extends WP_UnitTestCase {

	/**
	 * Requests to GitHub a test answered, by URL.
	 *
	 * @var array<int, string>
	 */
	private array $asked = array();

	/**
	 * Run as an administrator.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->asked = array();
	}

	/**
	 * A handbook.
	 *
	 * @param string $visibility Visibility.
	 * @return int
	 */
	private function handbook( string $visibility = Handbooks::VISIBILITY_PUBLIC ): int {
		$term_id = (int) self::factory()->term->create( array( 'taxonomy' => Handbooks::TAXONOMY ) );
		update_term_meta( $term_id, Handbooks::META_VISIBILITY, $visibility );
		return $term_id;
	}

	/**
	 * A page in a handbook.
	 *
	 * @param int                 $term_id Handbook.
	 * @param array<string,mixed> $args    More arguments.
	 * @return int
	 */
	private function page( int $term_id, array $args = array() ): int {
		$id = (int) self::factory()->post->create(
			array_merge(
				array(
					'post_type'   => Handbook::POST_TYPE,
					'post_status' => 'publish',
				),
				$args
			)
		);
		wp_set_object_terms( $id, array( $term_id ), Handbooks::TAXONOMY );
		return $id;
	}

	/**
	 * Lets the count go up again, as on the next day.
	 *
	 * @param int $term_id Handbook.
	 * @return void
	 */
	private static function next_day( int $term_id ): void {
		update_term_meta( $term_id, HandbookVersion::META_DAY, '2000-01-01' );
	}

	/**
	 * A new handbook is at 1.0; publishing counts once a day.
	 *
	 * @return void
	 */
	public function test_counts_once_a_day(): void {
		$term_id = $this->handbook();
		$this->assertSame( '1.0', HandbookVersion::get( $term_id ) );
		$this->assertSame( 'Handbook version 1.0', HandbookVersion::label( $term_id ) );

		$page = $this->page( $term_id );
		$this->assertSame( '1.1', HandbookVersion::get( $term_id ) );

		wp_update_post(
			array(
				'ID'           => $page,
				'post_content' => 'Neu',
			)
		);
		$this->assertSame( '1.1', HandbookVersion::get( $term_id ), 'The same day counts once.' );

		self::next_day( $term_id );
		wp_update_post(
			array(
				'ID'         => $page,
				'post_title' => 'Anders',
			)
		);
		$this->assertSame( '1.2', HandbookVersion::get( $term_id ) );
	}

	/**
	 * Drafts, excerpts, reviews and corrected addresses are no new content;
	 * taking a page back, trashing and deleting it are.
	 *
	 * @return void
	 */
	public function test_what_counts(): void {
		$term_id = $this->handbook();
		$draft   = $this->page( $term_id, array( 'post_status' => 'draft' ) );
		wp_update_post(
			array(
				'ID'           => $draft,
				'post_content' => 'Entwurf',
			)
		);
		$this->assertSame( '1.0', HandbookVersion::get( $term_id ), 'Drafts do not count.' );

		$page = $this->page( $term_id );
		$this->assertSame( '1.1', HandbookVersion::get( $term_id ) );

		self::next_day( $term_id );
		wp_update_post(
			array(
				'ID'           => $page,
				'post_excerpt' => 'Kurz',
				'menu_order'   => 3,
			)
		);
		update_post_meta( $page, Metadata::REVIEWED, '2026-11-10' );
		global $wpdb;
		$wpdb->update( $wpdb->posts, array( 'post_content' => 'Adresse nachgezogen' ), array( 'ID' => $page ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- as the file protection writes it.
		$this->assertSame( '1.1', HandbookVersion::get( $term_id ), 'Excerpt, order, review and a corrected address do not count.' );

		wp_update_post(
			array(
				'ID'          => $page,
				'post_status' => 'draft',
			)
		);
		$this->assertSame( '1.2', HandbookVersion::get( $term_id ), 'Taking a page back counts.' );

		$other = $this->page( $term_id );
		self::next_day( $term_id );
		wp_trash_post( $other );
		$this->assertSame( '1.3', HandbookVersion::get( $term_id ), 'Publishing the same day as the step before counts no more; trashing the next day does.' );

		$gone = $this->page( $term_id );
		self::next_day( $term_id );
		wp_delete_post( $gone, true );
		$this->assertSame( '1.4', HandbookVersion::get( $term_id ), 'Deleting a published page counts.' );
	}

	/**
	 * A published page that moves to another handbook changes both.
	 *
	 * @return void
	 */
	public function test_moving_a_page_counts_for_both(): void {
		$from = $this->handbook();
		$to   = $this->handbook();
		$page = $this->page( $from );
		self::next_day( $from );

		wp_set_object_terms( $page, array( $to ), Handbooks::TAXONOMY );
		$this->assertSame( '1.2', HandbookVersion::get( $from ) );
		$this->assertSame( '1.1', HandbookVersion::get( $to ) );
	}

	/**
	 * A new edition starts the count at 0, and the next change counts, even on
	 * the same day; the form keeps the count when the edition stays.
	 *
	 * @return void
	 */
	public function test_edition_by_hand(): void {
		$term_id = $this->handbook();
		$this->page( $term_id );
		$this->assertSame( '1.1', HandbookVersion::get( $term_id ) );

		$this->save_form( $term_id, array( 'living_handbook_version_edition' => '1' ) );
		$this->assertSame( '1.1', HandbookVersion::get( $term_id ), 'Saving the same edition keeps the count.' );

		$this->save_form( $term_id, array( 'living_handbook_version_edition' => '2.1' ) );
		$this->assertSame( '2.1.0', HandbookVersion::get( $term_id ) );

		$this->page( $term_id );
		$this->assertSame( '2.1.1', HandbookVersion::get( $term_id ), 'The first change after a new edition counts.' );

		$this->save_form( $term_id, array( 'living_handbook_version_edition' => 'v3 beta' ) );
		$this->assertSame( '2.1.1', HandbookVersion::get( $term_id ), 'No edition, no change.' );
	}

	/**
	 * Pages that follow a version tag give the edition without asking GitHub.
	 *
	 * @return void
	 */
	public function test_edition_from_the_ref_the_pages_follow(): void {
		$term_id = $this->handbook();
		$this->github_page( $term_id, 'https://raw.githubusercontent.com/acme/docs/v2.1/docs/a.md' );
		$this->github_page( $term_id, 'https://raw.githubusercontent.com/acme/docs/v2.1/docs/b.md' );
		$this->github_page( $term_id, 'https://raw.githubusercontent.com/other/notes/main/x.md' );
		$this->answer_github( array( 'v9.9' ) );

		$this->assertSame(
			array(
				'acme/docs'   => array( 'v2.1' ),
				'other/notes' => array( 'main' ),
			),
			HandbookVersion::repositories( $term_id )
		);

		$this->save_form(
			$term_id,
			array(
				'living_handbook_version_source' => HandbookVersion::SOURCE_GITHUB,
				'living_handbook_version_repo'   => 'acme/docs',
			)
		);
		$this->assertSame( '2.1.0', HandbookVersion::get( $term_id ) );
		$this->assertSame( 'v2.1', get_term_meta( $term_id, HandbookVersion::META_TAG, true ) );
		$this->assertSame( array(), $this->asked, 'The tag was in the address.' );

		$this->save_form( $term_id, array( 'living_handbook_version_source' => HandbookVersion::SOURCE_GITHUB, 'living_handbook_version_repo' => 'acme/docs', 'living_handbook_version_edition' => '7' ) );
		$this->assertSame( '2.1.0', HandbookVersion::get( $term_id ), 'The field is locked while the tag decides.' );
	}

	/**
	 * Pages that follow a branch: one request for the tags, the highest version
	 * tag wins, a new tag starts the count again, an error keeps the edition.
	 *
	 * @return void
	 */
	public function test_edition_from_the_tags_of_the_repository(): void {
		$term_id = $this->handbook();
		$this->github_page( $term_id, 'https://raw.githubusercontent.com/acme/docs/main/docs/a.md' );
		update_term_meta( $term_id, HandbookVersion::META_SOURCE, HandbookVersion::SOURCE_GITHUB );
		$this->answer_github( array( 'docs-final', 'v1.9', 'v1.10', 'beta', '1.2.3' ) );

		HandbookVersion::refresh_tags();
		$this->assertSame( array( 'https://api.github.com/repos/acme/docs/tags?per_page=100' ), $this->asked );
		$this->assertSame( '1.10.0', HandbookVersion::get( $term_id ), '1.10 is higher than 1.9 and 1.2.3.' );

		HandbookVersion::bump( $term_id );
		$this->assertSame( '1.10.1', HandbookVersion::get( $term_id ) );
		HandbookVersion::refresh_tags();
		$this->assertSame( '1.10.1', HandbookVersion::get( $term_id ), 'The same tag keeps the count.' );

		$this->answer_github( array( 'v1.10', 'v2.0' ) );
		HandbookVersion::refresh_tags();
		$this->assertSame( '2.0.0', HandbookVersion::get( $term_id ) );

		$this->answer_github( 503 );
		HandbookVersion::refresh_tags();
		$this->assertSame( '2.0.0', HandbookVersion::get( $term_id ) );
		$this->assertStringContainsString( '503', (string) get_term_meta( $term_id, HandbookVersion::META_ERROR, true ) );

		$this->answer_github( array( 'release', 'latest' ) );
		HandbookVersion::refresh_tags();
		$this->assertSame( '2.0.0', HandbookVersion::get( $term_id ) );
		$this->assertStringContainsString( 'v1.0', (string) get_term_meta( $term_id, HandbookVersion::META_ERROR, true ) );
	}

	/**
	 * The scheduled sync asks for the tags once, at the end of its pass.
	 *
	 * @return void
	 */
	public function test_the_sync_pass_asks_once(): void {
		$term_id = $this->handbook();
		$this->github_page( $term_id, 'https://raw.githubusercontent.com/acme/docs/main/docs/a.md' );
		$this->github_page( $term_id, 'https://raw.githubusercontent.com/acme/docs/main/docs/b.md' );
		update_term_meta( $term_id, HandbookVersion::META_SOURCE, HandbookVersion::SOURCE_GITHUB );
		$this->answer_github( array( 'v3.0' ) );

		( new GitSync() )->run_sync();

		$this->assertSame( array( 'https://api.github.com/repos/acme/docs/tags?per_page=100' ), $this->asked );
		$this->assertSame( '3.0.0', HandbookVersion::get( $term_id ) );
	}

	/**
	 * A handbook with the edition by hand is never sent to GitHub.
	 *
	 * @return void
	 */
	public function test_manual_handbooks_do_not_ask(): void {
		$term_id = $this->handbook();
		$this->github_page( $term_id, 'https://raw.githubusercontent.com/acme/docs/main/docs/a.md' );
		$this->answer_github( array( 'v5.0' ) );

		HandbookVersion::refresh_tags();
		$this->assertSame( array(), $this->asked );
		$this->assertSame( '1.1', HandbookVersion::get( $term_id ) );
	}

	/**
	 * The version shows on the entry page and below each page for every
	 * reader, in a line of its own; the exports and the ability carry it.
	 *
	 * @return void
	 */
	public function test_where_it_shows(): void {
		$term_id = $this->handbook();
		$page    = $this->page( $term_id, array( 'post_title' => 'Seite' ) );
		HandbookVersion::set_edition( $term_id, '3' );
		HandbookVersion::bump( $term_id );
		$term = get_term( $term_id, Handbooks::TAXONOMY );
		$this->assertInstanceOf( WP_Term::class, $term );

		wp_set_current_user( 0 );
		$entry = Entry::render_entry( $term );
		$this->assertStringContainsString( '<p class="living-handbook-handbook-version">Handbook version 3.1</p>', $entry );
		$this->assertStringNotContainsString( 'Living Handbook ' . LIVING_HANDBOOK_VERSION, $entry, 'Guests see no plugin version.' );

		$meta = PageMeta::render_meta( $page );
		$this->assertStringContainsString( 'Handbook version', $meta );
		$this->assertStringContainsString( '>3.1<', $meta );
		$this->assertStringNotContainsString( 'Handbook version', PageMeta::render_meta( $page, false, false ) );

		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$markdown = MarkdownExport::build( $term );
		$this->assertStringContainsString( "extra:\n  handbook_version: \"3.1\"\n", $markdown['files']['mkdocs.yml'] );
		$this->assertStringContainsString( "Handbook version 3.1.\n", $markdown['files']['EXPORT.txt'] );

		$media    = array();
		$manifest = ( new HandbookExport() )->build_manifest( $term, $media );
		$this->assertSame(
			array(
				'edition' => '3',
				'count'   => 1,
				'version' => '3.1',
			),
			$manifest['handbook']['version']
		);

		$listed = ( new ListHandbooks() )->run( array() );
		$this->assertSame( '3.1', $listed['handbooks'][0]['version'] );
	}

	/**
	 * A bundle import into a new handbook takes the version; into an existing
	 * one, the handbook keeps counting its own.
	 *
	 * @return void
	 */
	public function test_bundle_import_takes_the_version_for_a_new_handbook(): void {
		$manifest = array(
			'handbook' => array(
				'slug'    => 'neu-' . wp_generate_password( 6, false ),
				'name'    => 'Neu',
				'version' => array(
					'edition' => '4.2',
					'count'   => 7,
				),
			),
			'pages'    => array(
				array(
					'key'     => 'a',
					'title'   => 'A',
					'slug'    => 'a',
					'status'  => 'publish',
					'content' => '<!-- wp:paragraph --><p>A</p><!-- /wp:paragraph -->',
				),
			),
		);
		$report   = ( new HandbookImport() )->import_manifest( $manifest, static fn() => false, HandbookImport::RULE_CREATE );
		$this->assertSame( 1, $report['created'], (string) wp_json_encode( $report ) );
		$term = get_term_by( 'slug', $manifest['handbook']['slug'], Handbooks::TAXONOMY );
		$this->assertInstanceOf( WP_Term::class, $term );
		$this->assertSame( '4.2.7', HandbookVersion::get( (int) $term->term_id ) );

		$existing = $this->handbook();
		$this->page( $existing );
		$manifest['pages'][0]['key']  = 'b';
		$manifest['pages'][0]['slug'] = 'b';
		( new HandbookImport() )->import_manifest( $manifest, static fn() => false, HandbookImport::RULE_CREATE, $existing );
		$this->assertSame( '1.1', HandbookVersion::get( $existing ) );
	}

	/**
	 * The list of handbooks shows the version in a column.
	 *
	 * @return void
	 */
	public function test_list_column(): void {
		$term_id = $this->handbook();
		HandbookVersion::set_edition( $term_id, '2' );
		$admin = new HandbookAdmin();
		$this->assertArrayHasKey( 'lh_version', $admin->access_column( array() ) );
		$this->assertSame( '2.0', $admin->access_column_value( '', 'lh_version', $term_id ) );
	}

	/**
	 * A page synced from GitHub.
	 *
	 * @param int    $term_id Handbook.
	 * @param string $url     Raw address.
	 * @return int
	 */
	private function github_page( int $term_id, string $url ): int {
		$id = $this->page( $term_id );
		update_post_meta( $id, GitSync::META_SOURCE, GitSync::SOURCE_GITHUB );
		update_post_meta( $id, GitSync::META_URL, $url );
		return $id;
	}

	/**
	 * Answers requests to api.github.com with tags, or with an error status.
	 *
	 * @param array<int, string>|int $tags Tag names, or a status code.
	 * @return void
	 */
	private function answer_github( $tags ): void {
		remove_all_filters( 'pre_http_request' );
		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) use ( $tags ) {
				if ( 0 === strpos( (string) $url, 'https://raw.githubusercontent.com/' ) ) {
					// The pages themselves: unchanged here, the sync keeps them.
					return array(
						'headers'  => array(),
						'body'     => '',
						'response' => array(
							'code'    => 404,
							'message' => 'Not Found',
						),
						'cookies'  => array(),
					);
				}
				if ( 0 !== strpos( (string) $url, 'https://api.github.com/' ) ) {
					return $pre;
				}
				$this->asked[] = (string) $url;
				if ( is_int( $tags ) ) {
					return array(
						'headers'  => array(),
						'body'     => '{}',
						'response' => array(
							'code'    => $tags,
							'message' => 'Error',
						),
						'cookies'  => array(),
					);
				}
				return array(
					'headers'  => array(),
					'body'     => (string) wp_json_encode( array_map( static fn( $name ) => array( 'name' => $name ), $tags ) ),
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
				);
			},
			10,
			3
		);
	}

	/**
	 * Saves the handbook form as the edit screen sends it.
	 *
	 * @param int                  $term_id Handbook.
	 * @param array<string, string> $fields  Version fields.
	 * @return void
	 */
	private function save_form( int $term_id, array $fields ): void {
		$_POST = array_merge(
			array(
				'living_handbook_access_nonce' => wp_create_nonce( 'living_handbook_access' ),
				'living_handbook_visibility'   => (string) get_term_meta( $term_id, Handbooks::META_VISIBILITY, true ),
			),
			$fields
		);
		( new HandbookAdmin() )->save( $term_id );
		$_POST = array();
	}
}
