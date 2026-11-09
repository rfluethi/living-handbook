<?php
/**
 * What the blocks declare in block.json, and whether WordPress honours it.
 *
 * Every block of the plugin offers an HTML anchor (supports.anchor) and renders
 * on the server (save is null). Up to WordPress 6.9 the block editor defines the
 * anchor attribute with source "attribute", read from the saved markup's id. A
 * dynamic block has no saved markup, so the editor drops the attribute when it
 * serializes the block, and the server-side preview in the editor fails its
 * attribute check, because PHP did not know the attribute either: the anchor
 * support only registers it on the server from 7.0 on. An anchor typed into the
 * Advanced panel on 6.8 was gone after saving. Declaring "anchor" as a plain
 * string attribute in block.json is what makes both sides keep it, and core's
 * editor leaves an attribute alone that already has a type.
 *
 * The same files carried "border": false, a key that no core version reads:
 * the border support is still called __experimentalBorder in 6.8, 7.0 and 7.1,
 * and false is its default anyway. And the Mermaid block had neither a
 * description nor keywords for the inserter, and allowed "Edit as HTML" on a
 * block whose markup is generated.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use WP_Block_Type_Registry;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * blocks/*\/block.json against the running core.
 */
final class BlockMetadataTest extends WP_UnitTestCase {

	/**
	 * Every block.json of the plugin, keyed by block name.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function metadata(): array {
		$out = array();
		foreach ( glob( dirname( __DIR__, 2 ) . '/blocks/*/block.json' ) ?: array() as $file ) {
			$json = json_decode( (string) file_get_contents( $file ), true );
			$this->assertIsArray( $json, $file . ' is not valid JSON.' );
			$out[ (string) $json['name'] ] = $json;
		}
		$this->assertNotEmpty( $out );

		return $out;
	}

	/**
	 * A block that offers an anchor declares the attribute itself.
	 *
	 * @return void
	 */
	public function test_every_anchor_block_declares_the_attribute(): void {
		foreach ( $this->metadata() as $name => $json ) {
			if ( empty( $json['supports']['anchor'] ) ) {
				continue;
			}
			$this->assertSame(
				array( 'type' => 'string' ),
				$json['attributes']['anchor'] ?? null,
				$name . ' offers an anchor but does not declare it; the editor drops it before WordPress 7.0.'
			);
		}
	}

	/**
	 * The server knows the attribute on the running core, whichever version,
	 * which is what the block renderer endpoint validates against.
	 *
	 * @return void
	 */
	public function test_the_registered_blocks_know_the_anchor(): void {
		$registry = WP_Block_Type_Registry::get_instance();
		foreach ( array_keys( $this->metadata() ) as $name ) {
			$type = $registry->get_registered( $name );
			$this->assertNotNull( $type, $name . ' is not registered.' );
			$this->assertArrayHasKey( 'anchor', $type->get_attributes(), $name );
		}
	}

	/**
	 * The editor preview of a server-rendered block goes through the block
	 * renderer endpoint, which rejects every attribute the block does not
	 * declare. With an anchor set, that request must pass and the anchor must
	 * reach the markup. Before the fix it failed on 6.8 with "anchor is not a
	 * valid property of Object".
	 *
	 * @return void
	 */
	public function test_a_server_side_preview_with_an_anchor_renders(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/block-renderer/living-handbook/menu' );
		$request->set_param( 'context', 'edit' );
		$request->set_param( 'attributes', array( 'anchor' => 'handbooks' ) );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
	}

	/**
	 * A dynamic block rendered from post content with an anchor carries it as id.
	 *
	 * The markup is serialised by core, as the editor and the import write it:
	 * core encodes the Mermaid arrow (-->) in the attributes. Written by hand,
	 * the arrow closes the HTML comment, and the block parser of WordPress 7.2
	 * (WP_Block_Processor) then rightly sees no block at all.
	 *
	 * @return void
	 */
	public function test_the_anchor_reaches_the_rendered_markup(): void {
		$html = do_blocks(
			get_comment_delimited_block_content(
				'living-handbook/mermaid',
				array(
					'code'   => 'graph TD; A-->B;',
					'anchor' => 'flow',
				),
				''
			)
		);

		$this->assertStringContainsString( 'id="flow"', $html );
	}

	/**
	 * A Mermaid block as the Markdown import writes it still parses.
	 *
	 * markdown-import.js encodes <, > and & but not the dashes, so an arrow
	 * arrives as --\u003e. That must stay a block on every supported version,
	 * WordPress 7.2 with its stricter block parser included.
	 *
	 * @return void
	 */
	public function test_a_mermaid_block_written_by_the_import_parses(): void {
		$markup = '<!-- wp:living-handbook/mermaid {"code":"graph TD; A--\\u003eB"} /-->';
		$blocks = parse_blocks( $markup );

		$this->assertSame( 'living-handbook/mermaid', $blocks[0]['blockName'] );
		$this->assertSame( 'graph TD; A-->B', $blocks[0]['attrs']['code'] );
	}

	/**
	 * No block.json carries a supports key that no core version reads.
	 *
	 * @return void
	 */
	public function test_no_block_declares_an_unread_border_key(): void {
		foreach ( $this->metadata() as $name => $json ) {
			$this->assertArrayNotHasKey( 'border', $json['supports'] ?? array(), $name . ': core reads __experimentalBorder, "border" does nothing.' );
		}
	}

	/**
	 * The Mermaid block can be found in the inserter and cannot be edited as
	 * HTML, like every other block of the plugin.
	 *
	 * @return void
	 */
	public function test_the_mermaid_block_is_described_like_the_others(): void {
		$json = $this->metadata()['living-handbook/mermaid'];

		$this->assertNotEmpty( $json['description'] ?? '' );
		$this->assertNotEmpty( $json['keywords'] ?? array() );
		$this->assertContains( 'diagram', $json['keywords'] );
		$this->assertFalse( $json['supports']['html'] ?? true );
	}
}
