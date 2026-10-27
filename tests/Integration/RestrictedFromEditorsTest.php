<?php
/**
 * A handbook kept to administrators stays closed to editors, in the back end
 * as well as on the site.
 *
 * Until E-0075 (2026-10-27) everybody who may edit other people's posts read
 * every handbook, and the back end showed every page to everybody who may edit
 * posts. A handbook could not be kept from the editors: they read it on the
 * site, opened its pages in the editor, found its files in the media library,
 * and could open it altogether by changing its visibility. Now only
 * administrators read everything; an editor reads what a handbook grants them,
 * and the back end follows the same rule for other people's pages. One's own
 * pages stay one's own (E-0069).
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Access\AccessController;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\Import\HandbookImport;
use LivingHandbook\Import\MarkdownImportPage;
use LivingHandbook\PostType\Handbook;
use WP_Query;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * AccessController in wp-admin, on capabilities and on the write paths.
 */
final class RestrictedFromEditorsTest extends WP_UnitTestCase {

	/**
	 * An administrator.
	 *
	 * @var int
	 */
	private int $admin = 0;

	/**
	 * An editor.
	 *
	 * @var int
	 */
	private int $editor = 0;

	/**
	 * A handbook kept to administrators.
	 *
	 * @var int
	 */
	private int $board = 0;

	/**
	 * A handbook for every member.
	 *
	 * @var int
	 */
	private int $team = 0;

	/**
	 * Two handbooks, two people, a REST server.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init' );

		$this->admin  = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->editor = (int) self::factory()->user->create( array( 'role' => 'editor' ) );

		$this->board = (int) self::factory()->term->create(
			array(
				'taxonomy' => Handbooks::TAXONOMY,
				'name'     => 'Board',
			)
		);
		update_term_meta( $this->board, Handbooks::META_VISIBILITY, Handbooks::VISIBILITY_RESTRICTED );
		update_term_meta( $this->board, Handbooks::META_ROLES, array( 'administrator' ) );

		$this->team = (int) self::factory()->term->create(
			array(
				'taxonomy' => Handbooks::TAXONOMY,
				'name'     => 'Team',
			)
		);
		update_term_meta( $this->team, Handbooks::META_VISIBILITY, Handbooks::VISIBILITY_MEMBERS );
	}

	/**
	 * Back to the front end, no REST server.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		global $wp_rest_server, $pagenow;
		$wp_rest_server = null;
		$pagenow        = 'index.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restores the screen a test switched to.
		unset( $_GET['tag_ID'] );
		set_current_screen( 'front' );

		parent::tear_down();
	}

	/**
	 * A page.
	 *
	 * @param int    $author  Author.
	 * @param int    $term_id Handbook, 0 for none.
	 * @param string $title   Title.
	 * @return int
	 */
	private function page( int $author, int $term_id, string $title ): int {
		$id = (int) self::factory()->post->create(
			array(
				'post_type'   => Handbook::POST_TYPE,
				'post_status' => 'publish',
				'post_author' => $author,
				'post_title'  => $title,
			)
		);
		if ( $term_id > 0 ) {
			wp_set_object_terms( $id, array( $term_id ), Handbooks::TAXONOMY );
		}
		return $id;
	}

	/**
	 * The editor may not edit, delete or read somebody else's page in the
	 * handbook kept from them; their own page there, a page in their handbook
	 * and a page in no handbook stay theirs to work on.
	 *
	 * @return void
	 */
	public function test_editing_follows_the_handbook_for_other_peoples_pages(): void {
		$secret = $this->page( $this->admin, $this->board, 'Minutes' );
		$own    = $this->page( $this->editor, $this->board, 'My note' );
		$team   = $this->page( $this->admin, $this->team, 'Team page' );
		$loose  = $this->page( $this->admin, 0, 'Not filed yet' );
		wp_set_current_user( $this->editor );

		$this->assertFalse( current_user_can( 'edit_post', $secret ), 'Edit.' );
		$this->assertFalse( current_user_can( 'delete_post', $secret ), 'Delete.' );
		$this->assertFalse( current_user_can( 'read_post', $secret ), 'Read.' );
		$this->assertTrue( current_user_can( 'edit_post', $own ), 'Their own page stays theirs.' );
		$this->assertTrue( current_user_can( 'edit_post', $team ) );
		$this->assertTrue( current_user_can( 'edit_post', $loose ) );

		wp_set_current_user( $this->admin );
		$this->assertTrue( current_user_can( 'edit_post', $secret ), 'An administrator edits everything.' );
	}

