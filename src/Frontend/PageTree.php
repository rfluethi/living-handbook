<?php
/**
 * Shared page-tree loader for a handbook.
 *
 * Loads every published page of one handbook in a single query and groups it by
 * parent, so the navigation and the area tiles can build their hierarchy in PHP
 * instead of firing one query per branch. Both callers read the same map, so a
 * whole handbook costs one query rather than one per level.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Frontend;

use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use WP_Post;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds a parent-to-children map of a handbook's pages from one query.
 */
final class PageTree {

	/**
	 * Return the published pages of a handbook grouped by parent id.
	 *
	 * The map is keyed by parent post id (0 for the top level); each value is the
	 * ordered list of child pages. Pages are ordered by menu order, then title,
	 * so the order matches the per-branch ordering the callers used before.
	 *
	 * @param int $term_id Handbook term id.
	 * @return array<int, array<int, WP_Post>>
	 */
	public static function children_map( int $term_id ): array {
		if ( $term_id <= 0 ) {
			return array();
		}

		$query = new WP_Query(
			array(
				'post_type'      => Handbook::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
				'no_found_rows'  => true,
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => Handbooks::TAXONOMY,
						'field'    => 'term_id',
						'terms'    => $term_id,
					),
				),
			)
		);

		$map = array();
		foreach ( $query->posts as $post ) {
			if ( $post instanceof WP_Post ) {
				$map[ (int) $post->post_parent ][] = $post;
			}
		}
		return $map;
	}
}
