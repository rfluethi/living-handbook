<?php
/**
 * Writing a comment follows the same access rule as reading the page.
 *
 * Up to 0.75.1 the plugin guarded the reading side of comments only: the
 * comment queries, the comment feeds and the single REST read. The writing side
 * was left to core, and core asks nothing but comments_open() and the post
 * status. A published page of a members-only handbook therefore took a comment
 * from a guest through wp-comments-post.php, and from any signed-in reader of a
 * restricted handbook through POST /wp/v2/comments. The comment was stored;
 * only the answer came back as a 404. Somebody who may not read a handbook
 * could still write into its moderation queue, and, with comments approved
 * automatically, into the page.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use LivingHandbook\Training\Training;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * Comments on guarded pages, written through the form and through REST.
 */
final class CommentWriteAccessTest extends WP_UnitTestCase {

	/**
	 * Spin up the REST server.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init' );
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
	 * A handbook with a visibility and, optionally, a comment mode.
	 *
	 * @param string $visibility One of the VISIBILITY_* constants.
	 * @param string $comments   One of the COMMENTS_* constants, or ''.
	 * @return int Term id.
	 */
	private function handbook( string $visibility, string $comments = '' ): int {
		$term = (int) self::factory()->term->create( array( 'taxonomy' => Handbooks::TAXONOMY ) );
		update_term_meta( $term, Handbooks::META_VISIBILITY, $visibility );
		if ( '' !== $comments ) {
			update_term_meta( $term, Handbooks::META_COMMENTS, $comments );
		}

		return $term;
	}

	/**
	 * A published page with comments open, in a handbook.
	 *
	 * @param int    $term_id   Handbook.
	 * @param string $post_type Post type.
	 * @return int Post id.
	 */
	private function page( int $term_id, string $post_type = Handbook::POST_TYPE ): int {
		$id = (int) self::factory()->post->create(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'comment_status' => 'open',
				'ping_status'    => 'open',
			)
		);
		wp_set_object_terms( $id, array( $term_id ), Handbooks::TAXONOMY );

		return $id;
	}

	/**
	 * Submit a comment the way wp-comments-post.php does.
	 *
	 * @param int $post_id Page.
	 * @return WP_Error|\WP_Comment|mixed
	 */
	private function submit( int $post_id ) {
		return wp_handle_comment_submission(
			array(
				'comment_post_ID' => $post_id,
				'author'          => 'Visitor',
				'email'           => 'visitor@example.org',
				'url'             => '',
				'comment'         => 'Written by someone who may not read this.',
			)
		);
	}

	/**
	 * How many comments a page has, approved or not.
	 *
	 * @param int $post_id Page.
	 * @return int
	 */
	private function stored( int $post_id ): int {
		return (int) get_comments(
			array(
				'post_id' => $post_id,
				'count'   => true,
				'status'  => 'all',
			)
		);
	}

	/**
	 * A guest cannot comment on a page of a members-only handbook through the
	 * comment form handler, and nothing is stored.
	 *
	 * @return void
	 */
	public function test_a_guest_cannot_comment_on_a_members_page_through_the_form(): void {
		$page = $this->page( $this->handbook( Handbooks::VISIBILITY_MEMBERS ) );

		wp_set_current_user( 0 );
		$result = $this->submit( $page );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'comment_closed', $result->get_error_code() );
		$this->assertSame( 0, $this->stored( $page ), 'Nothing may have been stored.' );
	}

	/**
	 * A signed-in reader without access to a restricted handbook cannot create a
	 * comment on its page over REST.
	 *
	 * @return void
	 */
	public function test_a_reader_without_access_cannot_comment_over_rest(): void {
		$page       = $this->page( $this->handbook( Handbooks::VISIBILITY_RESTRICTED ) );
		$subscriber = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );

		wp_set_current_user( $subscriber );
		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->set_body_params(
			array(
				'post'    => $page,
				'content' => 'Written by someone who may not read this.',
			)
		);
		$response = rest_do_request( $request );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 0, $this->stored( $page ), 'Nothing may have been stored.' );
	}

	/**
	 * A handbook set to "comments open" does not reopen what the access rule
	 * closed: the handbook's comment switch decides about readers, not about
	 * people who may not read.
	 *
	 * @return void
	 */
	public function test_an_open_handbook_does_not_reopen_comments_for_an_outsider(): void {
		$page = $this->page( $this->handbook( Handbooks::VISIBILITY_MEMBERS, Handbooks::COMMENTS_OPEN ) );

		wp_set_current_user( 0 );
		$this->assertFalse( comments_open( $page ) );
		$this->assertFalse( pings_open( $page ) );
	}

	/**
	 * Learning paths are guarded the same way.
	 *
	 * @return void
	 */
	public function test_a_learning_path_is_closed_to_an_outsider(): void {
		$path = $this->page( $this->handbook( Handbooks::VISIBILITY_MEMBERS ), Training::POST_TYPE );

		wp_set_current_user( 0 );
		$this->assertFalse( comments_open( $path ) );
	}

	/**
	 * Nothing changes for the people who may read the page: a member still
	 * comments on a members-only page, and a guest on a public one.
	 *
	 * @return void
	 */
	public function test_readers_still_comment(): void {
		$members = $this->page( $this->handbook( Handbooks::VISIBILITY_MEMBERS ) );
		$public  = $this->page( $this->handbook( Handbooks::VISIBILITY_PUBLIC ) );

		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertTrue( comments_open( $members ) );

		wp_set_current_user( 0 );
		$this->assertTrue( comments_open( $public ) );
		$this->assertNotInstanceOf( WP_Error::class, $this->submit( $public ) );
	}

	/**
	 * A page whose own setting is closed stays closed for everybody; the guard
	 * only ever closes.
	 *
	 * @return void
	 */
	public function test_the_guard_never_opens_comments(): void {
		$page = $this->page( $this->handbook( Handbooks::VISIBILITY_PUBLIC ) );
		wp_update_post(
			array(
				'ID'             => $page,
				'comment_status' => 'closed',
			)
		);

		wp_set_current_user( 0 );
		$this->assertFalse( comments_open( $page ) );
	}
}
