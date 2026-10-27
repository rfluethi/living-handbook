<?php
/**
 * What the page tree costs in memory and queries.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Access\AccessController;
use LivingHandbook\Frontend\Navigation;
use LivingHandbook\Frontend\PageTree;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use WP_Post;
use WP_UnitTestCase;

/**
 * The navigation must not load the text of every page it lists.
 *
 * The tree is built on every page view from every page of the handbook, and it
 * prints titles and addresses. Up to 0.75.1 it loaded every page whole: on 2000
 * pages of about 8 KB each that was a memory peak of about 54 MB per view, which
 * grows with the handbook and exhausts a 128 MB memory limit somewhere between
 * four and five thousand pages.
 */
final class PageTreeCostTest extends WP_UnitTestCase {

	/**
	 * Create a public handbook.
	 *
	 * @return int Term id.
	 */
	private function handbook(): int {
		$term_id = (int) self::factory()->term->create(
			array(
				'taxonomy' => Handbooks::TAXONOMY,
				'name'     => 'Tree cost ' . wp_generate_password( 6, false ),
			)
		);
		update_term_meta( $term_id, Handbooks::META_VISIBILITY, 'public' );
		return $term_id;
	}

	/**
	 * Create a published page with a given amount of text.
	 *
	 * @param int $handbook Handbook term id.
	 * @param int $parent   Parent page id.
	 * @param int $bytes    Length of the content.
	 * @return int Post id.
	 */
	private function page( int $handbook, int $parent = 0, int $bytes = 100 ): int {
		$id = (int) self::factory()->post->create(
			array(
				'post_type'    => Handbook::POST_TYPE,
				'post_status'  => 'publish',
				'post_parent'  => $parent,
				'post_content' => str_repeat( 'x', $bytes ),
			)
		);
		wp_set_object_terms( $id, array( $handbook ), Handbooks::TAXONOMY );
		return $id;
	}

	/**
	 * How many bytes of page text the post cache holds for a set of pages.
	 *
	 * @param int[] $ids Post ids.
	 * @return int
	 */
	private function cached_text( array $ids ): int {
		$bytes = 0;
		foreach ( wp_cache_get_multiple( $ids, 'posts' ) as $post ) {
			if ( is_object( $post ) && isset( $post->post_content ) ) {
				$bytes += strlen( (string) $post->post_content );
			}
		}
		return $bytes;
	}

	/**
	 * Building the tree reads the text of the parents, which get_permalink()
	 * needs whole, and of no other page.
	 *
	 * Thirty pages of 10 KB under one parent: before, all thirty-one were read
	 * and kept, 310 KB of text for a list of thirty-one links.
	 */
	public function test_the_tree_does_not_load_the_text_of_its_pages(): void {
		$handbook = $this->handbook();
		$parent   = $this->page( $handbook, 0, 10000 );
		$ids      = array( $parent );
		for ( $i = 0; $i < 30; $i++ ) {
			$ids[] = $this->page( $handbook, $parent, 10000 );
		}

		wp_set_current_user( 0 );
		wp_cache_flush();
		$map = PageTree::children_map( $handbook );

		$this->assertCount( 30, $map[ $parent ] ?? array(), 'Every page is in the tree.' );

		$text = 0;
		foreach ( $map as $children ) {
			foreach ( $children as $post ) {
				$text += strlen( $post->post_content );
			}
		}
		$this->assertSame( 0, $text, 'The pages in the tree carry their text.' );

		$this->assertLessThanOrEqual(
			10000,
			$this->cached_text( $ids ),
			'Building the tree put the text of more than the one parent into the cache.'
		);
	}

	/**
	 * The addresses of a deep tree cost nothing beyond the tree: the parents
	 * are read once, together, not one get_post() per parent as get_permalink()
	 * would do on its own.
	 */
	public function test_the_addresses_of_a_deep_tree_cost_no_query_per_parent(): void {
		$handbook = $this->handbook();
		$parent   = 0;
		for ( $level = 0; $level < 6; $level++ ) {
			$parent = $this->page( $handbook, $parent );
			$this->page( $handbook, $parent );
		}

		global $wpdb;
		wp_set_current_user( 0 );
		wp_cache_flush();

		$before = $wpdb->num_queries;
		PageTree::children_map( $handbook );
		$tree = $wpdb->num_queries - $before;

		wp_cache_flush();
		$before = $wpdb->num_queries;
		$html   = Navigation::render( $handbook );
		$render = $wpdb->num_queries - $before;

		$this->assertSame( 12, substr_count( $html, '<li ' ), 'Every page is in the navigation.' );
		// The render reads the handbook term and its link besides the tree; what
		// matters is that six levels of parents do not add six queries.
		$this->assertLessThanOrEqual( $tree + 2, $render, sprintf( 'The tree took %d queries, the navigation %d.', $tree, $render ) );
	}

	/**
	 * Partial posts decided outside a query get the coarse layer of the query
	 * as well: a page that no readable handbook holds stays out, even when the
	 * page filter would let it in, because on a query it never reaches the
	 * filter.
	 */
	public function test_readable_posts_keeps_the_coarse_layer_of_a_query(): void {
		$closed = (int) self::factory()->term->create( array( 'taxonomy' => Handbooks::TAXONOMY ) );
		update_term_meta( $closed, Handbooks::META_VISIBILITY, 'members' );
		$open = $this->handbook();

		$hidden  = $this->page( $closed );
		$visible = $this->page( $open );

		$partial = static function ( int $id ): WP_Post {
			return new WP_Post(
				(object) array(
					'ID'          => $id,
					'post_type'   => Handbook::POST_TYPE,
					'post_status' => 'publish',
					'filter'      => 'raw',
				)
			);
		};

		$grant = static fn(): bool => true;
		add_filter( 'living_handbook_can_view_post', $grant );
		wp_set_current_user( 0 );
		wp_cache_flush();
		$readable = AccessController::readable_posts( array( $partial( $hidden ), $partial( $visible ) ) );
		remove_filter( 'living_handbook_can_view_post', $grant );

		$this->assertSame( array( $visible ), wp_list_pluck( $readable, 'ID' ) );
		$this->assertFalse( wp_cache_get( $visible, 'posts' ), 'Deciding put the partial post into the post cache.' );
	}
}
