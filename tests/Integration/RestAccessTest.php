<?php
/**
 * REST read access to handbook content, for callers who may not see it.
 *
 * The handbook post type is REST-visible and uses the standard post capabilities,
 * so the separation of internal content rests entirely on the AccessController
 * filters: the coarse pre_get_posts layer, the precise the_posts layer, and the
 * single-item REST guard. There is no second, independent gate. These tests pin
 * that behaviour down for the two callers who must see nothing, a logged-out
 * guest and a subscriber, across the routes that return handbook content:
 * the plugin's own /filter and /search (which carry permission_callback
 * '__return_true' on purpose, see Filters::register_rest) and the core routes
 * /wp/v2/handbook as a collection and as a single item.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Frontend\Filters;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * A guest and a subscriber must not read restricted handbook content over REST.
 */
final class RestAccessTest extends WP_UnitTestCase {

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
	 * Create a handbook with a visibility setting.
	 *
	 * @param string   $visibility Visibility value.
	 * @param string[] $roles      Allowed roles for the restricted case.
	 * @return int Term ID.
	 */
	private function make_handbook( string $visibility, array $roles = array() ): int {
		$term_id = self::factory()->term->create(
			array(
				'taxonomy' => Handbooks::TAXONOMY,
				'name'     => 'Handbook ' . wp_generate_password( 6, false ),
			)
		);
		update_term_meta( $term_id, Handbooks::META_VISIBILITY, $visibility );
		if ( array() !== $roles ) {
			update_term_meta( $term_id, Handbooks::META_ROLES, $roles );
		}
		return (int) $term_id;
	}

	/**
	 * Create a published handbook page in a handbook.
	 *
	 * @param int    $term_id Handbook term ID.
	 * @param string $title   Page title.
	 * @return int Post ID.
	 */
	private function make_page( int $term_id, string $title ): int {
		$post_id = self::factory()->post->create(
			array(
				'post_type'    => Handbook::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => 'Secret body of ' . $title,
			)
		);
		wp_set_object_terms( $post_id, array( $term_id ), Handbooks::TAXONOMY );
		return (int) $post_id;
	}

	/**
	 * Dispatch a GET request and return the response data.
	 *
	 * @param string               $route Route path.
	 * @param array<string, mixed> $args  Query args.
	 * @return array{status: int, data: mixed}
	 */
	private function get( string $route, array $args = array() ): array {
		$request = new WP_REST_Request( 'GET', $route );
		foreach ( $args as $key => $value ) {
			$request->set_param( $key, $value );
		}
		$response = $this->server->dispatch( $request );
		return array(
			'status' => $response->get_status(),
			'data'   => $response->get_data(),
		);
	}

