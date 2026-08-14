<?php
/**
 * What a learning path looks like on the frontend.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Training;

use LivingHandbook\Access\AccessController;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use WP_Post;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the lesson list on a learning path, and the path bar on a lesson.
 *
 * How a lesson knows which path it is being read in: the links out of a path
 * carry it in the URL (`?lh_path=<id>`). A page can sit in several paths, and
 * nothing on the page itself could say which one the reader came through. The
 * alternative, remembering it in the browser, would make the same URL mean
 * different things for different people and break the moment somebody shares a
 * link. Everything the path bar shows is therefore derived from the URL and
 * checked against the same access rules as the page itself.
 *
 * Progress in this stage lives in the browser and nowhere else: no table, no
 * user meta, no cookie, and so nothing personal to protect, export or delete.
 * The cost is stated plainly in the interface rather than hidden: it is a
 * guided path, not a record of attendance.
 */
final class PathView {

	/**
	 * The query argument that carries the path a lesson is read in.
	 */
	public const QUERY_ARG = 'lh_path';

	/**
	 * The learning path the current request is being read in, or 0.
	 *
	 * Reading a query argument, not acting on one: the value is cast to an int
	 * and then has to survive an access check before anything is rendered.
	 *
	 * @return int
	 */
	public static function current_path_id(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading which path is being viewed; nothing is written, and the id is checked against the access rules below.
		$raw = isset( $_GET[ self::QUERY_ARG ] ) ? absint( wp_unslash( $_GET[ self::QUERY_ARG ] ) ) : 0;

		return $raw;
	}

	/**
	 * Add the path argument to a lesson link.
	 *
	 * @param string $url         Lesson permalink.
	 * @param int    $training_id Learning path id.
	 * @return string
	 */
	public static function lesson_url( string $url, int $training_id ): string {
		if ( '' === $url || $training_id <= 0 ) {
			return $url;
		}

		return add_query_arg( self::QUERY_ARG, $training_id, $url );
	}

	/**
	 * Render the lesson list of the learning path being viewed.
	 *
	 * @return string
	 */
	public static function render_lessons(): string {
		$post = get_post();
		if ( ! $post instanceof WP_Post || Training::POST_TYPE !== $post->post_type || ! Training::is_enabled() ) {
			return '';
		}

		$user_id = get_current_user_id();
		$lessons = Lessons::visible( $post->ID, $user_id );
		$total   = count( $lessons );

		if ( 0 === $total ) {
			return '<div class="living-handbook-path"><p class="living-handbook-path__empty">'
				. esc_html__( 'This learning path has no lessons yet.', 'living-handbook' )
				. '</p></div>';
		}

		$items = '';
		foreach ( $lessons as $index => $lesson ) {
			$permalink = get_permalink( $lesson->ID );
			$url       = is_string( $permalink ) ? self::lesson_url( $permalink, $post->ID ) : '';

			$items .= sprintf(
				'<li class="living-handbook-path__item" data-lesson="%1$s">'
				. '<span class="living-handbook-path__number" aria-hidden="true">%2$s</span>'
				. '<a class="living-handbook-path__link" href="%3$s">%4$s</a>'
				. '<span class="living-handbook-path__state" data-done-label="%5$s"></span>'
				. '</li>',
				esc_attr( (string) $lesson->ID ),
				esc_html( (string) ( $index + 1 ) ),
				esc_url( $url ),
				esc_html( get_the_title( $lesson->ID ) ),
				esc_attr__( 'read', 'living-handbook' )
			);
		}

		$first      = $lessons[0];
		$first_link = get_permalink( $first->ID );
		$start      = is_string( $first_link ) ? self::lesson_url( $first_link, $post->ID ) : '';

		/* translators: %d: number of lessons in a learning path. */
		$count_label = sprintf( _n( '%d lesson', '%d lessons', $total, 'living-handbook' ), $total );

		return sprintf(
			'<div class="living-handbook-path" data-path="%1$s" data-total="%2$s">'
			. '<p class="living-handbook-path__progress" role="status">%3$s</p>'
			. '<ol class="living-handbook-path__list">%4$s</ol>'
			. '<p class="living-handbook-path__start"><a class="living-handbook-path__button" href="%5$s">%6$s</a></p>'
			. '<p class="living-handbook-path__note">%7$s</p>'
			. '</div>',
			esc_attr( (string) $post->ID ),
			esc_attr( (string) $total ),
			esc_html( $count_label ),
			$items,
			esc_url( $start ),
			esc_html__( 'Start', 'living-handbook' ),
			esc_html__( 'What you have read is remembered in this browser only, so it is gone when you clear the browser data or switch device.', 'living-handbook' )
		);
	}

