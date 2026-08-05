<?php
/**
 * Moving existing WordPress pages into a handbook.
 *
 * Turning a page into a handbook page is one field, `post_type`, and that is
 * exactly why doing it by hand goes wrong. Three things have to happen with it,
 * and none of them happens by itself:
 *
 * 1. The page needs a handbook. Access is fail-closed, so a handbook page
 *    without one is not moved, it is gone from the front end.
 * 2. Its address changes, from /about/ to /handbook/about/, and WordPress does
 *    not redirect on a type change. Every existing link would break, so the old
 *    path is remembered and answered with a 301.
 * 3. Its children have to come along. A child left behind keeps a parent that is
 *    no longer a page, and its own permalink is built from that chain.
 *
 * The bulk action therefore always takes the whole subtree. It is not offered as
 * a choice, because a bulk dropdown has no room for a question and the answer
 * "no" produces pages nobody can reach.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Admin;

use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A bulk action on the page list, and the redirect that keeps old links alive.
 */
final class MoveToHandbook {

	/**
	 * The path a page had before it was moved, relative to the site root and
	 * without the leading and trailing slash, for example "about/team".
	 */
	public const META_MOVED_FROM = '_lh_moved_from';

	/**
	 * Prefix of the bulk action, followed by the target handbook's term id.
	 */
	private const ACTION_PREFIX = 'lh_move_to_';

	/**
	 * Query argument carrying the result back to the list screen.
	 */
	private const RESULT_ARG = 'lh_moved';

	/**
	 * Set once a page has been moved, so a site that never used this feature
	 * never pays for it. Without it every 404 on every site would ask the
	 * database whether some page used to live at that address, and the answer on
	 * almost every site is no.
	 */
	private const OPTION_ANY_MOVED = 'living_handbook_moved_pages';

	/**
	 * Hook registration into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'bulk_actions-edit-page', array( $this, 'add_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-page', array( $this, 'handle_bulk_action' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'notice' ) );
		add_action( 'template_redirect', array( $this, 'redirect_moved' ) );
	}

	/**
	 * Whether the current user may move pages into a handbook.
	 *
	 * Moving content between post types is an editorial act on other people's
	 * pages as well as one's own, so it takes the capability that means exactly
	 * that. The per-page check follows for each page.
	 *
	 * @return bool
	 */
	public static function allowed(): bool {
		return current_user_can( 'edit_others_posts' );
	}

	/**
	 * One bulk action per handbook.
	 *
	 * One entry per handbook rather than a single entry plus a second control:
	 * WordPress gives a bulk action a dropdown and nothing else, and a site has a
	 * handful of handbooks, not a hundred.
	 *
	 * @param array<string, string> $actions Existing bulk actions.
	 * @return array<string, string>
	 */
	public function add_bulk_actions( array $actions ): array {
		if ( ! self::allowed() ) {
			return $actions;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => Handbooks::TAXONOMY,
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) ) {
			return $actions;
		}