	/**
	 * The two callers who must not see internal content: a guest and a subscriber.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function outsiders(): array {
		return array(
			'guest'      => array( '' ),
			'subscriber' => array( 'subscriber' ),
		);
	}

	/**
	 * Log in as the given role, or stay logged out for an empty role.
	 *
	 * @param string $role Role name, or '' for a guest.
	 * @return void
	 */
	private function become( string $role ): void {
		if ( '' === $role ) {
			wp_set_current_user( 0 );
			return;
		}
		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => $role ) ) );
	}

	/**
	 * The plugin's filter route returns nothing from a restricted handbook.
	 *
	 * @dataProvider outsiders
	 * @param string $role Role name, or '' for a guest.
	 * @return void
	 */
	public function test_filter_route_hides_a_restricted_handbook( string $role ): void {
		$handbook = $this->make_handbook( Handbooks::VISIBILITY_RESTRICTED, array( 'administrator' ) );
		$this->make_page( $handbook, 'Restricted page' );

		$this->become( $role );
		$result = $this->get( '/' . Filters::REST_NAMESPACE . Filters::REST_ROUTE, array( 'term_id' => $handbook ) );

		$this->assertStringNotContainsString( 'Restricted page', (string) wp_json_encode( $result['data'] ) );
	}

	/**
	 * The plugin's search route returns nothing from a members-only handbook for a
	 * guest, and nothing from a restricted one for a subscriber.
	 *
	 * @dataProvider outsiders
	 * @param string $role Role name, or '' for a guest.
	 * @return void
	 */
	public function test_search_route_hides_content_the_caller_may_not_see( string $role ): void {
		$visibility = ( '' === $role ) ? Handbooks::VISIBILITY_MEMBERS : Handbooks::VISIBILITY_RESTRICTED;
		$handbook   = $this->make_handbook( $visibility, array( 'administrator' ) );
		$this->make_page( $handbook, 'Findable page' );

		$this->become( $role );
		$result = $this->get(
			'/' . Filters::REST_NAMESPACE . Filters::REST_ROUTE_SEARCH,
			array(
				'term_id' => $handbook,
				'q'       => 'Findable',
			)
		);

		$this->assertStringNotContainsString( 'Findable page', (string) wp_json_encode( $result['data'] ) );
	}

	/**
	 * The core collection route does not list a page of a restricted handbook.
	 *
	 * @dataProvider outsiders
	 * @param string $role Role name, or '' for a guest.
	 * @return void
	 */
	public function test_core_collection_hides_a_restricted_page( string $role ): void {
		$handbook = $this->make_handbook( Handbooks::VISIBILITY_RESTRICTED, array( 'administrator' ) );
		$page     = $this->make_page( $handbook, 'Collection page' );

		$this->become( $role );
		$result = $this->get( '/wp/v2/' . Handbook::POST_TYPE );

		$ids = array();
		foreach ( (array) $result['data'] as $item ) {
			if ( is_array( $item ) && isset( $item['id'] ) ) {
				$ids[] = (int) $item['id'];
			}
		}
		$this->assertNotContains( $page, $ids );
	}

	/**
	 * The counter-check for all of the above: someone who may read the handbook
	 * does get the content over the same four routes.
	 *
	 * Without this, the tests above could pass for the wrong reason. A route that
	 * returns nothing to anyone, or a query that never matches, would satisfy every
	 * "must not contain" assertion while proving nothing. This test fails in exactly
	 * that case, so the negative tests keep their meaning.
	 *
	 * @return void
	 */
	public function test_a_content_manager_does_get_the_content(): void {
		$handbook = $this->make_handbook( Handbooks::VISIBILITY_RESTRICTED, array( 'administrator' ) );
		$page     = $this->make_page( $handbook, 'Restricted page' );

		$this->become( 'administrator' );

		$filter = $this->get( '/' . Filters::REST_NAMESPACE . Filters::REST_ROUTE, array( 'term_id' => $handbook ) );
		$this->assertStringContainsString( 'Restricted page', (string) wp_json_encode( $filter['data'] ) );

		$search = $this->get(
			'/' . Filters::REST_NAMESPACE . Filters::REST_ROUTE_SEARCH,
			array(
				'term_id' => $handbook,
				'q'       => 'Restricted',
			)
		);
		$this->assertStringContainsString( 'Restricted page', (string) wp_json_encode( $search['data'] ) );

		$collection = $this->get( '/wp/v2/' . Handbook::POST_TYPE );
		$ids        = array();
		foreach ( (array) $collection['data'] as $item ) {
			if ( is_array( $item ) && isset( $item['id'] ) ) {
				$ids[] = (int) $item['id'];
			}
		}
		$this->assertContains( $page, $ids );

		$single = $this->get( '/wp/v2/' . Handbook::POST_TYPE . '/' . $page );
		$this->assertSame( 200, $single['status'] );
	}

	/**
	 * The core single-item route does not hand out a page of a restricted handbook.
	 *
	 * @dataProvider outsiders
	 * @param string $role Role name, or '' for a guest.
	 * @return void
	 */
	public function test_core_single_item_hides_a_restricted_page( string $role ): void {
		$handbook = $this->make_handbook( Handbooks::VISIBILITY_RESTRICTED, array( 'administrator' ) );
		$page     = $this->make_page( $handbook, 'Single page' );

		$this->become( $role );
		$result = $this->get( '/wp/v2/' . Handbook::POST_TYPE . '/' . $page );

		$this->assertGreaterThan( 299, $result['status'] );
		$this->assertStringNotContainsString( 'Secret body', (string) wp_json_encode( $result['data'] ) );
	}
}
