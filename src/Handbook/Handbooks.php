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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the `handbook_set` grouping taxonomy (shown as "Handbook types").
 * Each handbook page belongs to exactly one handbook. Every handbook carries a
 * frontend access configuration (visibility plus optional roles and users); the
 * Access module enforces it.
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
					__( 'Handbook types', 'living-handbook' ),
					__( 'Handbook type', 'living-handbook' )
				),
				'hierarchical'       => true,
				'public'             => false,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_admin_column'  => true,
				'show_in_rest'       => true,
				'query_var'          => true,
				'rewrite'            => array(
					'slug'       => 'handbook-set',
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
