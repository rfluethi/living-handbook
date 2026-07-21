<?php
/**
 * Per-handbook sidebar navigation.
 *
 * Builds the page tree of one handbook as a self-contained, collapsible list
 * with its own accordion behaviour, so the navigation works on its own and does
 * not depend on any other plugin. The whole block is a native <details> whose
 * <summary> is the handbook title: clicking the title opens or closes the entire
 * navigation, exactly like the on-this-page table of contents, and it works the
 * same on desktop and on narrow screens (the frontend script starts it collapsed
 * on small viewports). A small arrow next to the title links to the handbook
 * start page.
 *
 * The two display variants:
 * - "sidebar" (Menu): the whole tree is shown, nothing collapses.
 * - "accordion": each branch with children collapses; the branch leading to the
 *   current page starts open, the rest closed, and a toggle on the left of the
 *   branch opens or closes it.
 *
 * The styling and the toggle behaviour ship with the plugin (frontend.css and
 * frontend.js); the VSN plugin is no longer required.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Frontend;

use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use WP_Post;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the self-contained navigation for a handbook.
 */
final class Navigation {

	/**
	 * Option holding the cache version; kept so the hook wiring stays valid and
	 * a future cache can key off it. The current renderer builds fresh, so the
	 * value is only bumped, not read.
	 */
	private const CACHE_VERSION_OPTION = 'living_handbook_nav_version';

	/**
	 * Render the sidebar navigation for a handbook.
	 *
	 * @param int    $term_id Handbook term ID.
	 * @param string $variant Either 'sidebar' (menu) or 'accordion'.
	 * @return string
	 */
	public static function render( int $term_id, string $variant = 'sidebar' ): string {
		if ( $term_id <= 0 ) {
			return '';
		}
		$term = get_term( $term_id );
		if ( ! $term instanceof WP_Term ) {
			return '';
		}

		$current  = self::current_post_id();
		$open_ids = $current > 0 ? array_map( 'intval', get_post_ancestors( $current ) ) : array();
		if ( $current > 0 ) {
			$open_ids[] = $current;
		}

		// One query for the whole handbook; the tree is built from the map.
		$map  = PageTree::children_map( $term_id );
		$tree = self::branch( 0, $map, $current, $open_ids );
		if ( '' === $tree ) {
			return '';
		}

		$accordion = 'accordion' === $variant;
		$classes   = 'living-handbook-nav ' . ( $accordion ? 'living-handbook-nav--accordion' : 'living-handbook-nav--tree' );

		// A small arrow next to the title links back to the handbook start page.
		// The title itself only opens or closes the navigation.
		$home  = '';
		$entry = get_term_link( $term );
		if ( ! is_wp_error( $entry ) ) {
			$home = sprintf(
				'<a class="living-handbook-nav__home" href="%1$s" aria-label="%2$s"><svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M13 8H5"/><path d="M8 4l-4 4 4 4"/></svg></a>',
				esc_url( (string) $entry ),
				esc_attr__( 'Open the handbook start page', 'living-handbook' )
			);
		}

		// The whole navigation is a native <details>, open by default; the
		// frontend script collapses it on narrow screens. The <summary> is the
		// handbook title and toggles the block, like the table of contents.
		$out  = '<details class="' . esc_attr( $classes ) . '" open>';
		$out .= '<summary class="living-handbook-nav__top">' . $home . esc_html( $term->name ) . '</summary>';
		$out .= '<nav aria-label="' . esc_attr( $term->name ) . '">';
		$out .= '<ul class="living-handbook-nav__list">' . $tree . '</ul>';
		$out .= '</nav>';
		$out .= '</details>';

		return '<div class="living-handbook-navwrap">' . $out . '</div>';
	}

	/**
	 * The current single handbook page ID, or 0 when not on one.
	 *
	 * @return int
	 */
	private static function current_post_id(): int {
		if ( ! is_singular( Handbook::POST_TYPE ) ) {
			return 0;
		}
		$id = get_the_ID();
		return false !== $id ? (int) $id : 0;
	}

