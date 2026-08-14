<?php
/**
 * Learning paths, first stage: the lesson list and what it looks like.
 *
 * The whole feature rests on one claim: a learning path adds an order on top of
 * pages that already exist, and changes nothing about the pages themselves.
 * These tests hold that claim to its consequences. A lesson that was deleted,
 * unpublished, moved to another handbook or hidden from this reader has to drop
 * out of the list, out of the counter and out of the next-lesson link, without
 * anybody having to maintain the path.
 *
 * The access half of the same feature is in TrainingAccessTest.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Frontend\Cards;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use LivingHandbook\Training\Lessons;
use LivingHandbook\Training\PathView;
use LivingHandbook\Training\Training;
use WP_UnitTestCase;

/**
 * Training, Lessons and PathView.
 */
final class TrainingTest extends WP_UnitTestCase {

	/**
	 * The handbook every fixture belongs to.
	 *
	 * @var int
	 */
	private int $handbook = 0;

	/**
	 * Switch the module on for the test and register the type as a real request
	 * would, then start from a public handbook.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		update_option( Training::OPTION_ENABLED, 1 );
		( new Training() )->register_post_type();

		$this->handbook = (int) self::factory()->term->create( array( 'taxonomy' => Handbooks::TAXONOMY ) );
		update_term_meta( $this->handbook, Handbooks::META_VISIBILITY, Handbooks::VISIBILITY_PUBLIC );
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
	 * A published handbook page in a handbook.
	 *
	 * @param string $title    Page title.
	 * @param int    $handbook Handbook term id, defaults to the fixture handbook.
	 * @param string $status   Post status.
	 * @return int
	 */
	private function page( string $title, int $handbook = 0, string $status = 'publish' ): int {
		$id = (int) self::factory()->post->create(
			array(
				'post_type'   => Handbook::POST_TYPE,
				'post_status' => $status,
				'post_title'  => $title,
			)
		);
		wp_set_object_terms( $id, array( $handbook > 0 ? $handbook : $this->handbook ), Handbooks::TAXONOMY );

		return $id;
	}

	/**
	 * A published learning path with the given lessons.
	 *
	 * @param array<int, int> $lessons  Lesson ids in order.
	 * @param int             $handbook Handbook term id, defaults to the fixture handbook.
	 * @return int
	 */
	private function path( array $lessons, int $handbook = 0 ): int {
		$id = (int) self::factory()->post->create(
			array(
				'post_type'   => Training::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Onboarding',
			)
		);
		wp_set_object_terms( $id, array( $handbook > 0 ? $handbook : $this->handbook ), Handbooks::TAXONOMY );
		Lessons::store( $id, $lessons );

		return $id;
	}

	/**
	 * The switch decides what is shown, never what exists. A path written while
	 * the module was on is still there after it is switched off, and comes back
	 * unchanged: that is the promise the settings page makes.
	 *
	 * @return void
	 */
	public function test_switching_the_module_off_hides_the_type_and_keeps_the_data(): void {
		$lesson = $this->page( 'First' );
		$path   = $this->path( array( $lesson ) );

		update_option( Training::OPTION_ENABLED, 0 );
		( new Training() )->register_post_type();

		$type = get_post_type_object( Training::POST_TYPE );
		$this->assertNotNull( $type, 'The type is unregistered while it is switched off.' );
		$this->assertFalse( $type->show_ui );
		$this->assertFalse( $type->publicly_queryable );
		$this->assertSame( array( $lesson ), Lessons::stored( $path ) );

		update_option( Training::OPTION_ENABLED, 1 );
		( new Training() )->register_post_type();

		$type = get_post_type_object( Training::POST_TYPE );
		$this->assertNotNull( $type );
		$this->assertTrue( $type->show_ui );
		$this->assertSame( array( $lesson ), Lessons::stored( $path ) );
	}

	/**
	 * The stored list is the order somebody put the lessons in, and nothing
	 * else: no duplicates, no zeroes, no strings.
	 *
	 * @return void
	 */
	public function test_the_lesson_list_keeps_its_order_and_drops_nonsense(): void {
		$one   = $this->page( 'One' );
		$two   = $this->page( 'Two' );
		$three = $this->page( 'Three' );

		$path = $this->path( array( $three, $one, $two, $one, 0, 'x' ) );

		$this->assertSame( array( $three, $one, $two ), Lessons::stored( $path ) );
	}

