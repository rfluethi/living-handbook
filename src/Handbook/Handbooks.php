<?php
/**
 * The handbook grouping and its per-handbook frontend access configuration.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Handbook;

use LivingHandbook\PostType\Handbook;
use LivingHandbook\Taxonomy\Taxonomies;
use WP_REST_Response;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the `handbook_set` grouping taxonomy (shown as "Handbooks"). Each
 * handbook page belongs to exactly one handbook, and that is enforced here, in
 * enforce_single(), rather than left as a sentence in a comment. Which handbook
 * a page belongs to is answered in one place, for_post(). Every handbook carries
 * a frontend access configuration (visibility plus optional roles and users);
 * the Access module enforces it.
 *
 * The taxonomy is registered hierarchical so the block editor shows a selectable
 * list of the existing handbooks, and publicly queryable so that each handbook
 * has its own entry page at the term archive (/handbook-set/<slug>/). Access to
 * that page is guarded by the Access module.
 *
 * The taxonomy stays in the REST API because the block editor needs it to assign
 * a handbook, but reading the terms over REST is limited to users who may edit
 * posts: otherwise the names, descriptions and page counts of every handbook
 * (including members-only and restricted ones) would be listed anonymously under
 * /wp-json/wp/v2/handbook_set.
 */
final class Handbooks {

	public const TAXONOMY = 'handbook_set';

	public const META_VISIBILITY = 'living_handbook_visibility';
	public const META_ROLES      = 'living_handbook_roles';
	public const META_USERS      = 'living_handbook_users';

	public const VISIBILITY_PUBLIC     = 'public';
	public const VISIBILITY_MEMBERS    = 'members';
	public const VISIBILITY_RESTRICTED = 'restricted';

	public const META_COMMENTS = 'living_handbook_comments';

	public const COMMENTS_INHERIT = 'inherit';
	public const COMMENTS_OPEN    = 'open';
	public const COMMENTS_CLOSED  = 'closed';

	/**
	 * Guard so that the enforcement's own write does not trigger it again.
	 *
	 * @var bool
	 */
	private static bool $enforcing = false;

	/**
	 * Hook registration into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_taxonomy' ) );
		add_action( 'init', array( $this, 'register_meta' ) );

		// Keep the REST list of handbooks out of anonymous reach.
		add_filter( 'rest_' . self::TAXONOMY . '_query', array( $this, 'restrict_rest_query' ), 10, 2 );
		add_filter( 'rest_prepare_' . self::TAXONOMY, array( $this, 'restrict_rest_item' ), 10, 2 );

		// One handbook per page, enforced instead of assumed. See enforce_single().
		add_action( 'set_object_terms', array( $this, 'enforce_single' ), 10, 6 );

		// A handbook may decide about comments for all of its pages at once.
		// Late priority so this is the last word: the point of the setting is to
		// spare somebody opening two hundred pages one by one.
		add_filter( 'comments_open', array( __CLASS__, 'filter_comments_open' ), 99, 2 );
	}

	/**
	 * Let a handbook decide about comments for every page it holds.
	 *
	 * Without this the only switch is the one on each page, which is fine for a
	 * page and useless for a handbook: turning comments on for two hundred pages
	 * means opening two hundred pages. The handbook's setting is therefore a
	 * plain override, not a default that pages could drift away from, because a
	 * default would have to be written onto every page at the moment it is set
	 * and would then be wrong for every page imported afterwards.
	 *
	 * "Inherit" leaves the page setting alone, and is the default, so an existing
	 * site notices nothing. Note what closing does and does not do: it hides the
	 * form, exactly as closing comments on a single page does. Comments already
	 * written stay readable, and stay in the database. Deleting them is a
	 * separate act and stays a manual one.
	 *
	 * @param bool       $open    Whether comments are open.
	 * @param int|string $post_id Post id.
	 * @return bool
	 */
	public static function filter_comments_open( $open, $post_id ): bool {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 || Handbook::POST_TYPE !== get_post_type( $post_id ) ) {
			return (bool) $open;
		}

		$mode = self::comments_mode( self::for_post( $post_id ) );
		if ( self::COMMENTS_OPEN === $mode ) {
			return true;
		}
		if ( self::COMMENTS_CLOSED === $mode ) {
			return false;
		}

