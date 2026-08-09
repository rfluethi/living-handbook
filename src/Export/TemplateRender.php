<?php
/**
 * Render a handbook page for the export the way the site renders it.
 *
 * The first version of the export built its pages by hand: breadcrumb, title,
 * badges, table of contents, content, metadata footer, in that order, decided
 * here. Which meant a site that had moved those blocks around in the Site
 * Editor got an export that ignored every one of those decisions. Rico put it
 * plainly, and he was right.
 *
 * So the export renders the block template instead, the same
 * `living-handbook//single-handbook` the front end uses, and the edited version
 * of it when there is one. What comes out has the blocks where the site put
 * them, because it is the same markup going through the same renderers.
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
	 * Blocks that are cut before rendering, and why.
	 *
	 * @var array<int, string>
	 */
	private const DROP = array(
		// A site header and footer: menus, a search form and a login link, all
		// pointing at a site the reader of this file may not have access to.
		'core/template-part',
		// Everything below needs a server to answer it.
		'living-handbook/feedback',
		'living-handbook/search',
		'core/comments',
		'core/post-comments-form',
		'core/comments-query-loop',
	);

	/**
	 * The template markup, with what cannot travel already removed.
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

		foreach ( self::DROP as $block ) {
			$content = self::without( $content, $block );
		}

		return $content;
	}

	/**
	 * Remove every occurrence of one block from block markup.
	 *
	 * Both spellings: the self-closing form a block without inner content uses,
	 * and the pair around inner blocks.
	 *
	 * @param string $markup Block markup.
	 * @param string $block  Block name, for example core/comments.
	 * @return string
	 */
	private static function without( string $markup, string $block ): string {
		$name = preg_quote( $block, '#' );

		$markup = (string) preg_replace( '#<!--\s+wp:' . $name . '(\s+\{.*?\})?\s+/-->#s', '', $markup );
		$markup = (string) preg_replace( '#<!--\s+wp:' . $name . '(\s+\{.*?\})?\s+-->.*?<!--\s+/wp:' . $name . '\s+-->#s', '', $markup );

		return $markup;
	}

	/**
	 * Render one page through the template.
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
	 * The layout rules WordPress collected while rendering.
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
