<?php
/**
 * Generates the "Handbook" navigation menu from the page hierarchy.
 *
 * The menu is a wp_navigation post that mirrors the handbook pages (parent and
 * order). The VSN plugin styles this menu as the sidebar. The menu updates
 * automatically on publish, change and delete, and keeps a stable ID so the
 * reference in a block template survives.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Navigation;

use LivingHandbook\PostType\Handbook;
use WP_Post;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds and maintains the handbook navigation menu.
 */
final class MenuGenerator {

	private const OPTION = 'living_handbook_nav_menu_id';
	private const TITLE  = 'Handbook';

	/**
	 * Hook registration into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'save_post_' . Handbook::POST_TYPE, array( $this, 'refresh' ) );
		add_action( 'trashed_post', array( $this, 'refresh_on_remove' ) );
		add_action( 'after_delete_post', array( $this, 'refresh_on_remove' ) );
	}

	/**
	 * Rebuild the menu after a handbook page is saved.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function refresh( int $post_id ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		$this->update();
	}

	/**
	 * Rebuild the menu after a handbook page is trashed or deleted.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function refresh_on_remove( int $post_id ): void {
		if ( Handbook::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}
		$this->update();
	}

	/**
	 * Create or update the menu, keeping the same post ID.
	 *
	 * @return int Menu post ID, or 0 on failure.
	 */
	public function update(): int {
		$markup  = $this->markup();
		$menu_id = (int) get_option( self::OPTION, 0 );

		if ( 0 === $menu_id || null === get_post( $menu_id ) ) {
			$existing = get_posts(
				array(
					'post_type'      => 'wp_navigation',
					'title'          => self::TITLE,
					'post_status'    => 'publish',
					'posts_per_page' => 1,
				)
			);
			$first = $existing[0] ?? null;
			if ( $first instanceof WP_Post ) {
				$menu_id = $first->ID;
				update_option( self::OPTION, $menu_id );
			}
		}

		if ( $menu_id > 0 && null !== get_post( $menu_id ) ) {
			wp_update_post(
				array(
					'ID'           => $menu_id,
					'post_content' => $markup,
				)
			);
			return $menu_id;
		}

		$new = wp_insert_post(
			array(
				'post_type'    => 'wp_navigation',
				'post_title'   => self::TITLE,
				'post_status'  => 'publish',
				'post_content' => $markup,
			)
		);
		if ( is_wp_error( $new ) || 0 === $new ) {
			return 0;
		}
		update_option( self::OPTION, $new );
		return $new;
	}

	/**
	 * Build the full navigation block markup.
	 *
	 * @return string
	 */
	private function markup(): string {
		$archive = (string) get_post_type_archive_link( Handbook::POST_TYPE );
		$home    = sprintf(
			'<!-- wp:navigation-link {"label":"Handbook","url":%s,"kind":"custom"} /-->',
			(string) wp_json_encode( $archive, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
		);
		return $home . $this->branch( 0 );
	}

	/**
	 * Build the block markup for one branch of the page tree.
	 *
	 * @param int $parent_id Parent post ID (0 for the top level).
	 * @return string
	 */
	private function branch( int $parent_id ): string {
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
			)
		);

		$out = '';
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$children = $this->branch( $post->ID );
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
