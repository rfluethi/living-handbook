<?php
/**
 * Ability living-handbook/list-feedback.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Abilities;

use LivingHandbook\Feedback\Feedback;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use WP_Query;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The pages readers found unhelpful, for the editors.
 *
 * Reads the two counters of "Was this helpful?". With few votes a ranking is
 * chance, so a page needs a minimum number of votes to appear, and the answer
 * gives the counts, not only the share.
 */
final class ListFeedback extends Ability {

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'list-feedback';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return __( 'List unhelpful pages', 'living-handbook' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return __( 'For editors: lists pages that readers marked as not helpful, ordered by the share of "no" answers, with the counts of both answers. Pages with fewer votes than the minimum are left out, because a share of few votes says little. Mention the counts when you report a share.', 'living-handbook' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function permitted(): bool {
		return Pages::may_edit();
	}

	/**
	 * {@inheritDoc}
	 */
	public function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'handbook'  => self::handbook_input(),
				'min_votes' => array(
					'type'    => 'integer',
					'minimum' => 1,
					'maximum' => 10000,
					'default' => 5,
				),
				'limit'     => array(
					'type'    => 'integer',
					'minimum' => 1,
					'maximum' => 100,
					'default' => 20,
				),
			),
			'additionalProperties' => false,
			'default'              => array(),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function output_schema(): array {
		$page                        = self::page_schema();
		$page['properties']['votes'] = array(
			'type'       => 'object',
			'properties' => array(
				'helpful'           => array( 'type' => 'integer' ),
				'not_helpful'       => array( 'type' => 'integer' ),
				'total'             => array( 'type' => 'integer' ),
				'not_helpful_share' => array(
					'type'        => 'number',
					'description' => 'Share of "no" answers, 0 to 1.',
				),
			),
		);

		return array(
			'type'       => 'object',
			'properties' => array(
				'pages' => array(
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
		$scope = Pages::scope( $input );
		if ( is_wp_error( $scope ) ) {
			return $scope;
		}
		if ( array() === $scope ) {
			return array( 'pages' => array() );
		}
		$min   = max( 1, (int) ( $input['min_votes'] ?? 5 ) );
		$limit = max( 1, min( 100, (int) ( $input['limit'] ?? 20 ) ) );

		$query = new WP_Query(
			array(
				'post_type'      => Handbook::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- only pages with a "no" vote; the counter is the only place that says so.
					array(
						'key'     => Feedback::NO,
						'value'   => 0,
						'compare' => '>',
						'type'    => 'NUMERIC',
					),
				),
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Pages belong to a handbook by term; there is no other key to scope by.
					array(
						'taxonomy'         => Handbooks::TAXONOMY,
						'field'            => 'term_id',
						'terms'            => array_map( static fn( WP_Term $term ): int => (int) $term->term_id, $scope ),
						'include_children' => false,
					),
				),
			)
		);
		$ids   = array_map( 'intval', $query->posts );
		update_meta_cache( 'post', $ids );

		$pages = array();
		foreach ( $ids as $id ) {
			$yes   = (int) get_post_meta( $id, Feedback::YES, true );
			$no    = (int) get_post_meta( $id, Feedback::NO, true );
			$total = $yes + $no;
			if ( $total < $min ) {
				continue;
			}
			$post = Pages::readable( $id );
			if ( null === $post ) {
				continue;
			}
			$entry          = Pages::reference( $post );
			$entry['votes'] = array(
				'helpful'           => $yes,
				'not_helpful'       => $no,
				'total'             => $total,
				'not_helpful_share' => round( $no / $total, 2 ),
			);
			$pages[]        = $entry;
		}

		usort(
			$pages,
			static fn( array $a, array $b ): int => array( $b['votes']['not_helpful_share'], $b['votes']['not_helpful'] ) <=> array( $a['votes']['not_helpful_share'], $a['votes']['not_helpful'] )
		);

		return array( 'pages' => array_slice( $pages, 0, $limit ) );
	}
}
