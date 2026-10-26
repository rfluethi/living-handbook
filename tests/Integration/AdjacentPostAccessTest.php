<?php
/**
 * The previous/next post links stay inside what the reader may view.
 *
 * get_adjacent_post() builds its own SQL and never runs through WP_Query, so
 * the access layer on pre_get_posts and the_posts does not see it. In a classic
 * theme, the_post_navigation() on a page of a public handbook showed a guest the
 * title and the address of the page next to it by date, also when that page was
 * in a members-only or restricted handbook (0.75.1 and earlier).
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use WP_Post;
use WP_UnitTestCase;

/**
 * Previous and next post of a handbook page.
 */
final class AdjacentPostAccessTest extends WP_UnitTestCase {

	/**
	 * A handbook with a visibility.
	 *
	 * @param string $visibility One of the VISIBILITY_* constants.
	 * @return int Term id.
	 */
	private function handbook( string $visibility ): int {
		$term = (int) self::factory()->term->create( array( 'taxonomy' => Handbooks::TAXONOMY ) );
		update_term_meta( $term, Handbooks::META_VISIBILITY, $visibility );

		return $term;
	}

	/**
	 * A published page on a given date, in a handbook (or none).
	 *
	 * @param int    $term_id Handbook, 0 for none.
	 * @param string $date    Post date.
	 * @param string $title   Title.
	 * @return int Post id.
	 */
	private function page( int $term_id, string $date, string $title ): int {
		$id = (int) self::factory()->post->create(
			array(
				'post_type'   => Handbook::POST_TYPE,
				'post_status' => 'publish',
				'post_date'   => $date,
				'post_title'  => $title,
			)
		);
		if ( $term_id > 0 ) {
			wp_set_object_terms( $id, array( $term_id ), Handbooks::TAXONOMY );
		}

		return $id;
	}

	/**
	 * The neighbour of a page, as core's template tags look it up.
	 *
	 * @param int  $post_id  The page being shown.
	 * @param bool $previous Previous or next.
	 * @return string Title of the neighbour, '' when there is none.
	 */
	private function neighbour( int $post_id, bool $previous ): string {
		$GLOBALS['post'] = get_post( $post_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- get_adjacent_post() reads the global post.
		$found           = get_adjacent_post( false, '', $previous );

		return $found instanceof WP_Post ? $found->post_title : '';
	}

	/**
	 * A guest on a public page does not get a members-only page as its
	 * neighbour, in either direction, but does get the next public one.
	 *
	 * @return void
	 */
	public function test_a_guest_does_not_see_a_hidden_neighbour(): void {
		$public  = $this->handbook( Handbooks::VISIBILITY_PUBLIC );
		$members = $this->handbook( Handbooks::VISIBILITY_MEMBERS );

		$this->page( $public, '2026-01-01 10:00:00', 'Public first' );
		$this->page( $members, '2026-01-02 10:00:00', 'Salary bands' );
		$here = $this->page( $public, '2026-01-03 10:00:00', 'Public here' );
		$this->page( $members, '2026-01-04 10:00:00', 'Board minutes' );
		$this->page( 0, '2026-01-05 10:00:00', 'Orphan' );

		wp_set_current_user( 0 );

		$this->assertSame( 'Public first', $this->neighbour( $here, true ) );
		$this->assertSame( '', $this->neighbour( $here, false ) );
	}

	/**
	 * A member sees the members-only neighbour; nothing changes for readers.
	 *
	 * @return void
	 */
	public function test_a_member_still_sees_it(): void {
		$public  = $this->handbook( Handbooks::VISIBILITY_PUBLIC );
		$members = $this->handbook( Handbooks::VISIBILITY_MEMBERS );

		$this->page( $members, '2026-01-02 10:00:00', 'Salary bands' );
		$here = $this->page( $public, '2026-01-03 10:00:00', 'Public here' );

		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertSame( 'Salary bands', $this->neighbour( $here, true ) );
	}

	/**
	 * A restricted handbook stays out for a member who is not on its list.
	 *
	 * @return void
	 */
	public function test_a_restricted_neighbour_stays_hidden_from_other_members(): void {
		$public     = $this->handbook( Handbooks::VISIBILITY_PUBLIC );
		$restricted = $this->handbook( Handbooks::VISIBILITY_RESTRICTED );

		$this->page( $restricted, '2026-01-02 10:00:00', 'Salary bands' );
		$here = $this->page( $public, '2026-01-03 10:00:00', 'Public here' );

		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertSame( '', $this->neighbour( $here, true ) );
	}

	/**
	 * Ordinary posts are not touched.
	 *
	 * @return void
	 */
	public function test_ordinary_posts_are_untouched(): void {
		self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_date'   => '2026-01-01 10:00:00',
				'post_title'  => 'Older post',
			)
		);
		$here = (int) self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_date'   => '2026-01-02 10:00:00',
			)
		);

		wp_set_current_user( 0 );

		$this->assertSame( 'Older post', $this->neighbour( $here, true ) );
	}
}
