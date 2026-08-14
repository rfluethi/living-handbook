<?php
/**
 * Learning paths: the content type and the module switch.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Training;

use LivingHandbook\PostType\Handbook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the `lh_training` post type, shown as "Learning paths".
 *
 * A learning path is an ordered selection of existing handbook pages, nothing
 * more: the pages stay where they are, keep their own freshness, access and
 * navigation, and a path only says which of them to read in which order. There
 * is deliberately no second place where content lives.
 *
 * The internal name is `lh_training` and stays that way. It was chosen before
 * the interface settled on "learning path", and renaming a post type is a data
 * migration on every installation that already has one, in exchange for a
 * string nobody ever sees.
 *
 * The type is off by default. A site that does not run learning paths should
 * not have to look at a menu entry for them, and the first stage keeps no
 * progress on the server, so switching it on is a decision, not a default.
 * Switching it off again hides the type and its pages; it deletes nothing, the
 * same promise the taxonomy switches make.
 */
final class Training {

	/**
	 * The post type name. Internal, and not renamed to match the label: see the
	 * class comment.
	 */
	public const POST_TYPE = 'lh_training';

	/**
	 * Option holding whether this site runs learning paths. '1' for on, absent
	 * or '0' for off, which is the default.
	 */
	public const OPTION_ENABLED = 'living_handbook_training';

	/**
	 * Flag saying the rewrite rules have to be rebuilt on the next request.
	 *
	 * Switching the module on or off changes whether the post type answers a URL
	 * at all, and the rules are built from the registration that ran before the
	 * option was saved. Flushing right here would therefore write the old rules
	 * back. So the moment is only recorded, and the flush happens on the next
	 * request, after the type has registered itself with the new value.
	 */
	public const OPTION_FLUSH = 'living_handbook_training_flush';

	/**
	 * The ordered lesson list of a path, as post ids. Protected, because it is
	 * bookkeeping the plugin writes about a path rather than a field somebody
	 * fills in by hand.
	 */
	public const META_LESSONS = '_lh_training_lessons';

	/**
	 * Whether this site runs learning paths.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		$enabled = (bool) get_option( self::OPTION_ENABLED, false );

		/**
		 * Filters whether the learning paths module is active.
		 *
		 * @param bool $enabled Whether the module is switched on.
		 */
		return (bool) apply_filters( 'living_handbook_training_enabled', $enabled );
	}

	/**
	 * Sanitize the module switch coming from the settings form.
	 *
	 * @param mixed $value Raw value from the form.
	 * @return int 1 or 0.
	 */
	public static function sanitize_enabled( $value ): int {
		return empty( $value ) ? 0 : 1;
	}

	/**
	 * Hook registration into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'init', array( $this, 'maybe_flush_rewrites' ), 99 );
		add_action( 'update_option_' . self::OPTION_ENABLED, array( $this, 'schedule_flush' ) );
		add_filter( 'wp_sitemaps_post_types', array( $this, 'exclude_from_sitemap' ) );
	}

	/**
	 * Register the post type.
	 *
	 * It is registered whether or not the module is switched on, and the switch
	 * decides only what is shown: an installation that turns learning paths off
	 * keeps its paths, its lesson lists and its revisions, and finds them again
	 * unchanged when it turns them back on. Unregistering the type instead would
	 * leave rows nothing knows about, and `wp_delete_post` on an unregistered
	 * type behaves differently, so "off" would quietly become "at risk".
	 *
	 * Visibility follows the handbook the path belongs to, enforced by the
	 * Access module, which guards this type exactly as it guards handbook pages.
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		$enabled = self::is_enabled();

		$labels = array(
			'name'          => __( 'Learning paths', 'living-handbook' ),
			'singular_name' => __( 'Learning path', 'living-handbook' ),
			'menu_name'     => __( 'Learning paths', 'living-handbook' ),
			'all_items'     => __( 'Learning paths', 'living-handbook' ),
			'add_new'       => __( 'New learning path', 'living-handbook' ),
			'add_new_item'  => __( 'Add new learning path', 'living-handbook' ),
			'edit_item'     => __( 'Edit learning path', 'living-handbook' ),
			'search_items'  => __( 'Search learning paths', 'living-handbook' ),
			'not_found'     => __( 'No learning paths yet.', 'living-handbook' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => $labels,
				// Same shape as the handbook type: reachable as a single page,
				// guarded by the Access module, and kept out of sitemaps, feeds,
				// site search and oEmbed.
				'public'              => false,
				'publicly_queryable'  => $enabled,
				'embeddable'          => false,
				'exclude_from_search' => true,
				'show_ui'             => $enabled,
				'show_in_menu'        => $enabled ? 'edit.php?post_type=' . Handbook::POST_TYPE : false,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => $enabled,
				'menu_icon'           => 'dashicons-editor-ol',
				'hierarchical'        => false,
				'has_archive'         => false,
				/**
				 * The URL base of a learning path. English and fixed by default,
				 * for the same reason as the handbook page slug: permalinks stay
				 * stable. Changing it on a live site rewrites every path URL and
				 * needs the permalinks flushed.
				 *
				 * @param string $slug The rewrite base. Default 'learning-path'.
				 */
				'rewrite'             => array( 'slug' => (string) apply_filters( 'living_handbook_training_slug', 'learning-path' ) ),
				'supports'            => array( 'title', 'editor', 'excerpt', 'revisions', 'author' ),
			)
		);
	}

	/**
	 * Register the lesson list as post meta.
	 *
	 * Not exposed in REST: the list is written by the lesson picker through the
	 * normal post form, and a REST-writable array of post ids would be a second
	 * way in that would need its own access rules for every id in it.
	 *
	 * @return void
	 */
	public function register_meta(): void {
		register_post_meta(
			self::POST_TYPE,
			self::META_LESSONS,
			array(
				'type'              => 'array',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => array( Lessons::class, 'sanitize' ),
				'auth_callback'     => static function ( bool $allowed, string $meta_key, int $post_id ): bool {
					unset( $allowed, $meta_key );

					return current_user_can( 'edit_post', $post_id );
				},
			)
		);
	}

	/**
	 * Note that the rewrite rules are stale, see OPTION_FLUSH.
	 *
	 * @return void
	 */
	public function schedule_flush(): void {
		update_option( self::OPTION_FLUSH, 1 );
	}

	/**
	 * Rebuild the rewrite rules once, on the first request after the switch was
	 * used.
	 *
	 * @return void
	 */
	public function maybe_flush_rewrites(): void {
		if ( ! get_option( self::OPTION_FLUSH, 0 ) ) {
			return;
		}

		delete_option( self::OPTION_FLUSH );
		flush_rewrite_rules( false );
	}

	/**
	 * Keep learning paths out of the core XML sitemap, as handbook pages are.
	 *
	 * @param array<string, \WP_Post_Type> $post_types Registered sitemap post types.
	 * @return array<string, \WP_Post_Type> Filtered list.
	 */
	public function exclude_from_sitemap( array $post_types ): array {
		unset( $post_types[ self::POST_TYPE ] );

		return $post_types;
	}
}
