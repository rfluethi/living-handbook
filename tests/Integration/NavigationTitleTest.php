<?php
/**
 * The navigation's title row.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Frontend\Navigation;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use WP_UnitTestCase;

/**
 * Navigation::render, the title row.
 */
final class NavigationTitleTest extends WP_UnitTestCase {

	/**
	 * A public handbook with one page.
	 *
	 * @return array{0:int,1:int} Term id and page id.
	 */
	private function handbook_with_page(): array {
		$term = self::factory()->term->create_and_get(
			array(
				'taxonomy' => Handbooks::TAXONOMY,
				'name'     => 'General',
			)
		);
		update_term_meta( (int) $term->term_id, Handbooks::META_VISIBILITY, 'public' );

		$page = (int) self::factory()->post->create(
			array(
				'post_type'   => Handbook::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Start',
			)
		);
		wp_set_object_terms( $page, array( (int) $term->term_id ), Handbooks::TAXONOMY );

		return array( (int) $term->term_id, $page );
	}

	/**
	 * The handbook name is a link to the handbook's start page, like every other
	 * entry in the list is a link to its page. It used to be the text of a
	 * <summary>, which can toggle or link but not both, so the way to the start
	 * page hung beside it as a small arrow that testers did not read as a way
	 * anywhere.
	 *
	 * @return void
	 */
	public function test_the_handbook_name_is_a_link_to_the_start_page(): void {
		list( $term_id ) = $this->handbook_with_page();

		$html = Navigation::render( $term_id, 'accordion' );
		$link = get_term_link( $term_id, Handbooks::TAXONOMY );

		$this->assertIsString( $link );
		$this->assertStringContainsString( '<a href="' . esc_url( $link ) . '">General</a>', $html );
	}

	/**
	 * The arrow is gone, and with it its class, so a stylesheet that still
	 * targets it is styling nothing rather than half of something.
	 *
	 * @return void
	 */
	public function test_the_arrow_link_is_gone(): void {
		list( $term_id ) = $this->handbook_with_page();

		$html = Navigation::render( $term_id, 'accordion' );

		$this->assertStringNotContainsString( 'living-handbook-nav__home', $html );
		$this->assertStringNotContainsString( '<summary', $html );
	}

	/**
	 * The title row carries a toggle of the same kind the branches carry, and it
	 * names what it opens: aria-controls points at the id of the list, and
	 * aria-expanded says whether it is open. Without those, a screen reader
	 * announces a button that does something unnamed.
	 *
	 * @return void
	 */
	public function test_the_title_row_has_a_named_toggle(): void {
		list( $term_id ) = $this->handbook_with_page();

		$html = Navigation::render( $term_id, 'accordion' );

		$this->assertStringContainsString( 'living-handbook-nav__toggle--all', $html );
		$this->assertStringContainsString( 'aria-expanded="true"', $html );

		$this->assertSame(
			1,
			preg_match( '/aria-controls="([^"]+)"/', $html, $controls ),
			'The title toggle names no panel.'
		);
		$this->assertStringContainsString( '<nav id="' . $controls[1] . '"', $html );
	}

	/**
	 * Both displays get the title row: collapsing the whole navigation is useful
	 * in the tree display too, and on a narrow screen it is the only control
	 * that gets the navigation out of the way of the content.
	 *
	 * @return void
	 */
	public function test_both_displays_carry_the_title_row(): void {
		list( $term_id ) = $this->handbook_with_page();

		foreach ( array( 'accordion', 'sidebar' ) as $variant ) {
			$html = Navigation::render( $term_id, $variant );
			$this->assertStringContainsString( 'living-handbook-nav__toggle--all', $html, $variant );
			$this->assertStringContainsString( 'living-handbook-nav__top', $html, $variant );
		}
	}
}