		foreach ( $terms as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}
			$actions[ self::ACTION_PREFIX . $term->term_id ] = sprintf(
				/* translators: %s: handbook name. */
				__( 'Move into the handbook: %s', 'living-handbook' ),
				$term->name
			);
		}

		return $actions;
	}

	/**
	 * Move the selected pages, and everything below them.
	 *
	 * @param string     $redirect The redirect URL.
	 * @param string     $action   The chosen bulk action.
	 * @param array<int> $ids      Selected post ids.
	 * @return string
	 */
	public function handle_bulk_action( string $redirect, string $action, array $ids ): string {
		if ( ! str_starts_with( $action, self::ACTION_PREFIX ) ) {
			return $redirect;
		}
		if ( ! self::allowed() ) {
			return $redirect;
		}

		$term_id = (int) substr( $action, strlen( self::ACTION_PREFIX ) );
		$term    = get_term( $term_id, Handbooks::TAXONOMY );
		if ( ! $term instanceof WP_Term ) {
			return $redirect;
		}

		$moved   = 0;
		$skipped = 0;
		foreach ( self::with_descendants( array_map( 'intval', $ids ) ) as $id ) {
			if ( self::move( $id, $term_id ) ) {
				++$moved;
			} else {
				++$skipped;
			}
		}

		if ( $moved > 0 ) {
			flush_rewrite_rules( false );
		}

		return add_query_arg(
			array(
				self::RESULT_ARG => $moved,
				'lh_skipped'     => $skipped,
			),
			$redirect
		);
	}

	/**
	 * The given pages plus every page below them, parents before children.
	 *
	 * @param array<int, int> $ids Selected page ids.
	 * @return array<int, int>
	 */
	public static function with_descendants( array $ids ): array {
		$out = array();

		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( $id <= 0 || in_array( $id, $out, true ) ) {
				continue;
			}
			$out[] = $id;

			$children = get_pages(
				array(
					'child_of'    => $id,
					'post_type'   => 'page',
					'post_status' => 'publish,draft,pending,private,future',
					'sort_column' => 'menu_order,post_title',
				)
			);
			if ( ! is_array( $children ) ) {
				continue;
			}
			foreach ( $children as $child ) {
				$child_id = (int) $child->ID;
				if ( ! in_array( $child_id, $out, true ) ) {
					$out[] = $child_id;
				}
			}
		}

		return $out;
	}

	/**
	 * Move one page into a handbook.
	 *
	 * The old path is written before the type changes, because afterwards the
	 * permalink is the new one and the old one is unrecoverable.
	 *
	 * @param int $post_id Page id.
	 * @param int $term_id Target handbook.
	 * @return bool Whether the page was moved.
	 */
	public static function move( int $post_id, int $term_id ): bool {
		$post = get_post( $post_id );
		if ( null === $post || 'page' !== $post->post_type ) {
			return false;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}

		$was = self::path_of( $post_id );

		$result = wp_update_post(
			array(
				'ID'        => $post_id,
				'post_type' => Handbook::POST_TYPE,
			),
			true
		);
		if ( is_wp_error( $result ) ) {
			return false;
		}

		wp_set_object_terms( $post_id, array( $term_id ), Handbooks::TAXONOMY, false );

		if ( '' !== $was ) {
			update_post_meta( $post_id, self::META_MOVED_FROM, $was );
			update_option( self::OPTION_ANY_MOVED, 1 );
		}

		return true;
	}

	/**
	 * The site-relative path of a post, without the leading and trailing slash.
	 *
	 * @param int $post_id Post id.
	 * @return string
	 */
	public static function path_of( int $post_id ): string {
		$permalink = get_permalink( $post_id );
		if ( ! is_string( $permalink ) ) {
			return '';
		}

		$path = (string) wp_parse_url( $permalink, PHP_URL_PATH );
		$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( '' !== $home && '/' !== $home && str_starts_with( $path, $home ) ) {
			$path = substr( $path, strlen( $home ) );
		}

		return trim( $path, '/' );
	}

	/**
	 * Answer a request for a moved page's old address with a 301.
	 *
	 * Only on a 404, so this costs nothing on any request that resolves. A moved
	 * page keeps its old address working for good: the alternative is that every
	 * link, bookmark and search result pointing at the documentation dies the day
	 * it becomes documentation.
	 *
	 * @return void
	 */
	public function redirect_moved(): void {
		if ( ! is_404() ) {
			return;
		}
		// Nothing has ever been moved on this site: no lookup, no query. This is
		// the common case, and it is the reason the query below is acceptable.
		if ( ! get_option( self::OPTION_ANY_MOVED ) ) {
			return;
		}

		$requested = isset( $_SERVER['REQUEST_URI'] )
			? (string) wp_parse_url( esc_url_raw( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH )
			: '';
		$home      = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( '' !== $home && '/' !== $home && str_starts_with( $requested, $home ) ) {
			$requested = substr( $requested, strlen( $home ) );
		}
		$requested = trim( $requested, '/' );
		if ( '' === $requested ) {
			return;
		}

		/*
		 * A meta lookup, which PHPCS flags as a possible slow query because
		 * meta_value carries no index. Kept, with three things that make it
		 * cheap: it runs on a 404 only, never on a request that resolves; it
		 * runs only on a site that has actually moved a page, which the option
		 * above decides; and meta_key is indexed, so the scan is over the moved
		 * pages of this site rather than over wp_postmeta. The alternative, an
		 * autoloaded option holding every old path, would be read on every
		 * request instead of on a rare one.
		 */
		$found = get_posts(
			array(
				'post_type'        => Handbook::POST_TYPE,
				'post_status'      => 'publish',
				'numberposts'      => 1,
				'fields'           => 'ids',
				'suppress_filters' => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- see above.
				'meta_key'         => self::META_MOVED_FROM,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- see above.
				'meta_value'       => $requested,
			)
		);
		if ( empty( $found ) ) {
			return;
		}

		$target = get_permalink( (int) $found[0] );
		if ( ! is_string( $target ) ) {
			return;
		}

		wp_safe_redirect( $target, 301 );
		exit;
	}

	/**
	 * Report what the bulk action did.
	 *
	 * @return void
	 */
	public function notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_REQUEST[ self::RESULT_ARG ] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$moved = absint( wp_unslash( $_REQUEST[ self::RESULT_ARG ] ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$skipped = isset( $_REQUEST['lh_skipped'] ) ? absint( wp_unslash( $_REQUEST['lh_skipped'] ) ) : 0;

		$text = sprintf(
			/* translators: %d: number of pages moved. */
			_n( '%d page moved into the handbook, subpages included. Its old address now redirects to the new one.', '%d pages moved into the handbook, subpages included. Their old addresses now redirect to the new ones.', $moved, 'living-handbook' ),
			$moved
		);
		if ( $skipped > 0 ) {
			$text .= ' ' . sprintf(
				/* translators: %d: number of pages skipped. */
				_n( '%d page was left alone, because it is not a page or you may not edit it.', '%d pages were left alone, because they are not pages or you may not edit them.', $skipped, 'living-handbook' ),
				$skipped
			);
		}
		$text .= ' ' . __( 'The moved pages have no review date yet, so they show as "Not reviewed" until you set one.', 'living-handbook' );

		printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $text ) );
	}
}
