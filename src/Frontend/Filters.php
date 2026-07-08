<?php
/**
 * Search and taxonomy filters for the handbook entry page.
 *
 * The facets are a GET form that constrains the term archive's main query
 * server-side (over the whole handbook, not just the rendered cards). The
 * search field filters the shown cards live and, on submit, runs a full-text
 * search within the handbook. Ported from the prototype.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Frontend;

use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\Taxonomy\Taxonomies;
use WP_Post;
use WP_Query;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies the entry-page filters to the main query and renders the controls.
 */
final class Filters {

	private const SEARCH_PARAM = 'lh_s';

	/**
	 * Hook registration into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'pre_get_posts', array( $this, 'apply' ) );
	}

	/**
	 * Constrain the handbook term archive's main query by the active filters.
	 *
	 * @param WP_Query $query The query.
	 * @return void
	 */
	public function apply( WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( ! $query->is_tax( Handbooks::TAXONOMY ) || ! self::is_active() ) {
			return;
		}

		$tax_query = array( 'relation' => 'AND' );
		$term_slug = (string) $query->get( Handbooks::TAXONOMY );
		if ( '' !== $term_slug ) {
			$tax_query[] = array(
				'taxonomy' => Handbooks::TAXONOMY,
				'field'    => 'slug',
				'terms'    => $term_slug,
			);
		}
		foreach ( self::facet_map() as $param => $taxonomy ) {
			$values = self::param_values( $param );
			if ( ! empty( $values ) ) {
				$tax_query[] = array(
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => $values,
				);
			}
		}

		$query->set( 'tax_query', $tax_query ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query

		$search = self::search_value();
		if ( '' !== $search ) {
			$query->set( 's', $search );
		}
		$query->set( 'posts_per_page', 24 );
		$query->set( 'orderby', 'modified' );
		$query->set( 'order', 'DESC' );
	}

	/**
	 * Whether any filter or the search is active in the request.
	 *
	 * @return bool
	 */
	public static function is_active(): bool {
		if ( '' !== self::search_value() ) {
			return true;
		}
		foreach ( array_keys( self::facet_map() ) as $param ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! empty( $_GET[ $param ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Render the prominent search form for a handbook.
	 *
	 * @param WP_Term $term Handbook term.
	 * @return string
	 */
	public static function search_form( WP_Term $term ): string {
		$action = get_term_link( $term );
		if ( is_wp_error( $action ) ) {
			return '';
		}

		return '<form class="living-handbook-start__search" role="search" method="get" action="' . esc_url( (string) $action ) . '">'
			. '<label class="screen-reader-text" for="living-handbook-search">' . esc_html__( 'Search this handbook', 'living-handbook' ) . '</label>'
			. '<input type="search" id="living-handbook-search" name="' . esc_attr( self::SEARCH_PARAM ) . '" value="' . esc_attr( self::search_value() ) . '" class="living-handbook-search__input" placeholder="' . esc_attr__( 'Search this handbook …', 'living-handbook' ) . '" autocomplete="off">'
			. '<button type="submit">' . esc_html__( 'Search', 'living-handbook' ) . '</button></form>';
	}

	/**
	 * Render the taxonomy facet form for a handbook.
	 *
	 * @param WP_Term $term Handbook term.
	 * @return string
	 */
	public static function facets( WP_Term $term ): string {
		$action = get_term_link( $term );
		if ( is_wp_error( $action ) ) {
			return '';
		}
		$action = (string) $action;

		$fields = '';
		foreach ( self::facet_map() as $param => $taxonomy ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => true,
				)
			);
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}
			$selected = self::param_values( $param );
			$fields  .= '<fieldset class="living-handbook-facet"><legend>' . esc_html( self::facet_label( $param ) ) . '</legend>';
			foreach ( $terms as $facet_term ) {
				if ( ! $facet_term instanceof WP_Term ) {
					continue;
				}
				$fields .= sprintf(
					'<label class="living-handbook-facet__opt"><input type="checkbox" class="living-handbook-facet__cb" name="%1$s[]" value="%2$s"%3$s> %4$s</label>',
					esc_attr( $param ),
					esc_attr( $facet_term->slug ),
					in_array( $facet_term->slug, $selected, true ) ? ' checked' : '',
					esc_html( $facet_term->name )
				);
			}
			$fields .= '</fieldset>';
		}

		if ( '' === $fields ) {
			return '';
		}

		return '<form class="living-handbook-filterform" method="get" action="' . esc_url( $action ) . '">'
			. $fields
			. '<button type="submit" class="living-handbook-reset">' . esc_html__( 'Filter', 'living-handbook' ) . '</button> '
			. '<a class="living-handbook-reset living-handbook-reset--link" href="' . esc_url( $action ) . '">' . esc_html__( 'Reset', 'living-handbook' ) . '</a>'
			. '</form>';
	}

	/**
	 * Render the filtered result cards from the main query, with pagination.
	 *
	 * @return string
	 */
	public static function filtered_results(): string {
		global $wp_query;
		$count = (int) $wp_query->found_posts;

		/* translators: %d: number of matching pages. */
		$out = '<p class="living-handbook-count">' . esc_html( sprintf( _n( '%d page found', '%d pages found', $count, 'living-handbook' ), $count ) ) . '</p>';

		if ( 0 === $count ) {
			return $out . '<p class="living-handbook-empty">' . esc_html__( 'No matches. Adjust the filters or the search.', 'living-handbook' ) . '</p>';
		}

		$cards = '';
		foreach ( $wp_query->posts as $post ) {
			if ( $post instanceof WP_Post ) {
				$cards .= Cards::page_card( $post->ID );
			}
		}

		$out .= '<div class="living-handbook-cards">' . $cards . '</div>';
		return $out . get_the_posts_pagination();
	}

	/**
	 * The sanitized full-text search value from the request.
	 *
	 * @return string
	 */
	public static function search_value(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET[ self::SEARCH_PARAM ] ) ) {
			return '';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return sanitize_text_field( wp_unslash( $_GET[ self::SEARCH_PARAM ] ) );
	}

	/**
	 * The sanitized slug values of one facet parameter from the request.
	 *
	 * @param string $param Request parameter name.
	 * @return array<int, string>
	 */
	private static function param_values( string $param ): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET[ $param ] ) ) {
			return array();
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return array_map( 'sanitize_title', (array) wp_unslash( $_GET[ $param ] ) );
	}

	/**
	 * Map of request parameters to the taxonomy they filter.
	 *
	 * @return array<string, string>
	 */
	private static function facet_map(): array {
		return array(
			'lh_type'     => Taxonomies::PAGE_TYPE,
			'lh_topic'    => Taxonomies::TOPIC,
			'lh_role'     => Taxonomies::ROLE,
			'lh_audience' => Taxonomies::AUDIENCE,
		);
	}

	/**
	 * Human-readable label for a facet parameter.
	 *
	 * @param string $param Request parameter name.
	 * @return string
	 */
	private static function facet_label( string $param ): string {
		switch ( $param ) {
			case 'lh_type':
				return __( 'Page type', 'living-handbook' );
			case 'lh_topic':
				return __( 'Topic', 'living-handbook' );
			case 'lh_role':
				return __( 'Responsible role', 'living-handbook' );
			case 'lh_audience':
				return __( 'Audience', 'living-handbook' );
			default:
				return '';
		}
	}
}
