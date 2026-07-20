<?php
/**
 * Frontend access control, enforced per handbook.
 *
 * Access is frontend-only. Editing in wp-admin uses the standard WordPress
 * roles and is not restricted here. A handbook page is viewable when its
 * handbook allows the current user; pages without a handbook are fail-closed.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Access;

use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use WP_Comment;
use WP_Post;
use WP_Query;
use WP_REST_Response;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The single place that decides who may read handbook content, wired into
 * every frontend read path: single pages, handbook entry pages (term
 * archives), result sets (archives, search, REST collections, custom loops),
 * single REST reads, and the comment channels (comment queries, comment feeds,
 * and single comment REST reads), which core exposes independently of the post
 * query.
 */
final class AccessController {

	/**
	 * Per-request memo of term visibility decisions, keyed "term_id:user_id".
	 *
	 * A result set of one handbook shares one term, so the same decision is
	 * asked for many times per request; this avoids repeating the term-meta and
	 * user lookups.
	 *
	 * @var array<string, bool>
	 */
	private static array $term_cache = array();

	/**
	 * Hook registration into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'template_redirect', array( $this, 'guard_singular' ) );
		add_action( 'template_redirect', array( $this, 'guard_term_archive' ) );
		add_filter( 'the_posts', array( $this, 'filter_posts' ), 10, 2 );
		add_filter( 'rest_prepare_' . Handbook::POST_TYPE, array( $this, 'guard_rest_item' ), 10, 2 );

		// Comments are a separate read channel that the post query does not
		// cover: gate the comment queries, the comment feeds, and single comment
		// REST reads, so comments on a handbook a user may not view do not leak.
		add_filter( 'comments_clauses', array( $this, 'filter_comment_clauses' ), 10, 2 );
		add_filter( 'comment_feed_where', array( $this, 'filter_comment_feed_where' ), 10, 2 );
		add_filter( 'rest_prepare_comment', array( $this, 'guard_rest_comment' ), 10, 2 );
	}

	/**
	 * Block direct access to a single handbook page the user may not view.
	 *
	 * @return void
	 */
	public function guard_singular(): void {
		if ( ! is_singular( Handbook::POST_TYPE ) ) {
			return;
		}

		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		if ( self::can_view_post( $post->ID, get_current_user_id() ) ) {
			return;
		}

		$this->deny();
	}

	/**
	 * Block direct access to a handbook entry page (term archive) the user may
	 * not view.
	 *
	 * @return void
	 */
	public function guard_term_archive(): void {
		if ( ! is_tax( Handbooks::TAXONOMY ) ) {
			return;
		}

		$term = get_queried_object();
		if ( ! $term instanceof WP_Term ) {
			return;
		}

		if ( self::can_view_term( $term->term_id, get_current_user_id() ) ) {
			return;
		}

		$this->deny();
	}

