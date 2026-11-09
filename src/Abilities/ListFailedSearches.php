<?php
/**
 * Ability living-handbook/list-failed-searches.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Abilities;

use LivingHandbook\Search\SearchLog;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The most frequent searches without a hit, for the editors (E-0078).
 *
 * Only handbooks the person may read are reported. A search term is what
 * someone typed and can contain a name; that is the cost E-0078 accepts, and
 * the reason this needs the editors' right.
 */
final class ListFailedSearches extends Ability {

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'list-failed-searches';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return __( 'List failed searches', 'living-handbook' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return __( 'For editors: lists the search terms that found no page, most frequent first, per handbook, from the searches on the site (source "site") and through the search ability (source "ai"). Counted per calendar week, without any data about who searched. Use it to find what readers miss and suggest which page is lacking.', 'living-handbook' );
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
				'handbook' => self::handbook_input(),
				'days'     => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'maximum'     => 365,
					'default'     => 30,
					'description' => 'How many days back. Counts are kept per calendar week, so the period starts on the Monday of the first day.',
				),
				'source'   => array(
					'type'    => 'string',
					'enum'    => array( '', SearchLog::SOURCE_SITE, SearchLog::SOURCE_AI ),
					'default' => '',
				),
				'limit'    => array(
					'type'    => 'integer',
					'minimum' => 1,
					'maximum' => 200,
					'default' => 50,
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
		return array(
			'type'       => 'object',
			'properties' => array(
				'enabled'        => array(
					'type'        => 'boolean',
					'description' => 'Whether failed searches are counted on this site at all.',
				),
				'retention_days' => array( 'type' => 'integer' ),
				'searches'       => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'term'      => array( 'type' => 'string' ),
							'hits'      => array( 'type' => 'integer' ),
							'weeks'     => array(
								'type'        => 'integer',
								'description' => 'In how many calendar weeks it was searched.',
							),
							'last_week' => array(
								'type'        => 'string',
								'description' => 'Monday of the last week it was searched.',
							),
							'handbook'  => self::handbook_schema(),
						),
					),
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

		$by_id = array();
		foreach ( $scope as $term ) {
			$by_id[ (int) $term->term_id ] = $term;
		}

		$rows = SearchLog::most_frequent(
			array_keys( $by_id ),
			(int) ( $input['days'] ?? 30 ),
			max( 1, min( 200, (int) ( $input['limit'] ?? 50 ) ) ),
			(string) ( $input['source'] ?? '' )
		);

		$searches = array();
		foreach ( $rows as $row ) {
			$term = $by_id[ $row['handbook_id'] ] ?? null;
			if ( ! $term instanceof WP_Term ) {
				continue;
			}
			$searches[] = array(
				'term'      => $row['term'],
				'hits'      => $row['hits'],
				'weeks'     => $row['weeks'],
				'last_week' => $row['last_week'],
				'handbook'  => Pages::handbook_reference( $term ),
			);
		}

		return array(
			'enabled'        => SearchLog::enabled(),
			'retention_days' => SearchLog::retention_days(),
			'searches'       => $searches,
		);
	}
}
