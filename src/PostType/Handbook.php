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
				'labels'             => $labels,
				'public'             => true,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_rest'       => true,
				'menu_icon'          => 'dashicons-book',
				'hierarchical'       => true,
				'has_archive'        => true,
				'rewrite'            => array( 'slug' => 'handbook' ),
				'supports'           => array( 'title', 'editor', 'excerpt', 'revisions', 'page-attributes', 'comments', 'author', 'custom-fields' ),
			)
		);
	}
}
