<?php
/**
 * Access side-channel integration tests.
 *
 * The per-handbook visibility is enforced on more than the single page. These
 * tests cover the channels core exposes independently of the main post query,
 * which the #8 hardening closed: the handbook_set term REST list and single
 * read, comment queries, single comment REST reads, single-item REST reads, and
 * any object result set through the_posts.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Access\AccessController;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use WP_Comment;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * Per-handbook visibility across the secondary read channels.
 */
final class AccessChannelsTest extends WP_UnitTestCase {

	/**
	 * The REST server used to dispatch requests.
	 *
	 * @var WP_REST_Server
	 */
	private WP_REST_Server $server;

	/**
	 * A members-only handbook term.
	 *
	 * @var int
	 */
	private int $handbook;

	/**
	 * A published page in that handbook.
	 *
	 * @var int
	 */
	private int $page;

	/**
	 * Spin up the REST server and a members-only handbook with one page.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );

		$this->handbook = (int) self::factory()->term->create(
			array(
				'taxonomy' => Handbooks::TAXONOMY,
				'name'     => 'Members Handbook',
			)
		);
		update_term_meta( $this->handbook, Handbooks::META_VISIBILITY, Handbooks::VISIBILITY_MEMBERS );

		$this->page = (int) self::factory()->post->create(
			array(
				'post_type'   => Handbook::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		wp_set_object_terms( $this->page, array( $this->handbook ), Handbooks::TAXONOMY );
	}

	/**
	 * Tear down the REST server.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null;
		parent::tear_down();
	}

	/**
	 * The handbook_set term list is empty for a guest over REST, so the names
	 * and page counts of members-only handbooks do not leak anonymously.
	 *
	 * @return void
	 */
	public function test_term_rest_list_is_empty_for_guest(): void {
		wp_set_current_user( 0 );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/' . Handbooks::TAXONOMY );
		$response = $this->server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array(), $response->get_data() );
	}

	/**
	 * An editor, who needs the list to assign a handbook in the block editor,
	 * still receives it.
	 *
	 * @return void
	 */
	public function test_term_rest_list_is_visible_to_editor(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/' . Handbooks::TAXONOMY );
		$response = $this->server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertNotEmpty( $response->get_data() );
	}

	/**
	 * The single term read is forbidden for a guest, so the per-id endpoint
	 * cannot be used to read one handbook around the empty list.
	 *
	 * @return void
	 */
	public function test_single_term_rest_is_forbidden_for_guest(): void {
		wp_set_current_user( 0 );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/' . Handbooks::TAXONOMY . '/' . $this->handbook );
		$response = $this->server->dispatch( $request );
		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * A single REST read of a non-viewable handbook page returns 404 through
	 * the prepare guard.
	 *
	 * @return void
	 */
	public function test_rest_item_guard_returns_404_for_guest(): void {
		wp_set_current_user( 0 );
		$controller = new AccessController();
		$response   = $controller->guard_rest_item( 'original', get_post( $this->page ) );
		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * The same guard leaves the response untouched for a member.
	 *
	 * @return void
	 */
	public function test_rest_item_guard_passes_for_member(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$controller = new AccessController();
		$this->assertSame( 'original', $controller->guard_rest_item( 'original', get_post( $this->page ) ) );
	}

	/**
	 * A comment on a non-viewable handbook page is excluded from a comment
	 * query for a guest, but returned for a member who may view the page.
	 *
	 * The comment-query cache is keyed by the query args, not by the current
	 * user, so the cache is flushed between the two identical queries; a real
	 * request only ever runs as one user, so this is a test-only concern.
	 *
	 * @return void
	 */
	public function test_comment_query_hides_comment_from_guest(): void {
		$comment = (int) self::factory()->comment->create(
			array(
				'comment_post_ID'  => $this->page,
				'comment_approved' => '1',
			)
		);

		wp_set_current_user( 0 );
		$guest_ids = get_comments(
			array(
				'post_id' => $this->page,
				'fields'  => 'ids',
			)
		);
		$this->assertNotContains( $comment, array_map( 'intval', $guest_ids ) );

		wp_cache_flush();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$member_ids = get_comments(
			array(
				'post_id' => $this->page,
				'fields'  => 'ids',
			)
		);
		$this->assertContains( $comment, array_map( 'intval', $member_ids ) );
	}

	/**
	 * A single comment REST read on a non-viewable page is refused for a guest.
	 *
	 * @return void
	 */
	public function test_single_comment_guard_returns_404_for_guest(): void {
		$comment_id = (int) self::factory()->comment->create(
			array(
				'comment_post_ID'  => $this->page,
				'comment_approved' => '1',
			)
		);
		$comment = get_comment( $comment_id );
		$this->assertInstanceOf( WP_Comment::class, $comment );

		wp_set_current_user( 0 );
		$controller = new AccessController();
		$response   = $controller->guard_rest_comment( 'original', $comment );
		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * A result set through the_posts drops a non-viewable handbook page for a
	 * guest and keeps it for a member. The query returns objects (the display
	 * path), which is the shape the_posts carries.
	 *
	 * @return void
	 */
	public function test_the_posts_filters_for_guest(): void {
		wp_set_current_user( 0 );
		$guest     = new WP_Query(
			array(
				'post_type'      => Handbook::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);
		$guest_ids = array_map( 'intval', wp_list_pluck( $guest->posts, 'ID' ) );
		$this->assertNotContains( $this->page, $guest_ids );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$member     = new WP_Query(
			array(
				'post_type'      => Handbook::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);
		$member_ids = array_map( 'intval', wp_list_pluck( $member->posts, 'ID' ) );
		$this->assertContains( $this->page, $member_ids );
	}
}
