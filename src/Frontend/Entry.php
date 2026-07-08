<?php
/**
 * The handbook overview (chooser) and the per-handbook entry page.
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
 * Builds the two landing views: the overview of readable handbooks and the
 * entry page of one handbook (search, filters, areas, recently updated).
 */
final class Entry {

	/**
	 * Render the overview: a card per handbook the current user may read.
	 *
	 * @return string
	 */
	public static function render_chooser(): string {
		$terms = get_terms(
			array(
				'taxonomy'   => Handbooks::TAXONOMY,
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) ) {
			return '';
		}

		$user_id = get_current_user_id();
		$cards   = '';
		foreach ( $terms as $term ) {
			if ( $term instanceof WP_Term && AccessController::can_view_term( $term->term_id, $user_id ) ) {
				$cards .= Cards::handbook_card( $term );
			}
		}

		if ( '' === $cards ) {
			return '<p class="living-handbook-empty">' . esc_html__( 'No handbooks available.', 'living-handbook' ) . '</p>';
		}
		return '<div class="living-handbook-overview"><div class="living-handbook-cards living-handbook-cards--books">' . $cards . '</div></div>';
	}

	/**
	 * Render the entry page of one handbook.
	 *
	 * Shows a prominent search, then either the filtered result list (when a
	 * search or facet is active) or the areas and recently updated pages, with
	 * the facet sidebar on the right.
	 *
	 * @param WP_Term $term Handbook term.
	 * @return string
	 */
	public static function render_entry( WP_Term $term ): string {
		$term_id = $term->term_id;

		$out  = '<div class="living-handbook-entry">';
		$out .= Filters::search_form( $term );
		$out .= '<div class="living-handbook-layout"><div class="living-handbook-main">';

		if ( Filters::is_active() ) {
			$out .= Filters::filtered_results();
		} else {
			$out .= self::default_body( $term_id );
		}

		$out .= '</div><aside class="living-handbook-aside">' . Filters::facets( $term ) . '</aside></div>';
		return $out . '</div>';
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
			return '<p class="living-handbook-empty">' . esc_html__( 'This handbook has no pages yet.', 'living-handbook' ) . '</p>';
		}
		return $body;
	}
}
