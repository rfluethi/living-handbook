<?php
/**
 * Search and taxonomy filters for the handbook entry page.
 *
 * The facets filter the whole handbook (not just the rendered cards). Only the
 * terms actually used by pages of the current handbook are offered. Selecting a
 * facet, or submitting the search, sends a REST request that returns the
 * filtered result list, which the frontend script swaps in place, so there is
 * no full page reload. When JavaScript is off the facet form and the search
 * form still submit normally with their own button, and the result list is
 * rendered on the server from the request; the frontend script hides the facet
 * submit button because it filters live on change. The result list runs its own
 * query, scoped to the handbook, rather than modifying the term archive's main
 * query (which is fragile on a taxonomy archive).
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Frontend;

use LivingHandbook\Access\AccessController;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use LivingHandbook\Taxonomy\Taxonomies;
use WP_Post;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the entry-page search and facet controls and the filtered result list.
 */
final class Filters {

	private const SEARCH_PARAM = 'lh_s';

	public const REST_NAMESPACE    = 'living-handbook/v1';
	public const REST_ROUTE        = '/filter';
	public const REST_ROUTE_SEARCH = '/search';

	/**
	 * Indent per hierarchy level in the facet list, in rem.
	 */
	private const FACET_INDENT = 0.9;

	/**
	 * Hook registration into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_rest' ) );
	}

	/**
	 * Register the REST route that returns the filtered result list.
	 *
	 * Both routes carry `permission_callback => '__return_true'` on purpose. A
	 * public handbook has to stay searchable for logged-out visitors, so a blanket
	 * login requirement would be wrong here. The access decision sits one level
	 * deeper instead: rest_search() and rest_filter() check can_view_term() for the
	 * handbook and can_view_post() for every single hit before anything is
	 * returned, so the routes never hand out more than the caller may see.
	 *
	 * That makes the in-handler check load-bearing. Anyone adding a new return path
	 * here must run it through the same check; `tests/Integration/RestAccessTest.php`
	 * guards this, so a regression fails the suite instead of reaching the frontend.
	 *
	 * @return void
	 */
	public function register_rest(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_filter' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'term_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE_SEARCH,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_search' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'term_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'q'       => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * REST handler: return the matching pages of one handbook as a small list of
	 * title and permalink, for the on-page search typeahead. Access to the
	 * handbook and to each page is checked, so it cannot surface a page the
	 * current user may not read.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function rest_search( WP_REST_Request $request ): WP_REST_Response {
		$term_id = (int) $request->get_param( 'term_id' );
		$term    = $term_id > 0 ? get_term( $term_id, Handbooks::TAXONOMY ) : null;
		if ( ! $term instanceof WP_Term || ! AccessController::can_view_term( $term_id, get_current_user_id() ) ) {
			return new WP_REST_Response( array( 'results' => array() ), 200 );
		}

		$search = sanitize_text_field( (string) $request->get_param( 'q' ) );
		if ( '' === $search ) {
			return new WP_REST_Response( array( 'results' => array() ), 200 );
		}

		$query = new WP_Query(
			array(
				'post_type'      => Handbook::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 8,
				'no_found_rows'  => true,
				's'              => $search,
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy'         => Handbooks::TAXONOMY,
						'field'            => 'term_id',
						'terms'            => $term_id,
						'include_children' => false,
					),
				),
			)
		);

