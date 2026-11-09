<?php
/**
 * The Mermaid block and the source note block outside and inside a block
 * render.
 *
 * Both are public render callbacks, so a theme, another plugin or a test can
 * call them directly. They used to ask WordPress for the block wrapper
 * attributes without the guard the other blocks have, and outside a block
 * render that reads a block that does not exist.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Blocks\MermaidBlock;
use LivingHandbook\Blocks\SourceNoteBlock;
use LivingHandbook\Git\GitSync;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use WP_UnitTestCase;

/**
 * MermaidBlock::render and SourceNoteBlock::render.
 */
final class ContentBlockCallbacksTest extends WP_UnitTestCase {

	/**
	 * Opens a public handbook page that was synced from GitHub.
	 *
	 * @return void
	 */
	private function go_to_synced_page(): void {
		$term = (int) self::factory()->term->create( array( 'taxonomy' => Handbooks::TAXONOMY ) );
		update_term_meta( $term, Handbooks::META_VISIBILITY, Handbooks::VISIBILITY_PUBLIC );

		$id = (int) self::factory()->post->create(
			array(
				'post_type'   => Handbook::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Synced page',
			)
		);
		wp_set_object_terms( $id, array( $term ), Handbooks::TAXONOMY );
		update_post_meta( $id, GitSync::META_SOURCE, GitSync::SOURCE_GITHUB );
		update_post_meta( $id, GitSync::META_URL, 'https://raw.githubusercontent.com/acme/docs/main/start.md' );

		$this->go_to( (string) get_permalink( $id ) );
		$this->assertTrue( is_singular( Handbook::POST_TYPE ) );
	}

	/**
	 * Called directly, both callbacks render with their own class and without
	 * a block's generated class, and raise no warning.
	 *
	 * @return void
	 */
	public function test_the_callbacks_survive_being_called_outside_a_block(): void {
		$mermaid = ( new MermaidBlock() )->render( array( 'code' => 'graph TD; A-->B' ) );
		$this->assertStringContainsString( 'class="living-handbook-figure"', $mermaid );

		$this->go_to_synced_page();
		$note = ( new SourceNoteBlock() )->render( array() );
		$this->assertStringContainsString( 'class="lh-source-note"', $note );
	}

	/**
	 * Inside a block render the block supports still reach the markup.
	 *
	 * @return void
	 */
	public function test_the_block_class_reaches_the_markup_in_a_render(): void {
		// Serialised by core, see BlockMetadataTest::test_the_anchor_reaches_the_rendered_markup.
		$out = do_blocks(
			get_comment_delimited_block_content(
				'living-handbook/mermaid',
				array(
					'code'      => 'graph TD; A-->B',
					'className' => 'is-wide',
				),
				''
			)
		);

		$this->assertStringContainsString( 'wp-block-living-handbook-mermaid', $out );
		$this->assertStringContainsString( 'is-wide', $out );
	}

	/**
	 * The link to the source opens a new tab and says so, for a reader who
	 * does not see that happen.
	 *
	 * @return void
	 */
	public function test_the_source_link_announces_the_new_tab(): void {
		$this->go_to_synced_page();
		$note = ( new SourceNoteBlock() )->render( array() );

		$this->assertStringContainsString( 'href="https://github.com/acme/docs/blob/main/start.md"', $note );
		$this->assertStringContainsString( 'target="_blank"', $note );
		$this->assertStringContainsString( '<span class="living-handbook-visually-hidden"> (opens in a new tab)</span>', $note );
	}
}
