<?php
/**
 * Where the handbook's stylesheet and script actually arrive.
 *
 * They used to be enqueued from one place that decided, by looking at the
 * current query, whether this looked like a handbook view. A block sitting in a
 * template part, a header or a footer is not visible to that decision, so the
 * block was rendered without the styles and the script it needs. The blocks now
 * name their handles in their own block.json, which makes WordPress load them
 * where a block is really rendered.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use WP_Block_Type_Registry;
use WP_UnitTestCase;

/**
 * Block asset handles.
 */
final class BlockAssetsTest extends WP_UnitTestCase {

	/**
	 * Start each test with nothing enqueued.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		foreach ( array( 'living-handbook' ) as $handle ) {
			wp_dequeue_style( $handle );
			wp_dequeue_script( $handle );
		}
	}

	/**
	 * The two shared handles exist before anything asks for them, and the script
	 * carries its data: without the endpoints and labels it does nothing.
	 *
	 * @return void
	 */
	public function test_the_shared_handles_are_registered_with_their_data(): void {
		$this->assertTrue( wp_style_is( 'living-handbook', 'registered' ) );
		$this->assertTrue( wp_script_is( 'living-handbook', 'registered' ) );

		$data = wp_scripts()->get_data( 'living-handbook', 'data' );
		$this->assertIsString( $data );
		$this->assertStringContainsString( 'livingHandbook', $data );
		$this->assertStringContainsString( 'feedback', $data, 'The feedback endpoint travels with the handle.' );
	}

	/**
	 * Every block that renders plugin markup names both handles, so WordPress
	 * can load them wherever that block ends up.
	 *
	 * @return void
	 */
	public function test_the_blocks_name_the_handles_they_need(): void {
		$registry = WP_Block_Type_Registry::get_instance();

		foreach ( array( 'navigation', 'toc', 'pagemeta', 'overview', 'entry', 'menu', 'badges', 'feedback', 'search' ) as $name ) {
			$type = $registry->get_registered( 'living-handbook/' . $name );
			$this->assertNotNull( $type, "The block {$name} should be registered." );
			$this->assertContains( 'living-handbook', $type->style_handles, "The block {$name} should bring the stylesheet." );
			$this->assertContains( 'living-handbook', $type->view_script_handles, "The block {$name} should bring the frontend script." );
			$this->assertContains( 'living-handbook-blocks', $type->editor_script_handles, "The block {$name} should name the editor bundle." );
		}
	}

	/**
	 * The point of the change: a handbook block rendered on a page that is not a
	 * handbook view brings its assets along. Before, this page stayed unstyled.
	 *
	 * @return void
	 */
	public function test_a_block_outside_a_handbook_view_still_gets_its_assets(): void {
		// The block has to render something: WordPress takes the assets back off
		// the queue when a block produces no output, which is right and means the
		// test needs a handbook for the menu to list.
		$term = wp_insert_term( 'Visible handbook', Handbooks::TAXONOMY );
		update_term_meta( (int) $term['term_id'], Handbooks::META_VISIBILITY, 'public' );

		$this->go_to( home_url( '/' ) );

		$this->assertFalse( wp_style_is( 'living-handbook', 'enqueued' ), 'Nothing is loaded before a block renders.' );

		$rendered = do_blocks( '<!-- wp:living-handbook/menu /-->' );
		$this->assertNotSame( '', trim( $rendered ), 'The block has to render for this test to mean anything.' );

		$this->assertTrue( wp_style_is( 'living-handbook', 'enqueued' ), 'The stylesheet comes with the block.' );
		$this->assertTrue( wp_script_is( 'living-handbook', 'enqueued' ), 'So does the script.' );
	}

	/**
	 * A page with none of the plugin's blocks stays clean: the assets are not
	 * loaded on the whole site just because the plugin is active.
	 *
	 * @return void
	 */
	public function test_a_page_without_handbook_blocks_stays_clean(): void {
		$this->go_to( home_url( '/' ) );

		do_blocks( '<!-- wp:paragraph --><p>Nothing to do with handbooks.</p><!-- /wp:paragraph -->' );

		$this->assertFalse( wp_style_is( 'living-handbook', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'living-handbook', 'enqueued' ) );
	}

	/**
	 * A handbook page loads them without any block being involved, because its
	 * template renders handbook markup directly.
	 *
	 * @return void
	 */
	public function test_a_handbook_view_loads_them_without_a_block(): void {
		$term = wp_insert_term( 'Assets handbook', Handbooks::TAXONOMY );
		$page = self::factory()->post->create(
			array(
				'post_type'   => Handbook::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		wp_set_object_terms( $page, array( (int) $term['term_id'] ), Handbooks::TAXONOMY );

		$this->go_to( (string) get_permalink( $page ) );
		do_action( 'wp_enqueue_scripts' );

		$this->assertTrue( wp_style_is( 'living-handbook', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'living-handbook', 'enqueued' ) );
	}

	/**
	 * The editor bundle is registered and reachable by the handle the blocks
	 * name; it is no longer enqueued on every editor screen from a global hook.
	 *
	 * @return void
	 */
	public function test_the_editor_bundle_is_registered_not_globally_enqueued(): void {
		$this->assertTrue( wp_script_is( 'living-handbook-blocks', 'registered' ) );
		$this->assertFalse(
			method_exists( \LivingHandbook\Blocks\Blocks::class, 'enqueue_editor' ),
			'The global editor hook and its callback are gone; the blocks name the bundle themselves.'
		);
	}
}
