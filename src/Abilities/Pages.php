<?php
/**
 * The access check and the page description every ability shares.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Abilities;

use LivingHandbook\Access\AccessController;
use LivingHandbook\Frontend\FreshnessStatus;
use LivingHandbook\Frontend\PageTree;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\Meta\Metadata;
use LivingHandbook\PostType\Handbook;
use LivingHandbook\Taxonomy\Taxonomies;
use WP_Error;
use WP_Post;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One place that says which page an ability may hand out, and how.
 *
 * Every ability goes through readable() for each page and through handbook()
 * or readable_handbooks() for each handbook. A page is handed out when it is
 * a published handbook page, has no password, may be read by the person
 * (AccessController::can_view_post(), the rule every other way into a handbook
 * uses) and is not marked with living_handbook_ai_exclude. Because the rule
 * sits here, no single ability can forget a part of it (Konzept 04, E-0056).
 *
 * Every page in an answer is described by reference(): its address and its
 * review status travel with it, so a model can link its source and say how
 * current it is (Konzept 04, precaution 3).
 */
final class Pages {

	/**
	 * Error code for a page or handbook that is missing or not readable. One
	 * code for both, so an answer does not tell what exists.
	 */
	public const NOT_FOUND = 'living_handbook_not_found';

	/**
	 * The page, if an ability may hand it out to the current person.
	 *
	 * @param int|WP_Post $post Page or id.
	 * @return WP_Post|null
	 */
	public static function readable( int|WP_Post $post ): ?WP_Post {
		$post = $post instanceof WP_Post ? $post : get_post( $post );
		if ( ! $post instanceof WP_Post || Handbook::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status || '' !== (string) $post->post_password ) {
			return null;
		}
		if ( self::excluded( (int) $post->ID ) || ! AccessController::can_view_post( $post, get_current_user_id() ) ) {
			return null;
		}
		return $post;
	}

	/**
	 * Whether a page is kept away from every AI access.
	 *
	 * @param int $post_id Page id.
	 * @return bool
	 */
	public static function excluded( int $post_id ): bool {
		return (bool) get_post_meta( $post_id, Metadata::AI_EXCLUDE, true );
	}

	/**
	 * The handbooks the current person may read, by name.
	 *
	 * @return array<int, WP_Term>
	 */
	public static function readable_handbooks(): array {
		$terms = AccessController::readable_terms( get_current_user_id() );
		usort( $terms, static fn( WP_Term $a, WP_Term $b ): int => strcasecmp( $a->name, $b->name ) );
		return $terms;
	}

	/**
	 * A handbook named by slug or id, if the current person may read it.
	 *
	 * @param mixed $ref Slug, or id as number or numeric string.
	 * @return WP_Term|WP_Error
	 */
	public static function handbook( $ref ) {
		$term = null;
		if ( is_int( $ref ) || ( is_string( $ref ) && ctype_digit( $ref ) ) ) {
			$term = get_term( (int) $ref, Handbooks::TAXONOMY );
		} elseif ( is_string( $ref ) && '' !== $ref ) {
			$term = get_term_by( 'slug', sanitize_title( $ref ), Handbooks::TAXONOMY );
		}

		if ( ! $term instanceof WP_Term || ! AccessController::can_view_term( (int) $term->term_id, get_current_user_id() ) ) {
			return new WP_Error( self::NOT_FOUND, __( 'No such handbook, or you may not read it.', 'living-handbook' ) );
		}
		return $term;
	}

	/**
	 * The handbooks an ability works on: the one named, or every readable one.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<int, WP_Term>|WP_Error
	 */
	public static function scope( array $input ) {
		if ( isset( $input['handbook'] ) && '' !== $input['handbook'] ) {
			$term = self::handbook( $input['handbook'] );
			return is_wp_error( $term ) ? $term : array( $term );
		}
		return self::readable_handbooks();
	}

	/**
	 * A handbook as it appears in answers.
	 *
	 * @param WP_Term $term Handbook.
	 * @return array{id:int, slug:string, name:string, url:string}
	 */
	public static function handbook_reference( WP_Term $term ): array {
		$link = get_term_link( $term );
		return array(
			'id'   => (int) $term->term_id,
			'slug' => (string) $term->slug,
			'name' => (string) $term->name,
			'url'  => is_string( $link ) ? $link : '',
		);
	}

