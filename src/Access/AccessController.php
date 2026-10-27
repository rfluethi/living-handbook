<?php
/**
 * Frontend access control, enforced per handbook.
 *
 * A handbook page is viewable when its handbook allows the current user.
 * Administrators read every handbook (reads_everything()); everybody else,
 * editors included, reads what a handbook grants them. A page in no handbook
 * is work in progress and readable only by those who look after other
 * people's pages (reads_unassigned()). In wp-admin the same rule keeps other
 * people's pages of a handbook the user may not read out of the lists and out
 * of the editor; one's own pages stay one's own.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Access;

use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use LivingHandbook\Training\Training;
use LivingHandbook\Setup\Settings;
use WP_Comment;
use WP_Comment_Query;
use WP_Post;
use WP_Query;
use WP_REST_Response;
use WP_Term;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Decides in one place who may read handbook content, and is wired into every
 * read path: on the front end single pages, handbook entry pages (term
 * archives), result sets (archives, search, REST collections, custom loops),
 * single REST reads, media, oEmbed and previous/next links; in the back end
 * the capabilities (map_meta_cap), the lists and the handbook term screens;
 * and the comment channels, reading (comment queries, feeds, single REST
 * reads) as well as writing (comments_open, pings_open), which core exposes
 * independently of the post query.
 */
final class AccessController {

	/**
	 * Query argument that marks an internal maintenance query.
	 *
	 * The read filters below protect the display path, but the plugin's own
	 * maintenance code reads handbook pages too: the scheduled sync looks up the
	 * pages it has to pull, the import looks up the page a source path already
	 * created, the post-processor resolves internal links, and the website
	 * export and the privacy tools collect pages. Many of them run without a
	 * logged-in user and outside wp-admin (cron, the REST import endpoints), so
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
	 * Re-entry guard for hide_handbooks_on_term_screen(), which asks get_terms()
	 * for the handbooks a user may not read while get_terms() is asking it.
	 *
	 * @var bool
	 */
	private static bool $listing_terms = false;

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
	 * Returns the post types this controller guards.
	 *
	 * Handbook pages, and learning paths, which carry the same handbook term and
	 * are therefore decided by exactly the same rules. The list is not narrowed
	 * when the learning paths module is switched off: a site that turns the
	 * module off still has its paths in the database, and an unguarded type would
	 * be the one moment they are readable.
	 *
	 * @return array<int, string>
	 */
	public static function guarded_post_types(): array {
		return array( Handbook::POST_TYPE, Training::POST_TYPE );
	}

	/**
	 * Whether a post is of a type this class guards.
	 *
	 * Every read channel asks this rather than comparing with the handbook type
	 * alone. The channels that compared with Handbook::POST_TYPE were the ones
	 * learning paths were missing from in 0.75.0: the media of a restricted
	 * path was readable through /wp/v2/media, its comments and its oEmbed card
	 * were not filtered.
	 *
	 * @param int $post_id Post id.
	 * @return bool
	 */
	private static function is_guarded( int $post_id ): bool {
		return in_array( get_post_type( $post_id ), self::guarded_post_types(), true );
	}

	/**
	 * Determines whether a user reads every handbook, whatever its settings say.
	 *
	 * Administrators, by the manage_options capability. Up to 0.75.1 it was
	 * everybody who may edit other people's posts, the editor role among them,
	 * and a handbook could not be kept from the editors. Since 0.76.0 (E-0075)
	 * an editor reads what a handbook grants them like everybody else. A site
	 * with roles of its own can name another capability.
	 *
	 * @param int $user_id User ID (0 for a guest).
	 * @return bool
	 */
	public static function reads_everything( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}

		/**
		 * Filters the capability that reads every handbook regardless of its
		 * visibility settings.
		 *
		 * @param string $capability Capability. Default 'manage_options'.
		 */
		$capability = (string) apply_filters( 'living_handbook_read_all_capability', 'manage_options' );