	/**
	 * Four ways a lesson leaves a path without anybody editing the path: it is
	 * deleted, it is unpublished, it is moved to another handbook, or it never
	 * belonged to this one. All four have to shorten the list and the counter
	 * rather than produce a dead entry.
	 *
	 * @return void
	 */
	public function test_a_lesson_that_no_longer_qualifies_drops_out(): void {
		$kept      = $this->page( 'Kept' );
		$deleted   = $this->page( 'Deleted' );
		$draft     = $this->page( 'Draft', 0, 'draft' );
		$other     = (int) self::factory()->term->create( array( 'taxonomy' => Handbooks::TAXONOMY ) );
		$elsewhere = $this->page( 'Elsewhere', $other );

		$path = $this->path( array( $kept, $deleted, $draft, $elsewhere ) );
		wp_delete_post( $deleted, true );

		$visible = Lessons::visible( $path, 0 );

		$this->assertSame( array( $kept ), wp_list_pluck( $visible, 'ID' ) );
		$this->assertSame( 1, Lessons::position( $path, $kept, 0 ) );
		$this->assertSame( 0, Lessons::position( $path, $elsewhere, 0 ) );
	}

	/**
	 * A path with no lessons left says so instead of rendering an empty list.
	 *
	 * @return void
	 */
	public function test_an_empty_path_says_so(): void {
		$path = $this->path( array() );
		$this->go_to( (string) get_permalink( $path ) );

		$markup = PathView::render_lessons();

		$this->assertStringContainsString( 'living-handbook-path__empty', $markup );
		$this->assertStringNotContainsString( 'living-handbook-path__list', $markup );
	}

	/**
	 * The lesson list numbers the lessons in the stored order, and every link
	 * carries the path along, because that is the only way the next page knows
	 * which path it is being read in.
	 *
	 * @return void
	 */
	public function test_the_lesson_list_renders_in_order_and_carries_the_path(): void {
		$one = $this->page( 'Alpha' );
		$two = $this->page( 'Beta' );

		$path = $this->path( array( $two, $one ) );
		$this->go_to( (string) get_permalink( $path ) );

		$markup = PathView::render_lessons();

		$this->assertStringContainsString( 'data-total="2"', $markup );
		$this->assertLessThan(
			strpos( $markup, 'Alpha' ),
			strpos( $markup, 'Beta' ),
			'The list does not follow the stored order.'
		);
		$this->assertStringContainsString( PathView::QUERY_ARG . '=' . $path, $markup );
	}

	/**
	 * The path bar is the only thing on a lesson that knows about the path, and
	 * it appears exactly when the URL says so. Without the argument a handbook
	 * page is a handbook page.
	 *
	 * @return void
	 */
	public function test_the_path_bar_appears_only_when_the_url_names_the_path(): void {
		$one  = $this->page( 'First' );
		$two  = $this->page( 'Second' );
		$path = $this->path( array( $one, $two ) );

		$this->go_to( (string) get_permalink( $one ) );
		$this->assertSame( '', PathView::render_path_nav() );

		$this->go_to( add_query_arg( PathView::QUERY_ARG, $path, (string) get_permalink( $one ) ) );
		$markup = PathView::render_path_nav();

		$this->assertStringContainsString( 'living-handbook-pathbar', $markup );
		$this->assertStringContainsString( 'Lesson 1 of 2', $markup );
		$this->assertStringContainsString( 'Second', $markup, 'The next lesson is not offered.' );
	}

	/**
	 * A page that is not in the path renders nothing, even when the URL claims
	 * it is. The argument can be stale, shared or guessed, and none of that is
	 * worth an error message on an otherwise fine page.
	 *
	 * @return void
	 */
	public function test_a_page_outside_the_path_renders_no_bar(): void {
		$inside  = $this->page( 'Inside' );
		$outside = $this->page( 'Outside' );
		$path    = $this->path( array( $inside ) );

		$this->go_to( add_query_arg( PathView::QUERY_ARG, $path, (string) get_permalink( $outside ) ) );

		$this->assertSame( '', PathView::render_path_nav() );
	}

