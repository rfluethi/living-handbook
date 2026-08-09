<?php
/**
 * A site can switch a taxonomy off, and it stays switched off everywhere.
 *
 * Four taxonomies are more than many teams want: a small handbook that only
 * ever uses topics still had page type, responsibility and audience in the
 * editor, in the filter bar, on every card and on every page. The switches let
 * a site say which ones it uses.
 *
 * The promise attached to them is the part that has to hold: **nothing is
 * deleted**. A switch that quietly threw away work is a switch nobody dares to
 * touch, so these tests check the assignments survive and come back, and that a
 * bundle export still carries all four, because a bundle leaves this site and
 * must not lose what this site happens to hide.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Frontend\Cards;
use LivingHandbook\Frontend\Filters;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\Import\HandbookExport;
use LivingHandbook\PostType\Handbook;
use LivingHandbook\Taxonomy\Taxonomies;
use WP_UnitTestCase;

/**
 * Taxonomy\Taxonomies and the six places a switched-off taxonomy disappears.
 */
final class TaxonomySwitchesTest extends WP_UnitTestCase {

	/**
	 * Reset the registration between tests: switching a taxonomy off changes
	 * the arguments it is registered with.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		delete_option( Taxonomies::OPTION_ENABLED );
		( new Taxonomies() )->register_taxonomies();
		parent::tear_down();
	}

	/**
	 * Switch taxonomies off and re-register, the way a settings save does.
	 *
	 * @param array<int, string> $off Taxonomies to switch off.
	 * @return void
	 */
	private function switch_off( array $off ): void {
		$value = array();
		foreach ( array_keys( Taxonomies::all() ) as $taxonomy ) {
			$value[ $taxonomy ] = in_array( $taxonomy, $off, true ) ? '0' : '1';
		}
		update_option( Taxonomies::OPTION_ENABLED, $value );
		( new Taxonomies() )->register_taxonomies();
	}

	/**
	 * A page with a term in every taxonomy.
	 *
	 * @return int Post id.
	 */
	private function page_with_terms(): int {
		$term = (int) self::factory()->term->create( array( 'taxonomy' => Handbooks::TAXONOMY ) );
		update_term_meta( $term, Handbooks::META_VISIBILITY, Handbooks::VISIBILITY_PUBLIC );

		$id = (int) self::factory()->post->create(
			array(
				'post_type'   => Handbook::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'A page',
			)
		);
		wp_set_object_terms( $id, array( $term ), Handbooks::TAXONOMY );
		wp_set_object_terms( $id, array( 'Guide' ), Taxonomies::PAGE_TYPE );
		wp_set_object_terms( $id, array( 'Onboarding' ), Taxonomies::TOPIC );
		wp_set_object_terms( $id, array( 'Editor' ), Taxonomies::ROLE );
		wp_set_object_terms( $id, array( 'Everyone' ), Taxonomies::AUDIENCE );

		return $id;
	}

	/**
	 * With nothing saved, all four are in use. That is what every site had
	 * before the switches existed, and an update must not change a site.
	 *
	 * @return void
	 */
	public function test_all_four_are_on_by_default(): void {
		$this->assertSame( array_keys( Taxonomies::all() ), Taxonomies::enabled() );
	}

	/**
	 * Switching every one off is a real state, not "nothing saved yet". The two
	 * mean opposite things and must not collapse into the same value.
	 *
	 * @return void
	 */
	public function test_switching_everything_off_is_not_the_default(): void {
		$this->switch_off( array_keys( Taxonomies::all() ) );

		$this->assertSame( array(), Taxonomies::enabled() );
	}

	/**
	 * The badges of a page skip a taxonomy the site does not use.
	 *
	 * @return void
	 */
	public function test_a_switched_off_taxonomy_has_no_badge(): void {
		$id = $this->page_with_terms();

		$this->assertStringContainsString( 'Everyone', Cards::badges( $id ) );

		$this->switch_off( array( Taxonomies::AUDIENCE ) );

		$out = Cards::badges( $id );
		$this->assertStringNotContainsString( 'Everyone', $out );
		$this->assertStringContainsString( 'Guide', $out );
	}

