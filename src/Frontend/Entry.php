<?php
/**
 * The handbook overview (chooser), the per-handbook entry page, and the
 * handbook menu.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Frontend;

use LivingHandbook\Access\AccessController;
use LivingHandbook\Handbook\Handbooks;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the landing views: the overview of readable handbooks, the entry page
 * of one handbook (search, filters, areas, recently updated), and a compact
 * menu of the handbooks the current user may read.
 */
final class Entry {

	/**
	 * Render the overview: a card per handbook the current user may read.
	 *
	 * A handbook that has handbooks of its own shows them set in below it. The
	 * grouping taxonomy was hierarchical from the start and nothing read it, so a
	 * structure someone built was invisible until now.
	 *
	 * @param string $display 'cards' (grid) or 'list' (single column).
	 * @param int    $preview How many page titles to list under each card; 0 for none.
	 * @return string
	 */
	public static function render_chooser( string $display = 'cards', int $preview = 0 ): string {
		$readable  = AccessController::readable_terms( get_current_user_id() );
		$by_parent = self::by_parent( $readable );
		$names     = array();
		foreach ( $readable as $term ) {
			$names[ (int) $term->term_id ] = $term->name;
		}

		$cards = '';
		foreach ( $by_parent[0] ?? array() as $term ) {
			$cards .= Cards::handbook_card( $term, $preview );

			$children = $by_parent[ (int) $term->term_id ] ?? array();
			if ( array() === $children ) {
				continue;
			}

			// The children of a handbook are set in, so the overview says which
			// handbook they belong to by where they stand rather than by repeating
			// the name on every card.
			$cards .= '<div class="living-handbook-cards living-handbook-cards--children">';
			foreach ( $children as $child ) {
				$cards .= Cards::handbook_card( $child, $preview, $names[ (int) $child->parent ] ?? '' );
			}
			$cards .= '</div>';
		}

		if ( '' === $cards ) {
			return '<p class="living-handbook-empty">' . esc_html__( 'No handbooks available.', 'living-handbook' ) . '</p>';
		}
		$modifier = 'list' === $display ? ' living-handbook-overview--list' : '';
		return '<div class="living-handbook-overview' . $modifier . '"><div class="living-handbook-cards living-handbook-cards--books">' . $cards . '</div></div>';
	}

	/**
	 * Group readable handbooks by the handbook they belong to.
	 *
	 * The grouping taxonomy has been hierarchical since it was registered, so a
	 * handbook could always have a parent; nothing read it, and the overview drew
	 * a flat list whatever the structure said. This is what reads it.
	 *
	 * Three decisions are built into these few lines, and they are the reason it
	 * is not a one-liner:
	 *
	 * 1. Access is not inherited. Every handbook decides for itself who may read
	 *    it, and a parent's rule says nothing about its children. Only readable
	 *    handbooks are in front of us here, which is what makes that true.
	 * 2. A child whose parent the visitor may not see is not hidden with it. It
	 *    moves up to the top level instead, because the alternative is a page
	 *    that exists, is readable, and cannot be reached.
	 * 3. Order inside a level is the name. A sort field of their own can come
	 *    when someone asks for one.
	 *
	 * @param array<int, WP_Term> $terms The handbooks the visitor may read.
	 * @return array<int, array<int, WP_Term>> Keyed by parent id, 0 for the top level.
	 */
	private static function by_parent( array $terms ): array {
		$known = array();
		foreach ( $terms as $term ) {
			$known[ (int) $term->term_id ] = true;
		}

		$out = array();
		foreach ( $terms as $term ) {
			$parent = (int) $term->parent;
			if ( $parent > 0 && ! isset( $known[ $parent ] ) ) {
				$parent = 0;
			}
			$out[ $parent ][] = $term;
		}

		foreach ( $out as &$level ) {
			usort(
				$level,
				static function ( WP_Term $a, WP_Term $b ): int {
					return strnatcasecmp( $a->name, $b->name );
				}
			);
		}
		unset( $level );

		return $out;
	}

	/**
	 * The handbooks below one handbook that the visitor may read.
	 *
	 * @param int $term_id Parent handbook id.
	 * @return array<int, WP_Term>
	 */
	public static function readable_children( int $term_id ): array {
		$by_parent = self::by_parent( AccessController::readable_terms( get_current_user_id() ) );

		return $by_parent[ $term_id ] ?? array();
	}

	/**
	 * Render a compact menu of the handbooks the current user may read, for use
	 * in a site header or navigation area. On narrow screens the list collapses
	 * behind a toggle button, so the block works on mobile like a menu.
	 *
	 * @return string
	 */
	public static function render_menu(): string {
		$by_parent = self::by_parent( AccessController::readable_terms( get_current_user_id() ) );
		$items     = self::menu_level( $by_parent, 0 );

		if ( '' === $items ) {
			return '';
		}

		$list_id = wp_unique_id( 'living-handbook-menu-' );
		$label   = esc_html__( 'Handbooks', 'living-handbook' );

		return '<nav class="living-handbook-menu" aria-label="' . esc_attr__( 'Handbooks', 'living-handbook' ) . '">'
			. '<button type="button" class="living-handbook-menu__toggle" aria-expanded="false" aria-controls="' . esc_attr( $list_id ) . '">' . $label . '</button>'
			. '<ul id="' . esc_attr( $list_id ) . '" class="living-handbook-menu__list">' . $items . '</ul>'
			. '</nav>';
	}

