<?php
/**
 * The handbook content type.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\PostType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the `handbook` custom post type.
 *
 * Access is enforced on the frontend per handbook (see the Access module).
 * Editing in wp-admin uses the standard WordPress roles and is not restricted
 * by this plugin.
 *
 * The type is registered with `public => false` and `publicly_queryable => true`:
 * single pages and the archive stay reachable (guarded by the Access module),
 * but the type is kept out of the XML sitemap, feeds and oEmbed so that titles
 * and URLs of an internal handbook do not leak to logged-out visitors or search
 * engines.
 */
final class Handbook {

	public const POST_TYPE = 'handbook';

	/**
	 * Hook registration into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_filter( 'wp_sitemaps_post_types', array( $this, 'exclude_from_sitemap' ) );
	}

	/**
	 * Register the post type.
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		$labels = array(
			'name'          => __( 'Handbook', 'living-handbook' ),
			'singular_name' => __( 'Handbook page', 'living-handbook' ),
			'menu_name'     => __( 'Handbook', 'living-handbook' ),
			'add_new_item'  => __( 'Add new handbook page', 'living-handbook' ),
			'edit_item'     => __( 'Edit handbook page', 'living-handbook' ),
			'search_items'  => __( 'Search handbook pages', 'living-handbook' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => $labels,
				'public'              => false,
				'publicly_queryable'  => true,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_nav_menus'   => true,
				'show_in_rest'        => true,
				'menu_icon'           => 'dashicons-book',
				'hierarchical'        => true,
				'has_archive'         => true,
				'rewrite'             => array( 'slug' => 'handbook' ),
				'supports'            => array( 'title', 'editor', 'excerpt', 'revisions', 'page-attributes', 'comments', 'author', 'custom-fields' ),
			)
		);
	}

	/**
	 * Keep the handbook type out of the core XML sitemap.
	 *
	 * @param array<string, \WP_Post_Type> $post_types Registered sitemap post types.
	 * @return array<string, \WP_Post_Type> Filtered list.
	 */
	public function exclude_from_sitemap( array $post_types ): array {
		unset( $post_types[ self::POST_TYPE ] );

		return $post_types;
	}
}
