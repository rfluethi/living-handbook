<?php
/**
 * Per-handbook sidebar navigation.
 *
 * Builds the page tree of one handbook as a self-contained, collapsible list
 * with its own accordion behaviour, so the navigation works on its own and does
 * not depend on any other plugin.
 *
 * The title row is built exactly like every other row that has children: a
 * toggle on the left opens and closes the whole navigation, and the handbook
 * name beside it is an ordinary link to the handbook's start page. That is a
 * change from the first version, which made the whole block a native <details>
 * with the title as its <summary>. A <summary> can toggle or it can link, not
 * both dependably, so the way to the start page had to hang beside it as a
 * small arrow, and testers did not read that arrow as a way anywhere. The
 * toggle is now a button like the others, which costs a few lines of script and
 * makes the title behave the way every other entry in the list behaves.
 *
 * The two display variants:
 * - "sidebar" (Menu): the whole tree is shown, nothing collapses.
 * - "accordion": each branch with children collapses; the branch leading to the
 *   current page starts open, the rest closed, and a toggle on the left of the
 *   branch opens or closes it. Only once the script has marked the navigation
 *   (living-handbook-nav--js): without it the toggles cannot do anything, so
 *   they are hidden and every branch is shown, as in the "sidebar" variant.
 *   Up to 0.75.1 the closed branches were hidden by the stylesheet alone, and a
 *   reader without the script never reached a page outside the current branch.
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
	 * Option holding the shared cache version of the frontend.
	 *
	 * The name says navigation for historical reasons; what reads it is the area
	 * cards cache in Cards::areas_cache_key() and the handbook previews on the
	 * overview in Cards::preview_cache_key(). The navigation itself is not
	 * cached, and deliberately so: its markup carries the current page and its
	 * open branches, so an entry would be needed per page and per viewer, and on
	 * a large handbook that is a cache that never hits and never stops growing.
	 * What the navigation costs is the query behind it, not the markup: measured
	 * on 2000 pages, 327 of 470 milliseconds are the query, and that cost is
	 * shared with everything else that reads the handbook.
	 */
	public const CACHE_VERSION_OPTION = 'living_handbook_nav_version';

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

		// The whole handbook at once; the tree is built from the map. Read before
		// the ancestors of the current page, which the map has just put into the
		// cache, so they cost nothing more.
		$map = PageTree::children_map( $term_id );

		$current  = self::current_post_id();
		$open_ids = $current > 0 ? array_map( 'intval', get_post_ancestors( $current ) ) : array();
		if ( $current > 0 ) {
			$open_ids[] = $current;
		}

		$tree = self::branch( 0, $map, $current, $open_ids );
		if ( '' === $tree ) {
			return '';
		}

		$accordion = 'accordion' === $variant;
		$classes   = 'living-handbook-nav ' . ( $accordion ? 'living-handbook-nav--accordion' : 'living-handbook-nav--tree' );

		// The title row: the same shape as any row with children, a toggle and a
		// link. The toggle carries aria-controls and aria-expanded so that what
		// it opens is named rather than implied; the link goes to the handbook's
		// start page, which is what a reader expects of a title in a list of
		// links. Open by default; the frontend script collapses it on narrow
		// screens.
		$panel = wp_unique_id( 'living-handbook-nav-' );
		$title = esc_html( $term->name );
		$entry = get_term_link( $term );

		$label = '' === $title ? '' : $title;
		if ( ! is_wp_error( $entry ) ) {
			$label = '<a href="' . esc_url( (string) $entry ) . '">' . $title . '</a>';
		}

		$out  = '<div class="' . esc_attr( $classes ) . '">';
		$out .= '<div class="living-handbook-nav__row living-handbook-nav__top">';
		$out .= sprintf(
			'<button type="button" class="living-handbook-nav__toggle living-handbook-nav__toggle--all" aria-expanded="true" aria-controls="%1$s" aria-label="%2$s"><span aria-hidden="true"></span></button>',
			esc_attr( $panel ),
			/* translators: %s: handbook name. */
			esc_attr( sprintf( __( 'Toggle %s', 'living-handbook' ), $term->name ) )
		);
		$out .= $label;
		$out .= '</div>';
		$out .= '<nav id="' . esc_attr( $panel ) . '" aria-label="' . esc_attr( $term->name ) . '">';
		$out .= '<ul class="living-handbook-nav__list">' . $tree . '</ul>';
		$out .= '</nav>';
		$out .= '</div>';

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
	 * Bump the shared cache version, which retires every cached area card list
	 * at once. Hooked to the events that change a handbook: saving, deleting or
	 * restoring a page, and changes to the handbook terms.
	 *
	 * @return void
	 */
	public static function invalidate(): void {
		update_option( self::CACHE_VERSION_OPTION, (int) get_option( self::CACHE_VERSION_OPTION, 0 ) + 1 );
	}

	/**
	 * Bumps the cache version only when the changed post is a handbook page.
	 *
	 * The generic trashed_post, untrashed_post and deleted_post hooks fire for
	 * every post type, and an unrelated post must not retire the cache.
	 *
	 * @param int          $post_id The changed post ID.
	 * @param WP_Post|null $post    The post, when the hook passes it. deleted_post
	 *                              runs after the row is gone, so the type can
	 *                              only be read from this object there.
	 * @return void
	 */
	public static function invalidate_for_post( int $post_id, $post = null ): void {
		$type = $post instanceof WP_Post ? $post->post_type : get_post_type( $post_id );
		if ( Handbook::POST_TYPE === $type ) {
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
		return self::render( Handbooks::for_post( $post_id ), $variant );
	}

	/**
	 * Recursively build the list markup for one branch of the tree.
	 *
	 * On the question of a cycle in post_parent, asked by the 0.52.0 review and
	 * answered here so nobody has to ask twice: it cannot loop. A page has one
	 * parent, so it appears exactly once in the map, under that parent. A cycle
	 * (A is the parent of B and B the parent of A) is therefore a component with
	 * no path from the root, and this walk starts at the root. Such pages are
	 * never rendered, which is its own kind of wrong, but it is not a recursion.
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
			$sublist      = '';
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
				// The toggle names the list it opens, like the title toggle does.
				// Up to 0.75.1 it said whether it was expanded but not what, and
				// the list had no id to point at.
				$sublist = wp_unique_id( 'living-handbook-nav-branch-' );
				$row    .= sprintf(
					'<button type="button" class="living-handbook-nav__toggle" aria-expanded="%1$s" aria-controls="%3$s" aria-label="%2$s"><span aria-hidden="true"></span></button>',
					$is_open ? 'true' : 'false',
					/* translators: %s: page title. */
					esc_attr( sprintf( __( 'Toggle %s', 'living-handbook' ), $title ) ),
					esc_attr( $sublist )
				);
			} else {
				$row .= '<span class="living-handbook-nav__spacer" aria-hidden="true"></span>';
			}
			$row .= '<a href="' . esc_url( (string) get_permalink( $post ) ) . '"' . ( $pid === $current ? ' aria-current="page"' : '' ) . '>' . esc_html( $title ) . '</a>';
			$row .= '</div>';

			$item = '<li class="' . esc_attr( implode( ' ', $classes ) ) . '">' . $row;
			if ( $has_children ) {
				$item .= '<ul class="living-handbook-nav__sublist" id="' . esc_attr( $sublist ) . '">' . $children . '</ul>';
			}
			$item .= '</li>';
			$out  .= $item;
		}
		return $out;
	}
}
