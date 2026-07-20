<?php
/**
 * REST permission integration tests.
 *
 * The import, feedback and filter endpoints only require edit_posts (or, for
 * the ZIP upload, upload_files), which a Contributor has, so the security rests
 * on the per-object checks inside the callbacks. These tests dispatch the routes
 * as a Contributor and assert that a low-privilege user cannot reach or misuse
 * them. The original Prio 1 finding (a Contributor overwriting another author's
 * published page through a re-import) is covered here so it cannot regress.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * Permission and per-object guards on the plugin's REST endpoints.
 */
final class RestPermissionsTest extends WP_UnitTestCase {

	/**
	 * The REST server used to dispatch requests.
	 *
	 * @var WP_REST_Server
	 */
	private WP_REST_Server $server;

	/**
	 * Spin up a fresh REST server and register the routes.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
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
	 * Create a handbook (grouping term).
	 *
	 * @return int Term ID.
	 */
	private function make_handbook(): int {
		$term_id = self::factory()->term->create(
			array(
				'taxonomy' => Handbooks::TAXONOMY,
				'name'     => 'Handbook ' . wp_generate_password( 6, false ),
			)
		);
		update_term_meta( $term_id, Handbooks::META_VISIBILITY, Handbooks::VISIBILITY_MEMBERS );
		return (int) $term_id;
	}

	/**
	 * Create a published handbook page owned by a given user, assigned to a
	 * handbook, with a known slug and content.
	 *
	 * @param int    $author  Author user ID.
	 * @param int    $term_id Handbook term ID.
	 * @param string $slug    Post slug.
	 * @param string $content Post content.
	 * @return int Post ID.
	 */
	private function make_page( int $author, int $term_id, string $slug, string $content ): int {
		$post_id = self::factory()->post->create(
			array(
				'post_type'    => Handbook::POST_TYPE,
				'post_status'  => 'publish',
				'post_author'  => $author,
				'post_name'    => $slug,
				'post_title'   => $slug,
				'post_content' => $content,
			)
		);
		wp_set_object_terms( $post_id, array( $term_id ), Handbooks::TAXONOMY );
		return (int) $post_id;
	}

	/**
	 * The ZIP import requires upload_files, which a Contributor does not have.
	 *
	 * @return void
	 */
	public function test_import_zip_denies_contributor(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );
		$request  = new WP_REST_Request( 'POST', '/living-handbook/v1/import-zip' );
		$response = $this->server->dispatch( $request );
		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * An editor may reach the ZIP import (it fails on the missing file, not on
	 * permission), so the guard is not simply denying everyone.
	 *
	 * @return void
	 */
	public function test_import_zip_allows_editor(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$request  = new WP_REST_Request( 'POST', '/living-handbook/v1/import-zip' );
		$response = $this->server->dispatch( $request );
		$this->assertNotSame( 403, $response->get_status() );
	}

	/**
	 * The create, finalize and GitHub import endpoints require edit_posts, so a
	 * Subscriber is denied. (These routes carry no required parameters, so the
	 * permission check is what the request hits first.)
	 *
	 * @return void
	 */
	public function test_write_endpoints_deny_subscriber(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		foreach ( array( '/create', '/finalize', '/import-github' ) as $route ) {
			$request  = new WP_REST_Request( 'POST', '/living-handbook/v1' . $route );
			$response = $this->server->dispatch( $request );
			$this->assertSame( 403, $response->get_status(), $route . ' should be forbidden for a subscriber' );
		}
	}

	/**
	 * A Contributor re-importing a slug that matches another author's published
	 * page must not overwrite it: the callback falls back to creating a new
	 * draft for the Contributor instead. This is the original Prio 1 case.
	 *
	 * @return void
	 */
	public function test_create_does_not_overwrite_foreign_published_page(): void {
		$editor      = self::factory()->user->create( array( 'role' => 'editor' ) );
		$handbook_id = $this->make_handbook();
		$victim      = $this->make_page( $editor, $handbook_id, 'shared', 'ORIGINAL' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );
		$request = new WP_REST_Request( 'POST', '/living-handbook/v1/create' );
		$request->set_body_params(
			array(
				'title'    => 'Injected',
				'content'  => 'NEW CONTENT',
				'handbook' => $handbook_id,
				'slug'     => 'shared',
			)
		);
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( $data['updated'], 'A foreign page must not be reported as updated.' );
		$this->assertNotSame( $victim, (int) $data['id'], 'A new page must be created, not the victim.' );

		$after = get_post( $victim );
		$this->assertSame( 'ORIGINAL', $after->post_content, 'The victim content must be unchanged.' );
		$this->assertSame( 'publish', $after->post_status, 'The victim must stay published.' );
	}

