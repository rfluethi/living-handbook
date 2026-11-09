<?php
/**
 * Start patterns for new handbook pages (E-0080).
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Blocks\Patterns;
use LivingHandbook\PostType\Handbook;
use WP_Block_Patterns_Registry;
use WP_UnitTestCase;

/**
 * Seven outlines, offered when a handbook page is new, holding no text.
 *
 * Whether the editor accepts the markup as valid blocks needs the block
 * validator of @wordpress/blocks, which is JavaScript and not part of this
 * suite; for 0.78.0 it was run by hand and found all seven valid without a
 * fix. Here the markup is held against the PHP block parser and the rules of
 * E-0080.
 */
final class PatternsTest extends WP_UnitTestCase {

	/**
	 * The plugin's patterns as registered.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function registered(): array {
		$out = array();
		foreach ( WP_Block_Patterns_Registry::get_instance()->get_all_registered() as $pattern ) {
			if ( 0 === strpos( (string) $pattern['name'], Patterns::CATEGORY . '/' ) ) {
				$out[ (string) $pattern['name'] ] = $pattern;
			}
		}
		return $out;
	}

	/**
	 * Seven patterns, start patterns for handbook pages only, in their category.
	 *
	 * @return void
	 */
	public function test_seven_start_patterns_for_handbook_pages(): void {
		$patterns = $this->registered();

		$this->assertSame(
			array( 'guide', 'process', 'tool', 'role', 'concept', 'faq', 'area-overview' ),
			array_map( static fn( string $name ): string => substr( $name, strlen( Patterns::CATEGORY ) + 1 ), array_keys( $patterns ) )
		);
		foreach ( $patterns as $name => $pattern ) {
			$this->assertSame( array( Handbook::POST_TYPE ), $pattern['postTypes'], $name );
			$this->assertContains( 'core/post-content', $pattern['blockTypes'], $name );
			$this->assertSame( array( Patterns::CATEGORY ), $pattern['categories'], $name );
			$this->assertNotSame( '', (string) $pattern['title'], $name );
		}
		$this->assertTrue( \WP_Block_Pattern_Categories_Registry::get_instance()->is_registered( Patterns::CATEGORY ) );
	}

	/**
	 * Every block is a known core block, every paragraph and list item is
	 * empty with a placeholder, and every collapsible section is closed.
	 *
	 * @return void
	 */
	public function test_the_patterns_are_an_outline_without_text(): void {
		foreach ( $this->registered() as $name => $pattern ) {
			$blocks = array_values( array_filter( parse_blocks( (string) $pattern['content'] ), static fn( array $b ): bool => null !== $b['blockName'] ) );
			$this->assertNotEmpty( $blocks, $name );

			$walk = function ( array $list ) use ( &$walk, $name ): void {
				foreach ( $list as $block ) {
					if ( null === $block['blockName'] ) {
						continue;
					}
					$this->assertContains( $block['blockName'], array( 'core/paragraph', 'core/heading', 'core/list', 'core/list-item', 'core/details' ), $name );
					if ( in_array( $block['blockName'], array( 'core/paragraph', 'core/list-item' ), true ) ) {
						$this->assertSame( '', trim( wp_strip_all_tags( (string) $block['innerHTML'] ) ), $name . ': no text in ' . $block['blockName'] );
						$this->assertNotSame( '', (string) ( $block['attrs']['placeholder'] ?? '' ), $name . ': a placeholder says what goes there' );
					}
					if ( 'core/details' === $block['blockName'] ) {
						$this->assertStringNotContainsString( ' open', (string) $block['innerHTML'], $name );
					}
					$walk( $block['innerBlocks'] );
				}
			};
			$walk( $blocks );
		}
	}

	/**
	 * The guide has the outline of the writing guide: steps and result in
	 * view, related pages at the end.
	 *
	 * @return void
	 */
	public function test_the_guide_has_steps_result_and_related_pages(): void {
		$content  = (string) $this->registered()[ Patterns::CATEGORY . '/guide' ]['content'];
		$headings = array();
		foreach ( parse_blocks( $content ) as $block ) {
			if ( 'core/heading' === $block['blockName'] ) {
				$headings[] = trim( wp_strip_all_tags( (string) $block['innerHTML'] ) );
			}
		}

		$this->assertSame( array( 'Steps', 'Result', 'Related pages' ), $headings );
		$this->assertStringContainsString( '<ol class="wp-block-list">', $content );
	}

	/**
	 * A placeholder with quotes or an arrow is encoded as core encodes it, so
	 * it cannot end the block comment (see the Mermaid tests of 0.77.0).
	 *
	 * @return void
	 */
	public function test_placeholders_are_encoded_like_core(): void {
		$filter = static fn( $translation, $text ) => 'First step, as an instruction' === $text ? 'Klick --> "Weiter"' : $translation;
		add_filter( 'gettext', $filter, 10, 2 );
		$content = (string) Patterns::all()['guide']['content'];
		remove_filter( 'gettext', $filter, 10 );

		$this->assertStringNotContainsString( '-->"', $content );
		$found = false;
		$walk  = static function ( array $list ) use ( &$walk, &$found ): void {
			foreach ( $list as $block ) {
				if ( 'Klick --> "Weiter"' === ( $block['attrs']['placeholder'] ?? '' ) ) {
					$found = true;
				}
				$walk( $block['innerBlocks'] );
			}
		};
		$walk( parse_blocks( $content ) );
		$this->assertTrue( $found );
	}
}
