<?php
/**
 * What the access filter costs in database queries.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Frontend\PageTree;
use WP_Query;
use WP_UnitTestCase;

/**
 * The per-post access decision must not cost a query per post.
 *
 * The_posts runs before WP_Query fills its caches, so a naive implementation
 * reads every post and its handbook membership back from the database, once per
 * row. That is invisible on a handbook with ten pages and fatal on one with two
 * thousand: measured on a seeded handbook of 2000 pages, rendering the
 * navigation tree cost 2009 queries before and 10 after.
 */
final class AccessQueryCostTest extends WP_UnitTestCase {

	/**
	 * Create a public handbook with a number of pages.
	 *
	 * @param int $pages How many pages.
	 * @return int The handbook term ID.
	 */
	private function make_handbook_with_pages( int $pages ): int {
		$term_id = (int) self::factory()->term->create(
			array(
				'taxonomy' => 'handbook_set',
				'name'     => 'Cost ' . wp_generate_password( 6, false ),
			)
		);
		update_term_meta( $term_id, 'living_handbook_visibility', 'public' );

		for ( $i = 0; $i < $pages; $i++ ) {
			$post_id = (int) self::factory()->post->create(
				array(
					'post_type'   => 'handbook',
					'post_status' => 'publish',
				)
			);
			wp_set_object_terms( $post_id, array( $term_id ), 'handbook_set' );
		}
		return $term_id;
	}

	/**
	 * Read a whole handbook as a guest and count the queries it took.
	 *
	 * PageTree::children_map() is the real path: an unlimited query, which
	 * WP_Query does not split into ids-then-objects, so nothing is cached when
	 * the access filter runs. A limited query would prime the caches by itself
	 * and hide the very thing this test is about.
	 *
	 * @param int $term_id Handbook term ID.
	 * @param int $expected How many pages must come back.
	 * @return int Number of queries.
	 */
	private function cost_of_reading( int $term_id, int $expected ): int {
		global $wpdb;

		wp_set_current_user( 0 );
		wp_cache_flush();

		$before = $wpdb->num_queries;
		$map    = PageTree::children_map( $term_id );
		$cost   = $wpdb->num_queries - $before;

		$count = 0;
		foreach ( $map as $children ) {
			$count += count( $children );
		}
		$this->assertSame( $expected, $count, 'Every public page should come back.' );

		return $cost;
	}

	/**
	 * Eight times as many pages must not cost eight times as many queries.
	 */
	public function test_reading_a_handbook_does_not_cost_a_query_per_post(): void {
		$small = $this->make_handbook_with_pages( 5 );
		$large = $this->make_handbook_with_pages( 40 );

		$small_cost = $this->cost_of_reading( $small, 5 );
		$large_cost = $this->cost_of_reading( $large, 40 );

		// The bound is deliberately loose: what matters is that the cost does not
		// follow the number of pages. Before the fix, reading 40 pages cost about
		// 40 queries more than reading 5.
		$this->assertLessThanOrEqual(
			$small_cost + 2,
			$large_cost,
			sprintf( 'Reading 40 pages took %d queries, reading 5 took %d.', $large_cost, $small_cost )
		);
	}

	/**
	 * The saving must not come at the price of the rule: a page of a members-only
	 * handbook still has to disappear for a guest, primed cache or not.
	 */
	public function test_priming_does_not_weaken_the_rule(): void {
		$public  = (int) self::factory()->term->create(
			array(
				'taxonomy' => 'handbook_set',
				'name'     => 'Public ' . wp_generate_password( 6, false ),
			)
		);
		$private = (int) self::factory()->term->create(
			array(
				'taxonomy' => 'handbook_set',
				'name'     => 'Private ' . wp_generate_password( 6, false ),
			)
		);
		update_term_meta( $public, 'living_handbook_visibility', 'public' );
		update_term_meta( $private, 'living_handbook_visibility', 'members' );

		$public_ids  = array();
		$private_ids = array();
		for ( $i = 0; $i < 3; $i++ ) {
			foreach ( array( $public, $private ) as $term_id ) {
				$post_id = (int) self::factory()->post->create(
					array(
						'post_type'   => 'handbook',
						'post_status' => 'publish',
					)
				);
				wp_set_object_terms( $post_id, array( $term_id ), 'handbook_set' );
				if ( $term_id === $public ) {
					$public_ids[] = $post_id;
				} else {
					$private_ids[] = $post_id;
				}
			}
		}

		wp_set_current_user( 0 );
		wp_cache_flush();

		$query = new WP_Query(
			array(
				'post_type'      => 'handbook',
				'post__in'       => array_merge( $public_ids, $private_ids ),
				'posts_per_page' => 10,
				'no_found_rows'  => true,
			)
		);

		$found = wp_list_pluck( $query->posts, 'ID' );
		sort( $found );
		$expected = $public_ids;
		sort( $expected );

		$this->assertSame( $expected, $found, 'A guest may see the public pages and nothing else.' );
	}
}
