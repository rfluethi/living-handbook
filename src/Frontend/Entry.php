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
	 * @param string $display 'cards' (grid) or 'list' (single column).
	 * @return string
	 */
	public static function render_chooser( string $display = 'cards' ): string {
		$cards = '';
		foreach ( AccessController::readable_terms( get_current_user_id() ) as $term ) {
			$cards .= Cards::handbook_card( $term );
		}

		if ( '' === $cards ) {
			return '<p class="living-handbook-empty">' . esc_html__( 'No handbooks available.', 'living-handbook' ) . '</p>';
		}
		$modifier = 'list' === $display ? ' living-handbook-overview--list' : '';
		return '<div class="living-handbook-overview' . $modifier . '"><div class="living-handbook-cards living-handbook-cards--books">' . $cards . '</div></div>';
	}

	/**
	 * Render a compact menu of the handbooks the current user may read, for use
	 * in a site header or navigation area. On narrow screens the list collapses
	 * behind a toggle button, so the block works on mobile like a menu.
	 *
	 * @return string
	 */
	public static function render_menu(): string {
		$items = '';
		foreach ( AccessController::readable_terms( get_current_user_id() ) as $term ) {
			$link = get_term_link( $term );
			if ( is_wp_error( $link ) ) {
				continue;
			}
			$items .= '<li class="living-handbook-menu__item"><a class="living-handbook-menu__link" href="' . esc_url( (string) $link ) . '">' . esc_html( $term->name ) . '</a></li>';
		}

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
	 * Render the entry page of one handbook.
	 *
	 * Shows a prominent search, then either the filtered result list (when a
	 * search or facet is active) or the areas and recently updated pages, with
	 * the facet sidebar on the right. The wrapper carries the handbook term id
	 * so the frontend script can filter through the REST route; the result
	 * column is a polite live region so screen readers announce AJAX updates.
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
		$out     .= Filters::search_form( $term );
		$out     .= '<div class="living-handbook-layout"><div class="living-handbook-main" aria-live="polite">';
		$out     .= self::main_body( $term, $selections, $search, $paged );
		$out     .= '</div><aside class="living-handbook-aside">' . Filters::facets( $term ) . '</aside></div>';
		return $out . '</div>';
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
