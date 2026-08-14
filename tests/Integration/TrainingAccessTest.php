<?php
/**
 * A learning path is as private as the handbook it belongs to.
 *
 * The path is a second post type carrying the same handbook term, and every
 * read channel that was closed for handbook pages had to be closed for it in
 * the same movement: the coarse query layer, the precise result filter, the
 * single-page guard and the REST item guard. A type that slipped through any
 * one of them would publish the table of contents of an internal handbook,
 * which is most of what the handbook is trying to keep to itself.
 *
 * The lesson picker's own REST route is checked here too, for the same reason:
 * it answers with page titles of a handbook, so it has to be at least as strict
 * as the page it is used from.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Access\AccessController;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use LivingHandbook\Training\LessonPicker;
use LivingHandbook\Training\Lessons;
use LivingHandbook\Training\PathView;
use LivingHandbook\Training\Training;
use WP_Query;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * The learning path type under the access rules of its handbook.
 */
final class TrainingAccessTest extends WP_UnitTestCase {

	/**
	 * The REST server used to dispatch requests.
	 *
	 * @var WP_REST_Server
	 */
	private WP_REST_Server $server;

	/**
	 * A handbook only members may read.
	 *
	 * @var int
	 */
	private int $internal = 0;

	/**
	 * Switch the module on, register the type, and spin up a REST server.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		update_option( Training::OPTION_ENABLED, 1 );
		( new Training() )->register_post_type();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );

		$this->internal = (int) self::factory()->term->create( array( 'taxonomy' => Handbooks::TAXONOMY ) );
		update_term_meta( $this->internal, Handbooks::META_VISIBILITY, Handbooks::VISIBILITY_MEMBERS );
	}

	/**
	 * Leave the registration as the rest of the suite expects to find it.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		delete_option( Training::OPTION_ENABLED );
		( new Training() )->register_post_type();

		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tear_down();
	}

	/**
	 * A learning path in the internal handbook, with one lesson.
	 *
	 * @return array{path:int, lesson:int}
	 */
	private function internal_path(): array {
		$lesson = (int) self::factory()->post->create(
			array(
				'post_type'   => Handbook::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Internal lesson',
			)
		);
		wp_set_object_terms( $lesson, array( $this->internal ), Handbooks::TAXONOMY );

		$path = (int) self::factory()->post->create(
			array(
				'post_type'   => Training::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Internal onboarding',
			)
		);
		wp_set_object_terms( $path, array( $this->internal ), Handbooks::TAXONOMY );
		Lessons::store( $path, array( $lesson ) );

		return array(
			'path'   => $path,
			'lesson' => $lesson,
		);
	}

	/**
	 * The plain decision, which everything else is built on.
	 *
	 * @return void
	 */
	public function test_a_guest_may_not_view_a_path_of_an_internal_handbook(): void {
		$fixture = $this->internal_path();

		$this->assertFalse( AccessController::can_view_post( $fixture['path'], 0 ) );
	}