	/**
	 * The files of such a page follow the page.
	 *
	 * @return void
	 */
	public function test_the_files_of_a_page_follow_the_page(): void {
		$secret     = $this->page( $this->admin, $this->board, 'Minutes' );
		$attachment = (int) self::factory()->attachment->create(
			array(
				'post_parent'    => $secret,
				'post_author'    => $this->admin,
				'post_mime_type' => 'image/png',
				'post_title'     => 'Balance sheet',
			)
		);
		wp_set_current_user( $this->editor );

		$this->assertFalse( current_user_can( 'edit_post', $attachment ) );
		$this->assertSame( 404, rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/media/' . $attachment ) )->get_status() );

		set_current_screen( 'upload' );
		$query = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => -1,
			)
		);
		$this->assertNotContains( $attachment, wp_list_pluck( $query->posts, 'ID' ), 'Not in the media library.' );
	}

	/**
	 * The page list in wp-admin leaves out somebody else's page in the handbook
	 * kept from the editor, also for get_posts(), which suppresses filters.
	 *
	 * @return void
	 */
	public function test_the_admin_list_leaves_out_what_the_editor_may_not_read(): void {
		$secret = $this->page( $this->admin, $this->board, 'Minutes' );
		$own    = $this->page( $this->editor, $this->board, 'My note' );
		$team   = $this->page( $this->admin, $this->team, 'Team page' );
		$loose  = $this->page( $this->admin, 0, 'Not filed yet' );
		wp_set_current_user( $this->editor );
		set_current_screen( 'edit-handbook' );
		$this->assertTrue( is_admin(), 'precondition: admin context' );

		$listed = wp_list_pluck(
			( new WP_Query(
				array(
					'post_type'      => Handbook::POST_TYPE,
					'post_status'    => 'any',
					'posts_per_page' => -1,
				)
			) )->posts,
			'ID'
		);
		$quiet  = array_map(
			'intval',
			get_posts(
				array(
					'post_type'      => Handbook::POST_TYPE,
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			)
		);

		foreach ( array( $listed, $quiet ) as $ids ) {
			$this->assertNotContains( $secret, $ids );
			$this->assertContains( $own, $ids );
			$this->assertContains( $team, $ids );
			$this->assertContains( $loose, $ids );
		}
	}

	/**
	 * The editor may not change the handbook kept from them, nor file pages
	 * into it, and does not see it in the block editor's list.
	 *
	 * @return void
	 */
	public function test_the_handbook_itself_is_closed(): void {
		wp_set_current_user( $this->editor );

		$this->assertTrue( current_user_can( 'manage_categories' ), 'precondition: an editor manages categories' );
		$this->assertFalse( current_user_can( 'edit_term', $this->board ) );
		$this->assertFalse( current_user_can( 'assign_term', $this->board ) );
		$this->assertFalse( Handbooks::can_edit_access( false, Handbooks::META_VISIBILITY, $this->board ), 'Its visibility cannot be opened.' );
		$this->assertTrue( current_user_can( 'edit_term', $this->team ) );

		$listed = wp_list_pluck( (array) rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/' . Handbooks::TAXONOMY ) )->get_data(), 'id' );
		$this->assertNotContains( $this->board, $listed );
		$this->assertContains( $this->team, $listed );
		$this->assertSame( 403, rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/' . Handbooks::TAXONOMY . '/' . $this->board ) )->get_status() );
	}

	/**
	 * Returns the handbooks get_terms() lists on an admin file.
	 *
	 * @param string $screen The admin file, edit-tags.php or term.php.
	 * @return array<int, int> Ids get_terms() lists there.
	 */
	private function listed_on( string $screen ): array {
		global $pagenow;
		$pagenow = $screen; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Simulates the admin file the filter looks at.
		wp_cache_flush();

		return array_map(
			'intval',
			(array) get_terms(
				array(
					'taxonomy'   => Handbooks::TAXONOMY,
					'hide_empty' => false,
					'fields'     => 'ids',
				)
			)
		);
	}

	/**
	 * The edit screen of a handbook leaves the closed handbook out of its
	 * parent selector, as the add form does. Up to 0.75.1 term.php listed it.
	 * A closed handbook that already is the parent stays, or saving the form
	 * would move the edited handbook to the top level.
	 *
	 * @return void
	 */
	public function test_the_edit_screen_of_a_handbook_leaves_the_closed_one_out(): void {
		wp_set_current_user( $this->editor );
		set_current_screen( 'edit-' . Handbooks::TAXONOMY );

		$this->assertNotContains( $this->board, $this->listed_on( 'edit-tags.php' ) );

		$_GET['tag_ID'] = (string) $this->team;
		$listed         = $this->listed_on( 'term.php' );
		$this->assertNotContains( $this->board, $listed );
		$this->assertContains( $this->team, $listed );

		$below = (int) self::factory()->term->create(
			array(
				'taxonomy' => Handbooks::TAXONOMY,
				'name'     => 'Below the board',
				'parent'   => $this->board,
			)
		);
		update_term_meta( $below, Handbooks::META_VISIBILITY, Handbooks::VISIBILITY_MEMBERS );
		$_GET['tag_ID'] = (string) $below;
		$this->assertContains( $this->board, $this->listed_on( 'term.php' ), 'The current parent stays selectable.' );
	}

	/**
	 * Returns the ids of the approved comments the current user gets from a
	 * comment query.
	 *
	 * The comment query cache is keyed by the arguments, not by the user, so it
	 * is emptied first; a real request runs as one user only.
	 *
	 * @return array<int, int>
	 */
	private function comments_listed(): array {
		wp_cache_flush();

		return array_map(
			'intval',
			(array) get_comments(
				array(
					'status' => 'approve',
					'fields' => 'ids',
				)
			)
		);
	}

	/**
	 * Adds an approved comment to a page.
	 *
	 * @param int $post_id Page.
	 * @return int Comment id.
	 */
	private function comment_on( int $post_id ): int {
		return (int) self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_approved' => '1',
			)
		);
	}

	/**
	 * On the site, comments follow can_view_post(): an editor reads the
	 * comments on a page in no handbook, as they read the page, and not those
	 * on a page in the handbook kept from them; a subscriber reads neither.
	 * Up to 0.75.1 the comment query hid the page in no handbook from editors
	 * as well, while the page itself and a single comment over REST were open.
	 *
	 * @return void
	 */
	public function test_comments_on_the_site_follow_the_page(): void {
		$loose  = $this->comment_on( $this->page( $this->admin, 0, 'Not filed yet' ) );
		$secret = $this->comment_on( $this->page( $this->admin, $this->board, 'Minutes' ) );
		$team   = $this->comment_on( $this->page( $this->admin, $this->team, 'Team page' ) );

		wp_set_current_user( $this->editor );
		$listed = $this->comments_listed();
		$this->assertContains( $loose, $listed );
		$this->assertContains( $team, $listed );
		$this->assertNotContains( $secret, $listed );

		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$listed = $this->comments_listed();
		$this->assertNotContains( $loose, $listed );
		$this->assertContains( $team, $listed );
		$this->assertNotContains( $secret, $listed );
	}

	/**
	 * In the back end, comments follow visible_in_backend(): the comments on
	 * one's own page in the handbook kept from the editor stay, those on
	 * somebody else's page there do not. Up to 0.75.1 the comment screen hid
	 * the comments on the editor's own page too.
	 *
	 * @return void
	 */
	public function test_comments_in_the_back_end_follow_the_page(): void {
		$own    = $this->comment_on( $this->page( $this->editor, $this->board, 'My note' ) );
		$secret = $this->comment_on( $this->page( $this->admin, $this->board, 'Minutes' ) );
		$loose  = $this->comment_on( $this->page( $this->admin, 0, 'Not filed yet' ) );

		wp_set_current_user( $this->editor );
		set_current_screen( 'edit-comments' );

		$listed = $this->comments_listed();
		$this->assertContains( $own, $listed );
		$this->assertContains( $loose, $listed );
		$this->assertNotContains( $secret, $listed );
	}

	/**
	 * A bundle and a Markdown import do not write into the handbook kept from
	 * the importing editor: an update would overwrite pages they may not read.
	 *
	 * @return void
	 */
	public function test_imports_do_not_write_into_it(): void {
		$this->page( $this->admin, $this->board, 'Minutes' );
		wp_set_current_user( $this->editor );

		$manifest = array(
			'format'   => 'living-handbook-bundle',
			'version'  => 1,
			'handbook' => array(
				'slug' => 'whatever',
				'name' => 'Whatever',
			),
			'pages'    => array(),
		);
		$result   = ( new HandbookImport() )->import_manifest(
			$manifest,
			static function ( string $file ) {
				unset( $file );
				return false;
			},
			HandbookImport::RULE_UPDATE,
			$this->board
		);
		$this->assertArrayHasKey( 'error', $result );

		$request = new WP_REST_Request( 'POST', '/living-handbook/v1/create' );
		$request->set_param( 'title', 'Planted' );
		$request->set_param( 'content', '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->' );
		$request->set_param( 'handbook', $this->board );
		$created = ( new MarkdownImportPage() )->create_callback( $request );
		$this->assertArrayHasKey( 'error', $created );
		$this->assertArrayNotHasKey( 'id', $created );
	}

	/**
	 * Nor does a file that names the handbook in its transport block: the
	 * page lands in the handbook chosen on the screen, not in the one the
	 * editor may not read.
	 *
	 * @return void
	 */
	public function test_a_transport_block_does_not_file_into_it(): void {
		wp_set_current_user( $this->editor );

		$request = new WP_REST_Request( 'POST', '/living-handbook/v1/create' );
		$request->set_param( 'title', 'Planted' );
		$request->set_param( 'content', '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->' );
		$request->set_param( 'handbook', $this->team );
		$request->set_param( 'transport', array( 'handbook' => 'Board' ) );
		$created = ( new MarkdownImportPage() )->create_callback( $request );

		$this->assertArrayHasKey( 'id', $created, wp_json_encode( $created ) );
		$terms = wp_get_object_terms( (int) $created['id'], Handbooks::TAXONOMY, array( 'fields' => 'ids' ) );
		$this->assertSame( array( $this->team ), array_map( 'intval', (array) $terms ) );
	}

	/**
	 * A bundle of one page that claims to be an existing page by its origin.
	 *
	 * @param int    $existing The page the bundle claims to be.
	 * @param string $text     Its new text.
	 * @return array<string, mixed>
	 */
	private function bundle_for( int $existing, string $text ): array {
		update_post_meta( $existing, HandbookImport::META_ORIGIN, 'https://elsewhere.example#' . $existing );
		return array(
			'format'   => 'living-handbook-bundle',
			'version'  => 1,
			'handbook' => array(
				'slug' => 'team',
				'name' => 'Team',
			),
			'pages'    => array(
				array(
					'key'       => 'shared',
					'origin_id' => 'https://elsewhere.example#' . $existing,
					'title'     => 'Shared',
					'slug'      => 'shared',
					'status'    => 'publish',
					'content'   => '<!-- wp:paragraph --><p>' . $text . '</p><!-- /wp:paragraph -->',
				),
			),
		);
	}

	/**
	 * Run a bundle import into the team handbook with the update rule.
	 *
	 * @param array<string, mixed> $manifest Manifest.
	 * @return array<string, mixed>
	 */
	private function update_team_from( array $manifest ): array {
		return ( new HandbookImport() )->import_manifest(
			$manifest,
			static function ( string $file ) {
				unset( $file );
				return false;
			},
			HandbookImport::RULE_UPDATE,
			$this->team
		);
	}

	/**
	 * A bundle update asks the page it found whether the editor may edit it,
	 * so a page a site keeps from them is neither overwritten nor doubled:
	 * the import leaves it alone and says so.
	 *
	 * @return void
	 */
	public function test_a_bundle_update_asks_the_page_itself(): void {
		$page = $this->page( $this->admin, $this->team, 'Shared' );
		wp_set_current_user( $this->editor );
		$this->assertTrue( current_user_can( 'edit_post', $page ), 'precondition: an editor may edit a team page' );

		add_filter(
			'map_meta_cap',
			static function ( $caps, $cap, $user_id, $args ) use ( $page ) {
				unset( $user_id );
				if ( 'edit_post' === $cap && isset( $args[0] ) && $page === (int) $args[0] ) {
					return array( 'do_not_allow' );
				}
				return $caps;
			},
			10,
			4
		);

		$report = $this->update_team_from( $this->bundle_for( $page, 'Overwritten' ) );

		$this->assertSame( 0, $report['updated'], 'A page the site keeps from the editor is not overwritten.' );
		$this->assertSame( 1, $report['refused'] );
		$this->assertSame( 0, $report['created'], 'No second page either.' );
		$this->assertStringNotContainsString( 'Overwritten', (string) get_post_field( 'post_content', $page ) );
		$this->assertNotEmpty( $report['notes'] );
	}

	/**
	 * Nor does a parent named in the transport block hang the page under a
	 * page the editor may not edit. The same line still works for a page they
	 * may edit.
	 *
	 * @return void
	 */
	public function test_a_transport_parent_must_be_a_page_the_importer_may_edit(): void {
		$secret = $this->page( $this->admin, $this->board, 'Minutes' );
		$open   = $this->page( $this->admin, $this->team, 'Team start' );
		wp_set_current_user( $this->editor );

		$created = array();
		foreach ( array(
			'planted' => 'Minutes',
			'welcome' => 'Team start',
		) as $slug => $parent_title ) {
			$request = new WP_REST_Request( 'POST', '/living-handbook/v1/create' );
			$request->set_param( 'title', ucfirst( $slug ) );
			$request->set_param( 'slug', $slug );
			$request->set_param( 'content', '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->' );
			$request->set_param( 'handbook', $this->team );
			$request->set_param( 'transport', array( 'parent' => $parent_title ) );
			$result = ( new MarkdownImportPage() )->create_callback( $request );
			$this->assertArrayHasKey( 'id', $result, wp_json_encode( $result ) );
			$created[ $slug ] = (int) $result['id'];
		}

		$finalize = new WP_REST_Request( 'POST', '/living-handbook/v1/finalize' );
		$finalize->set_param( 'ids', array_values( $created ) );
		( new MarkdownImportPage() )->finalize_callback( $finalize );

		$this->assertNotSame( $secret, (int) get_post_field( 'post_parent', $created['planted'] ), 'A page the editor may not edit does not become the parent.' );
		$this->assertSame( 0, (int) get_post_field( 'post_parent', $created['planted'] ) );
		$this->assertSame( $open, (int) get_post_field( 'post_parent', $created['welcome'] ), 'A page the editor may edit does.' );
	}

	/**
	 * A role the site adds itself works like the built-in ones: a handbook
	 * that names it is open to its members, and what they may do in the back
	 * end follows the role's capabilities, not its name.
	 *
	 * @return void
	 */
	public function test_a_role_of_the_sites_own_works_like_the_built_in_ones(): void {
		add_role( 'lh_course_lead', 'Course lead', array( 'read' => true ) );
		add_role(
			'lh_chief_editor',
			'Chief editor',
			array(
				'read'              => true,
				'edit_posts'        => true,
				'edit_others_posts' => true,
				'edit_published_posts' => true,
				'manage_categories' => true,
			)
		);
		$lead  = (int) self::factory()->user->create( array( 'role' => 'lh_course_lead' ) );
		$chief = (int) self::factory()->user->create( array( 'role' => 'lh_chief_editor' ) );

		$course = (int) self::factory()->term->create( array( 'taxonomy' => Handbooks::TAXONOMY ) );
		update_term_meta( $course, Handbooks::META_VISIBILITY, Handbooks::VISIBILITY_RESTRICTED );
		update_term_meta( $course, Handbooks::META_ROLES, array( 'lh_course_lead' ) );
		$lesson = $this->page( $this->admin, $course, 'Lesson' );
		$secret = $this->page( $this->admin, $this->board, 'Minutes' );

		$this->assertArrayHasKey( 'lh_course_lead', wp_roles()->get_names(), 'The role is offered in the handbook settings.' );
		$this->assertTrue( AccessController::can_view_post( $lesson, $lead ), 'The handbook that names the role is open to it.' );
		$this->assertFalse( AccessController::can_view_post( $lesson, $this->editor ), 'And closed to a role it does not name.' );

		// A role with an editor's capabilities is treated as an editor.
		$this->assertFalse( AccessController::can_view_post( $secret, $chief ) );
		wp_set_current_user( $chief );
		$this->assertFalse( current_user_can( 'edit_post', $secret ) );
		$this->assertFalse( current_user_can( 'edit_term', $this->board ) );

		remove_role( 'lh_course_lead' );
		remove_role( 'lh_chief_editor' );
	}

	/**
	 * A site that wants the old rule names the capability.
	 *
	 * @return void
	 */
	public function test_a_site_can_name_the_capability_that_reads_everything(): void {
		$secret = $this->page( $this->admin, $this->board, 'Minutes' );
		add_filter(
			'living_handbook_read_all_capability',
			static fn(): string => 'edit_others_posts'
		);

		$this->assertTrue( AccessController::reads_everything( $this->editor ) );
		$this->assertTrue( AccessController::can_view_post( $secret, $this->editor ) );
	}
}
