<?php
/**
 * The ordered lesson list of a learning path.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Training;

use LivingHandbook\Access\AccessController;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use WP_Post;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes the lesson list, and answers what a given person sees of it.
 *
 * The stored list is a plain array of post ids in the order the editor put them
 * in. Everything else is derived: which of them still exist, which are
 * published, which belong to the path's handbook, and which this person may
 * read. Nothing about a lesson is copied into the path, so a page renamed or
 * moved tomorrow needs no bookkeeping here.
 */
final class Lessons {

	/**
	 * How many lessons one path holds at most.
	 *
	 * Not a data model limit, a sanity bound: the list is stored as one meta
	 * value and rendered as one page, and a path with a thousand lessons is a
	 * mistake rather than a use case. It is far above any onboarding path and
	 * keeps a broken import from writing an unbounded array.
	 */
	public const MAX = 200;

	/**
	 * Sanitize a lesson list: positive integers, no duplicates, bounded.
	 *
	 * Deliberately not checking here whether the ids exist or belong to the
	 * handbook. That decision depends on the state of other posts, which can
	 * change after the save, so it is made when the list is read instead. What
	 * this guarantees is the shape.
	 *
	 * @param mixed $value Raw value.
	 * @return array<int, int>
	 */
	public static function sanitize( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$ids = array();
		foreach ( $value as $id ) {
			$id = absint( $id );
			if ( $id > 0 && ! in_array( $id, $ids, true ) ) {
				$ids[] = $id;
			}
			if ( count( $ids ) >= self::MAX ) {
				break;
			}
		}

		return $ids;
	}

	/**
	 * The stored lesson list of a path, in order and unfiltered.
	 *
	 * @param int $training_id Learning path id.
	 * @return array<int, int>
	 */
	public static function stored( int $training_id ): array {
		if ( $training_id <= 0 ) {
			return array();
		}

		return self::sanitize( get_post_meta( $training_id, Training::META_LESSONS, true ) );
	}

	/**
	 * Store a lesson list.
	 *
	 * @param int               $training_id Learning path id.
	 * @param array<int, mixed> $ids        Lesson ids in order.
	 * @return void
	 */
	public static function store( int $training_id, array $ids ): void {
		$clean = self::sanitize( $ids );
		if ( array() === $clean ) {
			delete_post_meta( $training_id, Training::META_LESSONS );

			return;
		}

		update_post_meta( $training_id, Training::META_LESSONS, $clean );
	}

	/**
	 * The lessons of a path that this person actually sees, in order.
	 *
	 * Four things drop a lesson out: it no longer exists, it is not published,
	 * it belongs to a different handbook than the path, or this person may not
	 * read it. The last one is the reason the whole list is resolved per person
	 * rather than cached once: a path that quietly listed the title of an
	 * internal page would give away that the page exists, and a counter that
	 * included it would tell everyone how many they are missing.
	 *
	 * The consequence is deliberate and worth saying out loud: the same path can
	 * be six lessons long for one person and eight for another.
	 *
	 * @param int $training_id Learning path id.
	 * @param int $user_id     User id (0 for a guest).
	 * @return array<int, WP_Post>
	 */
	public static function visible( int $training_id, int $user_id ): array {
		$ids = self::stored( $training_id );
		if ( array() === $ids ) {
			return array();
		}

		$handbook = Handbooks::for_post( $training_id );
		if ( $handbook <= 0 ) {
			return array();
		}

		$query = new WP_Query(
			array(
				'post_type'              => Handbook::POST_TYPE,
				'post_status'            => 'publish',
				'post__in'               => $ids,
				'orderby'                => 'post__in',
				'posts_per_page'         => count( $ids ),
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'tax_query'              => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy'         => Handbooks::TAXONOMY,
						'field'            => 'term_id',
						'terms'            => $handbook,
						'include_children' => false,
					),
				),
			)
		);

		$lessons = array();
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			if ( ! AccessController::can_view_post( $post->ID, $user_id ) ) {
				continue;
			}
			$lessons[] = $post;
		}

		return $lessons;
	}

	/**
	 * Where a page sits in a path for this person, counted from 1.
	 *
	 * @param int $training_id Learning path id.
	 * @param int $post_id     Handbook page id.
	 * @param int $user_id     User id (0 for a guest).
	 * @return int Position from 1, or 0 when the page is not in the path.
	 */
	public static function position( int $training_id, int $post_id, int $user_id ): int {
		$position = 0;
		foreach ( self::visible( $training_id, $user_id ) as $index => $lesson ) {
			if ( $lesson->ID === $post_id ) {
				$position = $index + 1;
				break;
			}
		}

		return $position;
	}

	/**
	 * The learning paths of one handbook that this person may read.
	 *
	 * @param int $handbook_term_id Handbook term id.
	 * @param int $user_id          User id (0 for a guest).
	 * @return array<int, WP_Post>
	 */
	public static function paths_of_handbook( int $handbook_term_id, int $user_id ): array {
		if ( $handbook_term_id <= 0 || ! Training::is_enabled() ) {
			return array();
		}

		$query = new WP_Query(
			array(
				'post_type'              => Training::POST_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => 50,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'tax_query'              => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy'         => Handbooks::TAXONOMY,
						'field'            => 'term_id',
						'terms'            => $handbook_term_id,
						'include_children' => false,
					),
				),
			)
		);

		$paths = array();
		foreach ( $query->posts as $post ) {
			if ( $post instanceof WP_Post && AccessController::can_view_post( $post->ID, $user_id ) ) {
				$paths[] = $post;
			}
		}

		return $paths;
	}
}
