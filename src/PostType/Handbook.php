<?php
/**
 * The handbook content type.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\PostType;

use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\Setup\Settings;
use LivingHandbook\Taxonomy\Taxonomies;
use LivingHandbook\Training\Training;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the `handbook` custom post type.
 *
 * Access is enforced per handbook by the Access module, on the front end and in
 * wp-admin: other people's pages in a handbook the user may not read stay out
 * of the admin lists and the editor. One's own pages and pages without a
 * handbook follow the usual WordPress roles.
 *
 * The type is registered with `public => false` and `publicly_queryable => true`:
 * single pages stay reachable (guarded by the Access module), but the type is
 * kept out of the XML sitemap and feeds so that titles and URLs of an internal
 * handbook do not leak to logged-out visitors or search engines. oEmbed needs
 * two things: `embeddable => false`, and the filters in the Access module,
 * which also cover a site re-enabling embedding through the
 * is_post_embeddable filter. The
 * post type archive is disabled (`has_archive => false`): the overview is a
 * normal page holding the living-handbook/overview block, so there is no second,
 * duplicate overview at /handbook/.
 */
final class Handbook {

	/**
	 * Name of the post type of a handbook page.
	 */
	public const POST_TYPE = 'handbook';

	/**
	 * Style handle that carries the divider of the admin submenu.
	 */
	public const MENU_STYLE_HANDLE = 'living-handbook-admin-menu';

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_filter( 'wp_sitemaps_post_types', array( $this, 'exclude_from_sitemap' ) );
		add_action( 'admin_menu', array( $this, 'reorder_submenu' ), 999 );
		add_action( 'admin_enqueue_scripts', array( $this, 'submenu_divider_style' ) );
		add_filter( 'get_default_comment_status', array( $this, 'default_comments_closed' ), 10, 2 );
	}

	/**
	 * Closes comments by default on new handbook pages, so a handbook is not a
	 * comment thread unless the site wants one. This is only the default: an
	 * editor can still switch comments on for a page in its Discussion panel.
	 * An imported page, the bundled app handbook (Import\AppHandbook) included,
	 * is created with comments off, because the import writes no explicit status
	 * and so takes this default. Other post types are untouched.
	 *
	 * @param string $status    The default comment status.
	 * @param string $post_type The post type it is asked for.
	 * @return string
	 */
	public function default_comments_closed( string $status, string $post_type ): string {
		return self::POST_TYPE === $post_type ? 'closed' : $status;
	}

	/**
	 * Draws a thin divider in the handbook submenu between the usage pages
	 * (pages, add new, import, export, learning paths) and the configuration
	 * pages (handbooks, the classifying taxonomies, settings), so the split is
	 * visible at a glance.
	 *
	 * WordPress submenus have no native separators, so this styles the first
	 * configuration item (the Handbooks taxonomy) with a top border and some
	 * spacing. The border colour is a translucent grey that reads on both the
	 * dark and the light admin colour schemes.
	 *
	 * The rule used to be echoed as a <style> tag into the head of every admin
	 * page, for every user. It now rides on a registered handle with
	 * wp_add_inline_style(), so WordPress prints it with the other styles and an
	 * optimiser or a Content Security Policy sees whose it is, and only for a
	 * user who sees the handbook menu at all: the menu, and so the item the rule
	 * styles, is shown to those who may edit handbook pages.
	 *
	 * @return void
	 */
	public function submenu_divider_style(): void {
		$type = get_post_type_object( self::POST_TYPE );
		if ( null === $type || ! current_user_can( $type->cap->edit_posts ) ) {
			return;
		}

		wp_register_style( self::MENU_STYLE_HANDLE, false, array(), LIVING_HANDBOOK_VERSION );
		wp_enqueue_style( self::MENU_STYLE_HANDLE );
		wp_add_inline_style(
			self::MENU_STYLE_HANDLE,
			'#menu-posts-' . self::POST_TYPE . ' .wp-submenu li a[href*="taxonomy=' . Handbooks::TAXONOMY . '"]{'
			. 'margin-top:6px;padding-top:12px;border-top:1px solid rgba(140,140,140,.4);}'
		);
	}

	/**
	 * Registers the post type.
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
				// Keeps the type out of oEmbed. The Access module also filters the
				// oEmbed lookup and response, for a site that turns embedding back on
				// through the is_post_embeddable filter.
				'embeddable'          => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_nav_menus'   => true,
				'show_in_rest'        => true,
				'menu_icon'           => 'dashicons-book',
				'hierarchical'        => true,
				'has_archive'         => false,
				/**
				 * Filters the URL base of a handbook page.
				 *
				 * English and fixed by default, so permalinks stay stable and do not
				 * collide. A site that needs a different base (a localized one, say)
				 * can filter it, but changing it on a live site rewrites every page
				 * URL and needs the permalinks flushed and old links redirected.
				 *
				 * @param string $slug The rewrite base. Default 'handbook'.
				 */
				'rewrite'             => array( 'slug' => (string) apply_filters( 'living_handbook_post_type_slug', 'handbook' ) ),
				'supports'            => array( 'title', 'editor', 'excerpt', 'revisions', 'page-attributes', 'comments', 'author', 'custom-fields' ),
			)
		);
	}

	/**
	 * Removes the handbook type from the core XML sitemap.
	 *
	 * @param array<string, \WP_Post_Type> $post_types Registered sitemap post types.
	 * @return array<string, \WP_Post_Type> Filtered list.
	 */
	public function exclude_from_sitemap( array $post_types ): array {
		unset( $post_types[ self::POST_TYPE ] );

		return $post_types;
	}

	/**
	 * Sorts the handbook submenu into a task-oriented order: pages, add new,
	 * import, export, learning paths, handbooks, the four classifying
	 * taxonomies, then the settings.
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
			'post_type=' . Training::POST_TYPE,
			'taxonomy=' . Handbooks::TAXONOMY,
			'taxonomy=' . Taxonomies::PAGE_TYPE,
			'taxonomy=' . Taxonomies::TOPIC,
			'taxonomy=' . Taxonomies::AUDIENCE,
			'taxonomy=' . Taxonomies::ROLE,
			Settings::PAGE_SLUG,
		);

		$items = $submenu[ $parent ];
		usort(
			$items,
			static function ( $a, $b ) use ( $order ): int {
				return self::submenu_rank( (string) ( $a[2] ?? '' ), $order ) <=> self::submenu_rank( (string) ( $b[2] ?? '' ), $order );
			}
		);
		$submenu[ $parent ] = array_values( $items ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Reordering the admin submenu means writing the $submenu global; core does the same.
	}

	/**
	 * Ranks a submenu slug against the desired order.
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