	/**
	 * The last lesson has no next, the first has no previous.
	 *
	 * @return void
	 */
	public function test_the_ends_of_a_path_have_no_step_beyond_them(): void {
		$one  = $this->page( 'First' );
		$two  = $this->page( 'Second' );
		$path = $this->path( array( $one, $two ) );

		$this->go_to( add_query_arg( PathView::QUERY_ARG, $path, (string) get_permalink( $one ) ) );
		$first = PathView::render_path_nav();

		$this->go_to( add_query_arg( PathView::QUERY_ARG, $path, (string) get_permalink( $two ) ) );
		$last = PathView::render_path_nav();

		$this->assertStringNotContainsString( 'pathbar__step--prev', $first );
		$this->assertStringContainsString( 'pathbar__step--next', $first );
		$this->assertStringContainsString( 'pathbar__step--prev', $last );
		$this->assertStringNotContainsString( 'pathbar__step--next', $last );
	}

	/**
	 * The handbook card counts pages. Learning paths live in the same term, so
	 * without a count of its own the card would report twelve pages for ten
	 * pages and two paths, and nothing would ever say why.
	 *
	 * @return void
	 */
	public function test_a_learning_path_does_not_count_as_a_page(): void {
		$this->page( 'One' );
		$this->page( 'Two' );
		$this->path( array() );

		$term = get_term( $this->handbook, Handbooks::TAXONOMY );
		$this->assertInstanceOf( \WP_Term::class, $term );

		$this->assertSame( 2, (int) $term->count );
		$this->assertStringContainsString( '2 pages', Cards::handbook_card( $term ) );
	}

	/**
	 * A path has no archive and stands in no navigation, so the entry page of
	 * its handbook is the one place it can be found. Without that list it would
	 * exist only for whoever was sent the link.
	 *
	 * @return void
	 */
	public function test_the_entry_page_lists_the_paths_of_this_handbook(): void {
		$lesson = $this->page( 'Lesson' );
		$this->path( array( $lesson ) );

		$elsewhere = (int) self::factory()->term->create( array( 'taxonomy' => Handbooks::TAXONOMY ) );
		update_term_meta( $elsewhere, Handbooks::META_VISIBILITY, Handbooks::VISIBILITY_PUBLIC );
		$other = (int) self::factory()->post->create(
			array(
				'post_type'   => Training::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Somewhere else',
			)
		);
		wp_set_object_terms( $other, array( $elsewhere ), Handbooks::TAXONOMY );

		$this->go_to( (string) get_term_link( $this->handbook, Handbooks::TAXONOMY ) );
		$markup = PathView::render_paths();

		$this->assertStringContainsString( 'Onboarding', $markup );
		$this->assertStringContainsString( '1 lesson', $markup );
		$this->assertStringNotContainsString( 'Somewhere else', $markup, 'The entry page lists a path of another handbook.' );
	}

	/**
	 * With the module off, none of the three places render anything. A site that
	 * switched learning paths off must not find a leftover of them on a page.
	 *
	 * @return void
	 */
	public function test_nothing_renders_while_the_module_is_off(): void {
		$lesson = $this->page( 'Lesson' );
		$path   = $this->path( array( $lesson ) );

		update_option( Training::OPTION_ENABLED, 0 );
		( new Training() )->register_post_type();

		$this->go_to( add_query_arg( PathView::QUERY_ARG, $path, (string) get_permalink( $lesson ) ) );
		$this->assertSame( '', PathView::render_path_nav() );

		$this->go_to( (string) get_term_link( $this->handbook, Handbooks::TAXONOMY ) );
		$this->assertSame( '', PathView::render_paths() );
	}

	/**
	 * A path belongs to one handbook, the same rule its lessons follow. Ticking
	 * a second one in the editor must not survive the save.
	 *
	 * @return void
	 */
	public function test_a_path_belongs_to_exactly_one_handbook(): void {
		$second = (int) self::factory()->term->create( array( 'taxonomy' => Handbooks::TAXONOMY ) );
		$path   = $this->path( array() );

		wp_set_object_terms( $path, array( $this->handbook, $second ), Handbooks::TAXONOMY );

		$terms = wp_get_object_terms( $path, Handbooks::TAXONOMY, array( 'fields' => 'ids' ) );
		$this->assertCount( 1, $terms );
	}
}
