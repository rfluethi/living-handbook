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
				// include_children is off in every handbook query, here and in Cards,
				// Filters, the export and the sync. One page belongs to exactly one
				// handbook, so a handbook's list is its own pages. WordPress would
				// add the pages of every handbook below this one, because the
				// grouping is hierarchical: harmless while nobody nested anything,
				// wrong the moment somebody does, and it would file a child's pages
				// under the parent's name.
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

		$map = array();
		foreach ( $query->posts as $post ) {
			if ( $post instanceof WP_Post ) {
				$map[ (int) $post->post_parent ][] = $post;
			}
		}
		return $map;
	}

	/**
	 * The first pages of a handbook's top level, for a preview on its card.
	 *
	 * The limit is in the query, not in the output: a handbook of two hundred
	 * pages must not be loaded to show three of them, and five such handbooks on
	 * one overview would otherwise load a thousand posts to print fifteen links.
	 * One row more than asked for is fetched, which is how the caller knows
	 * whether there is a "more" to offer without counting the whole handbook.
	 *
	 * Access is not checked here and does not have to be: every query for handbook
	 * pages passes AccessController, which drops what the current visitor may not
	 * read. That is the one rule this plugin has.
	 *
	 * @param int $term_id Handbook term id.
	 * @param int $limit   How many pages the caller wants to show.
	 * @return array<int, WP_Post> Up to $limit + 1 pages.
	 */
	public static function top_level( int $term_id, int $limit ): array {
		if ( $term_id <= 0 || $limit < 1 ) {
			return array();
		}

		$query = new WP_Query(
			array(
				'post_type'      => Handbook::POST_TYPE,
				'post_status'    => 'publish',
				'post_parent'    => 0,
				'posts_per_page' => $limit + 1,
				'orderby'        => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
				'no_found_rows'  => true,
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

		$out = array();
		foreach ( $query->posts as $post ) {
			if ( $post instanceof WP_Post ) {
				$out[] = $post;
			}
		}

		return $out;
	}
}
