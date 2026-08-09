<?php
/**
 * What building an export bundle costs in database queries.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Import\HandbookExport;
use WP_Term;
use WP_UnitTestCase;

/**
 * The export must not cost four queries per page.
 *
 * Every exported page is asked for its terms in four taxonomies. Asked one
 * page at a time that is four queries each, which on a handbook of 2000 pages
 * was 8011 queries and 3.4 seconds, in a request that also has to build a ZIP.
 */
final class ExportQueryCostTest extends WP_UnitTestCase {

	/**
	 * Create a handbook with pages that carry taxonomy terms.
	 *
	 * @param int $pages How many pages.
	 * @return WP_Term The handbook term.
	 */
	private function make_handbook( int $pages ): WP_Term {
		$term_id = (int) self::factory()->term->create(
			array(
				'taxonomy' => 'handbook_set',
				'name'     => 'Export ' . wp_generate_password( 6, false ),
			)
		);
		update_term_meta( $term_id, 'living_handbook_visibility', 'public' );

		$taxonomies = array( 'handbook_type', 'handbook_topic', 'handbook_role', 'handbook_audience' );
		$terms        = array();
		foreach ( $taxonomies as $taxonomy ) {
			$terms[ $taxonomy ] = (int) self::factory()->term->create(
				array(
					'taxonomy' => $taxonomy,
					'name'     => $taxonomy . ' ' . wp_generate_password( 6, false ),
				)
			);
		}

		for ( $i = 0; $i < $pages; $i++ ) {
			$post_id = (int) self::factory()->post->create(
				array(
					'post_type'   => 'handbook',
					'post_status' => 'publish',
				)
			);
			wp_set_object_terms( $post_id, array( $term_id ), 'handbook_set' );
			foreach ( $taxonomies as $taxonomy ) {
				wp_set_object_terms( $post_id, array( $terms[ $taxonomy ] ), $taxonomy );
			}
		}

		$term = get_term( $term_id );
		$this->assertInstanceOf( WP_Term::class, $term );
		return $term;
	}

	/**
	 * Build the manifest and report what it cost.
	 *
	 * @param WP_Term $term  Handbook term.
	 * @param int     $pages How many pages must appear in it.
	 * @return int Number of queries.
	 */
	private function cost_of_exporting( WP_Term $term, int $pages ): int {
		global $wpdb;

		wp_cache_flush();
		$media  = array();
		$before = $wpdb->num_queries;
		$export = new HandbookExport();

		$manifest = $export->build_manifest( $term, $media );
		$cost     = $wpdb->num_queries - $before;

		$this->assertCount( $pages, $manifest['pages'], 'Every page belongs in the bundle.' );
		$this->assertNotEmpty( $manifest['pages'][0]['terms']['handbook_topic'], 'And every page brings its terms along.' );

		return $cost;
	}

	/**
	 * Eight times the pages must not cost eight times the queries.
	 */
	public function test_the_manifest_does_not_cost_four_queries_per_page(): void {
		$small = $this->make_handbook( 5 );
		$large = $this->make_handbook( 40 );

		$small_cost = $this->cost_of_exporting( $small, 5 );
		$large_cost = $this->cost_of_exporting( $large, 40 );

		// The bound is loose on purpose: what matters is that the cost does not
		// follow the number of pages. Before, 35 more pages meant 140 more
		// queries.
		$this->assertLessThanOrEqual(
			$small_cost + 3,
			$large_cost,
			sprintf( 'Exporting 40 pages took %d queries, exporting 5 took %d.', $large_cost, $small_cost )
		);
	}
}
