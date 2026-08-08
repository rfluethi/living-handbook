<?php
/**
 * Sections have an address, and it survives an edit above them.
 *
 * The ids used to be made in the browser from the position of the heading, so
 * `#lh-section-3` meant "the fourth heading, whatever that is today". Every
 * link into a page was one inserted heading away from pointing somewhere else,
 * and nothing said so. These tests hold the property that replaces it: the id
 * comes from the heading's own text, so inserting a section above it changes
 * nothing.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Frontend\Headings;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use WP_UnitTestCase;

/**
 * Frontend\Headings.
 */
final class HeadingAnchorsTest extends WP_UnitTestCase {

	/**
	 * A published handbook page, set up as the queried single page so the filter
	 * takes effect at all.
	 *
	 * @param string $content Page content.
	 * @return int Post id.
	 */
	private function page_on_screen( string $content ): int {
		$term_id = (int) self::factory()->term->create(
			array(
				'taxonomy' => Handbooks::TAXONOMY,
			)
		);
		$id   = (int) self::factory()->post->create(
			array(
				'post_type'    => Handbook::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'A page',
				'post_content' => $content,
			)
		);
		wp_set_object_terms( $id, array( $term_id ), Handbooks::TAXONOMY );

		$this->go_to( (string) get_permalink( $id ) );
		the_post();

		return $id;
	}

	/**
	 * Run the filter on content as it appears on the page.
	 *
	 * @param string $content Page content.
	 * @return string
	 */
	private function rendered( string $content ): string {
		$this->page_on_screen( $content );
		return ( new Headings() )->add_anchors( $content );
	}

	/**
	 * The id is the heading's own text, and the link points at it.
	 *
	 * @return void
	 */
	public function test_a_heading_gets_a_readable_id_and_a_link(): void {
		$out = $this->rendered( '<h2>Getting started</h2>' );

		$this->assertStringContainsString( 'id="getting-started"', $out );
		$this->assertStringContainsString( 'href="#getting-started"', $out );
		$this->assertStringContainsString( 'living-handbook-anchor', $out );
	}

	/**
	 * The point of the whole change: a heading inserted above does not move the
	 * address of the ones below it.
	 *
	 * @return void
	 */
	public function test_an_inserted_heading_does_not_move_the_others(): void {
		$before = $this->rendered( '<h2>First</h2><h2>Second</h2>' );
		$after  = $this->rendered( '<h2>Brand new</h2><h2>First</h2><h2>Second</h2>' );

		foreach ( array( 'id="first"', 'id="second"' ) as $id ) {
			$this->assertStringContainsString( $id, $before );
			$this->assertStringContainsString( $id, $after );
		}
	}

	/**
	 * Two headings with the same text get distinct ids, and the first keeps the
	 * plain one.
	 *
	 * @return void
	 */
	public function test_a_repeated_heading_gets_a_counter(): void {
		$out = $this->rendered( '<h2>Result</h2><h3>Result</h3>' );

		$this->assertStringContainsString( 'id="result"', $out );
		$this->assertStringContainsString( 'id="result-2"', $out );
	}

	/**
	 * An id set in the editor wins, because that is the way out of a collision
	 * with something else on the page.
	 *
	 * @return void
	 */
	public function test_an_id_from_the_editor_is_kept(): void {
		$out = $this->rendered( '<h2 id="chosen-by-hand">Comments</h2>' );

		$this->assertStringContainsString( 'id="chosen-by-hand"', $out );
		$this->assertStringNotContainsString( 'id="comments"', $out );
		$this->assertStringContainsString( 'href="#chosen-by-hand"', $out );
	}

	/**
	 * An umlaut becomes a readable ASCII slug, not a percent escape. Which one
	 * WordPress makes of it (ubersicht or uebersicht) depends on the site
	 * language, so the test holds the property, not the spelling.
	 *
	 * @return void
	 */
	public function test_an_umlaut_becomes_a_readable_slug(): void {
		$out   = $this->rendered( '<h2>Übersicht</h2>' );
		$found = array();

		$this->assertSame( 1, preg_match( '/ id="([a-z0-9-]+)"/', $out, $found ) );
		$this->assertStringContainsString( 'bersicht', $found[1] );
		$this->assertStringContainsString( 'href="#' . $found[1] . '"', $out );
	}

	/**
	 * A heading with no text at all still gets an id, so the table of contents
	 * has something to link to.
	 *
	 * @return void
	 */
	public function test_a_heading_without_text_falls_back_to_its_position(): void {
		$out = $this->rendered( '<h2><img src="/x.png" alt=""></h2>' );

		$this->assertStringContainsString( 'id="section-1"', $out );
	}

	/**
	 * h1, h5 and h6 are left alone: the title, and detail inside a section.
	 *
	 * @return void
	 */
	public function test_only_h2_to_h4_take_part(): void {
		$out = $this->rendered( '<h1>Title</h1><h4>Detail</h4><h5>Aside</h5>' );

		$this->assertStringContainsString( 'id="detail"', $out );
		$this->assertStringNotContainsString( 'id="title"', $out );
		$this->assertStringNotContainsString( 'id="aside"', $out );
	}

	/**
	 * Running twice must not add a second link. The content filter is not
	 * guaranteed to run once.
	 *
	 * @return void
	 */
	public function test_running_twice_adds_one_link(): void {
		$this->page_on_screen( '<h2>Once</h2>' );

		$headings = new Headings();
		$out      = $headings->add_anchors( $headings->add_anchors( '<h2>Once</h2>' ) );

		$this->assertSame( 1, substr_count( $out, 'living-handbook-anchor' ) );
	}

	/**
	 * Off the single page there is nothing to link into, so nothing is touched.
	 *
	 * @return void
	 */
	public function test_nothing_happens_outside_a_handbook_page(): void {
		$this->go_to( home_url( '/' ) );

		$this->assertSame( '<h2>Untouched</h2>', ( new Headings() )->add_anchors( '<h2>Untouched</h2>' ) );
	}

	/**
	 * A site can switch the anchors off without switching the ids off, because
	 * the ids are what makes the links work.
	 *
	 * @return void
	 */
	public function test_the_filter_can_switch_the_whole_thing_off(): void {
		add_filter( 'living_handbook_heading_anchors', '__return_false' );

		$this->assertSame( '<h2>Left alone</h2>', $this->rendered( '<h2>Left alone</h2>' ) );
	}
}