		$user_id = get_current_user_id();
		$results = array();
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post || ! AccessController::can_view_post( $post->ID, $user_id ) ) {
				continue;
			}
			$permalink = get_permalink( $post->ID );
			$results[] = array(
				'title'   => get_the_title( $post->ID ),
				'url'     => is_string( $permalink ) ? $permalink : '',
				'snippet' => self::snippet( $post, $search ),
			);
		}

		return new WP_REST_Response( array( 'results' => $results ), 200 );
	}

	/**
	 * A piece of the page's own text around the search hit.
	 *
	 * Eight results with similar titles ("Access", "Access rules", "Access for
	 * external people") are eight guesses; the sentence the words were found in
	 * is what decides which one to open. This is deliberately not the page's
	 * excerpt: an excerpt is the same text for every search and would not show
	 * why this page matched. When the words are only in the title, there is
	 * nothing to show and the result carries no snippet.
	 *
	 * Returned as segments rather than as markup: each piece says whether it is
	 * part of the hit, and the browser builds the elements from text. Nothing
	 * from the content has to be trusted or escaped on the way, because nothing
	 * on the way is markup.
	 *
	 * The post is the one that already passed the access check in the caller.
	 * Reading its content anywhere else would have to repeat that check.
	 *
	 * @param WP_Post $post   The post whose content to quote.
	 * @param string  $search The search words.
	 * @return array<int, array{text:string, mark:bool}>
	 */
	private static function snippet( WP_Post $post, string $search ): array {
		$radius = 80;

		$text = wp_strip_all_tags( strip_shortcodes( excerpt_remove_blocks( $post->post_content ) ) );
		$text = wp_specialchars_decode( $text, ENT_QUOTES );
		$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
		if ( '' === $text || '' === $search ) {
			return array();
		}

		$at = mb_stripos( $text, $search );
		if ( false === $at ) {
			// WordPress matches each word on its own, so a two-word search can find
			// a page that never has the two next to each other. Fall back to the
			// longest word, which is the most telling one to quote around.
			$words = preg_split( '/\s+/u', $search );
			if ( ! is_array( $words ) ) {
				$words = array();
			}
			usort(
				$words,
				static function ( string $a, string $b ): int {
					return mb_strlen( $b ) <=> mb_strlen( $a );
				}
			);
			foreach ( $words as $word ) {
				if ( mb_strlen( $word ) >= 3 ) {
					$at = mb_stripos( $text, $word );
					if ( false !== $at ) {
						$search = $word;
						break;
					}
				}
			}
		}
		if ( false === $at ) {
			return array();
		}

		$start  = max( 0, $at - $radius );
		$length = mb_strlen( $search ) + ( 2 * $radius );
		$cut    = mb_substr( $text, $start, $length );

		// Do not start or end mid-word: a snippet that begins with "ngsseite" reads
		// as a typo rather than as a quotation.
		if ( $start > 0 ) {
			$space = mb_strpos( $cut, ' ' );
			$cut   = false !== $space ? mb_substr( $cut, $space + 1 ) : $cut;
		}
		if ( $start + $length < mb_strlen( $text ) ) {
			$space = mb_strrpos( $cut, ' ' );
			$cut   = false !== $space ? mb_substr( $cut, 0, $space ) : $cut;
		}

		$segments = array();
		if ( $start > 0 ) {
			$segments[] = array(
				'text' => '… ',
				'mark' => false,
			);
		}

		$rest = $cut;
		while ( '' !== $rest ) {
			$hit = mb_stripos( $rest, $search );
			if ( false === $hit ) {
				$segments[] = array(
					'text' => $rest,
					'mark' => false,
				);
				break;
			}
			if ( $hit > 0 ) {
				$segments[] = array(
					'text' => mb_substr( $rest, 0, $hit ),
					'mark' => false,
				);
			}
			// The page's own spelling, not the visitor's: the hit is quoted as it
			// stands in the text.
			$segments[] = array(
				'text' => mb_substr( $rest, $hit, mb_strlen( $search ) ),
				'mark' => true,
			);
			$rest       = mb_substr( $rest, $hit + mb_strlen( $search ) );
		}

		if ( $start + $length < mb_strlen( $text ) ) {
			$segments[] = array(
				'text' => ' …',
				'mark' => false,
			);
		}

		return $segments;
	}

	/**
	 * REST handler: return the main-column HTML for a handbook and the given
	 * selections and search. Access to the handbook is checked, so the endpoint
	 * cannot be used to read a handbook the current user may not see.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function rest_filter( WP_REST_Request $request ): WP_REST_Response {
		$term_id = (int) $request->get_param( 'term_id' );
		$term    = $term_id > 0 ? get_term( $term_id, Handbooks::TAXONOMY ) : null;
		if ( ! $term instanceof WP_Term || ! AccessController::can_view_term( $term_id, get_current_user_id() ) ) {
			return new WP_REST_Response( array( 'html' => '' ), 200 );
		}

		$search     = sanitize_text_field( (string) $request->get_param( self::SEARCH_PARAM ) );
		$selections = array();
		foreach ( array_keys( self::facet_map() ) as $param ) {
			$raw = $request->get_param( $param );
			if ( is_array( $raw ) ) {
				$slugs = array_values( array_filter( array_map( 'sanitize_title', $raw ) ) );
				if ( ! empty( $slugs ) ) {
					$selections[ $param ] = $slugs;
				}
			}
		}
		$paged = max( 1, (int) $request->get_param( 'paged' ) );

		return new WP_REST_Response( array( 'html' => Entry::main_body( $term, $selections, $search, $paged ) ), 200 );
	}

	/**
	 * Whether any filter or the search is active for the given selections.
	 *
	 * @param array<string, string[]> $selections Facet selections.
	 * @param string                  $search     Search term.
	 * @return bool
	 */
	public static function is_active( array $selections, string $search ): bool {
		return '' !== $search || ! empty( $selections );
	}

	/**
	 * The current facet selections from the request (parameter to slugs).
	 *
	 * @return array<string, string[]>
	 */
	public static function current_selections(): array {
		$out = array();
		foreach ( array_keys( self::facet_map() ) as $param ) {
			$values = self::param_values( $param );
			if ( ! empty( $values ) ) {
				$out[ $param ] = $values;
			}
		}
		return $out;
	}

	/**
	 * The current page number from the request, at least 1.
	 *
	 * @return int
	 */
	public static function current_paged(): int {
		$paged = (int) get_query_var( 'paged' );
		if ( $paged < 1 ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$paged = isset( $_GET['paged'] ) ? absint( wp_unslash( $_GET['paged'] ) ) : 0;
		}
		return max( 1, $paged );
	}

	/**
	 * Render the prominent search form for a handbook.
	 *
	 * Carries the active facet selections as hidden fields so a no-JS search
	 * submit keeps the current filters.
	 *
	 * The options are what the search-bar block offers: label on or off, its
	 * wording, the placeholder, the button's wording and where it sits. They are
	 * deliberately the same set the core search block has, minus the collapsible
	 * variant, because everything visual (colour, border, typography, spacing)
	 * comes from the block supports rather than from options here.
	 *
	 * @param WP_Term              $term    Handbook term.
	 * @param array<string, mixed> $options show_label, label, placeholder, button_text, button_position, wrapper_attributes.
	 * @return string
	 */
	public static function search_form( WP_Term $term, array $options = array() ): string {
		$action = get_term_link( $term );
		if ( is_wp_error( $action ) ) {
			return '';
		}

		$label       = isset( $options['label'] ) && '' !== $options['label']
			? (string) $options['label']
			: __( 'Search this handbook', 'living-handbook' );
		$placeholder = isset( $options['placeholder'] ) && '' !== $options['placeholder']
			? (string) $options['placeholder']
			: __( 'Search this handbook …', 'living-handbook' );
		$button      = isset( $options['button_text'] ) && '' !== $options['button_text']
			? (string) $options['button_text']
			: __( 'Search', 'living-handbook' );

		$position = isset( $options['button_position'] ) ? (string) $options['button_position'] : 'button-outside';
		if ( ! in_array( $position, array( 'button-outside', 'button-inside', 'no-button' ), true ) ) {
			$position = 'button-outside';
		}

		// The label is in the document either way: a search field with nothing but
		// a placeholder loses its accessible name the moment something is typed
		// into it. Showing it is a visual choice, leaving it out is not.
		$id         = wp_unique_id( 'living-handbook-search-' );
		$attributes = isset( $options['wrapper_attributes'] ) && '' !== $options['wrapper_attributes']
			? (string) $options['wrapper_attributes']
			: 'class="living-handbook-start__search"';

		$out = '<form ' . $attributes . ' data-button-position="' . esc_attr( $position ) . '" role="search" method="get" action="' . esc_url( (string) $action ) . '">'
			. '<label class="' . ( empty( $options['show_label'] ) ? 'living-handbook-visually-hidden' : 'living-handbook-start__search-label' ) . '" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>'
			. '<span class="living-handbook-start__search-field">'
			. '<input type="search" id="' . esc_attr( $id ) . '" name="' . esc_attr( self::SEARCH_PARAM ) . '" value="' . esc_attr( self::search_value() ) . '" class="living-handbook-search__input" placeholder="' . esc_attr( $placeholder ) . '" autocomplete="off">'
			. self::hidden_facet_fields();

		if ( 'no-button' !== $position ) {
			$out .= '<button type="submit">' . esc_html( $button ) . '</button>';
		}

		return $out . '</span></form>';
	}

	/**
	 * Render the taxonomy facet form for a handbook.
	 *
	 * Only the terms used by pages of this handbook are offered. The form has a
	 * submit button so it works without JavaScript; the frontend script hides
	 * the button and filters live as a facet is toggled. A Reset link clears the
	 * filters.
	 *
	 * A hierarchical taxonomy is listed as an outline: children follow their
	 * parent and are indented, instead of the flat alphabetical order that
	 * get_terms() returns, which would put a child above its own parent.
	 *
	 * @param WP_Term $term               Handbook term.
	 * @param string  $wrapper_attributes Attributes for the form element, when it is rendered as its own block.
	 * @return string
	 */
	public static function facets( WP_Term $term, string $wrapper_attributes = '' ): string {
		$action = get_term_link( $term );
		if ( is_wp_error( $action ) ) {
			return '';
		}
		$action = (string) $action;

		$post_ids = self::handbook_post_ids( $term );
		if ( empty( $post_ids ) ) {
			return '';
		}

		$fields = '';
		foreach ( self::facet_map() as $param => $taxonomy ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'object_ids' => $post_ids,
					'hide_empty' => true,
				)
			);
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}

			$rows = is_taxonomy_hierarchical( $taxonomy )
				? self::as_outline( $terms )
				: self::as_flat_list( $terms );
			if ( empty( $rows ) ) {
				continue;
			}

			$selected = self::param_values( $param );
			$fields  .= '<fieldset class="living-handbook-facet"><legend>' . esc_html( self::facet_label( $param ) ) . '</legend>';
			foreach ( $rows as $row ) {
				$facet_term = $row['term'];
				$indent     = $row['depth'] > 0
					? ' style="padding-inline-start:' . esc_attr( (string) round( $row['depth'] * self::FACET_INDENT, 2 ) ) . 'rem"'
					: '';
				$fields    .= sprintf(
					'<label class="living-handbook-facet__opt"%5$s><input type="checkbox" class="living-handbook-facet__cb" name="%1$s[]" value="%2$s"%3$s> %4$s</label>',
					esc_attr( $param ),
					esc_attr( $facet_term->slug ),
					in_array( $facet_term->slug, $selected, true ) ? ' checked' : '',
					esc_html( $facet_term->name ),
					$indent // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built above from an escaped numeric value.
				);
			}
			$fields .= '</fieldset>';
		}

		if ( '' === $fields ) {
			return '';
		}

		$attributes = '' !== $wrapper_attributes ? $wrapper_attributes : 'class="living-handbook-filterform"';

		return '<form ' . $attributes . ' method="get" action="' . esc_url( $action ) . '">'
			. self::hidden_search_field()
			. $fields
			. '<p class="living-handbook-filterform__actions">'
			. '<button type="submit" class="living-handbook-filterform__submit">' . esc_html__( 'Apply filters', 'living-handbook' ) . '</button> '
			. '<a class="living-handbook-reset living-handbook-reset--link" href="' . esc_url( $action ) . '">' . esc_html__( 'Reset', 'living-handbook' ) . '</a>'
			. '</p>'
			. '</form>';
	}

	/**
	 * Order terms of a hierarchical taxonomy as an outline: each term is
	 * followed by its children, one level deeper, and every level is sorted by
	 * name.
	 *
	 * A term whose parent is not in the set is treated as top level. That case
	 * is real: the facets only offer terms that are actually used, so a parent
	 * with no pages of its own does not appear, and its children must not be
	 * indented under a term that is missing from the list.
	 *
	 * @param WP_Term[] $terms Terms to order.
	 * @return array<int, array{term:WP_Term, depth:int}>
	 */
	private static function as_outline( array $terms ): array {
		$by_id = array();
		foreach ( $terms as $term ) {
			if ( $term instanceof WP_Term ) {
				$by_id[ $term->term_id ] = $term;
			}
		}
		if ( empty( $by_id ) ) {
			return array();
		}

		$by_parent = array();
		foreach ( $by_id as $term ) {
			$parent_id                 = ( $term->parent > 0 && isset( $by_id[ $term->parent ] ) ) ? (int) $term->parent : 0;
			$by_parent[ $parent_id ][] = $term;
		}
		foreach ( $by_parent as &$level ) {
			usort(
				$level,
				static function ( WP_Term $a, WP_Term $b ): int {
					return strnatcasecmp( $a->name, $b->name );
				}
			);
		}
		unset( $level );

		$out = array();
		self::append_level( $by_parent, 0, 0, $out );
		return $out;
	}

	/**
	 * Append one outline level and its descendants to the output.
	 *
	 * @param array<int, WP_Term[]>                      $by_parent Terms grouped by parent id.
	 * @param int                                        $parent_id Parent id whose children to append.
	 * @param int                                        $depth     Current depth.
	 * @param array<int, array{term:WP_Term, depth:int}> $out       Output, by reference.
	 * @return void
	 */
	private static function append_level( array $by_parent, int $parent_id, int $depth, array &$out ): void {
		if ( empty( $by_parent[ $parent_id ] ) ) {
			return;
		}
		foreach ( $by_parent[ $parent_id ] as $term ) {
			$out[] = array(
				'term'  => $term,
				'depth' => $depth,
			);
			self::append_level( $by_parent, (int) $term->term_id, $depth + 1, $out );
		}
	}

	/**
	 * Order terms of a non-hierarchical taxonomy: a flat list, sorted by name.
	 *
	 * @param WP_Term[] $terms Terms to order.
	 * @return array<int, array{term:WP_Term, depth:int}>
	 */
	private static function as_flat_list( array $terms ): array {
		$out = array();
		foreach ( $terms as $term ) {
			if ( $term instanceof WP_Term ) {
				$out[] = array(
					'term'  => $term,
					'depth' => 0,
				);
			}
		}
		usort(
			$out,
			static function ( array $a, array $b ): int {
				return strnatcasecmp( $a['term']->name, $b['term']->name );
			}
		);
		return $out;
	}

	/**
	 * Hidden inputs carrying the active facet selections (for the search form).
	 *
	 * @return string
	 */
	private static function hidden_facet_fields(): string {
		$out = '';
		foreach ( array_keys( self::facet_map() ) as $param ) {
			foreach ( self::param_values( $param ) as $value ) {
				$out .= '<input type="hidden" name="' . esc_attr( $param ) . '[]" value="' . esc_attr( $value ) . '">';
			}
		}
		return $out;
	}

	/**
	 * Hidden input carrying the active search (for the facet form).
	 *
	 * @return string
	 */
	private static function hidden_search_field(): string {
		$search = self::search_value();
		if ( '' === $search ) {
			return '';
		}
		return '<input type="hidden" name="' . esc_attr( self::SEARCH_PARAM ) . '" value="' . esc_attr( $search ) . '">';
	}

	/**
	 * Render the filtered result cards for a handbook, with pagination.
	 *
	 * Runs its own query scoped to the handbook (by term id) plus the given
	 * facet selections (by slug) and the search, so it does not depend on
	 * modifying the term archive's main query. Pagination links point back to
	 * the term archive with the active filters in the URL, so page two loads as
	 * a normal request that renders the same list.
	 *
	 * @param WP_Term                 $term       Handbook term.
	 * @param array<string, string[]> $selections Facet selections (parameter to slugs).
	 * @param string                  $search     Search term.
	 * @param int                     $paged      Page number (1-based).
	 * @return string
	 */
	public static function filtered_results( WP_Term $term, array $selections, string $search, int $paged ): string {
		$map       = self::facet_map();
		$tax_query = array(
			'relation' => 'AND',
			array(
				'taxonomy'         => Handbooks::TAXONOMY,
				'field'            => 'term_id',
				'terms'            => $term->term_id,
				'include_children' => false,
			),
		);
		foreach ( $selections as $param => $slugs ) {
			if ( isset( $map[ $param ] ) && ! empty( $slugs ) ) {
				$tax_query[] = array(
					'taxonomy' => $map[ $param ],
					'field'    => 'slug',
					'terms'    => $slugs,
				);
			}
		}

		$paged = max( 1, $paged );
		$args  = array(
			'post_type'      => Handbook::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 24,
			'paged'          => $paged,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'tax_query'      => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		);
		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		$query = new WP_Query( $args );

		$cards    = '';
		$rendered = 0;
		foreach ( $query->posts as $post ) {
			if ( $post instanceof WP_Post ) {
				$cards .= Cards::page_card( $post->ID );
				++$rendered;
			}
		}

		// The rendered count is exact when everything fits on one page. With
		// pagination we cannot recount across pages cheaply and show the query
		// total; it can be off only by the rare page that also belongs to a
		// second, more restrictive handbook, which the precise access filter
		// (the_posts) removes but the coarse query count still includes.
		$paginated = $query->max_num_pages > 1;
		$count     = $paginated ? (int) $query->found_posts : $rendered;

		/* translators: %d: number of matching pages. */
		$out = '<p class="living-handbook-count">' . esc_html( sprintf( _n( '%d page found', '%d pages found', $count, 'living-handbook' ), $count ) ) . '</p>';

		if ( 0 === $count ) {
			return $out . '<p class="living-handbook-empty">' . esc_html__( 'No matches. Adjust the filters or the search.', 'living-handbook' ) . '</p>';
		}

		$out .= '<div class="living-handbook-cards">' . $cards . '</div>';

		if ( $paginated ) {
			$links = paginate_links(
				array(
					'base'    => self::pagination_base( $term, $selections, $search ),
					'format'  => '',
					'current' => $paged,
					'total'   => (int) $query->max_num_pages,
				)
			);
			if ( is_string( $links ) && '' !== $links ) {
				$out .= '<nav class="living-handbook-pagination" aria-label="' . esc_attr__( 'Handbook pages', 'living-handbook' ) . '">' . $links . '</nav>';
			}
		}

		return $out;
	}

	/**
	 * Build the paginate_links base URL: the term archive with the active
	 * search and facets kept, and a %#% placeholder for the page number.
	 *
	 * @param WP_Term                 $term       Handbook term.
	 * @param array<string, string[]> $selections Facet selections.
	 * @param string                  $search     Search term.
	 * @return string
	 */
	private static function pagination_base( WP_Term $term, array $selections, string $search ): string {
		$term_link = get_term_link( $term );
		$term_link = is_wp_error( $term_link ) ? '' : (string) $term_link;

		$query_args = array();
		if ( '' !== $search ) {
			$query_args[ self::SEARCH_PARAM ] = $search;
		}
		foreach ( $selections as $param => $slugs ) {
			if ( ! empty( $slugs ) ) {
				$query_args[ $param ] = $slugs;
			}
		}

		$base = add_query_arg( array_merge( $query_args, array( 'paged' => 'LHPAGENUM' ) ), $term_link );
		return str_replace( 'LHPAGENUM', '%#%', $base );
	}

	/**
	 * Published page IDs of a handbook.
	 *
	 * @param WP_Term $term Handbook term.
	 * @return int[]
	 */
	private static function handbook_post_ids( WP_Term $term ): array {
		$ids = get_posts(
			array(
				'post_type'      => Handbook::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy'         => Handbooks::TAXONOMY,
						'field'            => 'term_id',
						'terms'            => $term->term_id,
						'include_children' => false,
					),
				),
			)
		);
		return array_map( 'intval', $ids );
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
		$all = array(
			'lh_type'     => Taxonomies::PAGE_TYPE,
			'lh_topic'    => Taxonomies::TOPIC,
			'lh_role'     => Taxonomies::ROLE,
			'lh_audience' => Taxonomies::AUDIENCE,
		);

		// A vocabulary this site does not use has no facet, and its parameter is
		// not read either. One map for both, so a switched-off facet cannot come
		// back through a hand-written URL.
		return array_filter(
			$all,
			static function ( string $taxonomy ): bool {
				return Taxonomies::is_enabled( $taxonomy );
			}
		);
	}

	/**
	 * Human-readable label for a facet parameter. Mirrors the taxonomy display
	 * names so the filter reads the same as the rest of the interface.
	 *
	 * @param string $param Request parameter name.
	 * @return string
	 */
	private static function facet_label( string $param ): string {
		switch ( $param ) {
			case 'lh_type':
				return __( 'Page type', 'living-handbook' );
			case 'lh_topic':
				return __( 'Topics', 'living-handbook' );
			case 'lh_role':
				return __( 'Responsibility', 'living-handbook' );
			case 'lh_audience':
				return __( 'Audience', 'living-handbook' );
			default:
				return '';
		}
	}
}
