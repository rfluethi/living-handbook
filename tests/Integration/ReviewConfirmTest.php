<?php
/**
 * The one-click review confirmation (E-0079).
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Export\StaticSite;
use LivingHandbook\Frontend\Filters;
use LivingHandbook\Frontend\PageMeta;
use LivingHandbook\Frontend\ReviewConfirm;
use LivingHandbook\Git\GitSync;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\Meta\Metadata;
use LivingHandbook\PostType\Handbook;
use ReflectionProperty;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * Whoever may edit the page sets date and reviewer with one click.
 */
final class ReviewConfirmTest extends WP_UnitTestCase {

	/**
	 * Start the REST server.
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
	 * A published page in a members handbook, reviewed long ago by someone else.
	 *
	 * @return int
	 */
	private function page(): int {
		$term_id = (int) self::factory()->term->create( array( 'taxonomy' => Handbooks::TAXONOMY ) );
		update_term_meta( $term_id, Handbooks::META_VISIBILITY, Handbooks::VISIBILITY_MEMBERS );
		$other   = (int) self::factory()->user->create( array( 'role' => 'editor' ) );
		$post_id = (int) self::factory()->post->create(
			array(
				'post_type'   => Handbook::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Ferienregelung',
				'meta_input'  => array(
					Metadata::REVIEWED => '2025-01-01',
					Metadata::INTERVAL => 90,
					Metadata::REVIEWER => $other,
				),
			)
		);
		wp_set_object_terms( $post_id, array( $term_id ), Handbooks::TAXONOMY );
		// After the insert: saving the page sets "last updated" to today.
		update_post_meta( $post_id, Metadata::UPDATED, '2025-01-01' );
		return $post_id;
	}

	/**
	 * POST the route.
	 *
	 * @param int $post_id Page.
	 * @return \WP_REST_Response
	 */
	private function confirm( int $post_id ) {
		$request = new WP_REST_Request( 'POST', '/' . Filters::REST_NAMESPACE . ReviewConfirm::REST_ROUTE );
		$request->set_param( 'post_id', $post_id );
		return rest_get_server()->dispatch( $request );
	}

	/**
	 * A click sets today and the clicking person, and nothing else.
	 *
	 * @return void
	 */
	public function test_a_click_sets_date_and_reviewer_only(): void {
		$post_id   = $this->page();
		$editor    = (int) self::factory()->user->create( array( 'role' => 'editor' ) );
		$revisions = count( wp_get_post_revisions( $post_id ) );
		$content   = get_post_field( 'post_content', $post_id );
		wp_set_current_user( $editor );

		$response = $this->confirm( $post_id );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( wp_date( 'Y-m-d' ), get_post_meta( $post_id, Metadata::REVIEWED, true ) );
		$this->assertSame( $editor, (int) get_post_meta( $post_id, Metadata::REVIEWER, true ) );
		$this->assertSame( '2025-01-01', get_post_meta( $post_id, Metadata::UPDATED, true ), 'The content did not change, so "last updated" stays.' );
		$this->assertSame( $content, get_post_field( 'post_content', $post_id ) );
		$this->assertCount( $revisions, wp_get_post_revisions( $post_id ) );
		$this->assertStringContainsString( 'living-handbook-badge--ok', $response->get_data()['html'] );
	}

	/**
	 * Without the right to edit the page, nothing changes.
	 *
	 * @return void
	 */
	public function test_who_may_not_edit_changes_nothing(): void {
		$post_id = $this->page();

		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->confirm( $post_id )->get_status() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertSame( 403, $this->confirm( $post_id )->get_status() );

		// An author may not edit someone else's page.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$this->assertSame( 403, $this->confirm( $post_id )->get_status() );

		$this->assertSame( '2025-01-01', get_post_meta( $post_id, Metadata::REVIEWED, true ) );
	}

	/**
	 * Only handbook pages.
	 *
	 * @return void
	 */
	public function test_other_post_types_are_refused(): void {
		$post = (int) self::factory()->post->create();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertSame( 403, $this->confirm( $post )->get_status() );
		$this->assertSame( '', get_post_meta( $post, Metadata::REVIEWED, true ) );
	}

	/**
	 * A page synced from GitHub keeps its date, which lives in the file.
	 *
	 * @return void
	 */
	public function test_a_synced_page_is_refused(): void {
		$post_id = $this->page();
		update_post_meta( $post_id, GitSync::META_SOURCE, GitSync::SOURCE_GITHUB );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->assertSame( 409, $this->confirm( $post_id )->get_status() );
		$this->assertSame( '2025-01-01', get_post_meta( $post_id, Metadata::REVIEWED, true ) );
		$this->assertStringContainsString( 'living-handbook-review-note', ReviewConfirm::control( $post_id, true ) );
		$this->assertStringNotContainsString( '<button', ReviewConfirm::control( $post_id, true ) );
	}

	/**
	 * The footer shows the button to an editor, not to a reader, and not in
	 * the static export.
	 *
	 * @return void
	 */
	public function test_the_button_is_for_editors_and_not_for_the_export(): void {
		$post_id = $this->page();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertStringNotContainsString( 'living-handbook-review-confirm', PageMeta::render_meta( $post_id ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertStringContainsString( 'class="living-handbook-review-confirm" data-post="' . $post_id . '"', PageMeta::render_meta( $post_id ) );

		$rendering = new ReflectionProperty( StaticSite::class, 'rendering' );
		$rendering->setAccessible( true );
		$rendering->setValue( null, true );
		try {
			$this->assertTrue( StaticSite::is_rendering() );
			$this->assertStringNotContainsString( 'living-handbook-review-confirm', PageMeta::render_meta( $post_id ) );
		} finally {
			$rendering->setValue( null, false );
		}
	}

	/**
	 * The script finds the route.
	 *
	 * @return void
	 */
	public function test_the_script_knows_the_route(): void {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/' . Filters::REST_NAMESPACE . ReviewConfirm::REST_ROUTE, $routes );

		do_action( 'wp_enqueue_scripts' );
		$data = (string) wp_scripts()->get_data( 'living-handbook', 'data' );
		$this->assertStringContainsString( '"review":', $data );
	}
}
