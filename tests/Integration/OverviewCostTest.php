<?php
/**
 * What the handbook overview costs in database queries.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Frontend\Entry;
use LivingHandbook\Frontend\Navigation;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use WP_UnitTestCase;

/**
 * The overview must not cost a handful of queries per handbook card.
 *
 * Each card can list the first pages of its handbook. Up to 0.75.1 every card
 * ran its own query for them on every view, with the term lookup, the meta and
 * the terms of every taxonomy of the listed pages: five queries per card, fifty
 * for an overview of ten handbooks. The previews are now cached like the area
 * tiles, and read for all cards at once.
 */
final class OverviewCostTest extends WP_UnitTestCase {

	/**
	 * A public handbook with a few top-level pages.
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

		for ( $i = 1; $i <= 4; $i++ ) {
			$this->page( $term_id, $name . ' page ' . $i );
		}
		return $term_id;
	}

	/**
	 * A published page in a handbook.
	 *
	 * @param int    $term_id Handbook term id.
	 * @param string $title   Title.
	 * @return int
	 */
	private function page( int $term_id, string $title ): int {
		$id = (int) self::factory()->post->create(
			array(
				'post_type'   => Handbook::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $title,
			)
		);
		wp_set_object_terms( $id, array( $term_id ), Handbooks::TAXONOMY );
		return $id;
	}

	/**
	 * Render the overview with a preview of three pages from an empty object
	 * cache, and count its queries.
	 *
	 * @param string $markup Receives the markup.
	 * @return int
	 */
	private function cost_of_overview( string &$markup ): int {
		global $wpdb;

		wp_cache_flush();
		$before = $wpdb->num_queries;
		$markup = Entry::render_chooser( 'cards', 3 );
		return $wpdb->num_queries - $before;
	}

	/**
	 * Once the previews are cached, ten handbooks cost what one does.
	 */
	public function test_ten_handbooks_cost_what_one_does(): void {
		wp_set_current_user( 0 );

		$this->handbook( 'Book 1' );
		$markup = '';
		$this->cost_of_overview( $markup );
		$one = $this->cost_of_overview( $markup );
		$this->assertStringContainsString( 'Book 1 page 1', $markup );

		for ( $i = 2; $i <= 10; $i++ ) {
			$this->handbook( 'Book ' . $i );
		}
		$this->cost_of_overview( $markup );
		$ten = $this->cost_of_overview( $markup );

		$this->assertSame( 10, substr_count( $markup, 'living-handbook-card__preview' ), 'Every card has its preview.' );
		$this->assertStringContainsString( 'Book 10 page 3', $markup );
		$this->assertSame( 10, substr_count( $markup, 'living-handbook-card__more' ), 'Four pages, three shown: every card offers more.' );

		// Before, each further card cost five queries on every view.
		$this->assertLessThanOrEqual( $one + 1, $ten, sprintf( 'One handbook took %d queries, ten took %d.', $one, $ten ) );
	}

	/**
	 * Building a preview that is not cached yet reads three times per card:
	 * the page ids, the pages and their handbook. Not the term behind the
	 * handbook id, not the meta of the pages, not their other terms.
	 */
	public function test_an_uncached_preview_reads_only_what_it_shows(): void {
		wp_set_current_user( 0 );
		$this->handbook( 'Book 1' );
		$this->handbook( 'Book 2' );

		$reads = 0;
		$count = static function ( $sql ) use ( &$reads ) {
			if ( 0 === stripos( ltrim( (string) $sql ), 'SELECT' ) ) {
				++$reads;
			}
			return $sql;
		};
		add_filter( 'query', $count );

		// A version nobody has cached anything under.
		update_option( Navigation::CACHE_VERSION_OPTION, 1000 );

		wp_cache_flush();
		$reads = 0;
		Entry::render_chooser( 'cards', 0 );
		$without = $reads;

		wp_cache_flush();
		$reads  = 0;
		$markup = Entry::render_chooser( 'cards', 3 );
		$with   = $reads;

		remove_filter( 'query', $count );

		$this->assertSame( 2, substr_count( $markup, 'living-handbook-card__preview' ) );
		// Three per card, and one read of the cache entries of both. Before, it
		// was five per card.
		$this->assertLessThanOrEqual( 2 * 3 + 1, $with - $without, sprintf( 'The overview read %d times without previews and %d times with two.', $without, $with ) );
	}

	/**
	 * The cache is per access profile. A page an editor may read and a guest
	 * may not is in the editor's preview, and the editor filling the cache first
	 * does not hand it to the guest.
	 */
	public function test_the_cached_preview_does_not_cross_access_profiles(): void {
		global $wpdb;

		$open   = $this->handbook( 'Open' );
		$closed = (int) self::factory()->term->create(
			array(
				'taxonomy' => Handbooks::TAXONOMY,
				'name'     => 'Closed',
			)
		);
		update_term_meta( $closed, Handbooks::META_VISIBILITY, Handbooks::VISIBILITY_MEMBERS );

		// A page left over in both handbooks, from before one handbook per page
		// was enforced: only who may read both may see it.
		$both        = $this->page( $open, 'Aaa in both' );
		$closed_term = get_term( $closed, Handbooks::TAXONOMY );
		$wpdb->insert(
			$wpdb->term_relationships,
			array(
				'object_id'        => $both,
				'term_taxonomy_id' => (int) $closed_term->term_taxonomy_id,
				'term_order'       => 0,
			)
		);
		clean_object_term_cache( $both, Handbook::POST_TYPE );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertStringContainsString( 'Aaa in both', Entry::render_chooser( 'cards', 3 ), 'The editor sees the page.' );

		wp_set_current_user( 0 );
		$this->assertStringNotContainsString( 'Aaa in both', Entry::render_chooser( 'cards', 3 ), 'The guest got the editor\'s preview.' );
	}

	/**
	 * A new page retires the cached preview, through the shared version counter.
	 */
	public function test_a_new_page_shows_in_the_preview(): void {
		wp_set_current_user( 0 );
		$term = $this->handbook( 'Growing' );
		$this->assertStringNotContainsString( 'Aaa new page', Entry::render_chooser( 'cards', 3 ) );

		$this->page( $term, 'Aaa new page' );
		$this->assertStringContainsString( 'Aaa new page', Entry::render_chooser( 'cards', 3 ) );
	}
}