	/**
	 * One level of the handbook menu and the levels below it.
	 *
	 * @param array<int, array<int, WP_Term>> $by_parent Handbooks grouped by parent.
	 * @param int                             $above     Id of the handbook whose children to render.
	 * @return string
	 */
	private static function menu_level( array $by_parent, int $above ): string {
		$items = '';
		foreach ( $by_parent[ $above ] ?? array() as $term ) {
			$link = get_term_link( $term );
			if ( is_wp_error( $link ) ) {
				continue;
			}

			$children = self::menu_level( $by_parent, (int) $term->term_id );
			$sub      = '' !== $children ? '<ul class="living-handbook-menu__sublist">' . $children . '</ul>' : '';

			$items .= '<li class="living-handbook-menu__item"><a class="living-handbook-menu__link" href="' . esc_url( (string) $link ) . '">' . esc_html( $term->name ) . '</a>' . $sub . '</li>';
		}

		return $items;
	}

	/**
	 * Render the entry page of one handbook.
	 *
	 * The result column, and only that: either the filtered result list (when a
	 * search or facet is active) or the areas and recently updated pages. The
	 * search bar and the filter bar are blocks of their own since 0.66.0, so a
	 * template places them where it wants instead of taking the layout this used
	 * to draw around them.
	 *
	 * The wrapper carries the handbook term id, which is what the search bar and
	 * the filter bar look for when they filter through the REST route, wherever
	 * on the page they sit. The status line announces the result count after an
	 * update, and sits outside the column so replacing the list does not replace
	 * the region that announces it.
	 *
	 * @param WP_Term $term    Handbook term.
	 * @param string  $display 'cards' (grid) or 'list' (single column).
	 * @return string
	 */
	public static function render_entry( WP_Term $term, string $display = 'cards' ): string {
		$selections = Filters::current_selections();
		$search     = Filters::search_value();
		$paged      = Filters::current_paged();

		$modifier = 'list' === $display ? ' living-handbook-entry--list' : '';
		$out      = '<div class="living-handbook-entry' . $modifier . '" data-term-id="' . esc_attr( (string) $term->term_id ) . '">';
		// A status line, not a live list. The main column holds up to two dozen
		// cards, and announcing all of them after every keystroke is unusable. The
		// status line sits outside the column, so replacing the list does not
		// replace the region that announces it, and it carries the one sentence
		// the list already shows: how many pages were found.
		$out .= '<p class="living-handbook-visually-hidden living-handbook-entry__status" role="status"></p>';
		$out .= '<div class="living-handbook-main">';
		$out .= self::main_body( $term, $selections, $search, $paged );

		return $out . '</div></div>';
	}

	/**
	 * The main-column content of a handbook entry: the filtered result list when
	 * a search or facet is active, otherwise the areas and recently updated
	 * pages. Shared by the server render and the REST filter route.
	 *
	 * @param WP_Term                 $term       Handbook term.
	 * @param array<string, string[]> $selections Facet selections (parameter to slugs).
	 * @param string                  $search     Search term.
	 * @param int                     $paged      Page number (1-based).
	 * @return string
	 */
	public static function main_body( WP_Term $term, array $selections, string $search, int $paged ): string {
		if ( Filters::is_active( $selections, $search ) ) {
			return Filters::filtered_results( $term, $selections, $search, $paged );
		}
		return self::default_body( $term->term_id );
	}

	/**
	 * The unfiltered entry body: areas and recently updated pages.
	 *
	 * @param int $term_id Handbook term ID.
	 * @return string
	 */
	private static function default_body( int $term_id ): string {
		$body = '';

		$areas = Cards::areas( $term_id );
		if ( '' !== $areas ) {
			$body .= '<h2 class="living-handbook-entry__h">' . esc_html__( 'Areas', 'living-handbook' ) . '</h2>' . $areas;
		}

		$recent = Cards::page_grid( $term_id, 6 );
		if ( '' !== $recent ) {
			$body .= '<h2 class="living-handbook-entry__h">' . esc_html__( 'Recently updated', 'living-handbook' ) . '</h2>' . $recent;
		}

		if ( '' === $body ) {
			// A handbook that holds other handbooks rather than pages of its own is
			// not empty, it is a level. Saying "no pages yet" there sends a visitor
			// away from the very list they were looking for.
			$children = self::readable_children( $term_id );
			if ( array() !== $children ) {
				$cards = '';
				foreach ( $children as $child ) {
					$cards .= Cards::handbook_card( $child );
				}

				return '<h2 class="living-handbook-entry__h">' . esc_html__( 'Handbooks', 'living-handbook' ) . '</h2>'
					. '<div class="living-handbook-cards living-handbook-cards--books">' . $cards . '</div>';
			}

			$empty = '<p class="living-handbook-empty">' . esc_html__( 'This handbook has no pages yet.', 'living-handbook' );
			// A guest may simply be missing member-only pages, so offer a login.
			if ( ! is_user_logged_in() ) {
				$link     = get_term_link( $term_id, Handbooks::TAXONOMY );
				$redirect = is_wp_error( $link ) ? '' : (string) $link;
				$empty   .= ' <a href="' . esc_url( wp_login_url( $redirect ) ) . '">' . esc_html__( 'Log in to see more.', 'living-handbook' ) . '</a>';
			}
			return $empty . '</p>';
		}
		return $body;
	}
}