	/**
	 * The list of learning paths a guest gets back is empty, and it is empty
	 * because the query layer says so rather than because nothing exists.
	 *
	 * @return void
	 */
	public function test_a_query_for_learning_paths_returns_none_to_a_guest(): void {
		$this->internal_path();
		wp_set_current_user( 0 );

		$query = new WP_Query(
			array(
				'post_type'   => Training::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		$this->assertSame( array(), wp_list_pluck( $query->posts, 'ID' ) );
	}

	/**
	 * A restricted handbook names the roles and people who may read it, and a
	 * subscriber who is on neither list gets nothing, logged in or not. Note the
	 * difference to "members", which does mean anybody with an account: the
	 * subscriber is only the wrong reader for a restricted handbook.
	 *
	 * @return void
	 */
	public function test_a_subscriber_gets_no_path_of_a_restricted_handbook(): void {
		$restricted = (int) self::factory()->term->create( array( 'taxonomy' => Handbooks::TAXONOMY ) );
		update_term_meta( $restricted, Handbooks::META_VISIBILITY, Handbooks::VISIBILITY_RESTRICTED );
		update_term_meta( $restricted, Handbooks::META_ROLES, array( 'editor' ) );

		$path = (int) self::factory()->post->create(
			array(
				'post_type'   => Training::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Restricted onboarding',
			)
		);
		wp_set_object_terms( $path, array( $restricted ), Handbooks::TAXONOMY );

		$user = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user );

		$this->assertFalse( AccessController::can_view_post( $path, $user ) );

		$query = new WP_Query(
			array(
				'post_type'   => Training::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		$this->assertNotContains( $path, wp_list_pluck( $query->posts, 'ID' ) );
	}

	/**
	 * The single REST item of a learning path is emptied for a caller who may
	 * not read it. Without this the type would hand out its title and content
	 * under /wp/v2/lh_training/<id>.
	 *
	 * @return void
	 */
	public function test_the_rest_item_of_a_path_is_guarded(): void {
		$fixture = $this->internal_path();
		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/' . Training::POST_TYPE . '/' . $fixture['path'] );
		$response = $this->server->dispatch( $request );

		$this->assertNotSame( 200, $response->get_status(), 'A guest reads an internal learning path over REST.' );
	}

	/**
	 * A lesson this reader may not see is not in the list and not in the count.
	 * The count is the part that matters: a path that said "1 of 8" while
	 * showing seven would tell everybody exactly how much they are missing.
	 *
	 * @return void
	 */
	public function test_a_lesson_the_reader_may_not_see_leaves_list_and_counter(): void {
		$handbook = (int) self::factory()->term->create( array( 'taxonomy' => Handbooks::TAXONOMY ) );
		update_term_meta( $handbook, Handbooks::META_VISIBILITY, Handbooks::VISIBILITY_PUBLIC );

		$open   = (int) self::factory()->post->create(
			array(
				'post_type'   => Handbook::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Open',
			)
		);
		$closed = (int) self::factory()->post->create(
			array(
				'post_type'   => Handbook::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Closed',
			)
		);
		wp_set_object_terms( $open, array( $handbook ), Handbooks::TAXONOMY );
		wp_set_object_terms( $closed, array( $handbook ), Handbooks::TAXONOMY );

		$path = (int) self::factory()->post->create(
			array(
				'post_type'   => Training::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Mixed',
			)
		);
		wp_set_object_terms( $path, array( $handbook ), Handbooks::TAXONOMY );
		Lessons::store( $path, array( $open, $closed ) );

		// A site can refuse a single page through this filter, and the path has
		// to follow that decision instead of second-guessing it.
		$hide = static function ( bool $allowed, int $post_id ) use ( $closed ): bool {
			return $post_id === $closed ? false : $allowed;
		};
		add_filter( 'living_handbook_can_view_post', $hide, 10, 2 );

		$visible = Lessons::visible( $path, 0 );

		$this->go_to( (string) get_permalink( $path ) );
		$markup = PathView::render_lessons();

		remove_filter( 'living_handbook_can_view_post', $hide, 10 );

		$this->assertSame( array( $open ), wp_list_pluck( $visible, 'ID' ) );
		$this->assertStringContainsString( 'data-total="1"', $markup );
		$this->assertStringNotContainsString( 'Closed', $markup );
	}

	/**
	 * The lesson search is a route that lists page titles, so it asks for edit
	 * rights on the path it is used from, and nothing weaker.
	 *
	 * @return void
	 */
	public function test_the_lesson_search_refuses_a_caller_without_edit_rights(): void {
		$fixture = $this->internal_path();
		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$request = new WP_REST_Request( 'GET', '/living-handbook/v1' . LessonPicker::REST_ROUTE );
		$request->set_param( 'training_id', $fixture['path'] );
		$request->set_param( 'q', 'Internal' );

		$this->assertSame( 403, $this->server->dispatch( $request )->get_status() );
	}

	/**
	 * The search is scoped to the handbook of the path: a page with the same
	 * title in another handbook is not offered, because adding it would build a
	 * path whose lessons nobody can see together.
	 *
	 * @return void
	 */
	public function test_the_lesson_search_stays_inside_the_handbook_of_the_path(): void {
		$fixture = $this->internal_path();
		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$elsewhere = (int) self::factory()->term->create( array( 'taxonomy' => Handbooks::TAXONOMY ) );
		update_term_meta( $elsewhere, Handbooks::META_VISIBILITY, Handbooks::VISIBILITY_PUBLIC );
		$stranger = (int) self::factory()->post->create(
			array(
				'post_type'   => Handbook::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Internal stranger',
			)
		);
		wp_set_object_terms( $stranger, array( $elsewhere ), Handbooks::TAXONOMY );

		$draft = (int) self::factory()->post->create(
			array(
				'post_type'   => Handbook::POST_TYPE,
				'post_status' => 'draft',
				'post_title'  => 'Internal draft',
			)
		);
		wp_set_object_terms( $draft, array( $this->internal ), Handbooks::TAXONOMY );

		$request = new WP_REST_Request( 'GET', '/living-handbook/v1' . LessonPicker::REST_ROUTE );
		$request->set_param( 'training_id', $fixture['path'] );
		$request->set_param( 'q', 'Internal' );

		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();
		$ids      = wp_list_pluck( $data['results'], 'id' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertContains( $fixture['lesson'], $ids );
		$this->assertNotContains( $stranger, $ids, 'The search leaves the handbook of the path.' );
		$this->assertNotContains( $draft, $ids, 'The search offers a draft.' );
	}
}
