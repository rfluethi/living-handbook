<?php
/**
 * Per-handbook sidebar navigation.
 *
 * Builds the page tree of a handbook as a core navigation block with a VSN
 * block style, so the VSN plugin styles it and handles the open path, active
 * marker and mobile burger. The tree is scoped to one handbook, so it never
 * lists pages of another handbook.
 *
 * Building the tree walks the page hierarchy with one query per branch, so the
 * assembled block markup is cached per handbook and variant and reused until a
 * handbook page or a handbook term changes (a version counter is bumped, see
 * invalidate()). The cached markup still runs through do_blocks on each request,
 * so the current-page marker stays correct.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Frontend;

use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use WP_Post;
use WP_Query;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the VSN-styled navigation for a handbook.
 */
final class Navigation {

	/**
	 * Option holding the cache version; bumped to invalidate the nav markup.
	 */
	private const CACHE_VERSION_OPTION = 'living_handbook_nav_version';

	/**
	 * Render the sidebar navigation for a handbook.
	 *
	 * @param int    $term_id Handbook term ID.
	 * @param string $variant Either 'sidebar' (menu) or 'accordion'.
	 * @return string
	 */
	public static function render( int $term_id, string $variant = 'sidebar' ): string {
		if ( $term_id <= 0 ) {
			return '';
		}

		$markup = self::markup( $term_id, $variant );
		if ( '' === $markup ) {
			return '';
		}

		return '<div class="living-handbook-navwrap">' . do_blocks( $markup ) . '</div>';
	}

	/**
	 * The cached navigation block markup for a handbook and variant.
	 *
	 * @param int    $term_id Handbook term ID.
	 * @param string $variant Either 'sidebar' (menu) or 'accordion'.
	 * @return string Block markup, or '' when the handbook has no pages.
	 */
	private static function markup( int $term_id, string $variant ): string {
		$version   = (int) get_option( self::CACHE_VERSION_OPTION, 0 );
		$cache_key = 'lh_nav_' . $version . '_' . $term_id . '_' . $variant;

		$cached = get_transient( $cache_key );
		if ( is_string( $cached ) ) {
			return $cached;
		}

		$inner = self::top_link( $term_id ) . self::branch( 0, $term_id );
		if ( '' === $inner ) {
			set_transient( $cache_key, '', HOUR_IN_SECONDS );
			return '';
		}

		$accordion = 'accordion' === $variant;
		$class     = $accordion ? 'is-style-vsn-sidebar-accordion' : 'is-style-vsn-sidebar';
		$on_click  = $accordion ? ',"openSubmenusOnClick":true' : '';

		$markup = '<!-- wp:navigation {"overlayMenu":"never","layout":{"type":"flex","orientation":"vertical","justifyContent":"left"}' . $on_click . ',"className":"' . $class . '"} -->'
			. $inner
			. '<!-- /wp:navigation -->';

		set_transient( $cache_key, $markup, DAY_IN_SECONDS );
		return $markup;
	}

	/**
	 * Invalidate the cached navigation markup by bumping the version counter.
	 *
	 * @return void
	 */
	public static function invalidate(): void {
		update_option( self::CACHE_VERSION_OPTION, (int) get_option( self::CACHE_VERSION_OPTION, 0 ) + 1 );
	}

	/**
	 * Render the navigation for the handbook a page belongs to.
	 *
	 * @param int    $post_id Current post ID.
	 * @param string $variant Either 'sidebar' (menu) or 'accordion'.
	 * @return string
	 */
	public static function render_for_post( int $post_id, string $variant = 'sidebar' ): string {
		if ( $post_id <= 0 ) {
			return '';
		}
		$terms   = wp_get_object_terms( $post_id, Handbooks::TAXONOMY, array( 'fields' => 'ids' ) );
		$term_id = ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? (int) $terms[0] : 0;
		return self::render( $term_id, $variant );
	}

	/**
	 * A link to the handbook entry page, shown at the top of the navigation.
	 *
	 * It carries the class living-handbook-nav-top so the top level can be
	 * styled (bold by default, adjustable with the --lh-nav-top-weight
	 * variable).
	 *
	 * @param int $term_id Handbook term ID.
	 * @return string
	 */
	private static function top_link( int $term_id ): string {
		$term = get_term( $term_id );
		if ( ! $term instanceof WP_Term ) {
			return '';
		}
		$link = get_term_link( $term );
		if ( is_wp_error( $link ) ) {
			return '';
		}

		return sprintf(
			'<!-- wp:navigation-link {"label":%1$s,"url":%2$s,"kind":"custom","className":"living-handbook-nav-top"} /-->',
			(string) wp_json_encode( $term->name, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			(string) wp_json_encode( (string) $link, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
		);
	}

	/**
	 * Recursively build the navigation block markup for one branch of the tree.
	 *
	 * @param int $parent_id Parent post ID (0 for the top level).
	 * @param int $term_id   Handbook term ID.
	 * @return string
	 */
	private static function branch( int $parent_id, int $term_id ): string {
		$query = new WP_Query(
			array(
				'post_type'      => Handbook::POST_TYPE,
				'post_parent'    => $parent_id,
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
				'no_found_rows'  => true,
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => Handbooks::TAXONOMY,
						'field'    => 'term_id',
						'terms'    => $term_id,
					),
				),
			)
		);

		$out = '';
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$children = self::branch( $post->ID, $term_id );
			$label    = (string) wp_json_encode( get_the_title( $post ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			$url      = (string) wp_json_encode( (string) get_permalink( $post ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

			if ( '' !== $children ) {
				$out .= sprintf(
					'<!-- wp:navigation-submenu {"label":%1$s,"type":"%2$s","id":%3$d,"url":%4$s,"kind":"post-type"} -->%5$s<!-- /wp:navigation-submenu -->',
					$label,
					Handbook::POST_TYPE,
					$post->ID,
					$url,
					$children
				);
			} else {
				$out .= sprintf(
					'<!-- wp:navigation-link {"label":%1$s,"type":"%2$s","id":%3$d,"url":%4$s,"kind":"post-type"} /-->',
					$label,
					Handbook::POST_TYPE,
					$post->ID,
					$url
				);
			}
		}
		return $out;
	}
}