	/**
	 * Deny the current request: send guests to the login, show a 404 to others.
	 *
	 * @return void
	 */
	private function deny(): void {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}

		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}

	/**
	 * Remove handbook posts the current user may not view from any result set.
	 *
	 * This runs on the_posts, which core applies to full-object queries (the
	 * display path). Core returns id-only and id=>parent queries (fields =>
	 * 'ids') before the_posts runs, so those never reach this filter. That is
	 * acceptable: a bare id exposes no content, and every actual content read
	 * (single page, entry page, single REST item, comments) is guarded on its
	 * own.
	 *
	 * @param WP_Post[] $posts Posts returned by the query.
	 * @param WP_Query  $query The query (unused, kept for the filter signature).
	 * @return WP_Post[]
	 */
	public function filter_posts( array $posts, WP_Query $query ): array {
		unset( $query );

		if ( is_admin() ) {
			return $posts;
		}

		$user_id = get_current_user_id();

		return array_values(
			array_filter(
				$posts,
				static function ( $post ) use ( $user_id ): bool {
					if ( ! $post instanceof WP_Post || Handbook::POST_TYPE !== $post->post_type ) {
						return true;
					}
					return self::can_view_post( $post->ID, $user_id );
				}
			)
		);
	}

	/**
	 * Return a 404 for single REST reads of non-viewable handbook pages.
	 *
	 * @param mixed   $response The prepared response.
	 * @param WP_Post $post     The post being prepared.
	 * @return mixed
	 */
	public function guard_rest_item( $response, $post ) {
		if ( $post instanceof WP_Post && ! self::can_view_post( $post->ID, get_current_user_id() ) ) {
			return new WP_REST_Response( null, 404 );
		}
		return $response;
	}

	/**
	 * Exclude comments on non-viewable handbook pages from a comment query.
	 *
	 * @param array<string, string> $clauses Comment query clauses.
	 * @param mixed                 $query   The comment query (unused).
	 * @return array<string, string>
	 */
	public function filter_comment_clauses( array $clauses, $query ): array {
		unset( $query );
		if ( ! self::should_filter_comments() ) {
			return $clauses;
		}
		$clauses['where'] = ( isset( $clauses['where'] ) ? $clauses['where'] : '' ) . self::hidden_comments_sql();
		return $clauses;
	}

	/**
	 * Exclude comments on non-viewable handbook pages from the comment feeds,
	 * which build their own query and do not run through comments_clauses.
	 *
	 * @param string $where The feed WHERE clause.
	 * @param mixed  $query The query (unused).
	 * @return string
	 */
	public function filter_comment_feed_where( string $where, $query ): string {
		unset( $query );
		if ( ! self::should_filter_comments() ) {
			return $where;
		}
		return $where . self::hidden_comments_sql();
	}

	/**
	 * Return a 404 for a single comment REST read on a non-viewable handbook
	 * page. The collection is already filtered by filter_comment_clauses; this
	 * closes the single-read path, which core serves from the comment cache and
	 * does not run through the comment query.
	 *
	 * @param mixed      $response The prepared response.
	 * @param WP_Comment $comment  The comment being prepared.
	 * @return mixed
	 */
	public function guard_rest_comment( $response, $comment ) {
		if ( ! self::should_filter_comments() || ! $comment instanceof WP_Comment ) {
			return $response;
		}
		$post_id = (int) $comment->comment_post_ID;
		if ( Handbook::POST_TYPE !== get_post_type( $post_id ) ) {
			return $response;
		}
		if ( self::can_view_post( $post_id, get_current_user_id() ) ) {
			return $response;
		}
		return new WP_REST_Response( null, 404 );
	}

	/**
	 * Whether the comment channels should be filtered for the current request.
	 * Not in wp-admin (moderators need every comment), and not for users who may
	 * edit others' posts (they may view every handbook anyway).
	 *
	 * @return bool
	 */
	private static function should_filter_comments(): bool {
		if ( is_admin() ) {
			return false;
		}
		if ( current_user_can( 'edit_others_posts' ) ) {
			return false;
		}
		return true;
	}

	/**
	 * SQL fragment that keeps a comment only when its post is not a handbook
	 * page, or is a handbook page in a handbook the current user may view. Built
	 * from fixed identifiers and integer term ids, so it needs no placeholders.
	 *
	 * @return string
	 */
	private static function hidden_comments_sql(): string {
		global $wpdb;

		$viewable = self::viewable_term_ids( get_current_user_id() );
		$in       = empty( $viewable ) ? '0' : implode( ',', array_map( 'absint', $viewable ) );
		$type     = esc_sql( Handbook::POST_TYPE );
		$taxonomy = esc_sql( Handbooks::TAXONOMY );

		return " AND ( {$wpdb->comments}.comment_post_ID NOT IN ("
			. " SELECT lhp.ID FROM {$wpdb->posts} lhp WHERE lhp.post_type = '{$type}'"
			. " ) OR {$wpdb->comments}.comment_post_ID IN ("
			. " SELECT lhtr.object_id FROM {$wpdb->term_relationships} lhtr"
			. " INNER JOIN {$wpdb->term_taxonomy} lhtt ON lhtt.term_taxonomy_id = lhtr.term_taxonomy_id"
			. " WHERE lhtt.taxonomy = '{$taxonomy}' AND lhtt.term_id IN ({$in})"
			. ' ) )';
	}

	/**
	 * The handbook term ids the given user may view.
	 *
	 * @param int $user_id User ID (0 for a guest).
	 * @return int[]
	 */
	public static function viewable_term_ids( int $user_id ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => Handbooks::TAXONOMY,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);
		if ( is_wp_error( $terms ) ) {
			return array();
		}
		$ids = array();
		foreach ( $terms as $term_id ) {
			if ( self::can_view_term( (int) $term_id, $user_id ) ) {
				$ids[] = (int) $term_id;
			}
		}
		return $ids;
	}

	/**
	 * Whether a user may view a handbook page.
	 *
	 * A page may belong to more than one handbook. Access is combined
	 * fail-closed: the page is viewable only when every handbook it belongs to
	 * allows the user, and a page without any handbook is not viewable.
	 *
	 * @param int $post_id Post ID.
	 * @param int $user_id User ID (0 for a guest).
	 * @return bool
	 */
	public static function can_view_post( int $post_id, int $user_id ): bool {
		if ( user_can( $user_id, 'edit_others_posts' ) ) {
			$allowed = true;
		} else {
			$terms = wp_get_object_terms( $post_id, Handbooks::TAXONOMY, array( 'fields' => 'ids' ) );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				$allowed = false;
			} else {
				$allowed = true;
				foreach ( $terms as $term_id ) {
					if ( ! self::can_view_term( (int) $term_id, $user_id ) ) {
						$allowed = false;
						break;
					}
				}
			}
		}

		/**
		 * Filter whether a user may view a handbook page.
		 *
		 * @param bool $allowed Whether access is granted.
		 * @param int  $post_id Post ID.
		 * @param int  $user_id User ID.
		 */
		return (bool) apply_filters( 'living_handbook_can_view_post', $allowed, $post_id, $user_id );
	}

	/**
	 * Whether a user may view a handbook (the grouping term).
	 *
	 * Memoized per request: the same term/user decision is reused rather than
	 * re-reading term meta and user data for every page of the handbook.
	 *
	 * @param int $term_id Handbook term ID.
	 * @param int $user_id User ID (0 for a guest).
	 * @return bool
	 */
	public static function can_view_term( int $term_id, int $user_id ): bool {
		$cache_key = $term_id . ':' . $user_id;
		if ( isset( self::$term_cache[ $cache_key ] ) ) {
			return self::$term_cache[ $cache_key ];
		}

		$result = self::evaluate_term( $term_id, $user_id );

		self::$term_cache[ $cache_key ] = $result;
		return $result;
	}

	/**
	 * Compute (uncached) whether a user may view a handbook term.
	 *
	 * @param int $term_id Handbook term ID.
	 * @param int $user_id User ID (0 for a guest).
	 * @return bool
	 */
	private static function evaluate_term( int $term_id, int $user_id ): bool {
		$visibility = (string) get_term_meta( $term_id, Handbooks::META_VISIBILITY, true );
		if ( '' === $visibility ) {
			$visibility = Handbooks::VISIBILITY_MEMBERS;
		}

		if ( Handbooks::VISIBILITY_PUBLIC === $visibility ) {
			return true;
		}
		if ( 0 === $user_id ) {
			return false;
		}
		if ( Handbooks::VISIBILITY_MEMBERS === $visibility ) {
			return true;
		}

		$users = array_map( 'intval', (array) get_term_meta( $term_id, Handbooks::META_USERS, true ) );
		if ( in_array( $user_id, $users, true ) ) {
			return true;
		}

		$roles = (array) get_term_meta( $term_id, Handbooks::META_ROLES, true );
		$user  = get_userdata( $user_id );
		if ( false !== $user ) {
			foreach ( (array) $user->roles as $role ) {
				if ( in_array( $role, $roles, true ) ) {
					return true;
				}
			}
		}

		return false;
	}
}
