<?php
/**
 * Navigation and overview rendering, shared by the shortcode and the blocks.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Frontend;

use LivingHandbook\Access\AccessController;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use WP_Post;
use WP_Query;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the per-handbook page tree and the overview of all viewable handbooks.
 */
final class Navigation {

	/**
	 * Render the page tree of the handbook a page belongs to.
	 *
	 * @param int $post_id Current post ID.
	 * @return string
	 */
	public static function render_for_post( int $post_id ): string {
		if ( $post_id <= 0 ) {
			return '';
		}
		$terms   = wp_get_object_terms( $post_id, Handbooks::TAXONOMY, array( 'fields' => 'ids' ) );
		$term_id = ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? (int) $terms[0] : 0;

		$tree = self::render_tree( 0, $term_id, $post_id );
		if ( '' === $tree ) {
			return '';
		}
		return '<nav class="living-handbook-nav" aria-label="' . esc_attr__( 'Handbook', 'living-handbook' ) . '">'
			. '<p class="living-handbook-nav__title">' . esc_html__( 'Handbook', 'living-handbook' ) . '</p>'
			. '<ul>' . $tree . '</ul></nav>';
	}

	/**
	 * Render an overview of every handbook the current user may view, as cards.
	 *
	 * @return string
	 */
	public static function render_overview(): string {
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
		$out     = '';
		foreach ( $terms as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}
			if ( ! AccessController::can_view_term( $term->term_id, $user_id ) ) {
				continue;
			}
			$cards = self::render_cards( $term->term_id );
			if ( '' === $cards ) {
				continue;
			}
			$out .= '<section class="living-handbook-overview__group"><h2 class="living-handbook-overview__title">'
				. esc_html( $term->name ) . '</h2>' . $cards . '</section>';
		}

		if ( '' === $out ) {
			return '';
		}
		return '<div class="living-handbook-overview">' . $out . '</div>';
	}

	/**
	 * Render the pages of a handbook as a card grid.
	 *
	 * @param int $term_id Handbook term ID.
	 * @return string
	 */
	private static function render_cards( int $term_id ): string {
		$query = new WP_Query(
			array(
				'post_type'      => Handbook::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'orderby'        => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => Handbooks::TAXONOMY,
						'field'    => 'term_id',
						'terms'    => $term_id,
					),
				),
			)
		);

		$cards = '';
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$status = FreshnessStatus::for_post( $post->ID );
			$dot    = FreshnessStatus::NONE !== $status
				? '<span class="living-handbook-card__dot living-handbook-card__dot--' . esc_attr( $status ) . '" aria-hidden="true"></span>'
				: '';
			$cards .= '<a class="living-handbook-card" href="' . esc_url( (string) get_permalink( $post ) ) . '">'
				. '<span class="living-handbook-card__title">' . esc_html( get_the_title( $post ) ) . '</span>'
				. $dot . '</a>';
		}

		if ( '' === $cards ) {
			return '';
		}
		return '<div class="living-handbook-cards">' . $cards . '</div>';
	}

	/**
	 * Recursively render the page tree of a handbook.
	 *
	 * @param int $parent_id Parent post ID (0 for the top level).
	 * @param int $term_id   Handbook term ID (0 for all).
	 * @param int $current   Currently viewed post ID (0 if none).
	 * @return string
	 */
	private static function render_tree( int $parent_id, int $term_id, int $current ): string {
		$args = array(
			'post_type'      => Handbook::POST_TYPE,
			'post_parent'    => $parent_id,
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
			'no_found_rows'  => true,
		);
		if ( $term_id > 0 ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => Handbooks::TAXONOMY,
					'field'    => 'term_id',
					'terms'    => $term_id,
				),
			);
		}

		$query = new WP_Query( $args );
		$out   = '';
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$children = self::render_tree( $post->ID, $term_id, $current );
			$class    = $post->ID === $current ? ' class="is-current"' : '';
			$out     .= '<li' . $class . '><a href="' . esc_url( (string) get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a>'
				. ( '' !== $children ? '<ul>' . $children . '</ul>' : '' ) . '</li>';
		}
		return $out;
	}
}
