<?php
/**
 * Ability living-handbook/list-due-pages.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Abilities;

use LivingHandbook\Frontend\FreshnessStatus;
use LivingHandbook\Meta\Metadata;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The pages whose review is due, overdue or missing, for the editors.
 *
 * The same rule as the review widget on the dashboard (FreshnessStatus), the
 * same right to see it (edit_others_posts), and on top of the widget the pages
 * nobody is named to review.
 */
final class ListDuePages extends Ability {

	/**
	 * Order of the problems, the most urgent first.
	 */
	private const RANK = array(
		FreshnessStatus::OVERDUE => 0,
		FreshnessStatus::DUE     => 1,
		FreshnessStatus::NONE    => 2,
		FreshnessStatus::OK      => 3,
	);

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'list-due-pages';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return __( 'List pages due for review', 'living-handbook' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return __( 'For editors: lists the pages whose review is overdue or due, that have no review date or interval, or that name no reviewer, most urgent first, with handbook, last review, interval, reviewer and address. Use it to answer what needs reviewing.', 'living-handbook' );
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
				'handbook'         => self::handbook_input(),
				'without_reviewer' => array(
					'type'        => 'boolean',
					'default'     => true,
					'description' => 'Also list pages that are reviewed in time but name no reviewer.',
				),
				'limit'            => array(
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
		$page                           = self::page_schema();
		$page['properties']['reviewer'] = array(
			'type'        => 'string',
			'description' => 'Display name of the reviewer, empty when none is named.',
		);
		$page['properties']['problems'] = array(
			'type'  => 'array',
			'items' => array(
				'type' => 'string',
				'enum' => array( 'overdue', 'due', 'no_review_date', 'no_reviewer' ),
			),
		);

		return array(
			'type'       => 'object',
			'properties' => array(
				'total' => array(
					'type'        => 'integer',
					'description' => 'Pages looked at.',
				),
				'found' => array(
					'type'        => 'integer',
					'description' => 'Pages with a problem, before the limit.',
				),
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
		$without_reviewer = (bool) ( $input['without_reviewer'] ?? true );
		$limit            = max( 1, min( 200, (int) ( $input['limit'] ?? 50 ) ) );

		$total = 0;
		$found = array();
		foreach ( $scope as $term ) {
			foreach ( Pages::tree( (int) $term->term_id ) as $node ) {
				++$total;
				$post     = $node['post'];
				$id       = (int) $post->ID;
				$status   = FreshnessStatus::for_post( $id );
				$reviewer = (int) get_post_meta( $id, Metadata::REVIEWER, true );

				$problems = array();
				if ( FreshnessStatus::OVERDUE === $status ) {
					$problems[] = 'overdue';
				} elseif ( FreshnessStatus::DUE === $status ) {
					$problems[] = 'due';
				} elseif ( FreshnessStatus::NONE === $status ) {
					$problems[] = 'no_review_date';
				}
				if ( 0 === $reviewer && ( $without_reviewer || array() !== $problems ) ) {
					$problems[] = 'no_reviewer';
				}
				if ( array() === $problems ) {
					continue;
				}

				$user              = $reviewer > 0 ? get_userdata( $reviewer ) : false;
				$entry             = Pages::reference( $post, $term );
				$entry['reviewer'] = $user instanceof WP_User ? (string) $user->display_name : '';
				$entry['problems'] = $problems;
				$found[]           = $entry;
			}
		}

		usort(
			$found,
			static fn( array $a, array $b ): int => array( self::RANK[ $a['review']['status'] ] ?? 9, $a['review']['last_reviewed'], $a['title'] )
				<=> array( self::RANK[ $b['review']['status'] ] ?? 9, $b['review']['last_reviewed'], $b['title'] )
		);

		return array(
			'total' => $total,
			'found' => count( $found ),
			'pages' => array_slice( $found, 0, $limit ),
		);
	}
}
