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
	 * Query argument that marks an internal maintenance query.
	 *
	 * The read filters below protect the display path, but the plugin's own
	 * maintenance code reads handbook pages too: the scheduled sync looks up the
	 * pages it has to pull, the import looks up the page a source path already
	 * created, and the post-processor resolves internal links. Those run without
	 * a logged-in user and outside wp-admin (cron, the REST import endpoints), so
	 * the display filters would narrow them to public handbooks and the plugin
	 * would silently stop maintaining every internal handbook.
	 *
	 * A query that sets this argument to true opts out of the read filters. It is
	 * deliberately an opt-in flag rather than a blanket exemption for cron: an
	 * unmarked query stays filtered, so a forgotten call site fails closed, the
	 * way it does today. The value never comes from user input; it is only set by
	 * this plugin's own lookups, and it is not a registered public query var, so
	 * it cannot be injected through a URL.
	 */
	public const INTERNAL_QUERY_ARG = 'living_handbook_internal';

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
		// Coarse pre-query layer: restricts handbook queries to the viewable
		// handbooks independent of suppress_filters, which the_posts cannot reach.
		add_action( 'pre_get_posts', array( $this, 'restrict_query' ) );
		add_filter( 'the_posts', array( $this, 'filter_posts' ), 10, 2 );
		add_filter( 'rest_prepare_' . Handbook::POST_TYPE, array( $this, 'guard_rest_item' ), 10, 2 );

		// oEmbed is a read channel of its own, and it does not go through the post
		// query. The type is publicly_queryable, so is_post_publicly_viewable()
		// says yes and core answers /wp-json/oembed/1.0/embed with the page title
		// and the author's display name. WordPress 6.8 added is_post_embeddable(),
		// which closes this for a type that is not embeddable, but the plugin
		// supports 6.7, and a site can re-open it through the is_post_embeddable
		// filter. Gate the lookup and the response ourselves rather than relying
		// on the core version in use.
		add_filter( 'oembed_request_post_id', array( $this, 'guard_oembed_request' ), 10, 2 );
		add_filter( 'oembed_response_data', array( $this, 'guard_oembed_data' ), 10, 2 );

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
	 * Mark query arguments as an internal maintenance lookup.
	 *
	 * Wrap the arguments of a lookup the plugin makes about its own pages, so it
	 * is not narrowed to the handbooks the current user may read.
	 *
	 * @see AccessController::INTERNAL_QUERY_ARG
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return array<string, mixed>
	 */
	public static function internal( array $args ): array {
		$args[ self::INTERNAL_QUERY_ARG ] = true;
		return $args;
	}

	/**
	 * Whether a query is one of the plugin's own maintenance lookups.
	 *
	 * @see AccessController::INTERNAL_QUERY_ARG
	 *
	 * @param WP_Query $query The query being prepared or filtered.
	 * @return bool
	 */
	private static function is_internal_query( WP_Query $query ): bool {
		return true === $query->get( self::INTERNAL_QUERY_ARG );
	}

	/**
	 * Coarse pre-query access layer.
	 *
	 * Runs on pre_get_posts, so it also covers queries that set suppress_filters
	 * (the get_posts default) and would never reach the_posts, and front-end
	 * admin-ajax reads. It appends a tax_query that keeps only pages in a handbook
	 * the user may view. This is deliberately the coarse layer: it checks "at
	 * least one viewable handbook", while a page may belong to several handbooks
	 * and needs all of them to allow the user. The precise, fail-closed decision
	 * stays in filter_posts() and can_view_post() on the display path; this layer
	 * only closes the channels that bypass it.
	 *
	 * @param WP_Query $query The query being prepared.
	 * @return void
	 */
	public function restrict_query( WP_Query $query ): void {
		// The plugin's own maintenance lookups mark themselves and are not a
		// content read (see INTERNAL_QUERY_ARG).
		if ( self::is_internal_query( $query ) ) {
			return;
		}
		// admin-ajax.php always runs with is_admin() true, so a front-end AJAX
		// read would look like a back-end one. Back-end tools use admin-ajax too,
		// and they are let through, but only for users who may edit other
		// people's posts: the same gate the comment channel uses. An earlier
		// version allowed every edit_posts user here, reasoning that the classic
		// editor's link search (wp-link-ajax) needed it. It does not:
		// WP_Editor::wp_link_query() asks for post types registered as public,
		// and this type is not one, so it never appears there. The wider gate
		// only opened a path for any other plugin's admin-ajax handler that
		// happens to query handbook pages.
		if ( is_admin() && ( ! wp_doing_ajax() || current_user_can( 'edit_others_posts' ) ) ) {
			return;
		}
		if ( current_user_can( 'edit_others_posts' ) ) {
			return;
		}
		$types = (array) $query->get( 'post_type' );
		if ( ! in_array( Handbook::POST_TYPE, $types, true ) ) {
			return;
		}

		$viewable    = self::viewable_term_ids( get_current_user_id() );
		$restriction = array(
			'taxonomy' => Handbooks::TAXONOMY,
			'field'    => 'term_id',
			// An empty set must match nothing, not everything, so fall back to a
			// term id that no page carries.
			'terms'    => empty( $viewable ) ? array( 0 ) : $viewable,
		);

		$existing = $query->get( 'tax_query' );
		if ( empty( $existing ) || ! is_array( $existing ) ) {
			$query->set( 'tax_query', array( $restriction ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			return;
		}
		// Nest the existing tax_query and AND our restriction, so an existing OR
		// relation cannot dissolve the restriction.
		$query->set( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			'tax_query',
			array(
				'relation' => 'AND',
				$existing,
				$restriction,
			)
		);
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
		// The plugin's own maintenance lookups mark themselves and are not a
		// content read (see INTERNAL_QUERY_ARG). get_posts() suppresses this
		// filter anyway, but a plain WP_Query (the post-processor's link lookups)
		// does reach it.
		if ( self::is_internal_query( $query ) ) {
			return $posts;
		}

		// admin-ajax.php runs with is_admin() true. A front-end AJAX read must
		// still be filtered; back-end tools are let through for users who may
		// edit other people's posts, consistent with restrict_query() and the
		// comment channel.
		if ( is_admin() && ( ! wp_doing_ajax() || current_user_can( 'edit_others_posts' ) ) ) {
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
	 * Hide a non-viewable handbook page from the oEmbed lookup.
	 *
	 * @param int|mixed $post_id Post id core resolved from the URL.
	 * @param string    $url     The requested URL (unused).
	 * @return int|mixed 0 when the page may not be viewed.
	 */
	public function guard_oembed_request( $post_id, $url ) {
		unset( $url );
		$id = (int) $post_id;
		if ( $id > 0 && Handbook::POST_TYPE === get_post_type( $id ) && ! self::can_view_post( $id, get_current_user_id() ) ) {
			return 0;
		}
		return $post_id;
	}

	/**
	 * Empty the oEmbed payload of a non-viewable handbook page.
	 *
	 * The lookup filter above already covers the REST route; this one closes the
	 * paths that resolve the post themselves before asking for the data.
	 *
	 * @param array<string, mixed>|mixed $data The oEmbed response data.
	 * @param WP_Post|mixed              $post The post being described.
	 * @return array<string, mixed>|mixed
	 */
	public function guard_oembed_data( $data, $post ) {
		if ( $post instanceof WP_Post && Handbook::POST_TYPE === $post->post_type
			&& ! self::can_view_post( $post->ID, get_current_user_id() ) ) {
			return array();
		}
		return $data;
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
		// admin-ajax.php runs with is_admin() true; a front-end AJAX read must
		// still be filtered, so only a real wp-admin request bypasses this.
		// Unlike restrict_query() and filter_posts(), edit_posts does not open an
		// AJAX bypass here: comment bodies are content, and the capability to see
		// them is edit_others_posts (comment moderation), not the ability to edit
		// one's own posts. Keeping the stricter gate avoids exposing comments on
		// non-viewable handbooks to contributors.
		if ( is_admin() && ! wp_doing_ajax() ) {
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
	 * The handbook terms the given user may view, as full term objects.
	 *
	 * The single source for the three frontend lists of readable handbooks (the
	 * overview cards, the compact menu, and the navigation-block links), so the
	 * "load all handbooks, keep the ones this user may read" step lives in one
	 * place. Each caller renders the returned terms in its own markup.
	 *
	 * @param int $user_id User ID (0 for a guest).
	 * @return array<int, WP_Term>
	 */
	public static function readable_terms( int $user_id ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => Handbooks::TAXONOMY,
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) ) {
			return array();
		}
		$readable = array();
		foreach ( $terms as $term ) {
			if ( $term instanceof WP_Term && self::can_view_term( $term->term_id, $user_id ) ) {
				$readable[] = $term;
			}
		}
		return $readable;
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
