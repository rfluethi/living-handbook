<?php
/**
 * Whoever may edit a page gets it back in the block editor and the preview.
 *
 * Up to 0.75.1 the REST guard and the single-page guard asked only whether the
 * reader may read the handbook. An author's own new page has no handbook yet,
 * and an author's page in a restricted handbook lies outside what the author
 * may read, so the block editor got a 404 for the page it was editing: "this
 * item does not exist" on opening, an error on saving although the change was
 * stored, and a refused preview. Editing rights now open the page itself, and
 * nothing else: lists, search and navigation still follow the handbook.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Access\AccessController;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;
use WPDieException;

/**
 * AccessController for people who may edit the page in question.
 */
final class EditorAccessTest extends WP_UnitTestCase {

	/**
	 * A REST server.
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
	 * Drop the REST server.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tear_down();
	}

	/**
	 * A handbook that only administrators may read.
	 *
	 * @return int Term id.
	 */
	private function restricted_handbook(): int {
		$term_id = (int) self::factory()->term->create( array( 'taxonomy' => Handbooks::TAXONOMY ) );
		update_term_meta( $term_id, Handbooks::META_VISIBILITY, Handbooks::VISIBILITY_RESTRICTED );
		update_term_meta( $term_id, Handbooks::META_ROLES, array( 'administrator' ) );
		return $term_id;
	}

	/**
	 * A handbook page.
	 *
	 * @param int    $author  Author id.
	 * @param string $status  Post status.
	 * @param int    $term_id Handbook, 0 for none.
	 * @return int Post id.
	 */
	private function page( int $author, string $status, int $term_id = 0 ): int {
		$id = (int) self::factory()->post->create(
			array(
				'post_type'   => Handbook::POST_TYPE,
				'post_status' => $status,
				'post_author' => $author,
				'post_title'  => 'Draft of mine',
			)
		);
		if ( $term_id > 0 ) {
			wp_set_object_terms( $id, array( $term_id ), Handbooks::TAXONOMY );
		}
		return $id;
	}

	/**
	 * GET the page the way the block editor does.
	 *
	 * @param int $id Post id.
	 * @return int Status.
	 */
	private function editor_get( int $id ): int {
		$request = new WP_REST_Request( 'GET', rest_get_route_for_post_type_items( Handbook::POST_TYPE ) . '/' . $id );
		$request->set_param( 'context', 'edit' );
		return rest_do_request( $request )->get_status();
	}

	/**
	 * An author opens and saves their own new page, which has no handbook yet.
	 *
	 * @return void
	 */
	public function test_an_author_works_on_a_new_page_without_a_handbook(): void {
		$author = (int) self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $author );
		$id = $this->page( $author, 'auto-draft' );

		$this->assertSame( 200, $this->editor_get( $id ), 'Opening the new page in the editor.' );

