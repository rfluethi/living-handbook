<?php
/**
 * The page tree of a handbook, and the freshness status of a page.
 *
 * PageTree is what the navigation and the area tiles are built from, in one
 * query instead of one per branch. If it groups or orders wrongly, every
 * handbook's navigation is wrong; if it hands out pages of a handbook the
 * visitor may not read, the access rules are worth nothing on this path.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Frontend\FreshnessStatus;
use LivingHandbook\Frontend\PageTree;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\Meta\Metadata;
use LivingHandbook\PostType\Handbook;
use WP_UnitTestCase;

/**
 * Page tree and freshness on a real installation.
 */
final class PageTreeTest extends WP_UnitTestCase {

	/**
	 * Create a handbook.
	 *
	 * @param string $name       Handbook name.
	 * @param string $visibility public, members or restricted.
	 * @return int Term id.
	 */
	private function handbook( string $name, string $visibility = 'public' ): int {
		$term = wp_insert_term( $name, Handbooks::TAXONOMY );
		$this->assertIsArray( $term );
		update_term_meta( (int) $term['term_id'], Handbooks::META_VISIBILITY, $visibility );
		return (int) $term['term_id'];
	}

	/**
	 * Create a published handbook page.
	 *
	 * @param int    $handbook_id Handbook term id.
	 * @param string $title       Page title.
	 * @param int    $parent      Parent page id.
	 * @param int    $order       Menu order.
	 * @param string $status      Post status.
	 * @return int Post id.
	 */
	private function page( int $handbook_id, string $title, int $parent = 0, int $order = 0, string $status = 'publish' ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'   => Handbook::POST_TYPE,
				'post_title'  => $title,
				'post_parent' => $parent,
				'menu_order'  => $order,
				'post_status' => $status,
			)
		);
		wp_set_object_terms( $id, array( $handbook_id ), Handbooks::TAXONOMY );
		return $id;
	}

	/**
	 * The map is keyed by parent, with the top level under 0.
	 *
	 * @return void
	 */
	public function test_pages_are_grouped_by_their_parent(): void {
		$handbook = $this->handbook( 'Grouping' );
		$top      = $this->page( $handbook, 'Top' );
		$child    = $this->page( $handbook, 'Child', $top );
		$grand    = $this->page( $handbook, 'Grandchild', $child );

		$map = PageTree::children_map( $handbook );

		$this->assertSame( array( $top ), wp_list_pluck( $map[0], 'ID' ), 'A page without a parent is top level.' );
		$this->assertSame( array( $child ), wp_list_pluck( $map[ $top ], 'ID' ) );
		$this->assertSame( array( $grand ), wp_list_pluck( $map[ $child ], 'ID' ) );
	}

	/**
	 * Siblings come out by menu order first, and by title where the order is
	 * equal. That is the order the navigation shows, so it is the order an
	 * editor sets with the order field.
	 *
	 * @return void
	 */
	public function test_siblings_are_ordered_by_menu_order_then_title(): void {
		$handbook = $this->handbook( 'Ordering' );
		$third    = $this->page( $handbook, 'Anfang', 0, 30 );
		$first    = $this->page( $handbook, 'Zuerst', 0, 10 );
		$second_b = $this->page( $handbook, 'Beta', 0, 20 );
		$second_a = $this->page( $handbook, 'Alpha', 0, 20 );

		$map = PageTree::children_map( $handbook );

		$this->assertSame(
			array( $first, $second_a, $second_b, $third ),
			wp_list_pluck( $map[0], 'ID' ),
			'Menu order decides, the title breaks the tie.'
		);
	}

	/**
	 * A draft is not part of the navigation: it is not readable, so it must not
	 * appear as an entry that leads nowhere.
	 *
	 * @return void
	 */
	public function test_only_published_pages_are_in_the_tree(): void {
		$handbook = $this->handbook( 'Drafts' );
		$live     = $this->page( $handbook, 'Live' );
		$this->page( $handbook, 'Draft', 0, 0, 'draft' );

		$map = PageTree::children_map( $handbook );

		$this->assertSame( array( $live ), wp_list_pluck( $map[0], 'ID' ) );
	}

	/**
	 * Two handbooks side by side stay apart, which is the whole point of having
	 * more than one.
	 *
	 * @return void
	 */
	public function test_a_handbook_only_sees_its_own_pages(): void {
		$one = $this->handbook( 'One' );
		$two = $this->handbook( 'Two' );

		$page_one = $this->page( $one, 'Page of one' );
		$page_two = $this->page( $two, 'Page of two' );

		$this->assertSame( array( $page_one ), wp_list_pluck( PageTree::children_map( $one )[0], 'ID' ) );
		$this->assertSame( array( $page_two ), wp_list_pluck( PageTree::children_map( $two )[0], 'ID' ) );
	}

	/**
	 * Without a handbook there is no tree, and asking for one must not turn into
	 * a query for every handbook page on the site.
	 *
	 * @return void
	 */
	public function test_no_handbook_means_no_tree(): void {
		$handbook = $this->handbook( 'Something' );
		$this->page( $handbook, 'A page' );

		$this->assertSame( array(), PageTree::children_map( 0 ) );
		$this->assertSame( array(), PageTree::children_map( -1 ) );
	}

	/**
	 * The tree is read through the ordinary query, so the access rules apply to
	 * it: a guest gets nothing out of a members-only handbook, and an editor
	 * gets the pages.
	 *
	 * @return void
	 */
	public function test_the_tree_respects_who_is_asking(): void {
		$handbook = $this->handbook( 'Internal', 'members' );
		$page     = $this->page( $handbook, 'Internal page' );

		wp_set_current_user( 0 );
		$this->assertSame( array(), PageTree::children_map( $handbook ), 'A guest sees nothing of a members handbook.' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertSame( array( $page ), wp_list_pluck( PageTree::children_map( $handbook )[0], 'ID' ) );
	}

	/**
	 * The freshness status on a real page: it reads the two meta fields and
	 * answers with nothing when they are not both there.
	 *
	 * @return void
	 */
	public function test_the_freshness_of_a_page_comes_from_its_meta(): void {
		$handbook = $this->handbook( 'Freshness' );
		$page     = $this->page( $handbook, 'A page' );

		$this->assertSame( FreshnessStatus::NONE, FreshnessStatus::for_post( $page ), 'A page nobody set an interval on says nothing.' );

		update_post_meta( $page, Metadata::REVIEWED, gmdate( 'Y-m-d', time() - ( 10 * DAY_IN_SECONDS ) ) );
		update_post_meta( $page, Metadata::INTERVAL, 90 );
		$this->assertSame( FreshnessStatus::OK, FreshnessStatus::for_post( $page ) );

		update_post_meta( $page, Metadata::REVIEWED, gmdate( 'Y-m-d', time() - ( 100 * DAY_IN_SECONDS ) ) );
		$this->assertSame( FreshnessStatus::DUE, FreshnessStatus::for_post( $page ) );

		update_post_meta( $page, Metadata::REVIEWED, gmdate( 'Y-m-d', time() - ( 200 * DAY_IN_SECONDS ) ) );
		$this->assertSame( FreshnessStatus::OVERDUE, FreshnessStatus::for_post( $page ) );
	}

	/**
	 * Every status a page can carry has a label, and "nothing to say" says
	 * nothing rather than an empty-looking badge.
	 *
	 * @return void
	 */
	public function test_every_status_has_a_label_except_none(): void {
		$this->assertNotSame( '', FreshnessStatus::label( FreshnessStatus::OK ) );
		$this->assertNotSame( '', FreshnessStatus::label( FreshnessStatus::DUE ) );
		$this->assertNotSame( '', FreshnessStatus::label( FreshnessStatus::OVERDUE ) );
		$this->assertSame( '', FreshnessStatus::label( FreshnessStatus::NONE ) );
		$this->assertSame( '', FreshnessStatus::label( 'something else' ) );
	}
}