	/**
	 * Invalidate any cached navigation. The renderer builds fresh, so this only
	 * bumps the version counter, kept for the hook wiring and a future cache.
	 *
	 * @return void
	 */
	public static function invalidate(): void {
		update_option( self::CACHE_VERSION_OPTION, (int) get_option( self::CACHE_VERSION_OPTION, 0 ) + 1 );
	}

	/**
	 * Invalidate only when the changed post is a handbook page, so the generic
	 * trashed_post and untrashed_post hooks (which fire for every post type) do
	 * not bump the cache on unrelated content.
	 *
	 * @param int $post_id The changed post ID.
	 * @return void
	 */
	public static function invalidate_for_post( int $post_id ): void {
		if ( Handbook::POST_TYPE === get_post_type( $post_id ) ) {
			self::invalidate();
		}
	}

	/**
	 * Render the navigation for the handbook a page belongs to.
	 *
	 * @param int    $post_id Current post ID.
	 * @param string $variant Either 'sidebar' (menu) or 'accordion'.
	 * @return string
	 */
	public static function render_for_post( int $post_id, string $variant = 'sidebar' ): string {
		if ( $post_id <= 0 ) {
			return '';
		}
		$terms   = wp_get_object_terms( $post_id, Handbooks::TAXONOMY, array( 'fields' => 'ids' ) );
		$term_id = ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? (int) $terms[0] : 0;
		return self::render( $term_id, $variant );
	}

	/**
	 * Recursively build the list markup for one branch of the tree.
	 *
	 * @param int                             $parent_id Parent post ID (0 for the top level).
	 * @param array<int, array<int, WP_Post>> $map       Parent-to-children map from PageTree.
	 * @param int                             $current   The current page ID (0 when not on a page).
	 * @param int[]                           $open_ids  Page IDs on the path to the current page.
	 * @return string
	 */
	private static function branch( int $parent_id, array $map, int $current, array $open_ids ): string {
		$posts = $map[ $parent_id ] ?? array();

		$out = '';
		foreach ( $posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$pid          = (int) $post->ID;
			$children     = self::branch( $pid, $map, $current, $open_ids );
			$has_children = '' !== $children;
			$is_open      = $has_children && in_array( $pid, $open_ids, true );
			$title        = get_the_title( $post );

			$classes = array( 'living-handbook-nav__item' );
			if ( $has_children ) {
				$classes[] = 'has-children';
			}
			if ( $pid === $current ) {
				$classes[] = 'is-current';
			}
			if ( $is_open ) {
				$classes[] = 'is-open';
			}

			// The row holds an optional toggle on the left, then the page link.
			// Leaves get a spacer so their labels line up with branch labels.
			$row = '<div class="living-handbook-nav__row">';
			if ( $has_children ) {
				$row .= sprintf(
					'<button type="button" class="living-handbook-nav__toggle" aria-expanded="%1$s" aria-label="%2$s"><span aria-hidden="true"></span></button>',
					$is_open ? 'true' : 'false',
					/* translators: %s: page title. */
					esc_attr( sprintf( __( 'Toggle %s', 'living-handbook' ), $title ) )
				);
			} else {
				$row .= '<span class="living-handbook-nav__spacer" aria-hidden="true"></span>';
			}
			$row .= '<a href="' . esc_url( (string) get_permalink( $post ) ) . '"' . ( $pid === $current ? ' aria-current="page"' : '' ) . '>' . esc_html( $title ) . '</a>';
			$row .= '</div>';

			$item = '<li class="' . esc_attr( implode( ' ', $classes ) ) . '">' . $row;
			if ( $has_children ) {
				$item .= '<ul class="living-handbook-nav__sublist">' . $children . '</ul>';
			}
			$item .= '</li>';
			$out  .= $item;
		}
		return $out;
	}
}
