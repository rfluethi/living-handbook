<?php
/**
 * The overview shows what is in a handbook, and how the handbooks relate.
 *
 * Two things were missing from the same screen. A card said a handbook's name
 * and its page count, which is what it is called and how big it is, not what is
 * in it. And the grouping taxonomy was hierarchical from the day it was
 * registered while nothing read that, so a structure someone built was
 * invisible: parent and child stood side by side as equals.
 *
 * The tests that matter here are the access ones. A preview reads pages, and a
 * hierarchy invites inheriting a parent's rule. Neither may happen: every
 * handbook decides for itself who may read it, and a preview must never name a
 * page its reader may not open.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Frontend\Cards;
use LivingHandbook\Frontend\Entry;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use WP_UnitTestCase;

/**
 * Entry::render_chooser, Entry::render_menu and Cards::handbook_card.
 */
final class OverviewStructureTest extends WP_UnitTestCase {

	/**
	 * A handbook, public unless asked otherwise.
	 *
	 * @param string $name       Handbook name.
	 * @param int    $parent     Parent handbook id.
	 * @param string $visibility Visibility constant.
	 * @return int Term id.
	 */
	private function handbook( string $name, int $parent = 0, string $visibility = Handbooks::VISIBILITY_PUBLIC ): int {
		$id = (int) self::factory()->term->create(
			array(
				'taxonomy' => Handbooks::TAXONOMY,
				'name'     => $name,
				'parent'   => $parent,
			)
		);
		update_term_meta( $id, Handbooks::META_VISIBILITY, $visibility );

		return $id;
	}

	/**
	 * A published page in a handbook.
	 *
	 * @param int    $term_id Handbook.
	 * @param string $title   Page title.
	 * @param int    $order   Menu order.
	 * @return int Post id.
	 */
	private function page( int $term_id, string $title, int $order = 0 ): int {
		$id = (int) self::factory()->post->create(
			array(
				'post_type'   => Handbook::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $title,
				'menu_order'  => $order,
			)
		);
		wp_set_object_terms( $id, array( $term_id ), Handbooks::TAXONOMY );

		return $id;
	}

	/**
	 * The card lists the first pages, in the handbook's own order, and offers the
	 * rest behind one link.
	 *
	 * @return void
	 */
	public function test_the_card_previews_the_first_pages(): void {
		$term = $this->handbook( 'Onboarding' );
		$this->page( $term, 'First page', 10 );
		$this->page( $term, 'Second page', 20 );
		$this->page( $term, 'Third page', 30 );

		$out = Cards::handbook_card( get_term( $term, Handbooks::TAXONOMY ), 2 );

		$this->assertStringContainsString( 'First page', $out );
		$this->assertStringContainsString( 'Second page', $out );
		$this->assertStringNotContainsString( 'Third page', $out );
		$this->assertStringContainsString( 'living-handbook-card__more', $out );
	}

	/**
	 * With nothing left over there is no "more" link, because there is no more.
	 *
	 * @return void
	 */
	public function test_no_more_link_when_everything_is_shown(): void {
		$term = $this->handbook( 'Small' );
		$this->page( $term, 'Only page' );

		$out = Cards::handbook_card( get_term( $term, Handbooks::TAXONOMY ), 3 );

		$this->assertStringContainsString( 'Only page', $out );
		$this->assertStringNotContainsString( 'living-handbook-card__more', $out );
	}

	/**
	 * Zero means no preview: the card is what it was.
	 *
	 * @return void
	 */
	public function test_the_preview_can_be_switched_off(): void {
		$term = $this->handbook( 'Quiet' );
		$this->page( $term, 'Hidden from the card' );

		$this->assertStringNotContainsString( 'living-handbook-card__preview', Cards::handbook_card( get_term( $term, Handbooks::TAXONOMY ) ) );
	}

	/**
	 * A preview names pages, so it must never name one its reader may not open.
	 * The card of a members-only handbook is not shown to a guest at all, so the
	 * case to hold is the one where the handbook is readable: what it lists is
	 * whatever the access check let through.
	 *
	 * @return void
	 */
	public function test_the_preview_shows_only_readable_pages(): void {
		$open   = $this->handbook( 'Open' );
		$closed = $this->handbook( 'Closed', 0, Handbooks::VISIBILITY_MEMBERS );
		$this->page( $open, 'Public page' );
		$this->page( $closed, 'Members only page' );

		wp_set_current_user( 0 );
		$out = Entry::render_chooser( 'cards', 3 );

		$this->assertStringContainsString( 'Public page', $out );
		$this->assertStringNotContainsString( 'Members only page', $out );
		$this->assertStringNotContainsString( 'Closed', $out );
	}

	/**
	 * A handbook below another is set in and says which one it belongs to.
	 *
	 * @return void
	 */
	public function test_a_child_handbook_is_shown_under_its_parent(): void {
		$parent = $this->handbook( 'Company' );
		$this->handbook( 'Engineering', $parent );

		$out = Entry::render_chooser();

		$this->assertStringContainsString( 'living-handbook-cards--children', $out );
		$this->assertStringContainsString( 'in Company', $out );
	}

	/**
	 * Access is not inherited: a child of a handbook nobody may read is readable
	 * on its own terms, and appears, rather than disappearing with its parent.
	 *
	 * @return void
	 */
	public function test_a_child_of_an_unreadable_parent_still_appears(): void {
		$parent = $this->handbook( 'Internal', 0, Handbooks::VISIBILITY_MEMBERS );
		$this->handbook( 'Public part', $parent );

		wp_set_current_user( 0 );
		$out = Entry::render_chooser();

		$this->assertStringContainsString( 'Public part', $out );
		$this->assertStringNotContainsString( 'Internal', $out );
		// Lifted to the top level, so it is reachable, and without a reference to
		// a handbook this visitor cannot see.
		$this->assertStringNotContainsString( 'living-handbook-cards--children', $out );
	}

	/**
	 * The menu nests the same way, so the header list says the same thing as the
	 * overview.
	 *
	 * @return void
	 */
	public function test_the_menu_nests_the_handbooks(): void {
		$parent = $this->handbook( 'Company' );
		$this->handbook( 'Engineering', $parent );

		$this->assertStringContainsString( 'living-handbook-menu__sublist', Entry::render_menu() );
	}

	/**
	 * A handbook that holds other handbooks rather than pages of its own lists
	 * them, instead of claiming it has nothing.
	 *
	 * @return void
	 */
	public function test_a_parent_without_pages_lists_its_children(): void {
		$parent = $this->handbook( 'Company' );
		$child  = $this->handbook( 'Engineering', $parent );
		$this->page( $child, 'A page of the child' );

		$term = get_term( $parent, Handbooks::TAXONOMY );
		$out  = Entry::render_entry( $term );

		$this->assertStringContainsString( 'Engineering', $out );
		$this->assertStringNotContainsString( 'has no pages yet', $out );
	}

	/**
	 * A handbook with neither pages nor children still says so.
	 *
	 * @return void
	 */
	public function test_a_truly_empty_handbook_still_says_so(): void {
		$term = get_term( $this->handbook( 'Nothing here' ), Handbooks::TAXONOMY );

		$this->assertStringContainsString( 'has no pages yet', Entry::render_entry( $term ) );
	}
}
