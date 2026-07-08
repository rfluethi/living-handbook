<?php
/**
 * The handbook grouping and its per-handbook frontend access configuration.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Handbook;

use LivingHandbook\PostType\Handbook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the `handbook_set` grouping taxonomy. Each handbook page belongs to
 * exactly one handbook. Every handbook carries a frontend access configuration
 * (visibility plus optional roles and users); the Access module enforces it.
 *
 * The taxonomy is publicly queryable so that each handbook has its own entry
 * page at the term archive (/handbook-set/<slug>/). Access to that page is
 * guarded by the Access module.
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
				'labels'             => array(
					'name'          => __( 'Handbooks', 'living-handbook' ),
					'singular_name' => __( 'Handbook', 'living-handbook' ),
				),
				'hierarchical'       => false,
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
