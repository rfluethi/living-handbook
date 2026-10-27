<?php
/**
 * What it costs to link a list of pages that sit below other pages.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Frontend\Filters;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use LivingHandbook\Training\Lessons;
use LivingHandbook\Training\PathView;
use LivingHandbook\Training\Training;
use WP_REST_Request;
use WP_Term;
use WP_UnitTestCase;

/**
 * A list of links must not cost a query per parent page.
 *
 * The address of a handbook page below another is its parents' slugs and its
 * own, and get_permalink() reads each parent with get_post(), one query for
 * every parent that is not in the cache. A navigation has all the pages in the
 * cache; a list of lessons, search hits or filter results picked from all over
 * a handbook does not. Measured on 2000 pages, a learning path with twenty
 * lessons took 31 queries to show its list, nineteen of them for parents.
 */
final class ParentPrimingCostTest extends WP_UnitTestCase {

	/**
	 * Switch the learning paths on and make the search route available.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		update_option( Training::OPTION_ENABLED, 1 );
		( new Training() )->register_post_type();
		do_action( 'rest_api_init' );
	}

	/**
	 * Leave the registration as the rest of the suite expects to find it.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		delete_option( Training::OPTION_ENABLED );
		( new Training() )->register_post_type();

		parent::tear_down();
	}

	/**
	 * A public handbook.
	 *
	 * @return int Term id.
	 */
	private function handbook(): int {
		$term_id = (int) self::factory()->term->create( array( 'taxonomy' => Handbooks::TAXONOMY ) );
		update_term_meta( $term_id, Handbooks::META_VISIBILITY, Handbooks::VISIBILITY_PUBLIC );
		return $term_id;
	}

	/**
	 * A published page in a handbook.
	 *
	 * @param int    $handbook Handbook term id.
	 * @param string $title    Title.
	 * @param int    $parent   Parent page id.
	 * @return int
	 */
	private function page( int $handbook, string $title, int $parent = 0 ): int {
		$id = (int) self::factory()->post->create(
			array(
				'post_type'    => Handbook::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_parent'  => $parent,
				'post_content' => $title,
			)
		);
		wp_set_object_terms( $id, array( $handbook ), Handbooks::TAXONOMY );
		return $id;
	}

	/**
	 * Pages two levels down, each below a parent of its own.
	 *
	 * @param int $handbook Handbook term id.
	 * @param int $count    How many.
	 * @return array<int, int> The page ids.
	 */
	private function nested_pages( int $handbook, int $count ): array {
		$ids = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$top    = $this->page( $handbook, 'Area ' . $i );
			$parent = $this->page( $handbook, 'Section ' . $i, $top );
			$ids[]  = $this->page( $handbook, 'Needle ' . $i, $parent );
		}
		return $ids;
	}

	/**
	 * Count the queries of a callback, starting from an empty object cache.
	 *
	 * @param callable $render What to measure.
	 * @return int
	 */
	private function cost( callable $render ): int {
		global $wpdb;

		wp_cache_flush();
		$before = $wpdb->num_queries;
		$render();
		return $wpdb->num_queries - $before;
	}

	/**
	 * The lesson list of a path: twelve lessons below twelve different parents
	 * cost what two do.
	 */
	public function test_the_lesson_list_costs_no_query_per_parent(): void {
		// Both handbooks exist before anything is measured, so the second
		// measurement does not pay for a handbook the first one did not know.
		$paths = array();
		foreach ( array( 2, 12 ) as $count ) {
			$handbook = $this->handbook();
			$path     = (int) self::factory()->post->create(
				array(
					'post_type'   => Training::POST_TYPE,
					'post_status' => 'publish',
					'post_title'  => 'Path ' . $count,
				)
			);
			wp_set_object_terms( $path, array( $handbook ), Handbooks::TAXONOMY );
			Lessons::store( $path, $this->nested_pages( $handbook, $count ) );
			$paths[ $count ] = $path;
		}

		$costs = array();
		foreach ( $paths as $count => $path ) {
			wp_set_current_user( 0 );
			$this->go_to( (string) get_permalink( $path ) );

			$markup          = '';
			$costs[ $count ] = $this->cost(
				static function () use ( &$markup ): void {
					$markup = PathView::render_lessons();
				}
			);
			$this->assertSame( $count, substr_count( $markup, 'living-handbook-path__item' ), 'Every lesson is listed.' );
			$this->assertStringContainsString( 'section-0', $markup, 'The lesson address runs through its parents.' );
		}

		// Before, twelve lessons took twenty queries more than two: two parents
		// per lesson, one get_post() each.
		$this->assertLessThanOrEqual( $costs[2], $costs[12], sprintf( 'Two lessons took %d queries, twelve took %d.', $costs[2], $costs[12] ) );
	}

	/**
	 * The search typeahead: eight hits below eight parents cost what two do.
	 */
	public function test_the_search_costs_no_query_per_parent(): void {
		$handbooks = array();
		foreach ( array( 2, 8 ) as $count ) {
			$handbooks[ $count ] = $this->handbook();
			$this->nested_pages( $handbooks[ $count ], $count );
		}

		$costs = array();
		foreach ( $handbooks as $count => $handbook ) {
			wp_set_current_user( 0 );
			$request = new WP_REST_Request( 'GET', '/' . Filters::REST_NAMESPACE . Filters::REST_ROUTE_SEARCH );
			$request->set_param( 'term_id', $handbook );
			$request->set_param( 'q', 'Needle' );

			$data            = array();
			$costs[ $count ] = $this->cost(
				static function () use ( $request, &$data ): void {
					$data = rest_do_request( $request )->get_data();
				}
			);
			$this->assertIsArray( $data );
			$this->assertCount( $count, $data['results'], 'Every hit is returned.' );
		}

		$this->assertLessThanOrEqual( $costs[2], $costs[8], sprintf( 'Two hits took %d queries, eight took %d.', $costs[2], $costs[8] ) );
	}

	/**
	 * The filtered result cards: ten results below ten parents cost what two do.
	 */
	public function test_the_filtered_results_cost_no_query_per_parent(): void {
		$terms = array();
		foreach ( array( 2, 10 ) as $count ) {
			$handbook = $this->handbook();
			$this->nested_pages( $handbook, $count );
			$term = get_term( $handbook, Handbooks::TAXONOMY );
			$this->assertInstanceOf( WP_Term::class, $term );
			$terms[ $count ] = $term;
		}

		$costs = array();
		foreach ( $terms as $count => $term ) {
			wp_set_current_user( 0 );
			$html            = '';
			$costs[ $count ] = $this->cost(
				static function () use ( $term, &$html ): void {
					$html = Filters::filtered_results( $term, array(), 'Needle', 1 );
				}
			);
			$this->assertSame( $count, substr_count( $html, '<article ' ), 'Every result has a card.' );
		}

		$this->assertLessThanOrEqual( $costs[2], $costs[10], sprintf( 'Two results took %d queries, ten took %d.', $costs[2], $costs[10] ) );
	}
}
