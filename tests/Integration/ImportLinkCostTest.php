<?php
/**
 * What resolving internal links costs in database queries.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Import\Postprocessor;
use WP_UnitTestCase;

/**
 * A link must not cost a query.
 *
 * Every .md link was resolved with its own lookup, by source path first, then by
 * slug, and the link text needed the handbooks of the target on top. A folder
 * import of a few hundred pages therefore ended in one request with thousands of
 * queries, the one request that has to finish for the links to work at all.
 * Postprocessor now reads the handbook into lookup tables once per run.
 */
final class ImportLinkCostTest extends WP_UnitTestCase {

	/**
	 * Create a handbook whose pages link to each other.
	 *
	 * @param int $pages Number of pages.
	 * @param int $links Links per page.
	 * @return int[] The post IDs.
	 */
	private function make_linked_pages( int $pages, int $links ): array {
		$term_id = (int) self::factory()->term->create(
			array(
				'taxonomy' => 'handbook_set',
				'name'     => 'Links ' . wp_generate_password( 6, false ),
			)
		);
		update_term_meta( $term_id, 'living_handbook_visibility', 'public' );

		$prefix = strtolower( wp_generate_password( 6, false ) );
		$ids    = array();
		for ( $i = 1; $i <= $pages; $i++ ) {
			$post_id = (int) self::factory()->post->create(
				array(
					'post_type'   => 'handbook',
					'post_status' => 'publish',
					'post_title'  => $prefix . ' page ' . $i,
				)
			);
			wp_set_object_terms( $post_id, array( $term_id ), 'handbook_set' );
			update_post_meta( $post_id, Postprocessor::META_SOURCE_PATH, 'docs/' . $prefix . '-' . $i . '.md' );
			$ids[] = $post_id;
		}

		foreach ( $ids as $index => $post_id ) {
			$body = '';
			for ( $k = 1; $k <= $links; $k++ ) {
				$target = ( ( $index + $k ) % $pages ) + 1;
				$body  .= '<p><a href="' . $prefix . '-' . $target . '.md">' . $prefix . '-' . $target . '.md</a></p>';
			}
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => $body,
				)
			);
		}

		return $ids;
	}

	/**
	 * Run the finalize pass and report what it cost and what it resolved.
	 *
	 * @param int[] $ids Post IDs.
	 * @return array{queries: int, converted: int}
	 */
	private function cost_of_finalizing( array $ids ): array {
		global $wpdb;

		wp_cache_flush();
		$before = $wpdb->num_queries;
		$report = Postprocessor::finalize_report( $ids );

		return array(
			'queries'   => $wpdb->num_queries - $before,
			'converted' => $report['converted'],
		);
	}

	/**
	 * Six times the links on the same number of pages must not cost six times
	 * the queries. What a page costs is its own update, not its links.
	 */
	public function test_a_link_does_not_cost_a_query(): void {
		$few  = $this->cost_of_finalizing( $this->make_linked_pages( 20, 1 ) );
		$many = $this->cost_of_finalizing( $this->make_linked_pages( 20, 6 ) );

		$this->assertSame( 20, $few['converted'], 'Every link should resolve.' );
		$this->assertSame( 120, $many['converted'], 'Every link should resolve.' );

		// 100 additional links, and the bound allows 20 additional queries: the
		// cost must follow the pages, not the links. Before the lookup tables,
		// those 100 links cost about 300 queries.
		$this->assertLessThanOrEqual(
			$few['queries'] + 20,
			$many['queries'],
			sprintf( '120 links took %d queries, 20 links took %d.', $many['queries'], $few['queries'] )
		);
	}

	/**
	 * The tables must answer exactly what the single lookups answered. The same
	 * page, converted on its own without the tables, has to come out the same.
	 */
	public function test_the_tables_resolve_what_the_single_lookups_resolved(): void {
		$ids = $this->make_linked_pages( 6, 3 );

		// One page through the single-page path, which does not load the tables.
		$alone = Postprocessor::convert_md_links( $ids[0] );
		$solo  = get_post( $ids[0] );

		// The same page again, this time through the run that loads them. The
		// content is already converted, so the run must leave it as it is.
		$report = Postprocessor::finalize_report( array( $ids[0] ) );
		$run    = get_post( $ids[0] );

		$this->assertSame( 3, $alone['converted'], 'The single-page path resolves all three links.' );
		$this->assertSame( 0, $report['converted'], 'Nothing is left to convert the second time.' );
		$this->assertNotNull( $solo );
		$this->assertNotNull( $run );
		$this->assertSame( $solo->post_content, $run->post_content, 'Both paths produce the same page.' );
		$this->assertStringNotContainsString( '.md"', (string) $run->post_content, 'No raw .md link is left.' );
	}
}
