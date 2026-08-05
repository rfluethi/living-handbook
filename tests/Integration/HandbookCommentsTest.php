<?php
/**
 * Comments switched per handbook instead of per page.
 *
 * The switch on a single page is the WordPress one and stays. What it cannot do
 * is answer the question a handbook owner actually has, "comments on, for this
 * handbook", without opening every page in it. So a handbook may override its
 * pages, and the tests here pin what that override does and, just as much, what
 * it leaves alone.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use WP_UnitTestCase;

/**
 * Handbooks::filter_comments_open.
 */
final class HandbookCommentsTest extends WP_UnitTestCase {

	/**
	 * A handbook with a comment mode set.
	 *
	 * @param string $mode One of the COMMENTS_* constants, or '' to set nothing.
	 * @return int Term id.
	 */
	private function handbook( string $mode = '' ): int {
		$term = self::factory()->term->create_and_get( array( 'taxonomy' => Handbooks::TAXONOMY ) );
		if ( '' !== $mode ) {
			update_term_meta( (int) $term->term_id, Handbooks::META_COMMENTS, $mode );
		}

		return (int) $term->term_id;
	}

	/**
	 * A handbook page with a comment status of its own.
	 *
	 * @param int    $term_id Handbook.
	 * @param string $status  'open' or 'closed'.
	 * @return int Post id.
	 */
	private function page( int $term_id, string $status ): int {
		$id = (int) self::factory()->post->create(
			array(
				'post_type'      => Handbook::POST_TYPE,
				'post_status'    => 'publish',
				'post_title'     => 'A page',
				'comment_status' => $status,
			)
		);
		wp_set_object_terms( $id, array( $term_id ), Handbooks::TAXONOMY );

		return $id;
	}

	/**
	 * The default changes nothing. This is the test that protects every existing
	 * site: the setting arrives switched off, and a page keeps deciding for
	 * itself until somebody says otherwise.
	 *
	 * @return void
	 */
	public function test_by_default_the_page_still_decides(): void {
		$handbook = $this->handbook();

		$this->assertTrue( comments_open( $this->page( $handbook, 'open' ) ) );
		$this->assertFalse( comments_open( $this->page( $handbook, 'closed' ) ) );
	}

	/**
	 * A handbook set to closed closes its pages, including a page that says open.
	 *
	 * @return void
	 */
	public function test_a_closed_handbook_closes_a_page_that_says_open(): void {
		$handbook = $this->handbook( Handbooks::COMMENTS_CLOSED );

		$this->assertFalse( comments_open( $this->page( $handbook, 'open' ) ) );
		$this->assertFalse( comments_open( $this->page( $handbook, 'closed' ) ) );
	}

	/**
	 * And a handbook set to open opens a page that says closed. This is the
	 * direction that made the feature necessary: pages arrive from the import
	 * with the site default, which is closed.
	 *
	 * @return void
	 */
	public function test_an_open_handbook_opens_a_page_that_says_closed(): void {
		$handbook = $this->handbook( Handbooks::COMMENTS_OPEN );

		$this->assertTrue( comments_open( $this->page( $handbook, 'closed' ) ) );
		$this->assertTrue( comments_open( $this->page( $handbook, 'open' ) ) );
	}

	/**
	 * Two handbooks side by side do not bleed into each other.
	 *
	 * @return void
	 */
	public function test_the_setting_reaches_only_its_own_handbook(): void {
		$open   = $this->handbook( Handbooks::COMMENTS_OPEN );
		$closed = $this->handbook( Handbooks::COMMENTS_CLOSED );

		$this->assertTrue( comments_open( $this->page( $open, 'closed' ) ) );
		$this->assertFalse( comments_open( $this->page( $closed, 'open' ) ) );
	}

	/**
	 * Everything that is not a handbook page is left alone. The filter runs on
	 * every post of every type on the site, so this is the test that keeps the
	 * plugin out of the rest of a site.
	 *
	 * @return void
	 */
	public function test_an_ordinary_post_is_untouched(): void {
		$this->handbook( Handbooks::COMMENTS_CLOSED );

		$post = (int) self::factory()->post->create(
			array(
				'post_type'      => 'post',
				'comment_status' => 'open',
			)
		);

		$this->assertTrue( comments_open( $post ) );
	}

	/**
	 * A page in no handbook keeps its own setting. It cannot inherit from a
	 * handbook it does not have, and it must not fall to a hard default either.
	 *
	 * @return void
	 */
	public function test_a_page_without_a_handbook_keeps_its_own_setting(): void {
		$id = (int) self::factory()->post->create(
			array(
				'post_type'      => Handbook::POST_TYPE,
				'post_status'    => 'publish',
				'comment_status' => 'open',
			)
		);

		$this->assertTrue( comments_open( $id ) );
	}

	/**
	 * A value nobody recognises reads as "each page decides", never as open. A
	 * stray meta value, from a botched import or a hand-edited database, must not
	 * be able to open comments on a whole handbook.
	 *
	 * @return void
	 */
	public function test_an_unknown_value_reads_as_inherit(): void {
		$handbook = $this->handbook( 'yes-please' );

		$this->assertSame( Handbooks::COMMENTS_INHERIT, Handbooks::comments_mode( $handbook ) );
		$this->assertFalse( comments_open( $this->page( $handbook, 'closed' ) ) );
		$this->assertTrue( comments_open( $this->page( $handbook, 'open' ) ) );
	}

	/**
	 * comments_mode() answers for a handbook that does not exist without a
	 * warning and without opening anything.
	 *
	 * @return void
	 */
	public function test_comments_mode_survives_a_handbook_that_is_not_there(): void {
		$this->assertSame( Handbooks::COMMENTS_INHERIT, Handbooks::comments_mode( 0 ) );
		$this->assertSame( Handbooks::COMMENTS_INHERIT, Handbooks::comments_mode( 999999 ) );
	}
}
