<?php
/**
 * A URL decides the shape of a parameter, not the code that reads it.
 *
 * ?lh_type=x is a string, ?lh_type[]=x a list, ?lh_type[a][]=x a list of
 * lists. The facet filter read all three with array_map( 'sanitize_title' ),
 * and the third one reached sanitize_title() as an array. In PHP 8 that is a
 * TypeError, so any visitor could put a handbook page on the "critical error"
 * screen with a hand-written link (0.75.0), on the page and through REST.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Frontend\Filters;
use LivingHandbook\Handbook\Handbooks;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * Filters with request values of every shape.
 */
final class RequestShapeTest extends WP_UnitTestCase {

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
	 * Leave $_GET as it was.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		unset( $_GET['lh_type'], $_GET['lh_topic'] );
		parent::tear_down();
	}

	/**
	 * Nested values are dropped on the page, flat ones still count.
	 *
	 * @return void
	 */
	public function test_a_nested_value_on_the_page_is_dropped(): void {
		$_GET['lh_type']  = array( 'a' => array( 'x' ), 'how-to' );
		$_GET['lh_topic'] = 'tools';

		$selections = Filters::current_selections();

		$this->assertSame( array( 'how-to' ), $selections['lh_type'] ?? null );
		$this->assertSame( array( 'tools' ), $selections['lh_topic'] ?? null, 'A plain string still counts as one value.' );
	}

	/**
	 * The REST filter answers a nested value like any other request.
	 *
	 * @return void
	 */
	public function test_a_nested_value_through_rest_is_dropped(): void {
		$term = (int) self::factory()->term->create(
			array(
				'taxonomy' => Handbooks::TAXONOMY,
				'name'     => 'Open',
			)
		);
		update_term_meta( $term, Handbooks::META_VISIBILITY, Handbooks::VISIBILITY_PUBLIC );

		$request = new WP_REST_Request( 'GET', '/' . Filters::REST_NAMESPACE . Filters::REST_ROUTE );
		$request->set_param( 'term_id', $term );
		$request->set_param( 'lh_type', array( 'a' => array( 'x' ) ) );

		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
	}
}
