<?php
/**
 * The markup an assistive technology has to work with.
 *
 * Two of these were promises the markup made and the code did not keep: an ARIA
 * combobox whose only key was Escape, and a live region wrapped around two dozen
 * result cards, which reads the whole list again after every keystroke. Both are
 * server-rendered, so they can be pinned here; the keyboard behaviour itself
 * lives in the browser and is not what these tests claim to cover.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Blocks\Blocks;
use LivingHandbook\Frontend\Entry;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use WP_Term;
use WP_UnitTestCase;

/**
 * Accessibility-relevant markup.
 */
final class AccessibilityMarkupTest extends WP_UnitTestCase {

	/**
	 * A public handbook with one published page.
	 *
	 * @return WP_Term
	 */
	private function handbook(): WP_Term {
		$term = wp_insert_term( 'Accessible handbook', Handbooks::TAXONOMY );
		$this->assertIsArray( $term );
		update_term_meta( (int) $term['term_id'], Handbooks::META_VISIBILITY, 'public' );

		$page = self::factory()->post->create(
			array(
				'post_type'   => Handbook::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'A page',
			)
		);
		wp_set_object_terms( $page, array( (int) $term['term_id'] ), Handbooks::TAXONOMY );

		$found = get_term( (int) $term['term_id'], Handbooks::TAXONOMY );
		$this->assertInstanceOf( WP_Term::class, $found );
		return $found;
	}

	/**
	 * The result column is no longer a live region. It held up to two dozen
	 * cards, so every keystroke in the search field made a screen reader read
	 * the whole list again.
	 *
	 * @return void
	 */
	public function test_the_result_column_is_not_a_live_region(): void {
		$html = Entry::render_entry( $this->handbook() );

		$this->assertStringNotContainsString( 'living-handbook-main" aria-live', $html, 'The whole column must not announce itself.' );
		$this->assertStringContainsString( 'living-handbook-entry__status', $html, 'A status line takes its place.' );
		$this->assertStringContainsString( 'role="status"', $html );
	}

	/**
	 * The status line sits outside the column that is replaced when the filter
	 * runs. A live region that is removed and rebuilt announces nothing.
	 *
	 * @return void
	 */
	public function test_the_status_line_survives_a_replaced_list(): void {
		$html = Entry::render_entry( $this->handbook() );

		$status = strpos( $html, 'living-handbook-entry__status' );
		$main   = strpos( $html, 'living-handbook-main' );

		$this->assertIsInt( $status );
		$this->assertIsInt( $main );
		$this->assertLessThan( $main, $status, 'The status line comes before the column, so it is not inside it.' );
	}

	/**
	 * The page search no longer claims to be an ARIA combobox. It promised a
	 * keyboard pattern it did not implement, and its options wrapped links,
	 * which ARIA does not allow.
	 *
	 * @return void
	 */
	public function test_the_page_search_does_not_claim_a_pattern_it_has_not(): void {
		$term = $this->handbook();
		$page = self::factory()->post->create(
			array(
				'post_type'   => Handbook::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		wp_set_object_terms( $page, array( $term->term_id ), Handbooks::TAXONOMY );
		$this->go_to( (string) get_permalink( $page ) );

		$html = ( new Blocks() )->render_search();

		$this->assertNotSame( '', $html, 'The search box should render on a handbook page.' );
		$this->assertStringNotContainsString( 'role="combobox"', $html );
		$this->assertStringNotContainsString( 'role="listbox"', $html );
		$this->assertStringNotContainsString( 'aria-expanded', $html );
		$this->assertStringContainsString( 'role="status"', $html, 'The count is announced instead.' );
		$this->assertStringContainsString( 'aria-describedby', $html, 'The field points at that status line.' );
	}

	/**
	 * The search field keeps its label, which is the part that was always right.
	 *
	 * @return void
	 */
	public function test_the_page_search_keeps_its_label(): void {
		$term = $this->handbook();
		$page = self::factory()->post->create(
			array(
				'post_type'   => Handbook::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		wp_set_object_terms( $page, array( $term->term_id ), Handbooks::TAXONOMY );
		$this->go_to( (string) get_permalink( $page ) );

		$html = ( new Blocks() )->render_search();

		$this->assertMatchesRegularExpression( '/<label[^>]+for="living-handbook-search-[^"]*"/', $html );
	}
}
