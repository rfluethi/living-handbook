<?php
/**
 * Ability living-handbook/search.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Abilities;

use LivingHandbook\Frontend\Filters;
use LivingHandbook\Frontend\PageTree;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use LivingHandbook\Search\SearchLog;
use LivingHandbook\Taxonomy\Taxonomies;
use WP_Post;
use WP_Query;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Full-text search over the handbooks the person may read.
 *
 * The same WordPress search as the handbook's own, over title and content,
 * without synonyms and without tolerance for typos. A model makes up for part
 * of that by searching again with other words, which is why the description
 * tells it to. A search with no hit and no filter is counted (E-0078), once
 * per handbook searched.
 */
final class Search extends Ability {

	/**
	 * Most results in one answer.
	 */
	public const MAX_RESULTS = 20;

	/**
	 * Input name to taxonomy, for the four filters.
	 */
	private const FACETS = array(
		'type'     => Taxonomies::PAGE_TYPE,
		'topic'    => Taxonomies::TOPIC,
		'role'     => Taxonomies::ROLE,
		'audience' => Taxonomies::AUDIENCE,
	);

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'search';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return __( 'Search handbooks', 'living-handbook' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return __( 'Searches the title and text of the handbook pages you may read, in all handbooks or in one, optionally filtered by page type, topic, responsibility or audience. Returns up to 20 pages with title, excerpt, the text around the hit, address and review status. The search matches words literally, without synonyms or typo tolerance: if nothing is found, search again with other or shorter words before you answer that the handbook has nothing on it.', 'living-handbook' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function input_schema(): array {
		$properties = array(
			'query'    => array(
				'type'        => 'string',
				'minLength'   => 2,
				'maxLength'   => 200,
				'description' => 'Words to search for.',
			),
			'handbook' => self::handbook_input(),
			'limit'    => array(
				'type'    => 'integer',
				'minimum' => 1,
				'maximum' => self::MAX_RESULTS,
				'default' => 10,
			),
		);
		foreach ( array_keys( self::FACETS ) as $facet ) {
			$properties[ $facet ] = array(
				'type'        => 'string',
				'description' => 'Slug of a term to filter by. Ignored when this classification is switched off on the site.',
			);
		}

		return array(
			'type'                 => 'object',
			'properties'           => $properties,
			'required'             => array( 'query' ),
			'additionalProperties' => false,
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function output_schema(): array {
		$page                          = self::page_schema();
		$page['properties']['excerpt'] = array( 'type' => 'string' );
		$page['properties']['match']   = array(
			'type'        => 'string',
			'description' => 'Text around the hit, when the words are in the text.',
		);

		return array(
			'type'       => 'object',
			'properties' => array(
				'results' => array(
					'type'  => 'array',
					'items' => $page,
				),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function run( array $input ) {
		$search = trim( sanitize_text_field( (string) ( $input['query'] ?? '' ) ) );
		$scope  = Pages::scope( $input );
		if ( is_wp_error( $scope ) ) {
			return $scope;
		}
		if ( '' === $search || array() === $scope ) {
			return array( 'results' => array() );
		}

		$limit     = max( 1, min( self::MAX_RESULTS, (int) ( $input['limit'] ?? 10 ) ) );
		$tax_query = array(
			'relation' => 'AND',
			array(
				'taxonomy'         => Handbooks::TAXONOMY,
				'field'            => 'term_id',
				'terms'            => array_map( static fn( WP_Term $term ): int => (int) $term->term_id, $scope ),
				'include_children' => false,
			),
		);
		$filtered  = false;
		foreach ( self::FACETS as $facet => $taxonomy ) {
			$slug = isset( $input[ $facet ] ) ? sanitize_title( (string) $input[ $facet ] ) : '';
			if ( '' !== $slug && Taxonomies::is_enabled( $taxonomy ) ) {
				$tax_query[] = array(
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => array( $slug ),
				);
				$filtered    = true;
			}
		}

		// Some hits may be left out below (AI-excluded), so ask for a few more
		// than the limit rather than answer short.
		$query = new WP_Query(
			array(
				'post_type'      => Handbook::POST_TYPE,
				'post_status'    => 'publish',
				's'              => $search,
				'posts_per_page' => 2 * $limit,
				'no_found_rows'  => true,
				'has_password'   => false,
				'tax_query'      => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Pages belong to a handbook by term; there is no other key to scope by.
			)
		);
		PageTree::prime_ancestors( $query->posts );

		$results = array();
		foreach ( $query->posts as $post ) {
			$post = $post instanceof WP_Post ? Pages::readable( $post ) : null;
			if ( null === $post ) {
				continue;
			}
			$entry            = Pages::reference( $post );
			$entry['excerpt'] = self::excerpt( $post );
			$entry['match']   = implode( '', array_column( Filters::snippet( $post, $search ), 'text' ) );
			$results[]        = $entry;
			if ( count( $results ) >= $limit ) {
				break;
			}
		}

		if ( array() === $results && ! $filtered ) {
			foreach ( $scope as $term ) {
				SearchLog::record( (int) $term->term_id, $search, SearchLog::SOURCE_AI );
			}
		}

		return array( 'results' => $results );
	}

	/**
	 * The page's excerpt, or the start of its text.
	 *
	 * Not get_the_excerpt(): without a hand-written excerpt it renders the whole
	 * content through the_content to cut it, once per result.
	 *
	 * @param WP_Post $post Page.
	 * @return string
	 */
	public static function excerpt( WP_Post $post ): string {
		$text = '' !== trim( (string) $post->post_excerpt ) ? (string) $post->post_excerpt : (string) $post->post_content;
		// A space for every tag first: wp_strip_all_tags() alone joins the last
		// word of one block to the first of the next ("StepsCollect").
		$text = wp_strip_all_tags( (string) preg_replace( '/<[^>]*>/', ' ', strip_shortcodes( excerpt_remove_blocks( $text ) ) ) );
		$text = wp_specialchars_decode( $text, ENT_QUOTES );

		return wp_trim_words( (string) preg_replace( '/\s+/u', ' ', $text ), 40, ' …' );
	}
}