		return '' !== $capability && user_can( $user_id, $capability );
	}

	/**
	 * Determines whether a user reads the pages that are in no handbook yet.
	 *
	 * A page in no handbook is work in progress rather than content of any
	 * handbook, so whoever looks after other people's pages (edit_others_posts,
	 * the editor role and up) reads it, and nobody else does. The same people's
	 * admin-ajax tools count as back-end reads (is_backend_read()). This is the
	 * one exception to "a handbook decides" besides reads_everything(); every
	 * read channel asks here instead of naming the capability itself.
	 *
	 * @param int $user_id User ID (0 for a guest).
	 * @return bool
	 */
	public static function reads_unassigned( int $user_id ): bool {
		return $user_id > 0 && user_can( $user_id, 'edit_others_posts' );
	}

	/**
	 * Returns the handbooks a user may not read.
	 *
	 * @param int $user_id User ID (0 for a guest).
	 * @return int[]
	 */
	public static function hidden_term_ids( int $user_id ): array {
		return array_values( array_filter( self::handbook_ids(), static fn( int $term_id ): bool => ! self::can_view_term( $term_id, $user_id ) ) );
	}

	/**
	 * Returns the ids of every handbook of the site.
	 *
	 * @return array<int, int>
	 */
	private static function handbook_ids(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => Handbooks::TAXONOMY,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);
		return is_array( $terms ) ? array_map( 'intval', $terms ) : array();
	}

	/**
	 * Determines whether the current user may put pages into a handbook, or take
	 * them out of it: import into it, move pages into it, export it.
	 *
	 * Whoever may not read a handbook may not write into it either. A bundle
	 * update into a handbook kept to administrators would otherwise overwrite
	 * its pages, and an export would hand them out. The scheduled sync and
	 * WP-CLI run without a user and are the site itself.
	 *
	 * @param int $term_id Handbook term ID.
	 * @return bool
	 */
	public static function may_use_handbook( int $term_id ): bool {
		$user_id = get_current_user_id();
		if ( 0 === $user_id && ( wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) ) {
			return true;
		}
		return self::reads_everything( $user_id ) || self::can_view_term( $term_id, $user_id );
	}

	/**
	 * Returns the handbooks of a list the current user may use.
	 *
	 * For the plugin's own pickers: the import target, the export, the bulk move.
	 *
	 * @param array<int|string, mixed> $terms Terms.
	 * @return array<int, WP_Term>
	 */
	public static function usable_handbooks( array $terms ): array {
		$out = array();
		foreach ( $terms as $term ) {
			if ( $term instanceof WP_Term && self::may_use_handbook( (int) $term->term_id ) ) {
				$out[] = $term;
			}
		}
		return $out;
	}

	/**
	 * Whether a post belongs to a handbook the user may not read.
	 *
	 * @param int|WP_Post $post    Post.
	 * @param int         $user_id User ID.
	 * @return bool
	 */
	private static function in_hidden_handbook( int|WP_Post $post, int $user_id ): bool {
		$terms = get_the_terms( $post, Handbooks::TAXONOMY );
		if ( ! is_array( $terms ) ) {
			return false;
		}
		foreach ( $terms as $term ) {
			if ( $term instanceof WP_Term && ! self::can_view_term( $term->term_id, $user_id ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether a post appears in the back end for a user who does not read
	 * everything.
	 *
	 * The back end shows more than the front end: pages in no handbook yet, and
	 * one's own pages wherever they are, because that is where they are worked
	 * on. What it does not show is somebody else's page in a handbook the user
	 * may not read, nor that page's files.
	 *
	 * @param WP_Post $post    Post.
	 * @param int     $user_id User ID.
	 * @return bool
	 */
	private static function visible_in_backend( WP_Post $post, int $user_id ): bool {
		if ( (int) $post->post_author === $user_id ) {
			return true;
		}
		if ( in_array( $post->post_type, self::guarded_post_types(), true ) ) {
			return ! self::in_hidden_handbook( $post, $user_id );
		}
		if ( 'attachment' === $post->post_type && $post->post_parent > 0 && self::is_guarded( (int) $post->post_parent ) ) {
			$parent = get_post( (int) $post->post_parent );
			return ! $parent instanceof WP_Post || self::visible_in_backend( $parent, $user_id );
		}
		return true;
	}

	/**
	 * Other people's pages in handbooks the user may not read, by id.
	 *
	 * For the back-end lists. A tax_query cannot say "or my own", and a
	 * posts_where filter would not reach get_posts(), which suppresses filters;
	 * post__not_in is set in pre_get_posts and holds for both.
	 *
	 * @param int $user_id User ID.
	 * @return int[]
	 */
	private static function hidden_post_ids( int $user_id ): array {
		$hidden = self::hidden_term_ids( $user_id );
		if ( array() === $hidden ) {
			return array();
		}

		global $wpdb;

		$hidden = array_map( 'absint', $hidden );
		$types  = self::guarded_post_types();

		// Every value is a placeholder, so the query is prepared as a whole.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the answer depends on the user and on every page's membership, which no cache group tracks.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT p.ID FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
				INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
				WHERE tt.taxonomy = %s
				AND tt.term_id IN (" . implode( ', ', array_fill( 0, count( $hidden ), '%d' ) ) . ')
				AND p.post_type IN (' . implode( ', ', array_fill( 0, count( $types ), '%s' ) ) . ')
				AND p.post_author <> %d',
				array_merge( array( Handbooks::TAXONOMY ), $hidden, $types, array( $user_id ) )
			)
		);

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Whether this request is a back-end read: wp-admin itself, or an
	 * admin-ajax tool of somebody who reads pages in no handbook
	 * (reads_unassigned()).
	 *
	 * The file admin-ajax.php always runs with is_admin() true, so a front-end
	 * AJAX read would look like a back-end one; only the second kind is let in.
	 *
	 * @return bool
	 */
	private static function is_backend_read(): bool {
		return is_admin() && ( ! wp_doing_ajax() || self::reads_unassigned( get_current_user_id() ) );
	}

	/**
	 * Closes editing, deleting and reading of other people's pages in a handbook
	 * the user may not read, and the settings of such a handbook.
	 *
	 * The front-end rule alone is no rule when the back end hands out the same
	 * page: an editor who may not read a handbook kept to administrators would
	 * open its pages in the editor, and change its visibility in the handbook
	 * settings (manage_categories, which every editor has). One's own pages stay
	 * editable wherever they are (E-0069), and the files of a page follow it.
	 * map_meta_cap is asked everywhere, not only in wp-admin, so this also holds
	 * for the block editor's REST requests and for may_edit().
	 *
	 * @param string[]|mixed $caps    Primitive capabilities required.
	 * @param string|mixed   $cap     The capability asked for.
	 * @param int|mixed      $user_id User ID.
	 * @param array<mixed>   $args    The object id first.
	 * @return string[]
	 */
	public function guard_meta_caps( $caps, $cap, $user_id, $args ): array {
		$caps    = (array) $caps;
		$object  = isset( $args[0] ) && is_numeric( $args[0] ) ? (int) $args[0] : 0;
		$user_id = (int) $user_id;
		if ( $object <= 0 ) {
			return $caps;
		}

		if ( in_array( $cap, array( 'edit_post', 'delete_post', 'read_post', 'publish_post', 'edit_page', 'delete_page', 'read_page' ), true ) ) {
			$post = get_post( $object );
			if ( ! $post instanceof WP_Post ) {
				return $caps;
			}
			$guarded = in_array( $post->post_type, self::guarded_post_types(), true )
				|| ( 'attachment' === $post->post_type && $post->post_parent > 0 && self::is_guarded( (int) $post->post_parent ) );
			if ( ! $guarded || self::reads_everything( $user_id ) || self::visible_in_backend( $post, $user_id ) ) {
				return $caps;
			}
			return array( 'do_not_allow' );
		}

		if ( in_array( $cap, array( 'edit_term', 'delete_term', 'assign_term' ), true ) ) {
			$term = get_term( $object );
			if ( ! $term instanceof WP_Term || Handbooks::TAXONOMY !== $term->taxonomy ) {
				return $caps;
			}
			if ( self::reads_everything( $user_id ) || self::can_view_term( (int) $term->term_id, $user_id ) ) {
				return $caps;
			}
			return array( 'do_not_allow' );
		}

		return $caps;
	}

	/**
	 * Leaves the handbooks a user may not read out of the handbook screens.
	 *
	 * Their settings are closed already (guard_meta_caps()); this keeps their
	 * names and page counts out of the list, and out of the parent selector on
	 * the add form (edit-tags.php) and on the edit screen of a handbook the user
	 * may edit (term.php). Up to 0.75.1 the edit screen was not covered and
	 * listed every handbook as a possible parent.
	 *
	 * @param array<string, mixed>|mixed $args       get_terms() arguments.
	 * @param string[]|mixed             $taxonomies Taxonomies asked for.
	 * @return array<string, mixed>|mixed
	 */
	public function hide_handbooks_on_term_screen( $args, $taxonomies ) {
		global $pagenow;
		if ( self::$listing_terms || ! is_array( $args ) || ! is_admin() || wp_doing_ajax() || ! in_array( $pagenow, array( 'edit-tags.php', 'term.php' ), true ) ) {
			return $args;
		}
		if ( ! in_array( Handbooks::TAXONOMY, (array) $taxonomies, true ) ) {
			return $args;
		}
		$user_id = get_current_user_id();
		if ( self::reads_everything( $user_id ) ) {
			return $args;
		}

		self::$listing_terms = true;
		$hidden              = self::hidden_term_ids( $user_id );
		self::$listing_terms = false;

		// The edit screen preselects the handbook's current parent. Were that
		// parent left out, saving the form would quietly move the handbook to
		// the top level, so its name stays: the lesser harm of the two.
		if ( 'term.php' === $pagenow ) {
			$hidden = array_values( array_diff( $hidden, array( self::edited_term_parent() ) ) );
		}

		if ( array() !== $hidden ) {
			$args['exclude'] = array_merge( wp_parse_id_list( $args['exclude'] ?? array() ), $hidden );
		}
		return $args;
	}

	/**
	 * The parent of the handbook open on the edit screen, or 0.
	 *
	 * @return int
	 */
	private static function edited_term_parent(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reads which handbook the edit screen shows, as core does; nothing is written.
		$term_id = isset( $_GET['tag_ID'] ) ? absint( wp_unslash( $_GET['tag_ID'] ) ) : 0;
		$term    = $term_id > 0 ? get_term( $term_id, Handbooks::TAXONOMY ) : null;

		return $term instanceof WP_Term ? (int) $term->parent : 0;
	}

	/**
	 * Registers the hooks with WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'template_redirect', array( $this, 'guard_singular' ) );
		add_action( 'template_redirect', array( $this, 'guard_term_archive' ) );
		// Priority 9: before redirect_canonical (10), which on a site with
		// attachment pages switched off answers the attachment page with a
		// redirect to the file and would hand out its address first.
		add_action( 'template_redirect', array( $this, 'guard_attachment' ), 9 );
		// Coarse pre-query layer: restricts handbook queries to the viewable
		// handbooks independent of suppress_filters, which the_posts cannot reach.
		add_action( 'pre_get_posts', array( $this, 'restrict_query' ) );
		add_filter( 'the_posts', array( $this, 'filter_posts' ), 10, 2 );
		foreach ( self::guarded_post_types() as $guarded_type ) {
			add_filter( 'rest_prepare_' . $guarded_type, array( $this, 'guard_rest_item' ), 10, 2 );
		}

		// Media is a read channel of its own. An image imported into a handbook
		// page is an attachment whose parent is that page, and core answers
		// /wp/v2/media for it: an attachment inherits its status from the parent,
		// and the parent is published, so the check passes and title, alt text and
		// file URL are handed out. The attachment has to inherit the parent's
		// visibility instead. Note what this cannot do: the file itself stays
		// readable under its uploads URL, which only a server rule can prevent.
		add_filter( 'rest_prepare_attachment', array( $this, 'guard_rest_attachment' ), 10, 2 );

		// oEmbed is a read channel of its own, and it does not go through the post
		// query. The type is publicly_queryable, so is_post_publicly_viewable()
		// says yes and core answers /wp-json/oembed/1.0/embed with the page title
		// and the author's display name. WordPress 6.8 added is_post_embeddable(),
		// which closes this for a type that is not embeddable, and 6.8 is the
		// plugin's minimum. It is still not enough on its own: a site, a theme or
		// another plugin can re-open it through the is_post_embeddable filter. Gate
		// the lookup and the response ourselves rather than relying on that filter
		// staying untouched.
		add_filter( 'oembed_request_post_id', array( $this, 'guard_oembed_request' ), 10, 2 );
		add_filter( 'oembed_response_data', array( $this, 'guard_oembed_data' ), 10, 2 );

		// Comments are a separate read channel that the post query does not
		// cover: gate the comment queries, the comment feeds, and single comment
		// REST reads, so comments on a handbook a user may not view do not leak.
		add_action( 'pre_get_comments', array( $this, 'separate_comment_cache' ) );
		add_filter( 'comments_clauses', array( $this, 'filter_comment_clauses' ), 10, 2 );
		add_filter( 'comment_feed_where', array( $this, 'filter_comment_feed_where' ), 10, 2 );
		add_filter( 'rest_prepare_comment', array( $this, 'guard_rest_comment' ), 10, 2 );

		// Writing a comment is a channel of its own as well. Up to 0.75.1 only the
		// reading side was guarded: a guest could post to wp-comments-post.php, and
		// a signed-in reader to POST /wp/v2/comments, on a page of a handbook they
		// may not read, and only the answer came back as a 404. Both core paths ask
		// comments_open() before they store anything, and so do XML-RPC and the
		// comment form, so the decision is made there. Priority 100 runs after
		// Handbooks::filter_comments_open() (99): a handbook set to "comments open"
		// must not be able to reopen what the access rule has closed. Pings are
		// the same write through a different door.
		add_filter( 'comments_open', array( $this, 'guard_comments_open' ), 100, 2 );
		add_filter( 'pings_open', array( $this, 'guard_comments_open' ), 100, 2 );

		// The previous/next post links are a read channel that bypasses WP_Query:
		// get_adjacent_post() writes its own SQL, so neither restrict_query() nor
		// filter_posts() sees it. In a classic theme, the_post_navigation() on a
		// public handbook page handed a guest the title and the address of the
		// neighbouring page by date, whichever handbook that page was in.
		add_filter( 'get_previous_post_where', array( $this, 'filter_adjacent_where' ), 10, 5 );
		add_filter( 'get_next_post_where', array( $this, 'filter_adjacent_where' ), 10, 5 );

		// The back end is a read channel too, and a write channel: see
		// guard_meta_caps().
		add_filter( 'map_meta_cap', array( $this, 'guard_meta_caps' ), 10, 4 );
		add_filter( 'get_terms_args', array( $this, 'hide_handbooks_on_term_screen' ), 10, 2 );
	}

	/**
	 * Keeps the previous/next post of a guarded page inside what the user may
	 * view.
	 *
	 * The neighbour has to be in a handbook the user may view, and in none they
	 * may not: the fail-closed rule of can_view_post(), expressed in SQL. It is
	 * stricter in two ways, both in the safe direction: a page in no handbook is
	 * never a neighbour, not even for reads_unassigned(), because previous/next
	 * leads readers through a handbook and such a page is in none; and the
	 * living_handbook_can_view_post filter, which SQL cannot express, is not
	 * applied.
	 *
	 * The clause ends up in core's cache key for the adjacent post (an md5 of the
	 * whole query), so two users with different handbooks do not share an entry.
	 *
	 * @param string       $where          The WHERE clause, already prepared.
	 * @param bool         $in_same_term   Unused.
	 * @param int[]|string $excluded_terms Unused.
	 * @param string       $taxonomy       Unused.
	 * @param WP_Post|null $post           The post the neighbour is looked up for.
	 * @return string
	 */
	public function filter_adjacent_where( $where, $in_same_term = false, $excluded_terms = '', $taxonomy = '', $post = null ): string {
		unset( $in_same_term, $excluded_terms, $taxonomy );
		$where = (string) $where;

		if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, self::guarded_post_types(), true ) ) {
			return $where;
		}
		$user_id = get_current_user_id();
		if ( self::reads_everything( $user_id ) ) {
			return $where;
		}

		global $wpdb;

		$viewable = self::viewable_term_ids( $user_id );
		$in       = empty( $viewable ) ? '0' : implode( ',', array_map( 'absint', $viewable ) );
		$taxonomy = esc_sql( Handbooks::TAXONOMY );
		$terms    = "SELECT lhtr.object_id FROM {$wpdb->term_relationships} lhtr"
			. " INNER JOIN {$wpdb->term_taxonomy} lhtt ON lhtt.term_taxonomy_id = lhtr.term_taxonomy_id"
			. " WHERE lhtt.taxonomy = '{$taxonomy}' AND lhtt.term_id";

		return $where
			. " AND p.ID IN ( {$terms} IN ({$in}) )"
			. " AND p.ID NOT IN ( {$terms} NOT IN ({$in}) )";
	}

	/**
	 * Closes comments and pings on a guarded post the current user may not view.
	 *
	 * @param bool|mixed       $open    Whether comments (or pings) are open.
	 * @param int|string|mixed $post_id Post id.
	 * @return bool
	 */
	public function guard_comments_open( $open, $post_id ): bool {
		$open = (bool) $open;
		if ( ! $open ) {
			return false;
		}
		$post_id = (int) $post_id;
		if ( $post_id <= 0 || ! self::is_guarded( $post_id ) ) {
			return $open;
		}

		return self::can_view_post( $post_id, get_current_user_id() );
	}

	/**
	 * Blocks direct access to a single handbook page the user may not view.
	 *
	 * @return void
	 */
	public function guard_singular(): void {
		if ( ! is_singular( self::guarded_post_types() ) ) {
			return;
		}

		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		if ( self::can_view_post( $post->ID, get_current_user_id() ) || self::may_edit( $post->ID ) ) {
			return;
		}

		$this->deny();
	}

	/**
	 * Whether the current user may edit this post, by WordPress's own rules.
	 *
	 * Somebody who may edit a page sees its whole content in the editor anyway,
	 * so refusing them the page itself protects nothing and breaks their work.
	 * Up to 0.75.1 it did: an author's own new page has no handbook yet, an
	 * author's page in a restricted handbook is outside what the author may
	 * read, and in both cases the block editor got a 404 for the page it was
	 * editing, the preview was refused, and saving reported an error although
	 * the change had been stored. The edit_post capability is the right line:
	 * an author passes it for their own pages only, a contributor for their own
	 * drafts only. An editor passes it for other people's pages too, but
	 * guard_meta_caps() denies it for pages in a handbook the editor may not
	 * read, so nobody reads a page of somebody else this way that they could not
	 * read anyway.
	 *
	 * This is deliberately not part of can_view_post(). Lists, search, the
	 * navigation and the entry page still follow the handbook's rules, so an
	 * author's own page in a handbook they may not read does not pull the rest
	 * of that handbook into view.
	 *
	 * @param int $post_id Post id.
	 * @return bool
	 */
	private static function may_edit( int $post_id ): bool {
		return $post_id > 0 && current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Blocks the attachment page of a file that belongs to a page the user may
	 * not view.
	 *
	 * The REST and query channels already let an attachment inherit the
	 * visibility of its parent page (guard_rest_attachment(), filter_posts()),
	 * but the attachment page itself (?attachment_id=N, or its pretty URL) is a
	 * singular view of type attachment, which guard_singular() does not look at.
	 * A guest could open it for an image of an internal page and read its title,
	 * caption and description, see the image, and follow the link back to the
	 * parent; with attachment pages switched off core redirected them to the file
	 * instead, which is the address the REST guard keeps to itself. Both now get
	 * the same answer as the page itself.
	 *
	 * @return void
	 */
	public function guard_attachment(): void {
		if ( ! is_attachment() ) {
			return;
		}

		$attachment = get_queried_object();
		if ( ! $attachment instanceof WP_Post || $attachment->post_parent <= 0 ) {
			return;
		}

		// Whoever may edit the parent page may open its files too, as they may
		// open the page itself in guard_singular().
		$parent = (int) $attachment->post_parent;
		if ( ! self::is_guarded( $parent ) || self::can_view_post( $parent, get_current_user_id() ) || self::may_edit( $parent ) ) {
			return;
		}

		$this->deny();
	}

	/**
	 * Blocks direct access to a handbook entry page (term archive) the user may
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
	 * Denies the current request.
	 *
	 * A guest is sent to the login form, which returns them to the page they
	 * asked for once they are signed in. A signed-in visitor who still may not
	 * read the handbook gets a 403 and an explanation: the page exists, it is
	 * their account that lacks access, and someone has to grant it. A 404 was
	 * wrong here; it says the address is invalid and leaves people guessing.
	 *
	 * A site can point the "No-access page" setting at one of its own pages to
	 * explain access in its own words, and the message and heading of the
	 * built-in fallback can be replaced through filters.
	 *
	 * @return void
	 */
	private function deny(): void {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}

		nocache_headers();

		// A no-access page that this visitor may not read either would send them
		// round in a loop. Settings refuses a guarded page, but a value stored
		// before that check, or written around it, still reaches this point, so
		// such a page is skipped here and the built-in message answers instead.
		$page_id = (int) get_option( Settings::OPTION_DENIED_PAGE, 0 );
		if ( $page_id > 0 && self::is_guarded( $page_id ) && ! self::can_view_post( $page_id, get_current_user_id() ) ) {
			$page_id = 0;
		}
		if ( $page_id > 0 && 'publish' === get_post_status( $page_id ) ) {
			$url = get_permalink( $page_id );
			if ( is_string( $url ) && '' !== $url ) {
				// A redirect cannot carry the 403, so the chosen page answers with
				// its own status. That is the price of letting a site design this
				// page in its theme.
				wp_safe_redirect( $url, 302 );
				exit;
			}
		}

		$user = wp_get_current_user();

		$title   = __( 'No access to this handbook', 'living-handbook' );
		$message = sprintf(
			/* translators: %s: display name of the signed-in user. */
			__( 'You are signed in as %s, but your account does not have access to this handbook. Ask an administrator or the team that maintains the handbook to grant you access.', 'living-handbook' ),
			$user->display_name
		);

		/**
		 * Filters the heading of the built-in no-access page.
		 *
		 * @param string $title Heading.
		 */
		$title = (string) apply_filters( 'living_handbook_access_denied_title', $title );

		/**
		 * Filters the message of the built-in no-access page.
		 *
		 * @param string  $message Message shown to the signed-in visitor.
		 * @param WP_User $user    The signed-in user.
		 */
		$message = (string) apply_filters( 'living_handbook_access_denied_message', $message, $user );

		$body = '<h1>' . esc_html( $title ) . '</h1><p>' . esc_html( $message ) . '</p>'
			. '<p><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Back to the site', 'living-handbook' ) . '</a></p>';

		wp_die(
			wp_kses_post( $body ),
			esc_html( $title ),
			array(
				'response'  => 403,
				'back_link' => true,
			)
		);
	}

	/**
	 * Marks query arguments as an internal maintenance lookup.
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
	 * Adds the coarse access restriction to a query before it runs.
	 *
	 * Runs on pre_get_posts, so it also covers queries that set suppress_filters
	 * (the get_posts default) and would never reach the_posts, and front-end
	 * admin-ajax reads. What it adds depends on who asks and where:
	 *
	 * - A back-end read (is_backend_read()): post__not_in for other people's
	 *   pages in a handbook the user may not read, the rule of
	 *   visible_in_backend(). A tax_query cannot say "or my own".
	 * - A user who reads pages in no handbook (reads_unassigned()): a NOT IN
	 *   tax_query on the handbooks they may not read, so those pages stay.
	 * - Everybody else: a tax_query on the handbooks they may view.
	 *
	 * The last two are deliberately coarse: they check "at least one viewable
	 * handbook", while a page may belong to several handbooks and needs all of
	 * them to allow the user. The precise, fail-closed decision stays in
	 * filter_posts() and can_view_post() on the display path; this layer only
	 * closes the channels that bypass it.
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
		// A singular main query is left alone: guard_singular() decides it on
		// template_redirect and can tell the visitor why they are not allowed in.
		// Filtering it here would empty the query first, and WordPress would
		// answer with a bare 404 before the guard ever runs.
		if ( $query->is_main_query() && $query->is_singular() ) {
			return;
		}
		$user_id = get_current_user_id();
		if ( self::reads_everything( $user_id ) ) {
			return;
		}
		$types = (array) $query->get( 'post_type' );
		if ( array() === array_intersect( self::guarded_post_types(), $types ) ) {
			return;
		}

		// A back-end read keeps pages in no handbook and one's own pages, which is
		// what the back end is for, and leaves out other people's pages in a
		// handbook the user may not read. Up to 0.75.1 it left out nothing.
		if ( self::is_backend_read() ) {
			$hidden = self::hidden_post_ids( $user_id );
			if ( array() !== $hidden ) {
				$query->set( 'post__not_in', array_values( array_unique( array_merge( wp_parse_id_list( $query->get( 'post__not_in' ) ), $hidden ) ) ) );
			}
			return;
		}

		if ( self::reads_unassigned( $user_id ) ) {
			// They also read pages in no handbook, so their restriction names
			// what they may not read rather than what they may.
			$hidden = self::hidden_term_ids( $user_id );
			if ( array() === $hidden ) {
				return;
			}
			$restriction = array(
				'taxonomy'         => Handbooks::TAXONOMY,
				'field'            => 'term_id',
				'terms'            => $hidden,
				'operator'         => 'NOT IN',
				'include_children' => false,
			);
		} else {
			$viewable    = self::viewable_term_ids( $user_id );
			$restriction = array(
				'taxonomy' => Handbooks::TAXONOMY,
				'field'    => 'term_id',
				// An empty set must match nothing, not everything, so fall back to
				// a term id that no page carries.
				'terms'    => empty( $viewable ) ? array( 0 ) : $viewable,
			);
		}

		$existing = $query->get( 'tax_query' );
		if ( empty( $existing ) || ! is_array( $existing ) ) {
			$query->set( 'tax_query', array( $restriction ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- The access restriction itself; a handbook query without it would hand out every page.
			return;
		}
		// Nest the existing tax_query and AND our restriction, so an existing OR
		// relation cannot dissolve the restriction.
		$query->set( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- See above.
			'tax_query',
			array(
				'relation' => 'AND',
				$existing,
				$restriction,
			)
		);
	}

	/**
	 * Removes the posts the current user may not read from a result set.
	 *
	 * In wp-admin by the back-end rule (visible_in_backend()), everywhere else
	 * by can_view_post(); attachments follow the page they belong to.
	 *
	 * This runs on the_posts, which core applies to full-object queries.
	 * Core returns id-only and id=>parent queries (fields => 'ids') before
	 * the_posts runs, so those never reach this filter. That is acceptable: a
	 * bare id exposes no content, and every actual content read (single page,
	 * entry page, single REST item, comments) is guarded on its own.
	 *
	 * @param WP_Post[] $posts Posts returned by the query.
	 * @param WP_Query  $query The query; the plugin's internal lookups and the
	 *                         singular main query pass unchanged.
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

		// Same reasoning as in restrict_query(): the singular main query belongs
		// to guard_singular(), which answers with an explanation instead of an
		// empty result that turns into a 404.
		if ( $query->is_main_query() && $query->is_singular() ) {
			return $posts;
		}

		$user_id = get_current_user_id();
		if ( self::reads_everything( $user_id ) ) {
			return $posts;
		}

		// A back-end read follows the back-end rule, as in restrict_query(). The
		// media library in particular is read here: the files of somebody else's
		// page in a handbook the user may not read stay out of it.
		if ( self::is_backend_read() ) {
			self::prime_post_terms( $posts, false !== $query->get( 'update_post_term_cache', true ) );
			return array_values(
				array_filter(
					$posts,
					static fn( $post ): bool => ! $post instanceof WP_Post || self::visible_in_backend( $post, $user_id )
				)
			);
		}

		// Read the handbook membership of the whole result set in one query
		// instead of one per row. Without this, filtering a set costs a query per
		// post, which a navigation tree over a few thousand pages turns into a few
		// thousand queries. can_view_post() reads through the same cache, so
		// priming it here is what makes the decision a cache hit.
		self::prime_post_terms( $posts, false !== $query->get( 'update_post_term_cache', true ) );

		return self::drop_unreadable( $posts, $user_id );
	}

	/**
	 * Returns the posts of a set the current user may read, for posts that were
	 * not read through WP_Query.
	 *
	 * The same decision filter_posts() and restrict_query() make for a query, in
	 * one call, for a caller that loads its rows itself: PageTree reads the few
	 * columns a navigation needs instead of whole posts, and WP_Query cannot do
	 * that. In the back end it is visible_in_backend(). Elsewhere both layers are
	 * applied: the coarse one, as restrict_query() adds it to a query (no hidden
	 * handbook for reads_unassigned(), at least one readable handbook for
	 * everybody else), and the precise one (can_view_post(), with its filter,
	 * per page), so a page that is dropped from a query is dropped here as well.
	 *
	 * Unlike filter_posts(), this puts nothing into the post cache. The posts
	 * handed in may be partial objects, and a partial object in the cache would
	 * be what get_post() returns everywhere else for the rest of the request.
	 * Only the handbook membership is read, into the term relationship cache.
	 *
	 * @param array<int, mixed> $posts Posts to decide on.
	 * @return array<int, mixed> The readable ones, in their order, reindexed.
	 */
	public static function readable_posts( array $posts ): array {
		$user_id = get_current_user_id();
		if ( self::reads_everything( $user_id ) ) {
			return array_values( $posts );
		}

		$guarded = array();
		foreach ( $posts as $post ) {
			if ( $post instanceof WP_Post && in_array( $post->post_type, self::guarded_post_types(), true ) ) {
				$guarded[] = (int) $post->ID;
			}
		}
		self::prime_handbook_terms( $guarded );

		if ( self::is_backend_read() ) {
			return array_values(
				array_filter(
					$posts,
					static fn( $post ): bool => ! $post instanceof WP_Post || self::visible_in_backend( $post, $user_id )
				)
			);
		}

		// The coarse layer, as restrict_query() would have added it to a query.
		// can_view_post() alone is not the same thing: its filter can let a page
		// through that no readable handbook holds, and in a query such a page
		// never reaches the filter.
		if ( self::reads_unassigned( $user_id ) ) {
			$posts = array_filter(
				$posts,
				static fn( $post ): bool => ! $post instanceof WP_Post
					|| ! in_array( $post->post_type, self::guarded_post_types(), true )
					|| ! self::in_hidden_handbook( $post, $user_id )
			);
		} else {
			$viewable = array_flip( self::viewable_term_ids( $user_id ) );
			$posts    = array_filter(
				$posts,
				static function ( $post ) use ( $viewable ): bool {
					if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, self::guarded_post_types(), true ) ) {
						return true;
					}
					$terms = get_the_terms( $post, Handbooks::TAXONOMY );
					if ( ! is_array( $terms ) ) {
						return false;
					}
					foreach ( $terms as $term ) {
						if ( $term instanceof WP_Term && isset( $viewable[ $term->term_id ] ) ) {
							return true;
						}
					}
					return false;
				}
			);
		}

		return self::drop_unreadable( $posts, $user_id );
	}

	/**
	 * Drops the posts a user may not read: guarded posts by can_view_post(), and
	 * attachments by the page they belong to.
	 *
	 * @param array<int|string, mixed> $posts   Posts to decide on.
	 * @param int                      $user_id User ID (0 for a guest).
	 * @return array<int, mixed>
	 */
	private static function drop_unreadable( array $posts, int $user_id ): array {
		return array_values(
			array_filter(
				$posts,
				static function ( $post ) use ( $user_id ): bool {
					if ( ! $post instanceof WP_Post ) {
						return true;
					}
					if ( in_array( $post->post_type, self::guarded_post_types(), true ) ) {
						// The object, not the id: get_the_terms() then works on the
						// post it is handed, where an id would make it load the
						// post first, which for a partial post from readable_posts()
						// is one query per row.
						return self::can_view_post( $post, $user_id );
					}
					// An attachment of a handbook page inherits that page's
					// visibility: the media library of an internal handbook is part
					// of its content.
					if ( 'attachment' === $post->post_type && $post->post_parent > 0
						&& self::is_guarded( (int) $post->post_parent ) ) {
						return self::can_view_post( (int) $post->post_parent, $user_id );
					}
					return true;
				}
			)
		);
	}

	/**
	 * Returns a 404 for a single REST read of an attachment that belongs to a
	 * handbook page the user may not view.
	 *
	 * @param mixed   $response The prepared response.
	 * @param WP_Post $post     The attachment being prepared.
	 * @return mixed
	 */
	public function guard_rest_attachment( $response, $post ) {
		if ( ! $post instanceof WP_Post || $post->post_parent <= 0 ) {
			return $response;
		}
		if ( ! self::is_guarded( (int) $post->post_parent ) ) {
			return $response;
		}
		// The block editor loads the page's images through this route; an author
		// whose own page sits in a handbook they may not read must get them, as
		// they get the page (see may_edit()).
		if ( self::can_view_post( (int) $post->post_parent, get_current_user_id() ) || self::may_edit( (int) $post->post_parent ) ) {
			return $response;
		}
		return new WP_REST_Response( null, 404 );
	}

	/**
	 * Hides a non-viewable handbook page from the oEmbed lookup.
	 *
	 * @param int|mixed $post_id Post id core resolved from the URL.
	 * @param string    $url     The requested URL (unused).
	 * @return int|mixed 0 when the page may not be viewed.
	 */
	public function guard_oembed_request( $post_id, $url ) {
		unset( $url );
		$id = (int) $post_id;
		if ( $id > 0 && self::is_guarded( $id ) && ! self::can_view_post( $id, get_current_user_id() ) ) {
			return 0;
		}
		return $post_id;
	}

	/**
	 * Empties the oEmbed payload of a non-viewable handbook page.
	 *
	 * The lookup filter above already covers the REST route; this one closes the
	 * paths that resolve the post themselves before asking for the data.
	 *
	 * @param array<string, mixed>|mixed $data The oEmbed response data.
	 * @param WP_Post|mixed              $post The post being described.
	 * @return array<string, mixed>|mixed
	 */
	public function guard_oembed_data( $data, $post ) {
		if ( $post instanceof WP_Post && in_array( $post->post_type, self::guarded_post_types(), true )
			&& ! self::can_view_post( $post->ID, get_current_user_id() ) ) {
			return array();
		}
		return $data;
	}

	/**
	 * Returns a 404 for single REST reads of non-viewable handbook pages.
	 *
	 * @param mixed   $response The prepared response.
	 * @param WP_Post $post     The post being prepared.
	 * @return mixed
	 */
	public function guard_rest_item( $response, $post ) {
		// See may_edit(): the block editor reads and saves the page through this
		// route, so whoever may edit it must get it back.
		if ( $post instanceof WP_Post && ! self::can_view_post( $post->ID, get_current_user_id() ) && ! self::may_edit( $post->ID ) ) {
			return new WP_REST_Response( null, 404 );
		}
		return $response;
	}

	/**
	 * Keeps the cached result of a comment query to the readers it was made for.
	 *
	 * WP_Comment_Query caches the ids it found under a key built from its
	 * arguments alone, and comments_clauses, where filter_comment_clauses()
	 * narrows the query, runs only when the cache misses. Without this, the
	 * first reader's answer was served to everybody who asked the same
	 * question: with a persistent object cache, a guest got the comments a
	 * member had been shown, internal pages included. cache_domain is the
	 * argument core puts into the key for exactly this; it gets a fingerprint
	 * of the read rule (back end or site, the readable handbooks,
	 * reads_unassigned(), and in the back end the user, whose own pages count),
	 * appended to any domain the caller set.
	 *
	 * @param WP_Comment_Query|mixed $query The comment query being prepared.
	 * @return void
	 */
	public function separate_comment_cache( $query ): void {
		if ( ! $query instanceof WP_Comment_Query || ! self::should_filter_comments() ) {
			return;
		}

		$user_id = get_current_user_id();
		$rule    = array(
			self::is_backend_read() ? 'admin:' . $user_id : 'site',
			implode( ',', self::viewable_term_ids( $user_id ) ),
			self::reads_unassigned( $user_id ) ? 'unassigned' : '',
		);
		$domain  = isset( $query->query_vars['cache_domain'] ) && is_string( $query->query_vars['cache_domain'] ) ? $query->query_vars['cache_domain'] : 'core';

		$query->query_vars['cache_domain'] = $domain . ':living-handbook-' . substr( md5( implode( '|', $rule ) ), 0, 12 );
	}

	/**
	 * Excludes the comments on guarded pages the current user may not read
	 * from a comment query.
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
	 * Excludes the comments on guarded pages the current user may not read from
	 * the comment feeds, which build their own query and do not run through
	 * comments_clauses.
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
	 * Returns a 404 for a single comment REST read on a guarded page the
	 * current user may not read.
	 *
	 * The collection is already filtered by filter_comment_clauses(); this
	 * closes the single-read path, which core serves from the comment cache and
	 * does not run through the comment query. REST is not the back end, so the
	 * decision is can_view_post(), as for the page itself.
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
		if ( ! self::is_guarded( $post_id ) ) {
			return $response;
		}
		if ( self::can_view_post( $post_id, get_current_user_id() ) ) {
			return $response;
		}
		return new WP_REST_Response( null, 404 );
	}

	/**
	 * Determines whether the comment channels are filtered for this request.
	 *
	 * Always, except for those who read every handbook. Up to 0.75.1 wp-admin
	 * and everybody who may edit other people's posts were let through, so the
	 * comment screen showed every comment on every handbook page to every
	 * editor, which a handbook kept to administrators cannot allow.
	 *
	 * @return bool
	 */
	private static function should_filter_comments(): bool {
		return ! self::reads_everything( get_current_user_id() );
	}

	/**
	 * SQL fragment that keeps a comment only when the current user may read
	 * the page it belongs to.
	 *
	 * Comments follow the page. In the back end that is visible_in_backend():
	 * one's own pages always, other people's pages unless they are in a
	 * handbook the user may not read. Everywhere else it is can_view_post():
	 * every handbook of the page allows the user, or the page is in no
	 * handbook and the user reads such pages (reads_unassigned()). Comments on
	 * posts of other types are not touched. What SQL cannot express is the
	 * living_handbook_can_view_post filter; a site that widens access through
	 * it gets the stricter answer here, which is the safe direction.
	 *
	 * Built from fixed identifiers, escaped type names and integer ids, so it
	 * needs no placeholders.
	 *
	 * @return string
	 */
	private static function hidden_comments_sql(): string {
		global $wpdb;

		$user_id  = get_current_user_id();
		$column   = "{$wpdb->comments}.comment_post_ID";
		$types    = "'" . implode( "','", array_map( static fn( string $type ): string => (string) esc_sql( $type ), self::guarded_post_types() ) ) . "'";
		$taxonomy = esc_sql( Handbooks::TAXONOMY );
		$filed    = "SELECT lhtr.object_id FROM {$wpdb->term_relationships} lhtr"
			. " INNER JOIN {$wpdb->term_taxonomy} lhtt ON lhtt.term_taxonomy_id = lhtr.term_taxonomy_id"
			. " WHERE lhtt.taxonomy = '{$taxonomy}'";

		if ( self::is_backend_read() ) {
			$hidden = self::hidden_term_ids( $user_id );
			if ( array() === $hidden ) {
				return '';
			}
			$in = implode( ',', array_map( 'absint', $hidden ) );

			return " AND {$column} NOT IN ("
				. " SELECT lhp.ID FROM {$wpdb->posts} lhp WHERE lhp.post_type IN ({$types})"
				. ' AND lhp.post_author <> ' . absint( $user_id )
				. " AND lhp.ID IN ( {$filed} AND lhtt.term_id IN ({$in}) )"
				. ' )';
		}

		$viewable = self::viewable_term_ids( $user_id );
		$in       = empty( $viewable ) ? '0' : implode( ',', array_map( 'absint', $viewable ) );
		$readable = "( {$column} IN ( {$filed} AND lhtt.term_id IN ({$in}) )"
			. " AND {$column} NOT IN ( {$filed} AND lhtt.term_id NOT IN ({$in}) ) )";
		if ( self::reads_unassigned( $user_id ) ) {
			$readable .= " OR {$column} NOT IN ( {$filed} )";
		}

		return " AND ( {$column} NOT IN ("
			. " SELECT lhp.ID FROM {$wpdb->posts} lhp WHERE lhp.post_type IN ({$types})"
			. " ) OR {$readable} )";
	}

	/**
	 * Returns the handbook term ids the given user may view.
	 *
	 * @param int $user_id User ID (0 for a guest).
	 * @return int[]
	 */
	public static function viewable_term_ids( int $user_id ): array {
		return array_values( array_filter( self::handbook_ids(), static fn( int $term_id ): bool => self::can_view_term( $term_id, $user_id ) ) );
	}

	/**
	 * Returns the handbook terms the given user may view, as term objects.
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
	 * Fills the caches the per-post access decision reads from, for the whole
	 * result set at once.
	 *
	 * WordPress applies the_posts before WP_Query fills its own caches, so at
	 * this point the post objects handed in are not in the post cache and their
	 * handbook membership is not in the term cache. Deciding access post by post then
	 * costs two queries per row, which a navigation tree over a few thousand
	 * pages turns into a few thousand queries. Both caches are filled here in
	 * one go; WordPress fills them again a few lines later, and finds them warm.
	 *
	 * Which terms are read follows the query. A query that wants the term cache
	 * (the default) gets every taxonomy here, because WordPress reads them all
	 * right after anyway, and reading only the handbook first would cost a
	 * second query for the rest. A query that turned the term cache off
	 * (update_post_term_cache => false) gets only the handbook membership the
	 * decision needs: up to 0.75.1 it got every taxonomy regardless, so a
	 * preview that prints three titles also loaded the type, topic, role and
	 * audience terms of every page it listed. Whoever turns the term cache off
	 * and then reads those terms page by page pays a query per page, exactly as
	 * with any other WP_Query.
	 *
	 * @param WP_Post[] $posts     Posts about to be filtered.
	 * @param bool      $all_terms Whether the query wants the terms of every taxonomy cached.
	 * @return void
	 */
	private static function prime_post_terms( array $posts, bool $all_terms = true ): void {
		$objects = array_values( array_filter( $posts, static fn( $post ) => $post instanceof WP_Post ) );
		if ( count( $objects ) < 2 ) {
			return;
		}

		if ( $all_terms ) {
			update_post_caches( $objects, Handbook::POST_TYPE, true, false );
		} else {
			update_post_cache( $objects );
			self::prime_handbook_terms( array_map( static fn( WP_Post $post ): int => (int) $post->ID, $objects ) );
		}

		// An attachment is judged by its parent page, which is not part of this
		// result set and so was not primed above.
		$parents = array();
		foreach ( $objects as $post ) {
			if ( 'attachment' === $post->post_type && $post->post_parent > 0 ) {
				$parents[] = (int) $post->post_parent;
			}
		}
		if ( $parents ) {
			_prime_post_caches( array_values( array_unique( $parents ) ), true, false );
		}
	}

	/**
	 * Reads the handbook membership of a set of posts into the term cache, in
	 * one query, and nothing else.
	 *
	 * What update_object_term_cache() does, for the one taxonomy the access
	 * decision reads. That function cannot be narrowed: it reads every taxonomy
	 * of the post type, and for a navigation over two thousand pages the terms
	 * nobody looks at are most of what it loads. A post without a handbook gets
	 * an empty entry, so asking again does not query again.
	 *
	 * @param array<int, int> $ids Post IDs.
	 * @return void
	 */
	private static function prime_handbook_terms( array $ids ): void {
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		if ( array() === $ids ) {
			return;
		}

		$group   = Handbooks::TAXONOMY . '_relationships';
		$missing = array();
		foreach ( wp_cache_get_multiple( $ids, $group ) as $id => $value ) {
			if ( false === $value ) {
				$missing[] = (int) $id;
			}
		}
		if ( array() === $missing ) {
			return;
		}

		$terms = wp_get_object_terms(
			$missing,
			Handbooks::TAXONOMY,
			array(
				'fields'                 => 'all_with_object_id',
				'orderby'                => 'name',
				'update_term_meta_cache' => false,
			)
		);
		if ( is_wp_error( $terms ) ) {
			return;
		}

		$by_object = array_fill_keys( $missing, array() );
		foreach ( $terms as $term ) {
			if ( $term instanceof WP_Term && isset( $term->object_id ) ) {
				$by_object[ (int) $term->object_id ][] = (int) $term->term_id;
			}
		}
		wp_cache_add_multiple( $by_object, $group );
	}

	/**
	 * Determines whether a user may view a handbook page.
	 *
	 * A page may belong to more than one handbook. Access is combined
	 * fail-closed: the page is viewable only when every handbook it belongs to
	 * allows the user. A page in no handbook is viewable only for those who read
	 * every handbook or who read pages in no handbook (reads_unassigned()).
	 *
	 * The membership is read with get_the_terms(), which goes through the object
	 * term cache; wp_get_object_terms() would query every time. See
	 * prime_post_terms(), which fills that cache for a whole result set at once.
	 *
	 * The post can be handed in as an object. get_the_terms() then works on that
	 * object rather than loading the post by its id, which matters for the
	 * partial posts PageTree reads: loading them by id would be one query per
	 * page, and would put the whole post back into memory. The filter is given
	 * the id either way.
	 *
	 * @param int|WP_Post $post    Post ID or post object.
	 * @param int         $user_id User ID (0 for a guest).
	 * @return bool
	 */
	public static function can_view_post( int|WP_Post $post, int $user_id ): bool {
		$post_id = $post instanceof WP_Post ? (int) $post->ID : $post;

		if ( self::reads_everything( $user_id ) ) {
			$allowed = true;
		} else {
			$terms = get_the_terms( $post, Handbooks::TAXONOMY );
			if ( is_wp_error( $terms ) ) {
				$allowed = false;
			} elseif ( ! is_array( $terms ) || empty( $terms ) ) {
				// A page in no handbook is work in progress: see reads_unassigned().
				$allowed = self::reads_unassigned( $user_id );
			} else {
				$allowed = true;
				foreach ( $terms as $term ) {
					if ( ! $term instanceof WP_Term || ! self::can_view_term( $term->term_id, $user_id ) ) {
						$allowed = false;
						break;
					}
				}
			}
		}

		/**
		 * Filters whether a user may view a handbook page.
		 *
		 * @param bool $allowed Whether access is granted.
		 * @param int  $post_id Post ID.
		 * @param int  $user_id User ID.
		 */
		return (bool) apply_filters( 'living_handbook_can_view_post', $allowed, $post_id, $user_id );
	}

	/**
	 * Determines whether a user may view a handbook (the grouping term).
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
	 * Computes (uncached) whether a user may view a handbook term.
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
		// Who reads every page reads every handbook: the pages and the handbook
		// follow one rule, reads_everything().
		if ( self::reads_everything( $user_id ) ) {
			return true;
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
