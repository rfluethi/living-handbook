<?php
/**
 * Frontend access control, enforced per handbook.
 *
 * Access is frontend-only. Editing in wp-admin uses the standard WordPress
 * roles and is not restricted here. A handbook page is viewable when its
 * handbook allows the current user; pages without a handbook are fail-closed.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Access;

use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use WP_Post;
use WP_Query;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The single place that decides who may read handbook content, wired into
 * every frontend read path: single pages, result sets (archives, search, REST
 * collections, custom loops), and single REST reads.
 */
final class AccessController {

	/**
	 * Hook registration into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'template_redirect', array( $this, 'guard_singular' ) );
		add_filter( 'the_posts', array( $this, 'filter_posts' ), 10, 2 );
		add_filter( 'rest_prepare_' . Handbook::POST_TYPE, array( $this, 'guard_rest_item' ), 10, 2 );
	}

	/**
	 * Block direct access to a single handbook page the user may not view.
	 *
	 * @return void
	 */
	public function guard_singular(): void {
		if ( ! is_singular( Handbook::POST_TYPE ) ) {
			return;
		}

		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		if ( self::can_view_post( $post->ID, get_current_user_id() ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}

		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}

	/**
	 * Remove handbook posts the current user may not view from any result set.
	 *
	 * @param WP_Post[] $posts Posts returned by the query.
	 * @param WP_Query  $query The query (unused, kept for the filter signature).
	 * @return WP_Post[]
	 */
	public function filter_posts( array $posts, WP_Query $query ): array {
		unset( $query );

		if ( is_admin() ) {
			return $posts;
		}

		$user_id = get_current_user_id();

		return array_values(
			array_filter(
				$posts,
				static function ( $post ) use ( $user_id ): bool {
					if ( ! $post instanceof WP_Post || Handbook::POST_TYPE !== $post->post_type ) {
						return true;
					}
					return self::can_view_post( $post->ID, $user_id );
				}
			)
		);
	}

	/**
	 * Return a 404 for single REST reads of non-viewable handbook pages.
	 *
	 * @param mixed   $response The prepared response.
	 * @param WP_Post $post     The post being prepared.
	 * @return mixed
	 */
	public function guard_rest_item( $response, $post ) {
		if ( $post instanceof WP_Post && ! self::can_view_post( $post->ID, get_current_user_id() ) ) {
			return new WP_REST_Response( null, 404 );
		}
		return $response;
	}

	/**
	 * Whether a user may view a handbook page.
	 *
	 * @param int $post_id Post ID.
	 * @param int $user_id User ID (0 for a guest).
	 * @return bool
	 */
	public static function can_view_post( int $post_id, int $user_id ): bool {
		if ( user_can( $user_id, 'edit_others_posts' ) ) {
			$allowed = true;
		} else {
			$terms = wp_get_object_terms( $post_id, Handbooks::TAXONOMY, array( 'fields' => 'ids' ) );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				$allowed = false;
			} else {
				$allowed = self::can_view_term( (int) $terms[0], $user_id );
			}
		}

		/**
		 * Filter whether a user may view a handbook page.
		 *
		 * @param bool $allowed Whether access is granted.
		 * @param int  $post_id Post ID.
		 * @param int  $user_id User ID.
		 */
		return (bool) apply_filters( 'living_handbook_can_view_post', $allowed, $post_id, $user_id );
	}

	/**
	 * Whether a user may view a handbook (the grouping term).
	 *
	 * @param int $term_id Handbook term ID.
	 * @param int $user_id User ID (0 for a guest).
	 * @return bool
	 */
	public static function can_view_term( int $term_id, int $user_id ): bool {
		$visibility = (string) get_term_meta( $term_id, Handbooks::META_VISIBILITY, true );
		if ( '' === $visibility ) {
			$visibility = Handbooks::VISIBILITY_MEMBERS;
		}

		if ( Handbooks::VISIBILITY_PUBLIC === $visibility ) {
			return true;
		}
		if ( 0 === $user_id ) {
			return false;
		}
		if ( Handbooks::VISIBILITY_MEMBERS === $visibility ) {
			return true;
		}

		$users = array_map( 'intval', (array) get_term_meta( $term_id, Handbooks::META_USERS, true ) );
		if ( in_array( $user_id, $users, true ) ) {
			return true;
		}

		$roles = (array) get_term_meta( $term_id, Handbooks::META_ROLES, true );
		$user  = get_userdata( $user_id );
		if ( false !== $user ) {
			foreach ( (array) $user->roles as $role ) {
				if ( in_array( $role, $roles, true ) ) {
					return true;
				}
			}
		}

		return false;
	}
}
