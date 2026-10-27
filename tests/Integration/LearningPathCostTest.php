<?php
/**
 * What the learning paths of an entry page cost in database queries.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use LivingHandbook\Training\Lessons;
use LivingHandbook\Training\PathView;
use LivingHandbook\Training\Training;
use WP_UnitTestCase;

/**
 * The list of paths on an entry page must not cost queries per path.
 *
 * Every path is listed with the number of lessons it has for the reader, and
 * that number comes from the same per-person rule as the path page. Up to
 * 0.75.1 it was asked path by path: the lesson list (a meta value) and a query
 * for the lessons, two queries and more for every path.
 */
final class LearningPathCostTest extends WP_UnitTestCase {

	/**
	 * Switch the module on and register the type as a real request would.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		update_option( Training::OPTION_ENABLED, 1 );
		( new Training() )->register_post_type();
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
	 * @param int $handbook Handbook term id.
	 * @return int
	 */
	private function page( int $handbook ): int {
		$id = (int) self::factory()->post->create(
			array(
				'post_type'   => Handbook::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		wp_set_object_terms( $id, array( $handbook ), Handbooks::TAXONOMY );
		return $id;
	}

	/**
	 * A published learning path in a handbook.
	 *
	 * @param int        $handbook Handbook term id.
	 * @param array<int> $lessons  Lesson ids.
	 * @param string     $title    Title.
	 * @return int
	 */
	private function path( int $handbook, array $lessons, string $title ): int {
		$id = (int) self::factory()->post->create(
			array(
				'post_type'   => Training::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $title,
			)
		);
		wp_set_object_terms( $id, array( $handbook ), Handbooks::TAXONOMY );
		Lessons::store( $id, $lessons );
		return $id;
	}

	/**
	 * Render the paths block on a handbook's entry page and count its queries.
	 *
	 * @param int    $handbook Handbook term id.
	 * @param string $markup   Receives the rendered markup.
	 * @return int
	 */
	private function cost_of_paths( int $handbook, string &$markup ): int {
		global $wpdb;

		wp_set_current_user( 0 );
		$this->go_to( (string) get_term_link( $handbook, Handbooks::TAXONOMY ) );
		wp_cache_flush();

		$before = $wpdb->num_queries;
		$markup = PathView::render_paths();
		return $wpdb->num_queries - $before;
	}

	/**
	 * Ten paths cost what one does.
	 */
	public function test_ten_paths_cost_what_one_does(): void {
		$one = $this->handbook();
		$ten = $this->handbook();

		$pages = array();
		for ( $i = 0; $i < 12; $i++ ) {
			$pages[] = $this->page( $one );
		}
		$this->path( $one, array_slice( $pages, 0, 5 ), 'Path 0' );

		$pages = array();
		for ( $i = 0; $i < 12; $i++ ) {
			$pages[] = $this->page( $ten );
		}
		for ( $p = 0; $p < 10; $p++ ) {
			$this->path( $ten, array_slice( $pages, $p, 3 ), 'Path ' . $p );
		}

		$markup   = '';
		$cost_one = $this->cost_of_paths( $one, $markup );
		$this->assertSame( 1, substr_count( $markup, 'living-handbook-paths__item' ) );

		$cost_ten = $this->cost_of_paths( $ten, $markup );
		$this->assertSame( 10, substr_count( $markup, 'living-handbook-paths__item' ) );
		$this->assertSame( 10, substr_count( $markup, '3 lessons' ), 'Every path has its own count.' );

		// Before, each further path cost two queries and more: twenty-odd more
		// for ten paths than for one.
		$this->assertLessThanOrEqual( $cost_one + 1, $cost_ten, sprintf( 'One path took %d queries, ten took %d.', $cost_one, $cost_ten ) );
	}

	/**
	 * Shared out path by path, the rule stays per lesson: a lesson the reader
	 * may not read, a draft and a page of another handbook are not counted, in
	 * every path that names them, and only there.
	 */
	public function test_every_path_counts_only_its_own_readable_lessons(): void {
		$handbook = $this->handbook();
		$other    = $this->handbook();

		$open    = $this->page( $handbook );
		$second  = $this->page( $handbook );
		$refused = $this->page( $handbook );
		$foreign = $this->page( $other );
		$draft   = $this->page( $handbook );
		wp_update_post(
			array(
				'ID'          => $draft,
				'post_status' => 'draft',
			)
		);

		$a = $this->path( $handbook, array( $refused, $open, $foreign ), 'A' );
		$b = $this->path( $handbook, array( $second, $draft, $open ), 'B' );
		$c = $this->path( $handbook, array(), 'C' );

		$filter = static fn( $allowed, $post_id ) => $post_id === $refused ? false : $allowed;
		add_filter( 'living_handbook_can_view_post', $filter, 10, 2 );
		wp_set_current_user( 0 );
		wp_cache_flush();
		$lessons = Lessons::visible_ids_for( array( $a, $b, $c ), 0 );
		remove_filter( 'living_handbook_can_view_post', $filter, 10 );

		$this->assertSame( array( $open ), $lessons[ $a ] );
		$this->assertSame( array( $second, $open ), $lessons[ $b ], 'The stored order is kept.' );
		$this->assertSame( array(), $lessons[ $c ] );

		// The single-path answer is the same answer.
		add_filter( 'living_handbook_can_view_post', $filter, 10, 2 );
		$this->assertSame( array( $open ), wp_list_pluck( Lessons::visible( $a, 0 ), 'ID' ) );
		remove_filter( 'living_handbook_can_view_post', $filter, 10 );
	}
}
