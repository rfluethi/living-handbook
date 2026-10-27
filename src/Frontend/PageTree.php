<?php
/**
 * Shared page-tree loader for a handbook.
 *
 * Loads every published page of one handbook at once and groups it by parent,
 * so the navigation and the area tiles can build their hierarchy in PHP instead
 * of firing one query per branch. Both callers read the same map, so a whole
 * handbook costs the same few queries whatever its size, rather than one per
 * level or per page.
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
 * Builds a parent-to-children map of a handbook's pages.
 */
final class PageTree {

	/**
	 * The columns a page in the map carries.
	 *
	 * What the navigation and the area tiles read of a page, and what core reads
	 * to build its title and its address: the parent for the tree and the
	 * hierarchical path, the name for the path, order and title for the order,
	 * password and status for the title prefix and the excerpt rule, type and
	 * date for get_permalink(). Not the content, which is the bulk of a page.
	 */
	private const COLUMNS = array( 'ID', 'post_parent', 'post_title', 'post_name', 'menu_order', 'post_password', 'post_status', 'post_type', 'post_date' );

	/**
	 * Return the published pages of a handbook grouped by parent id.
	 *
	 * The map is keyed by parent post id (0 for the top level); each value is the
	 * ordered list of child pages. Pages are ordered by menu order, then title,
	 * so the order matches the per-branch ordering the callers used before.
	 *
	 * The pages in the map are partial posts: they carry the columns in COLUMNS
	 * and nothing else, no content and no excerpt. Up to 0.75.1 they were whole
	 * posts, content included, and the navigation is built on every page view
	 * from every page of the handbook. Measured on 2000 pages of about 8 KB
	 * each, that was a peak of about 54 MB per view for a tree that prints
	 * titles and addresses, enough to exhaust a 128 MB memory limit at four to
	 * five thousand pages. A caller that needs the content of a page asks
	 * get_post() for it by id, which loads the whole page.
	 *
	 * A partial post never goes into the post cache, so get_post() elsewhere in
	 * the request keeps returning whole pages. The objects are marked as raw,
	 * which makes get_post(), get_the_title() and get_permalink() use them as
	 * they are instead of loading the page again.
	 *
	 * How it is read, in four queries whatever the size of the handbook:
	 * - the ids, through WP_Query, so the access layer on the query
	 *   (AccessController::restrict_query()) and any other plugin's query
	 *   filters apply as to every other read of handbook pages;
	 * - the columns of those ids;
	 * - their handbook membership, for the access decision per page, which is
	 *   AccessController::readable_posts(), the same rule and filter as on a query;
	 * - the parents, whole, because get_permalink() builds the hierarchical
	 *   address from the parents' slugs and loads every parent it does not find
	 *   in the cache, one query each (see prime_ancestors()).
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
				'fields'         => 'ids',
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

		$ids = array_map( 'intval', $query->posts );
		if ( array() === $ids ) {
			return array();
		}

		$pages = AccessController::readable_posts( self::partial_posts( $ids ) );
		self::prime_ancestors( $pages );

		$map = array();
		foreach ( $pages as $post ) {
			if ( $post instanceof WP_Post ) {
				$map[ (int) $post->post_parent ][] = $post;
			}
		}
		return $map;
	}

	/**
	 * Read the COLUMNS of a list of pages, in the order of the list.
	 *
	 * WP_Query has no way to ask for a few columns, only for ids or for whole
	 * posts, hence the one direct query. The ids are meant to come from a
	 * WP_Query that has already applied every filter, so this only fills in
	 * what they are; the access decision per page is then
	 * AccessController::readable_posts().
	 *
	 * What comes back are partial posts: no content, no excerpt. They must not
	 * be handed to code that reads more than COLUMNS, and they are never put
	 * into the post cache.
	 *
	 * @param array<int, int> $ids Page ids, in the order wanted.
	 * @return array<int, WP_Post> Partial posts, in the order of $ids.
	 */
	public static function partial_posts( array $ids ): array {
		if ( array() === $ids ) {
			return array();
		}

		global $wpdb;

		$in   = implode( ',', array_map( 'absint', $ids ) );
		$cols = implode( ', ', self::COLUMNS );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- the columns are a constant, the ids integers; a cache entry would be the partial post this must not leave behind.
		$rows = $wpdb->get_results( "SELECT {$cols} FROM {$wpdb->posts} WHERE ID IN ({$in})", OBJECT_K );
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$posts = array();
		foreach ( $ids as $id ) {
			if ( ! isset( $rows[ $id ] ) ) {
				continue;
			}
			// sanitize_post() in the raw context casts the id, parent and order to
			// integers and marks the object as raw, as WP_Post::get_instance()
			// does for a whole post. A raw WP_Post is used by get_post() as it is.
			$row = sanitize_post( $rows[ $id ], 'raw' );
			if ( is_object( $row ) ) {
				$posts[] = new WP_Post( $row );
			}
		}
		return $posts;
	}

	/**
	 * Load the parents of a set of pages, and theirs, up to the top, into the
	 * post cache, a level per query.
	 *
	 * The address of a page in a hierarchical type is its parents' slugs and
	 * its own, and get_permalink() gets each parent with get_post(), one query for
	 * every parent not in the cache. A list of lessons or search results from
	 * all over a handbook paid that per page; a list of twenty lessons took
	 * thirty queries. Here the missing parents of the whole list are read at
	 * once, then the missing parents of those, until there are none: one query
	 * per level of the deepest page instead of one per parent.
	 *
	 * The parents are loaded whole, as get_post() would load them, so the cache
	 * holds nothing it would not hold anyway; the terms and meta of a parent
	 * are not read, nothing on the way to an address needs them.
	 *
	 * @param array<int, mixed> $posts Pages whose addresses are about to be built.
	 * @return void
	 */
	public static function prime_ancestors( array $posts ): void {
		$next = array();
		foreach ( $posts as $post ) {
			if ( $post instanceof WP_Post && $post->post_parent > 0 ) {
				$next[ (int) $post->post_parent ] = true;
			}
		}

		$seen = array();
		while ( array() !== $next ) {
			$ids  = array_keys( $next );
			$seen = $seen + $next;
			_prime_post_caches( $ids, false, false );

			$next = array();
			foreach ( wp_cache_get_multiple( $ids, 'posts' ) as $parent ) {
				$grandparent = is_object( $parent ) && isset( $parent->post_parent ) ? (int) $parent->post_parent : 0;
				if ( $grandparent > 0 && ! isset( $seen[ $grandparent ] ) ) {
					$next[ $grandparent ] = true;
				}
			}
		}
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
		$term = get_term( $term_id, Handbooks::TAXONOMY );
		if ( ! $term instanceof WP_Term ) {
			return array();
		}

		$query = new WP_Query(
			array(
				'post_type'              => Handbook::POST_TYPE,
				'post_status'            => 'publish',
				'post_parent'            => 0,
				'posts_per_page'         => $limit + 1,
				'orderby'                => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
				'no_found_rows'          => true,
				// A preview prints a title and a link. The meta of the pages is
				// not read at all, and of their terms only the handbook, which
				// the access filter needs and reads by itself.
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				// By term_taxonomy_id, which the term already carries: by term_id
				// WordPress first looks that up with a query of its own, one per
				// card on the overview.
				'tax_query'              => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy'         => Handbooks::TAXONOMY,
						'field'            => 'term_taxonomy_id',
						'terms'            => (int) $term->term_taxonomy_id,
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