		return (bool) $open;
	}

	/**
	 * What a handbook says about comments: inherit, open or closed.
	 *
	 * Anything unknown, including a handbook that does not exist, reads as
	 * inherit, so a stray value can never silently open comments.
	 *
	 * @param int $term_id Handbook term id.
	 * @return string One of the COMMENTS_* constants.
	 */
	public static function comments_mode( int $term_id ): string {
		if ( $term_id <= 0 ) {
			return self::COMMENTS_INHERIT;
		}

		$mode = (string) get_term_meta( $term_id, self::META_COMMENTS, true );

		return in_array( $mode, array( self::COMMENTS_OPEN, self::COMMENTS_CLOSED ), true )
			? $mode
			: self::COMMENTS_INHERIT;
	}

	/**
	 * The handbook a page belongs to, as a term id, or 0.
	 *
	 * The one definition of "the handbook of this page". Three places carried
	 * their own copy of the same expression, and they did not agree with each
	 * other: wp_get_object_terms() orders by name, so a page that ended up in two
	 * handbooks got whichever sorted first, and renaming a handbook moved pages
	 * from one navigation tree into another. A page carries one handbook, see
	 * enforce_single(); where an older assignment still holds more than one, the
	 * lowest term id wins, because that answer does not change when someone edits
	 * a name.
	 *
	 * Read through get_the_terms(), so a result set that has primed the object
	 * term cache is not asked again page by page.
	 *
	 * @param int $post_id Page id.
	 * @return int Term id, or 0 when the page belongs to no handbook.
	 */
	public static function for_post( int $post_id ): int {
		if ( $post_id <= 0 ) {
			return 0;
		}

		$terms = get_the_terms( $post_id, self::TAXONOMY );
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return 0;
		}

		$ids = array();
		foreach ( $terms as $term ) {
			if ( $term instanceof WP_Term ) {
				$ids[] = (int) $term->term_id;
			}
		}

		return array() === $ids ? 0 : min( $ids );
	}

	/**
	 * Keep a handbook page in exactly one handbook.
	 *
	 * The data model says one handbook per page, and everything built on it
	 * assumes exactly that: the navigation shows one tree, a page has one entry
	 * page, an export carries the configuration of one handbook. The block editor
	 * renders a hierarchical taxonomy as a list of checkboxes and lets you tick
	 * two, and nothing stopped it. The result was not an error message but
	 * something worse: a page whose navigation showed a tree the page is not in.
	 *
	 * So the rule is enforced where the terms are written, and only for handbook
	 * pages. Of several handbooks the one just added wins, because ticking a
	 * second box is the deliberate act; when several arrive at once, the lowest
	 * term id decides, the same rule for_post() reads by.
	 *
	 * Access is deliberately not relaxed: can_view_post() still requires every
	 * handbook of a page to allow the reader, so an assignment made before this
	 * existed stays fail-closed until the page is saved again.
	 *
	 * @param int    $object_id  Object id.
	 * @param mixed  $terms      Terms as submitted (unused).
	 * @param mixed  $tt_ids     Term taxonomy ids now assigned.
	 * @param string $taxonomy   Taxonomy.
	 * @param bool   $append     Whether the terms were appended to the existing ones.
	 * @param mixed  $old_tt_ids Term taxonomy ids before this write.
	 * @return void
	 */
	public function enforce_single( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ): void {
		unset( $terms );

		if ( self::$enforcing || self::TAXONOMY !== $taxonomy ) {
			return;
		}

		$tt_ids = array_map( 'intval', (array) $tt_ids );

		// Appending writes only the appended terms, so the count of this call says
		// nothing about how many the page now has. Every other case does.
		if ( ! $append && count( $tt_ids ) < 2 ) {
			return;
		}

		$object_id = (int) $object_id;
		if ( Handbook::POST_TYPE !== get_post_type( $object_id ) ) {
			return;
		}

		$current = wp_get_object_terms( $object_id, $taxonomy, array( 'fields' => 'ids' ) );
		$current = is_wp_error( $current ) ? array() : array_map( 'intval', $current );
		if ( count( $current ) < 2 ) {
			return;
		}

		// The terms of this call are the deliberate act and win over what was
		// there before; among equals the lowest term id decides.
		$added = array_values( array_diff( $tt_ids, array_map( 'intval', (array) $old_tt_ids ) ) );
		$keep  = array() !== $added ? self::lowest_term_id( $added ) : min( $current );
		if ( $keep <= 0 ) {
			return;
		}

		self::$enforcing = true;
		wp_set_object_terms( $object_id, array( $keep ), $taxonomy, false );
		self::$enforcing = false;
	}

	/**
	 * The lowest term id behind a list of term taxonomy ids.
	 *
	 * @param int[] $tt_ids Term taxonomy ids.
	 * @return int Term id, or 0.
	 */
	private static function lowest_term_id( array $tt_ids ): int {
		$ids = array();
		foreach ( $tt_ids as $tt_id ) {
			$term = get_term_by( 'term_taxonomy_id', (int) $tt_id, self::TAXONOMY );
			if ( $term instanceof WP_Term ) {
				$ids[] = (int) $term->term_id;
			}
		}

		return array() === $ids ? 0 : min( $ids );
	}

	/**
	 * Register the grouping taxonomy.
	 *
	 * @return void
	 */
	public function register_taxonomy(): void {
		register_taxonomy(
			self::TAXONOMY,
			array( Handbook::POST_TYPE ),
			array(
				'labels'             => Taxonomies::labels(
					__( 'Handbooks', 'living-handbook' ),
					__( 'Handbook', 'living-handbook' )
				),
				'hierarchical'       => true,
				'public'             => false,
				'publicly_queryable' => true,
				'show_ui'            => true,
				// The list table gets its Handbook column from Maintenance, not from
				// here. Both at once is what shipped in 0.61.0: two columns saying
				// the same thing, one of them in the wrong place and neither of
				// them saying what an empty cell means.
				'show_admin_column'  => false,
				'show_in_rest'       => true,
				'query_var'          => true,
				/**
				 * The URL base of a handbook grouping term. English and fixed by
				 * default; filterable for the same reason and with the same caveat
				 * as the page slug, see living_handbook_post_type_slug.
				 *
				 * @param string $slug The rewrite base. Default 'handbook-set'.
				 */
				'rewrite'            => array(
					'slug'       => (string) apply_filters( 'living_handbook_taxonomy_slug', 'handbook-set' ),
					'with_front' => false,
				),
			)
		);
	}

	/**
	 * Force an empty REST term list for users who may not edit posts, so the
	 * handbook list is not readable anonymously. Editors keep the list they need
	 * to assign a handbook in the block editor.
	 *
	 * @param array<string, mixed> $args    Prepared query args.
	 * @param mixed                $request The REST request (unused).
	 * @return array<string, mixed>
	 */
	public function restrict_rest_query( array $args, $request ): array {
		unset( $request );
		if ( ! current_user_can( 'edit_posts' ) ) {
			// A slug no term carries: matches nothing, so the response is empty.
			$args['slug'] = array( '__living_handbook_no_access__' );
		}
		return $args;
	}

	/**
	 * Block a single REST term read for users who may not edit posts, so the
	 * per-id endpoint cannot be used to read one handbook around the empty list.
	 *
	 * @param mixed $response The prepared response.
	 * @param mixed $item     The term (unused).
	 * @return mixed
	 */
	public function restrict_rest_item( $response, $item ) {
		unset( $item );
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_REST_Response( null, 403 );
		}
		return $response;
	}

	/**
	 * Register the access configuration as term meta on the grouping taxonomy.
	 *
	 * The default visibility, used when nothing is configured, is members only
	 * (fail-closed).
	 *
	 * @return void
	 */
	public function register_meta(): void {
		register_term_meta(
			self::TAXONOMY,
			self::META_VISIBILITY,
			array(
				'type'          => 'string',
				'single'        => true,
				'default'       => self::VISIBILITY_MEMBERS,
				'show_in_rest'  => false,
				'auth_callback' => array( __CLASS__, 'can_edit_access' ),
			)
		);

		register_term_meta(
			self::TAXONOMY,
			self::META_ROLES,
			array(
				'type'          => 'array',
				'single'        => true,
				'show_in_rest'  => false,
				'auth_callback' => array( __CLASS__, 'can_edit_access' ),
			)
		);

		register_term_meta(
			self::TAXONOMY,
			self::META_USERS,
			array(
				'type'          => 'array',
				'single'        => true,
				'show_in_rest'  => false,
				'auth_callback' => array( __CLASS__, 'can_edit_access' ),
			)
		);

		register_term_meta(
			self::TAXONOMY,
			self::META_COMMENTS,
			array(
				'type'          => 'string',
				'single'        => true,
				'default'       => self::COMMENTS_INHERIT,
				'show_in_rest'  => false,
				'auth_callback' => array( __CLASS__, 'can_edit_access' ),
			)
		);
	}

	/**
	 * Whether the current user may edit a handbook's access configuration.
	 *
	 * @return bool
	 */
	public static function can_edit_access(): bool {
		return current_user_can( 'manage_categories' );
	}
}
