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
 * single pages stay reachable (guarded by the Access module), but the type is
 * kept out of the XML sitemap, feeds and oEmbed so that titles and URLs of an
 * internal handbook do not leak to logged-out visitors or search engines. The
 * post type archive is disabled (`has_archive => false`): the overview is a
 * normal page holding the living-handbook/overview block, so there is no second,
 * duplicate overview at /handbook/.
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
		add_action( 'admin_menu', array( $this, 'reorder_submenu' ), 999 );
		add_action( 'admin_head', array( $this, 'submenu_divider_style' ) );
	}

	/**
	 * Draw a thin divider in the handbook submenu between the three usage pages
	 * (pages, add new, import) and the six configuration pages (the taxonomies
	 * and the settings), so the split is visible at a glance.
	 *
	 * WordPress submenus have no native separators, so this styles the first
	 * configuration item (the Handbooks taxonomy) with a top border and some
	 * spacing. The border colour is a translucent grey that reads on both the
	 * dark and the light admin colour schemes.
	 *
	 * @return void
	 */
	public function submenu_divider_style(): void {
		echo '<style id="living-handbook-menu-divider">'
			. '#menu-posts-' . esc_attr( self::POST_TYPE ) . ' .wp-submenu li a[href*="taxonomy=handbook_set"]{'
			. 'margin-top:6px;padding-top:12px;border-top:1px solid rgba(140,140,140,.4);}'
			. '</style>';
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
			'all_items'     => __( 'Handbook pages', 'living-handbook' ),
			'add_new'       => __( 'New pages', 'living-handbook' ),
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
				'has_archive'         => false,
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

	/**
	 * Put the handbook submenu into a task-oriented order: pages, add new,
	 * import, the handbook types, the four vocabularies, then the settings.
	 *
	 * @return void
	 */
	public function reorder_submenu(): void {
		global $submenu;
		$parent = 'edit.php?post_type=' . self::POST_TYPE;
		if ( empty( $submenu[ $parent ] ) || ! is_array( $submenu[ $parent ] ) ) {
			return;
		}

		// Match each submenu by a stable part of its slug (index 2).
		$order = array(
			'edit.php?post_type=' . self::POST_TYPE,
			'post-new.php?post_type=' . self::POST_TYPE,
			'living-handbook-import',
			'living-handbook-export',
			'taxonomy=handbook_set',
			'taxonomy=handbook_type',
			'taxonomy=handbook_topic',
			'taxonomy=handbook_audience',
			'taxonomy=handbook_role',
			'living-handbook-sync',
		);

		$items = $submenu[ $parent ];
		usort(
			$items,
			static function ( $a, $b ) use ( $order ): int {
				return self::submenu_rank( (string) ( $a[2] ?? '' ), $order ) <=> self::submenu_rank( (string) ( $b[2] ?? '' ), $order );
			}
		);
		// Reordering the admin submenu requires writing to the $submenu global; core does the same.
		$submenu[ $parent ] = array_values( $items ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/**
	 * Rank a submenu slug against the desired order.
	 *
	 * @param string   $slug  Submenu slug.
	 * @param string[] $order Ordered list of slug fragments.
	 * @return int
	 */
	private static function submenu_rank( string $slug, array $order ): int {
		foreach ( $order as $index => $needle ) {
			if ( $slug === $needle || false !== strpos( $slug, $needle ) ) {
				return $index;
			}
		}
		return count( $order );
	}
}