	/**
	 * And so does the card, in what it shows and in the data attributes the
	 * filter reads.
	 *
	 * @return void
	 */
	public function test_a_switched_off_taxonomy_is_off_the_card(): void {
		$id = $this->page_with_terms();

		$this->switch_off( array( Taxonomies::ROLE ) );

		$out = Cards::page_card( $id );
		$this->assertStringContainsString( 'data-role=""', $out );
		$this->assertStringContainsString( 'data-topic="onboarding"', $out );
	}

	/**
	 * The facet is gone from the entry page, and its parameter is not read
	 * either, so a hand-written URL cannot bring a switched-off facet back.
	 *
	 * @return void
	 */
	public function test_a_switched_off_taxonomy_has_no_facet(): void {
		$id   = $this->page_with_terms();
		$term = (int) Handbooks::for_post( $id );

		$this->switch_off( array( Taxonomies::TOPIC ) );

		$out = Filters::facets( get_term( $term, Handbooks::TAXONOMY ) );
		$this->assertStringNotContainsString( 'Onboarding', $out );
		$this->assertStringContainsString( 'Guide', $out );

		$_GET['lh_topic'] = array( 'onboarding' );
		$this->assertArrayNotHasKey( 'lh_topic', Filters::current_selections() );
		$_GET = array();
	}

	/**
	 * The editor sidebar and the term screen are gone with it: both hang on the
	 * registration, which is where the switch takes effect.
	 *
	 * @return void
	 */
	public function test_a_switched_off_taxonomy_leaves_the_editor(): void {
		$this->switch_off( array( Taxonomies::AUDIENCE ) );

		$object = get_taxonomy( Taxonomies::AUDIENCE );

		$this->assertNotFalse( $object, 'It stays registered, or the terms would be orphaned.' );
		$this->assertFalse( $object->show_ui );
		$this->assertFalse( $object->show_in_rest );
		$this->assertFalse( $object->show_admin_column );
	}

	/**
	 * The promise: nothing is deleted, and switching back on brings every
	 * assignment back exactly as it was.
	 *
	 * @return void
	 */
	public function test_nothing_is_deleted_and_it_all_comes_back(): void {
		$id = $this->page_with_terms();

		$this->switch_off( array( Taxonomies::AUDIENCE, Taxonomies::ROLE ) );

		// Still on the page while hidden.
		$this->assertSame( array( 'Everyone' ), wp_get_object_terms( $id, Taxonomies::AUDIENCE, array( 'fields' => 'names' ) ) );

		$this->switch_off( array() );

		$this->assertStringContainsString( 'Everyone', Cards::badges( $id ) );
		$this->assertSame( array( 'Editor' ), wp_get_object_terms( $id, Taxonomies::ROLE, array( 'fields' => 'names' ) ) );
	}

	/**
	 * The switches are a display decision on this site. A bundle leaves this
	 * site, so it carries all four whatever is switched off here; anything else
	 * would lose data on the way to another installation.
	 *
	 * @return void
	 */
	public function test_a_bundle_still_carries_every_taxonomy(): void {
		$id   = $this->page_with_terms();
		$term = (int) Handbooks::for_post( $id );
		$this->switch_off( array( Taxonomies::AUDIENCE ) );

		$media    = array();
		$root     = 0;
		$manifest = ( new HandbookExport() )->build_manifest( get_term( $term, Handbooks::TAXONOMY ), $media, $root );

		$this->assertNotEmpty( $manifest['pages'] );
		$page = $manifest['pages'][0];

		$this->assertArrayHasKey( Taxonomies::AUDIENCE, $page['terms'] );
		$this->assertSame( array( 'Everyone' ), array_column( $page['terms'][ Taxonomies::AUDIENCE ], 'name' ) );
	}
}