	/**
	 * Render the learning paths of the handbook whose entry page is being shown.
	 *
	 * Without this a path could only be reached by somebody sending its link
	 * around, because the type has no archive and appears in no navigation: the
	 * lessons are the handbook's pages, and the path itself would be the one
	 * thing nobody can find. It renders nothing when there are no paths, so an
	 * entry page that has none looks exactly as it did before.
	 *
	 * @return string
	 */
	public static function render_paths(): string {
		if ( ! Training::is_enabled() ) {
			return '';
		}

		$term = get_queried_object();
		if ( ! $term instanceof WP_Term || Handbooks::TAXONOMY !== $term->taxonomy ) {
			return '';
		}

		$user_id = get_current_user_id();
		$paths   = Lessons::paths_of_handbook( (int) $term->term_id, $user_id );
		if ( array() === $paths ) {
			return '';
		}

		$items = '';
		foreach ( $paths as $path ) {
			$permalink = get_permalink( $path->ID );
			if ( ! is_string( $permalink ) ) {
				continue;
			}
			$count = count( Lessons::visible( $path->ID, $user_id ) );

			$items .= sprintf(
				'<li class="living-handbook-paths__item"><a href="%1$s">%2$s</a> <span class="living-handbook-paths__count">%3$s</span></li>',
				esc_url( $permalink ),
				esc_html( get_the_title( $path->ID ) ),
				esc_html(
					sprintf(
						/* translators: %d: number of lessons in a learning path. */
						_n( '%d lesson', '%d lessons', $count, 'living-handbook' ),
						$count
					)
				)
			);
		}

		if ( '' === $items ) {
			return '';
		}

		return '<nav class="living-handbook-paths" aria-labelledby="living-handbook-paths-title">'
			. '<h2 class="living-handbook-paths__title" id="living-handbook-paths-title">'
			. esc_html__( 'Learning paths', 'living-handbook' )
			. '</h2><ul class="living-handbook-paths__list">' . $items . '</ul></nav>';
	}

	/**
	 * Render the path bar on a handbook page that is being read as a lesson.
	 *
	 * Renders nothing at all unless every condition holds: the module is on, the
	 * URL names a path, that path exists and is published, the reader may view
	 * it, and this page is one of its lessons for this reader. A page that is
	 * not in the path renders nothing rather than an error, because the argument
	 * can be stale, guessed or simply wrong, and none of that is worth a message.
	 *
	 * @return string
	 */
	public static function render_path_nav(): string {
		if ( ! Training::is_enabled() ) {
			return '';
		}

		$post = get_post();
		if ( ! $post instanceof WP_Post || Handbook::POST_TYPE !== $post->post_type ) {
			return '';
		}

		$training_id = self::current_path_id();
		if ( $training_id <= 0 ) {
			return '';
		}

		$training = get_post( $training_id );
		if ( ! $training instanceof WP_Post
			|| Training::POST_TYPE !== $training->post_type
			|| 'publish' !== $training->post_status ) {
			return '';
		}

		$user_id = get_current_user_id();
		if ( ! AccessController::can_view_post( $training_id, $user_id ) ) {
			return '';
		}

		$lessons  = Lessons::visible( $training_id, $user_id );
		$total    = count( $lessons );
		$position = 0;
		foreach ( $lessons as $index => $lesson ) {
			if ( $lesson->ID === $post->ID ) {
				$position = $index + 1;
				break;
			}
		}
		if ( 0 === $position ) {
			return '';
		}

		$path_link = get_permalink( $training_id );
		$path_url  = is_string( $path_link ) ? $path_link : '';

		$links = '';
		if ( $position > 1 ) {
			$links .= self::step_link( $lessons[ $position - 2 ], $training_id, __( 'Previous', 'living-handbook' ), 'prev' );
		}
		if ( $position < $total ) {
			$links .= self::step_link( $lessons[ $position ], $training_id, __( 'Next', 'living-handbook' ), 'next' );
		}

		return sprintf(
			'<nav class="living-handbook-pathbar" data-path="%1$s" data-lesson="%2$s" aria-label="%3$s">'
			. '<p class="living-handbook-pathbar__where"><a href="%4$s">%5$s</a> <span class="living-handbook-pathbar__position">%6$s</span></p>'
			. '<p class="living-handbook-pathbar__steps">%7$s</p>'
			. '</nav>',
			esc_attr( (string) $training_id ),
			esc_attr( (string) $post->ID ),
			esc_attr__( 'Learning path', 'living-handbook' ),
			esc_url( $path_url ),
			esc_html( get_the_title( $training_id ) ),
			esc_html(
				sprintf(
					/* translators: 1: position of this lesson, 2: number of lessons. */
					__( 'Lesson %1$d of %2$d', 'living-handbook' ),
					$position,
					$total
				)
			),
			$links
		);
	}

	/**
	 * One step link of the path bar.
	 *
	 * @param WP_Post $lesson      The lesson to link to.
	 * @param int     $training_id Learning path id.
	 * @param string  $label       Visible label.
	 * @param string  $direction   'prev' or 'next'.
	 * @return string
	 */
	private static function step_link( WP_Post $lesson, int $training_id, string $label, string $direction ): string {
		$permalink = get_permalink( $lesson->ID );
		if ( ! is_string( $permalink ) ) {
			return '';
		}

		return sprintf(
			'<a class="living-handbook-pathbar__step living-handbook-pathbar__step--%1$s" href="%2$s" rel="%1$s">'
			. '<span class="living-handbook-pathbar__direction">%3$s</span>'
			. '<span class="living-handbook-pathbar__title">%4$s</span></a>',
			esc_attr( $direction ),
			esc_url( self::lesson_url( $permalink, $training_id ) ),
			esc_html( $label ),
			esc_html( get_the_title( $lesson->ID ) )
		);
	}
}
