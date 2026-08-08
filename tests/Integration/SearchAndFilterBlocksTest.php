<?php
/**
 * The search bar and the filter bar as blocks of their own.
 *
 * The entry block used to draw the whole entry page in one fixed layout:
 * search on top, facets on the right, cards in the middle. A template could
 * take it or leave it. 0.65.0 added two switches for leaving a control out,
 * which was a setting to manage that weakness rather than remove it; 0.66.0
 * removes both the switches and the drawing. The entry block is the result
 * column, the other two are blocks, and the shipped template holds all three.
 *
 * These tests hold that split: the entry block draws no control, each control
 * stands on its own and finds its handbook, and the template still has the
 * page a fresh install had before.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Blocks\Blocks;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use WP_UnitTestCase;

/**
 * Blocks::render_entry, render_search_form and render_filters.
 */
final class SearchAndFilterBlocksTest extends WP_UnitTestCase {

	/**
	 * The handbook under test.
	 *
	 * @var int
	 */
	private int $term_id = 0;

	/**
	 * A public handbook with one page, shown as its term archive, which is the
	 * context all three blocks read.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->term_id = (int) self::factory()->term->create( array( 'taxonomy' => Handbooks::TAXONOMY ) );
		update_term_meta( $this->term_id, Handbooks::META_VISIBILITY, Handbooks::VISIBILITY_PUBLIC );

		$post_id = (int) self::factory()->post->create(
			array(
				'post_type'   => Handbook::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'A page with a topic',
			)
		);
		wp_set_object_terms( $post_id, array( $this->term_id ), Handbooks::TAXONOMY );
		wp_set_object_terms( $post_id, array( 'Onboarding' ), 'handbook_topic' );

		$link = get_term_link( $this->term_id, Handbooks::TAXONOMY );
		$this->go_to( is_string( $link ) ? $link : '/' );
	}

	/**
	 * Both new blocks are registered, so a template can reference them.
	 *
	 * @return void
	 */
	public function test_the_blocks_are_registered(): void {
		$registry = \WP_Block_Type_Registry::get_instance();

		$this->assertTrue( $registry->is_registered( 'living-handbook/search-form' ) );
		$this->assertTrue( $registry->is_registered( 'living-handbook/filters' ) );
	}

	/**
	 * The entry block is the result column and nothing else. It used to draw the
	 * search bar and the facets as well, with two switches to turn them off,
	 * which was a setting managing a weakness rather than removing it.
	 *
	 * @return void
	 */
	public function test_the_entry_block_is_the_result_column_only(): void {
		$out = ( new Blocks() )->render_entry( array() );

		$this->assertStringContainsString( 'living-handbook-main', $out );
		$this->assertStringContainsString( 'data-term-id', $out );
		$this->assertStringNotContainsString( 'living-handbook-start__search', $out );
		$this->assertStringNotContainsString( 'living-handbook-filterform', $out );
	}

	/**
	 * The shipped entry template holds all three, so a fresh install has the page
	 * it had before, now as blocks a person can see and move.
	 *
	 * @return void
	 */
	public function test_the_entry_template_holds_all_three(): void {
		$template = get_block_template( 'living-handbook//taxonomy-' . Handbooks::TAXONOMY, 'wp_template' );

		$this->assertNotNull( $template );
		foreach ( array( 'living-handbook/search-form', 'living-handbook/entry', 'living-handbook/filters' ) as $block ) {
			$this->assertStringContainsString( $block, (string) $template->content );
		}
	}

	/**
	 * The quick search renders on an entry page too. It used to bail on anything
	 * but a single page, so a person who reached for it there got an empty spot
	 * and no reason why.
	 *
	 * @return void
	 */
	public function test_the_quick_search_also_renders_on_an_entry_page(): void {
		$this->assertStringContainsString( 'living-handbook-page-search', ( new Blocks() )->render_search( array() ) );
	}

	/**
	 * The search bar finds its handbook and submits to it.
	 *
	 * @return void
	 */
	public function test_the_search_bar_stands_on_its_own(): void {
		$out = ( new Blocks() )->render_search_form( array() );

		$this->assertStringContainsString( 'living-handbook-start__search', $out );
		$this->assertStringContainsString( 'name="lh_s"', $out );
		$this->assertStringContainsString( (string) get_term_link( $this->term_id, Handbooks::TAXONOMY ), $out );
	}

	/**
	 * The wording is the block's to set, and the label is in the document even
	 * when it is not shown, because a search field needs an accessible name.
	 *
	 * @return void
	 */
	public function test_the_search_bar_takes_its_wording_from_the_block(): void {
		$out = ( new Blocks() )->render_search_form(
			array(
				'showLabel'   => true,
				'label'       => 'Find a rule',
				'placeholder' => 'Type a word',
				'buttonText'  => 'Go',
			)
		);

		$this->assertStringContainsString( 'Find a rule', $out );
		$this->assertStringContainsString( 'placeholder="Type a word"', $out );
		$this->assertStringContainsString( '>Go</button>', $out );
		$this->assertStringNotContainsString( 'living-handbook-visually-hidden', $out );

		$hidden_label = ( new Blocks() )->render_search_form( array( 'label' => 'Find a rule' ) );
		$this->assertStringContainsString( 'living-handbook-visually-hidden', $hidden_label );
		$this->assertStringContainsString( 'Find a rule', $hidden_label );
	}

	/**
	 * Without a button the form still submits, by Enter in the field, so the
	 * option is a visual one and not a way to break the search.
	 *
	 * @return void
	 */
	public function test_the_button_can_be_left_out(): void {
		$out = ( new Blocks() )->render_search_form( array( 'buttonPosition' => 'no-button' ) );

		$this->assertStringNotContainsString( '<button', $out );
		$this->assertStringContainsString( 'name="lh_s"', $out );
		$this->assertStringContainsString( 'method="get"', $out );
	}

	/**
	 * An unknown position falls back rather than writing itself into a class
	 * name, because the attribute is saved in post content and can be anything.
	 *
	 * @return void
	 */
	public function test_an_unknown_button_position_falls_back(): void {
		$out = ( new Blocks() )->render_search_form( array( 'buttonPosition' => '"><script>' ) );

		$this->assertStringContainsString( 'data-button-position="button-outside"', $out );
		$this->assertStringNotContainsString( '<script>', $out );
	}

	/**
	 * The filter bar on its own offers the terms the handbook's pages use.
	 *
	 * @return void
	 */
	public function test_the_filter_bar_stands_on_its_own(): void {
		$out = ( new Blocks() )->render_filters( array() );

		$this->assertStringContainsString( 'living-handbook-filterform', $out );
		$this->assertStringContainsString( 'Onboarding', $out );
	}

	/**
	 * Off a handbook there is no handbook to search, so both render nothing
	 * rather than a control that would go nowhere.
	 *
	 * @return void
	 */
	public function test_both_render_nothing_without_a_handbook(): void {
		$this->go_to( home_url( '/' ) );

		$blocks = new Blocks();
		$this->assertSame( '', $blocks->render_search_form( array() ) );
		$this->assertSame( '', $blocks->render_filters( array() ) );
	}
}