	/**
	 * A Contributor cannot use the finalize pass to rewrite a foreign published
	 * page: its id is filtered out, so nothing is converted and its content is
	 * left untouched.
	 *
	 * @return void
	 */
	public function test_finalize_skips_foreign_page(): void {
		$editor      = self::factory()->user->create( array( 'role' => 'editor' ) );
		$handbook_id = $this->make_handbook();
		$this->make_page( $editor, $handbook_id, 'target', 'TARGET' );
		$foreign = $this->make_page( $editor, $handbook_id, 'foreign', '<p><a href="target.md">target.md</a></p>' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );
		$request = new WP_REST_Request( 'POST', '/living-handbook/v1/finalize' );
		$request->set_body_params( array( 'ids' => array( $foreign ) ) );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 0, (int) $data['converted'], 'A foreign page must not be finalized.' );
		$this->assertStringContainsString( 'target.md', get_post( $foreign )->post_content, 'The foreign link must be left as-is.' );
	}

	/**
	 * The same finalize call by an editor, who may edit the page, does rewrite
	 * the internal link, so the guard above is the reason for the difference,
	 * not a broken finalize.
	 *
	 * @return void
	 */
	public function test_finalize_runs_for_editable_page(): void {
		$editor      = self::factory()->user->create( array( 'role' => 'editor' ) );
		$handbook_id = $this->make_handbook();
		$this->make_page( $editor, $handbook_id, 'target', 'TARGET' );
		$own = $this->make_page( $editor, $handbook_id, 'own', '<p><a href="target.md">target.md</a></p>' );

		wp_set_current_user( $editor );
		$request = new WP_REST_Request( 'POST', '/living-handbook/v1/finalize' );
		$request->set_body_params( array( 'ids' => array( $own ) ) );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 1, (int) $data['converted'], 'The internal link should be converted for an editable page.' );
		$this->assertStringNotContainsString( 'href="target.md"', get_post( $own )->post_content );
	}

	/**
	 * The feedback endpoint rejects a logged-out request.
	 *
	 * @return void
	 */
	public function test_feedback_denies_guest(): void {
		wp_set_current_user( 0 );
		$request = new WP_REST_Request( 'POST', '/living-handbook/v1/feedback' );
		$request->set_body_params(
			array(
				'post_id' => 1,
				'value'   => 'yes',
			)
		);
		$response = $this->server->dispatch( $request );
		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * The feedback endpoint refuses a vote on a page the member may not view,
	 * so voting follows the same per-handbook access rules as reading.
	 *
	 * @return void
	 */
	public function test_feedback_denies_vote_on_hidden_page(): void {
		$editor      = self::factory()->user->create( array( 'role' => 'editor' ) );
		$handbook_id = $this->make_handbook();
		update_term_meta( $handbook_id, Handbooks::META_VISIBILITY, Handbooks::VISIBILITY_RESTRICTED );
		update_term_meta( $handbook_id, Handbooks::META_ROLES, array( 'administrator' ) );
		$page = $this->make_page( $editor, $handbook_id, 'secret', 'SECRET' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$request = new WP_REST_Request( 'POST', '/living-handbook/v1/feedback' );
		$request->set_body_params(
			array(
				'post_id' => $page,
				'value'   => 'yes',
			)
		);
		$response = $this->server->dispatch( $request );
		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * The filter endpoint returns an empty body for a handbook the current user
	 * may not view, so it cannot be used to read a hidden handbook's pages.
	 *
	 * @return void
	 */
	public function test_filter_returns_empty_for_hidden_handbook(): void {
		$handbook_id = $this->make_handbook();

		wp_set_current_user( 0 );
		$request = new WP_REST_Request( 'GET', '/living-handbook/v1/filter' );
		$request->set_param( 'term_id', $handbook_id );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( '', $data['html'] );
	}
}