		$save = new WP_REST_Request( 'POST', rest_get_route_for_post_type_items( Handbook::POST_TYPE ) . '/' . $id );
		$save->set_param( 'title', 'First version' );
		$save->set_param( 'status', 'draft' );
		$this->assertSame( 200, rest_do_request( $save )->get_status(), 'Saving answers with the page, not with a 404.' );
	}

	/**
	 * An author's own page in a handbook the author may not read opens in the
	 * editor and in the preview.
	 *
	 * @return void
	 */
	public function test_an_author_works_on_their_page_in_a_restricted_handbook(): void {
		$author = (int) self::factory()->user->create( array( 'role' => 'author' ) );
		$id     = $this->page( $author, 'publish', $this->restricted_handbook() );
		wp_set_current_user( $author );

		$this->assertSame( 200, $this->editor_get( $id ) );

		$this->go_to( (string) get_permalink( $id ) );
		try {
			( new AccessController() )->guard_singular();
		} catch ( WPDieException $e ) {
			$this->fail( 'The author was refused their own page: ' . $e->getMessage() );
		}
		$this->assertTrue( is_singular( Handbook::POST_TYPE ) );
	}

	/**
	 * An author opens the attachment page of an image on their own page in a
	 * handbook they may not read, and the editor gets the image through REST.
	 * Both guards asked only whether the parent may be read, so the author was
	 * refused a file of the page they may open.
	 *
	 * @return void
	 */
	public function test_an_author_opens_the_files_of_their_page_in_a_restricted_handbook(): void {
		$author = (int) self::factory()->user->create( array( 'role' => 'author' ) );
		$id     = $this->page( $author, 'publish', $this->restricted_handbook() );
		$image  = (int) self::factory()->attachment->create(
			array(
				'post_parent'    => $id,
				'post_mime_type' => 'image/png',
				'post_title'     => 'Screenshot',
				'post_author'    => $author,
				'file'           => 'screenshot.png',
			)
		);
		wp_set_current_user( $author );

		$this->assertSame( 200, rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/media/' . $image ) )->get_status(), 'The editor gets the image.' );

		$this->go_to( (string) get_permalink( $image ) );
		$this->assertTrue( is_attachment() );
		try {
			( new AccessController() )->guard_attachment();
		} catch ( WPDieException $e ) {
			$this->fail( 'The author was refused a file of their own page: ' . $e->getMessage() );
		}

		$other = (int) self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $other );
		$this->assertSame( 404, rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/media/' . $image ) )->get_status(), 'Another author still does not.' );
		try {
			( new AccessController() )->guard_attachment();
			$this->fail( 'Another author should have been refused.' );
		} catch ( WPDieException $e ) {
			$this->assertStringContainsString( 'does not have access', $e->getMessage() );
		}
	}

	/**
	 * A contributor gets their own draft back as well.
	 *
	 * @return void
	 */
	public function test_a_contributor_works_on_their_own_draft(): void {
		$contributor = (int) self::factory()->user->create( array( 'role' => 'contributor' ) );
		$id          = $this->page( $contributor, 'draft', $this->restricted_handbook() );
		wp_set_current_user( $contributor );

		$this->assertSame( 200, $this->editor_get( $id ) );
	}

	/**
	 * The edit right is the line: another author's page in the restricted
	 * handbook stays closed, through REST and on the front end.
	 *
	 * @return void
	 */
	public function test_somebody_elses_page_stays_closed(): void {
		$owner = (int) self::factory()->user->create( array( 'role' => 'author' ) );
		$other = (int) self::factory()->user->create( array( 'role' => 'author' ) );
		$id    = $this->page( $owner, 'publish', $this->restricted_handbook() );
		wp_set_current_user( $other );

		$this->assertSame( 404, rest_do_request( new WP_REST_Request( 'GET', rest_get_route_for_post_type_items( Handbook::POST_TYPE ) . '/' . $id ) )->get_status() );

		$this->go_to( (string) get_permalink( $id ) );
		try {
			( new AccessController() )->guard_singular();
			$this->fail( 'Another author should have been refused.' );
		} catch ( WPDieException $e ) {
			$this->assertStringContainsString( 'does not have access', $e->getMessage() );
		}
	}

	/**
	 * Editing one's own page does not open the handbook around it: the other
	 * pages stay out of a list the author asks for.
	 *
	 * @return void
	 */
	public function test_the_rest_of_the_handbook_stays_closed(): void {
		$author  = (int) self::factory()->user->create( array( 'role' => 'author' ) );
		$term_id = $this->restricted_handbook();
		$this->page( $author, 'publish', $term_id );
		$foreign = $this->page( (int) self::factory()->user->create( array( 'role' => 'editor' ) ), 'publish', $term_id );
		wp_set_current_user( $author );

		$response = rest_do_request( new WP_REST_Request( 'GET', rest_get_route_for_post_type_items( Handbook::POST_TYPE ) ) );
		$ids      = wp_list_pluck( (array) $response->get_data(), 'id' );

		$this->assertNotContains( $foreign, $ids );
	}

	/**
	 * An administrator reads a restricted handbook on both levels: the pages and
	 * the handbook itself, its entry page and its place in the lists of
	 * handbooks. Up to 0.75.1 the pages were open and the handbook was not.
	 *
	 * @return void
	 */
	public function test_an_administrator_reads_the_restricted_handbook_itself(): void {
		$term_id = (int) self::factory()->term->create( array( 'taxonomy' => Handbooks::TAXONOMY ) );
		update_term_meta( $term_id, Handbooks::META_VISIBILITY, Handbooks::VISIBILITY_RESTRICTED );
		update_term_meta( $term_id, Handbooks::META_ROLES, array( 'author' ) );
		$this->page( (int) self::factory()->user->create( array( 'role' => 'author' ) ), 'publish', $term_id );
		$admin = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$this->assertTrue( AccessController::can_view_term( $term_id, $admin ) );
		$this->assertContains( $term_id, wp_list_pluck( AccessController::readable_terms( $admin ), 'term_id' ), 'The handbook is in the overview, the menu and the navigation links.' );

		$this->go_to( (string) get_term_link( $term_id, Handbooks::TAXONOMY ) );
		try {
			( new AccessController() )->guard_term_archive();
		} catch ( WPDieException $e ) {
			$this->fail( 'The administrator was refused the entry page: ' . $e->getMessage() );
		}
		$this->assertTrue( is_tax( Handbooks::TAXONOMY ) );
	}

	/**
	 * An editor does not read a handbook kept to administrators, on neither
	 * level. Decided on 2026-10-27 (E-0075), replacing E-0070.
	 *
	 * @return void
	 */
	public function test_an_editor_does_not_read_a_handbook_kept_to_administrators(): void {
		$term_id = $this->restricted_handbook();
		$page    = $this->page( (int) self::factory()->user->create( array( 'role' => 'administrator' ) ), 'publish', $term_id );
		$editor  = (int) self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor );

		$this->assertFalse( AccessController::can_view_term( $term_id, $editor ) );
		$this->assertFalse( AccessController::can_view_post( $page, $editor ) );
		$this->assertNotContains( $term_id, wp_list_pluck( AccessController::readable_terms( $editor ), 'term_id' ) );
		// 403 from core: the editor context asks for the edit right first, and
		// that is closed too (see RestrictedFromEditorsTest).
		$this->assertContains( $this->editor_get( $page ), array( 403, 404 ), 'Not through REST either.' );

		$this->go_to( (string) get_permalink( $page ) );
		try {
			( new AccessController() )->guard_singular();
			$this->fail( 'The editor should have been refused the page.' );
		} catch ( WPDieException $e ) {
			$this->assertStringContainsString( 'does not have access', $e->getMessage() );
		}
	}

	/**
	 * An author outside the allowed roles still does not get the handbook.
	 *
	 * @return void
	 */
	public function test_an_author_does_not_read_the_restricted_handbook(): void {
		$term_id = $this->restricted_handbook();
		$author  = (int) self::factory()->user->create( array( 'role' => 'author' ) );

		$this->assertFalse( AccessController::can_view_term( $term_id, $author ) );
	}
}
