<?php
/**
 * The two block templates the plugin ships.
 *
 * They decide what a fresh installation looks like, and they are strings of
 * block markup: nothing checks them at runtime. A misspelt block name renders
 * as nothing, a broken block comment swallows everything after it, and neither
 * says a word. That is what these tests are for.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Frontend\Navigation;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use WP_Block_Templates_Registry;
use WP_Block_Type_Registry;
use WP_UnitTestCase;

/**
 * The shipped templates.
 */
final class BlockTemplatesTest extends WP_UnitTestCase {

	/**
	 * The template of the handbook entry page.
	 */
	private const ENTRY = 'living-handbook//taxonomy-handbook_set';

	/**
	 * The template of a single handbook page.
	 */
	private const SINGLE = 'living-handbook//single-handbook';

	/**
	 * Read a registered template's block markup.
	 *
	 * @param string $name Template name, including the plugin namespace.
	 * @return string
	 */
	private function content( string $name ): string {
		$template = WP_Block_Templates_Registry::get_instance()->get_registered( $name );
		$this->assertNotNull( $template, $name . ' is not registered.' );

		return (string) $template->content;
	}

	/**
	 * Collect every block name in a markup string, nested ones included.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @return array<int, string>
	 */
	private function names( array $blocks ): array {
		$names = array();

		foreach ( $blocks as $block ) {
			if ( is_string( $block['blockName'] ) && '' !== $block['blockName'] ) {
				$names[] = $block['blockName'];
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$names = array_merge( $names, $this->names( $block['innerBlocks'] ) );
			}
		}

		return $names;
	}

	/**
	 * Both templates are registered, under the names the taxonomy and the post
	 * type give them. A renamed taxonomy would silently leave the entry page on
	 * the theme's fallback template.
	 *
	 * @return void
	 */
	public function test_both_templates_are_registered(): void {
		$this->assertSame( 'taxonomy-handbook_set', 'taxonomy-' . Handbooks::TAXONOMY );

		$registry = WP_Block_Templates_Registry::get_instance();
		$this->assertTrue( $registry->is_registered( self::ENTRY ) );
		$this->assertTrue( $registry->is_registered( self::SINGLE ) );
	}

	/**
	 * The markup is well formed: parsing and serialising it again returns the
	 * same string. An unbalanced block comment shows up here and nowhere else.
	 *
	 * @return void
	 */
	public function test_the_markup_survives_a_parse_and_serialise_round_trip(): void {
		foreach ( array( self::ENTRY, self::SINGLE ) as $name ) {
			$content = $this->content( $name );
			$this->assertSame( $content, serialize_blocks( parse_blocks( $content ) ), $name );
		}
	}

	/**
	 * Every block the templates name really exists. This is the failure that
	 * costs the most and shows the least: an unknown block renders as nothing.
	 *
	 * @return void
	 */
	public function test_every_block_in_the_templates_is_registered(): void {
		$registry = WP_Block_Type_Registry::get_instance();

		foreach ( array( self::ENTRY, self::SINGLE ) as $name ) {
			foreach ( $this->names( parse_blocks( $this->content( $name ) ) ) as $block ) {
				$this->assertNotNull( $registry->get_registered( $block ), $block . ' in ' . $name );
			}
		}
	}

	/**
	 * The entry template holds the navigation and the entry block, inside the
	 * theme's header and footer.
	 *
	 * @return void
	 */
	public function test_the_entry_template_holds_the_navigation_and_the_entry(): void {
		$names = $this->names( parse_blocks( $this->content( self::ENTRY ) ) );

		foreach ( array( 'core/template-part', 'core/query-title', 'core/term-description', 'living-handbook/navigation', 'living-handbook/entry' ) as $block ) {
			$this->assertContains( $block, $names );
		}
	}