	/**
	 * A page as it appears in answers: title, address, handbook and review.
	 *
	 * The post is used as it is handed in, so a partial post from PageTree
	 * is not loaded again; a caller that already knows the handbook passes it.
	 *
	 * @param WP_Post      $post     Page, already checked.
	 * @param WP_Term|null $handbook Its handbook, when the caller knows it.
	 * @return array<string, mixed>
	 */
	public static function reference( WP_Post $post, ?WP_Term $handbook = null ): array {
		$post_id   = (int) $post->ID;
		$permalink = get_permalink( $post );
		if ( null === $handbook ) {
			$terms    = get_the_terms( $post, Handbooks::TAXONOMY );
			$first    = is_array( $terms ) ? reset( $terms ) : false;
			$handbook = $first instanceof WP_Term ? $first : null;
		}

		return array(
			'id'       => $post_id,
			'title'    => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			'url'      => (string) $permalink,
			'handbook' => null === $handbook ? '' : (string) $handbook->slug,
			'review'   => self::review( $post_id ),
		);
	}

	/**
	 * The review status of a page.
	 *
	 * @param int $post_id Page id.
	 * @return array{status:string, label:string, last_reviewed:string, interval_days:int}
	 */
	public static function review( int $post_id ): array {
		$status = FreshnessStatus::for_post( $post_id );
		return array(
			'status'        => $status,
			'label'         => FreshnessStatus::label( $status ),
			'last_reviewed' => (string) get_post_meta( $post_id, Metadata::REVIEWED, true ),
			'interval_days' => (int) get_post_meta( $post_id, Metadata::INTERVAL, true ),
		);
	}

	/**
	 * The name of a page's page type, or ''.
	 *
	 * @param WP_Post $post Page.
	 * @return string
	 */
	public static function page_type( WP_Post $post ): string {
		if ( ! Taxonomies::is_enabled( Taxonomies::PAGE_TYPE ) ) {
			return '';
		}
		$terms = get_the_terms( $post, Taxonomies::PAGE_TYPE );
		return is_array( $terms ) && isset( $terms[0] ) && $terms[0] instanceof WP_Term ? (string) $terms[0]->name : '';
	}

	/**
	 * The pages of a handbook an ability may hand out, in navigation order,
	 * each with its depth and its nearest handed-out ancestor.
	 *
	 * Built on PageTree::children_map(), the tree the navigation shows. A page
	 * that is left out (AI-excluded or with a password) takes nothing with it:
	 * its children move up to its parent. A page the person may not read at all
	 * is not in the map in the first place, as in the navigation.
	 *
	 * @param int $term_id   Handbook.
	 * @param int $max_depth Deepest level, from 1. 0 for all.
	 * @return array<int, array{post:WP_Post, depth:int, parent:int}>
	 */
	public static function tree( int $term_id, int $max_depth = 0 ): array {
		$map = PageTree::children_map( $term_id );
		if ( array() === $map ) {
			return array();
		}

		$ids = array();
		foreach ( $map as $children ) {
			foreach ( $children as $child ) {
				$ids[] = (int) $child->ID;
			}
		}
		update_meta_cache( 'post', $ids );
		update_object_term_cache( $ids, Handbook::POST_TYPE );

		$out = array();
		self::walk( $map, 0, 0, 1, $max_depth, $out );
		return $out;
	}

	/**
	 * One level of tree().
	 *
	 * @param array<int, array<int, WP_Post>>                        $map       Children by parent.
	 * @param int                                                    $from      Parent in the map.
	 * @param int                                                    $shown     Nearest handed-out ancestor.
	 * @param int                                                    $depth     Depth of this level.
	 * @param int                                                    $max_depth Deepest level, 0 for all.
	 * @param array<int, array{post:WP_Post, depth:int, parent:int}> $out       Result.
	 * @return void
	 */
	private static function walk( array $map, int $from, int $shown, int $depth, int $max_depth, array &$out ): void {
		if ( $max_depth > 0 && $depth > $max_depth ) {
			return;
		}
		foreach ( $map[ $from ] ?? array() as $post ) {
			$id   = (int) $post->ID;
			$hide = '' !== (string) $post->post_password || self::excluded( $id );
			if ( ! $hide ) {
				$out[] = array(
					'post'   => $post,
					'depth'  => $depth,
					'parent' => $shown,
				);
			}
			self::walk( $map, $id, $hide ? $shown : $id, $hide ? $depth : $depth + 1, $max_depth, $out );
		}
	}

	/**
	 * Whether the current person may use the editorial abilities: the same
	 * right as the review widget on the dashboard.
	 *
	 * @return bool
	 */
	public static function may_edit(): bool {
		return current_user_can( 'edit_others_posts' );
	}

	/**
	 * Whether the current person may use the reading abilities: any signed-in
	 * account. What each one sees is decided page by page.
	 *
	 * @return bool
	 */
	public static function may_read(): bool {
		return is_user_logged_in() && current_user_can( 'read' );
	}
}
