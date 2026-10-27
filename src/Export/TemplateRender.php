<?php
/**
 * Render a handbook page for the export the way the site renders it.
 *
 * The export renders the block template, the same
 * `living-handbook//single-handbook` the front end uses, and the edited version
 * of it when there is one. What comes out has the blocks where the site put
 * them, because it is the same markup going through the same renderers. A page
 * built here by hand, in an order decided here, would ignore every change a
 * site made in the Site Editor.
 *
 * Two things have to be true for that to work, and both are the reason this
 * class exists rather than a one-line `do_blocks()`:
 *
 * 1. **The blocks have to believe they are on a page.** Half of them ask
 *    `is_singular()` or read the current post; rendered outside a query they
 *    return nothing at all, silently. A single-post query is set up around the
 *    render and taken down afterwards.
 * 2. **Some blocks have no business in a file.** The theme's header and footer
 *    carry a site navigation that leads nowhere, and feedback, comments and the
 *    typeahead search need a server that is not there. They are cut from the
 *    markup before rendering, rather than rendered and hidden with CSS.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Export;

use LivingHandbook\PostType\Handbook;
use WP_Block_Template;
use WP_Post;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The block template behind a static export.
 */
final class TemplateRender {

	/**
	 * The template the single handbook page uses.
	 */
	private const TEMPLATE = 'single-handbook';

	/**
	 * Blocks that are cut before rendering, by their full name.
	 *
	 * Full names as parse_blocks() reports them: a core block carries the core/
	 * namespace there, although its markup leaves it out (wp:template-part).
	 *
	 * @var array<int, string>
	 */
	private const DROP = array(
		// The theme's header and footer, with a site navigation that leads
		// nowhere once the page is a file.
		'core/template-part',
		// Everything below needs a server to answer it.
		'living-handbook/feedback',
		'living-handbook/search',
		'core/comments',
		'core/post-comments-form',
		'core/comments-query-loop',
	);

	/**
	 * Returns the template markup, with what cannot travel already removed.
	 *
	 * The edited version wins over the one the plugin registers: if somebody
	 * rearranged the page in the Site Editor, that arrangement is the point.
	 *
	 * @return string Empty when no template can be found at all.
	 */
	public static function markup(): string {
		if ( ! function_exists( 'get_block_templates' ) ) {
			return '';
		}

		$templates = array_values( (array) get_block_templates( array( 'slug__in' => array( self::TEMPLATE ) ), 'wp_template' ) );
		$content   = '';
		foreach ( $templates as $template ) {
			if ( ! $template instanceof WP_Block_Template || self::TEMPLATE !== $template->slug ) {
				continue;
			}
			$content = (string) $template->content;
			// A custom source is the edited copy, and it ends the search.
			if ( 'custom' === $template->source ) {
				break;
			}
		}

		if ( '' === $content ) {
			return '';
		}

		return self::without( $content, self::DROP );
	}

	/**
	 * Removes every block with one of the given names from block markup, at any
	 * depth, together with everything inside it.
	 *
	 * The markup is parsed and serialized again rather than cut with a pattern.
	 * Up to 0.75.1 a pattern did it, and the attribute part of a block with
	 * attributes could run past the end of its own comment: a comments block
	 * as the Site Editor writes it took every block up to the next
	 * self-closing one with attributes along with it.
	 *
	 * @param string             $markup Block markup.
	 * @param array<int, string> $names  Full block names, for example core/comments.
	 * @return string
	 */
	private static function without( string $markup, array $names ): string {
		$kept = array();
		foreach ( parse_blocks( $markup ) as $block ) {
			if ( self::is_dropped( $block, $names ) ) {
				continue;
			}
			$kept[] = self::without_inner( $block, $names );
		}

		return serialize_blocks( $kept );
	}

	/**
	 * Removes the dropped blocks from the inner blocks of one parsed block.
	 *
	 * A parsed block marks the place of each inner block in its innerContent
	 * with a null, in order. A removed inner block takes its null with it, or
	 * the serializer would put the next block in its place.
	 *
	 * @param array<string, mixed> $block A parsed block.
	 * @param array<int, string>   $names Full block names to remove.
	 * @return array<string, mixed>
	 */
	private static function without_inner( array $block, array $names ): array {
		$inner = isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ? array_values( $block['innerBlocks'] ) : array();
		if ( array() === $inner ) {
			return $block;
		}

		$blocks  = array();
		$content = array();
		$next    = 0;
		foreach ( (array) ( $block['innerContent'] ?? array() ) as $chunk ) {
			if ( null !== $chunk ) {
				$content[] = $chunk;
				continue;
			}
			$child = $inner[ $next ] ?? null;
			++$next;
			if ( ! is_array( $child ) || self::is_dropped( $child, $names ) ) {
				continue;
			}
			$blocks[]  = self::without_inner( $child, $names );
			$content[] = null;
		}

		$block['innerBlocks']  = $blocks;
		$block['innerContent'] = $content;

		return $block;
	}

	/**
	 * Whether a parsed block is one of the blocks to remove.
	 *
	 * @param array<string, mixed> $block A parsed block.
	 * @param array<int, string>   $names Full block names to remove.
	 * @return bool
	 */
	private static function is_dropped( array $block, array $names ): bool {
		return in_array( (string) ( $block['blockName'] ?? '' ), $names, true );
	}

	/**
	 * Renders one page through the template.
	 *
	 * @param WP_Post $post   The page.
	 * @param string  $markup The prepared template markup.
	 * @return string Empty when there is nothing to render, so the caller can fall back.
	 */
	public static function render( WP_Post $post, string $markup ): string {
		if ( '' === trim( $markup ) ) {
			return '';
		}

		$previous_query = $GLOBALS['wp_query'] ?? null;
		$previous_the   = $GLOBALS['wp_the_query'] ?? null;
		$previous_post  = $GLOBALS['post'] ?? null;

		// A real query for this one page, and it is the main query while it runs:
		// is_singular(), in_the_loop() and is_main_query() all have to answer yes,
		// or the navigation, the table of contents, the badges and the metadata
		// footer render as empty strings and nobody finds out until the file is
		// opened.
		$query = new WP_Query(
			array(
				'p'              => (int) $post->ID,
				'post_type'      => Handbook::POST_TYPE,
				'posts_per_page' => 1,
				'no_found_rows'  => true,
			)
		);

		$GLOBALS['wp_query']     = $query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored below.
		$GLOBALS['wp_the_query'] = $query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored below.

		$html = '';
		if ( $query->have_posts() ) {
			$query->the_post();
			$html = do_blocks( $markup );
			wp_reset_postdata();
		}

		$GLOBALS['wp_query']     = $previous_query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restoring what was there.
		$GLOBALS['wp_the_query'] = $previous_the; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restoring what was there.
		$GLOBALS['post']         = $previous_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restoring what was there.

		return $html;
	}

	/**
	 * Returns the layout rules WordPress collected while rendering.
	 *
	 * Block supports (the flex rules behind a columns block, gaps, and so on) are
	 * not in any stylesheet: the style engine gathers them per request and the
	 * page prints them inline. An export that skipped them would put its columns
	 * underneath each other.
	 *
	 * @return string
	 */
	public static function block_support_css(): string {
		if ( ! function_exists( 'wp_style_engine_get_stylesheet_from_context' ) ) {
			return '';
		}

		return (string) wp_style_engine_get_stylesheet_from_context( 'block-supports', array( 'optimize' => true ) );
	}
}