	/**
	 * The single template holds the page itself and everything about it, in the
	 * order the layout puts them: title, content, then the block at the foot.
	 *
	 * @return void
	 */
	public function test_the_single_template_holds_the_page_and_what_belongs_to_it(): void {
		$names = $this->names( parse_blocks( $this->content( self::SINGLE ) ) );

		foreach ( array( 'living-handbook/navigation', 'living-handbook/search', 'core/post-title', 'core/post-content', 'living-handbook/feedback', 'living-handbook/git-source-note', 'living-handbook/badges', 'living-handbook/pagemeta', 'living-handbook/toc' ) as $block ) {
			$this->assertContains( $block, $names );
		}

		$order = array_values(
			array_filter(
				$names,
				static fn( string $block ): bool => in_array( $block, array( 'core/post-title', 'core/post-content', 'living-handbook/feedback', 'living-handbook/badges', 'living-handbook/pagemeta' ), true )
			)
		);

		$this->assertSame(
			array( 'core/post-title', 'core/post-content', 'living-handbook/feedback', 'living-handbook/badges', 'living-handbook/pagemeta' ),
			$order
		);
	}

	/**
	 * The single template carries both Tables of Contents, the mobile one above
	 * the content and the desktop one in its own column. They are hidden by
	 * complementary media queries, so exactly one is ever visible; shipping only
	 * one of them loses the other width.
	 *
	 * @return void
	 */
	public function test_the_single_template_carries_both_tables_of_contents(): void {
		$blocks   = parse_blocks( $this->content( self::SINGLE ) );
		$variants = array();

		$walk = static function ( array $list ) use ( &$walk, &$variants ): void {
			foreach ( $list as $block ) {
				if ( 'living-handbook/toc' === $block['blockName'] ) {
					$variants[] = $block['attrs']['variant'] ?? 'desktop';
				}

				if ( ! empty( $block['innerBlocks'] ) ) {
					$walk( $block['innerBlocks'] );
				}
			}
		};

		$walk( $blocks );

		sort( $variants );
		$this->assertSame( array( 'desktop', 'mobile' ), $variants );
	}

	/**
	 * Both templates ask the navigation for the accordion, which is the display
	 * that survives a deep handbook in a narrow column. "accordion" has to be a
	 * value the block knows: a typo would silently fall back to the menu.
	 *
	 * @return void
	 */
	public function test_the_navigation_ships_as_an_accordion(): void {
		foreach ( array( self::ENTRY, self::SINGLE ) as $name ) {
			$found = false;

			$walk = static function ( array $list ) use ( &$walk, &$found ): void {
				foreach ( $list as $block ) {
					if ( 'living-handbook/navigation' === $block['blockName'] ) {
						$found = 'accordion' === ( $block['attrs']['variant'] ?? '' );
					}

					if ( ! empty( $block['innerBlocks'] ) ) {
						$walk( $block['innerBlocks'] );
					}
				}
			};

			$walk( parse_blocks( $this->content( $name ) ) );
			$this->assertTrue( $found, 'The navigation of ' . $name . ' is not an accordion.' );
		}

		// And the value really is one the block knows: the renderer compares it
		// literally, so anything it does not recognise falls back to the menu
		// without saying so.
		$term = self::factory()->term->create_and_get( array( 'taxonomy' => Handbooks::TAXONOMY ) );
		update_term_meta( (int) $term->term_id, Handbooks::META_VISIBILITY, 'public' );
		$page = self::factory()->post->create(
			array(
				'post_type'   => Handbook::POST_TYPE,
				'post_title'  => 'Start',
				'post_status' => 'publish',
			)
		);
		wp_set_object_terms( $page, array( (int) $term->term_id ), Handbooks::TAXONOMY );

		$this->assertStringContainsString( 'living-handbook-nav--accordion', Navigation::render( (int) $term->term_id, 'accordion' ) );
		$this->assertStringContainsString( 'living-handbook-nav--tree', Navigation::render( (int) $term->term_id, 'accordeon' ) );
	}
}
