<?php
/**
 * One handbook per page, enforced rather than assumed.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Frontend\Navigation;
use LivingHandbook\Handbook\Handbooks;
use WP_UnitTestCase;

/**
 * The data model says a page belongs to exactly one handbook, and everything
 * built on it assumes that: one navigation tree, one entry page, one access
 * configuration. The block editor renders the handbooks as a checkbox list and
 * used to let a page land in two, which produced no error message but a page
 * whose navigation showed a tree the page is not in.
 */
final class OneHandbookPerPageTest extends WP_UnitTestCase {

	/**
	 * Create a handbook.
	 *
	 * @param string $name Handbook name.
	 * @return int Term id.
	 */
	private function handbook( string $name ): int {
		$term_id = (int) self::factory()->term->create(
			array(
				'taxonomy' => Handbooks::TAXONOMY,
				'name'     => $name,
			)
		);
		update_term_meta( $term_id, Handbooks::META_VISIBILITY, Handbooks::VISIBILITY_PUBLIC );
		return $term_id;
	}

	/**
	 * Create a handbook page.
	 *
	 * @return int Post id.
	 */
	private function page(): int {
		return (int) self::factory()->post->create(
			array(
				'post_type'   => 'handbook',
				'post_status' => 'publish',
			)
		);
	}

	/**
	 * The handbooks a page is actually in, as term ids.
	 *
	 * @param int $post_id Post id.
	 * @return int[]
	 */
	private function assigned( int $post_id ): array {
		$ids = wp_get_object_terms( $post_id, Handbooks::TAXONOMY, array( 'fields' => 'ids' ) );
		$ids = is_wp_error( $ids ) ? array() : array_map( 'intval', $ids );
		sort( $ids );
		return $ids;
	}

	/**
	 * Assigning two handbooks at once leaves one, deterministically.
	 */
	public function test_two_handbooks_at_once_leave_one(): void {
		$first  = $this->handbook( 'Zebra' );
		$second = $this->handbook( 'Alpha' );
		$page   = $this->page();

		wp_set_object_terms( $page, array( $first, $second ), Handbooks::TAXONOMY );

		$this->assertSame( array( min( $first, $second ) ), $this->assigned( $page ), 'Exactly one handbook, the lower term id.' );
		$this->assertSame( min( $first, $second ), Handbooks::for_post( $page ) );
	}

	/**
	 * Ticking a second handbook moves the page, it does not add to it. The
	 * deliberate act wins over what was there before.
	 */
	public function test_the_newly_added_handbook_wins(): void {
		$old  = $this->handbook( 'Alpha' );
		$new  = $this->handbook( 'Zebra' );
		$page = $this->page();

		wp_set_object_terms( $page, array( $old ), Handbooks::TAXONOMY );
		$this->assertSame( array( $old ), $this->assigned( $page ) );

		// What the editor sends when someone ticks a second box.
		wp_set_object_terms( $page, array( $old, $new ), Handbooks::TAXONOMY );

		$this->assertSame( array( $new ), $this->assigned( $page ), 'The handbook just ticked is the one that counts.' );
		$this->assertSame( $new, Handbooks::for_post( $page ) );
	}

	/**
	 * Appending must not sneak a second handbook in either.
	 */
	public function test_appending_a_handbook_also_leaves_one(): void {
		$old  = $this->handbook( 'Alpha' );
		$new  = $this->handbook( 'Beta' );
		$page = $this->page();

		wp_set_object_terms( $page, array( $old ), Handbooks::TAXONOMY );
		wp_set_object_terms( $page, array( $new ), Handbooks::TAXONOMY, true );

		$this->assertCount( 1, $this->assigned( $page ), 'Appending leaves one handbook, not two.' );
	}

	/**
	 * A single assignment is left completely alone, and so is another post type.
	 */
	public function test_one_handbook_and_other_post_types_are_untouched(): void {
		$term = $this->handbook( 'Alpha' );
		$page = $this->page();
		wp_set_object_terms( $page, array( $term ), Handbooks::TAXONOMY );
		$this->assertSame( array( $term ), $this->assigned( $page ) );

		$other = $this->handbook( 'Beta' );
		$post  = (int) self::factory()->post->create( array( 'post_type' => 'post' ) );
		register_taxonomy_for_object_type( Handbooks::TAXONOMY, 'post' );
		wp_set_object_terms( $post, array( $term, $other ), Handbooks::TAXONOMY );

		$this->assertCount(
			2,
			wp_get_object_terms( $post, Handbooks::TAXONOMY, array( 'fields' => 'ids' ) ),
			'The rule is about handbook pages; another post type is none of its business.'
		);
		unregister_taxonomy_for_object_type( Handbooks::TAXONOMY, 'post' );
	}

	/**
	 * An assignment made before the rule existed still resolves to one handbook,
	 * and to the same one everywhere, whatever the handbooks are called.
	 */
	public function test_an_older_double_assignment_resolves_the_same_way_everywhere(): void {
		$first  = $this->handbook( 'Zebra' );
		$second = $this->handbook( 'Alpha' );
		$page   = $this->page();

		// Write both rows the way a version before the enforcement left them,
		// past the hook rather than through it.
		global $wpdb;
		wp_set_object_terms( $page, array( $first ), Handbooks::TAXONOMY );
		$second_term = get_term( $second, Handbooks::TAXONOMY );
		$wpdb->insert(
			$wpdb->term_relationships,
			array(
				'object_id'        => $page,
				'term_taxonomy_id' => (int) $second_term->term_taxonomy_id,
				'term_order'       => 0,
			)
		);
		clean_object_term_cache( $page, 'handbook' );

		$this->assertCount( 2, $this->assigned( $page ), 'Precondition: the page really is in two handbooks.' );

		// The rule does not depend on the names, so renaming must not move the page.
		$resolved = Handbooks::for_post( $page );
		wp_update_term( $first, Handbooks::TAXONOMY, array( 'name' => 'Aardvark' ) );
		clean_object_term_cache( $page, 'handbook' );

		$this->assertSame( min( $first, $second ), $resolved );
		$this->assertSame( $resolved, Handbooks::for_post( $page ), 'Renaming a handbook must not move the page.' );
	}

	/**
	 * The navigation of a page is the tree of the handbook the page is in.
	 */
	public function test_the_navigation_shows_the_tree_the_page_is_in(): void {
		$alpha = $this->handbook( 'Alpha' );
		$zebra = $this->handbook( 'Zebra' );

		$in_zebra = $this->page();
		wp_update_post(
			array(
				'ID'         => $in_zebra,
				'post_title' => 'The page in Zebra',
			)
		);
		wp_set_object_terms( $in_zebra, array( $alpha, $zebra ), Handbooks::TAXONOMY );

		$other = $this->page();
		wp_update_post(
			array(
				'ID'         => $other,
				'post_title' => 'A page in Alpha',
			)
		);
		wp_set_object_terms( $other, array( $alpha ), Handbooks::TAXONOMY );

		wp_set_current_user( 0 );
		$markup = Navigation::render_for_post( $in_zebra );

		$this->assertStringContainsString( 'The page in Zebra', $markup, 'The page has to appear in its own navigation.' );
	}
}
