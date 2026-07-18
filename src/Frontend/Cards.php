<?php
/**
 * Card rendering for the handbook overview and entry pages.
 *
 * Ported from the prototype: a page card carries a type badge, a short excerpt,
 * the responsible role and a freshness dot. Area tiles show the top level of a
 * handbook, and a handbook card links from the overview to a handbook entry.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Frontend;

use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use LivingHandbook\Taxonomy\Taxonomies;
use WP_Post;
use WP_Query;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stateless renderers for the handbook cards.
 */
final class Cards {

	/**
	 * Render a single handbook page as a card.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function page_card( int $post_id ): string {
		$type_name = self::first_term_name( $post_id, Taxonomies::PAGE_TYPE );
		$type_slug = self::first_term_slug( $post_id, Taxonomies::PAGE_TYPE );
		$role_name = self::first_term_name( $post_id, Taxonomies::ROLE );
		$role_slug = self::first_term_slug( $post_id, Taxonomies::ROLE );
		$topics    = self::term_slugs( $post_id, Taxonomies::TOPIC );
		$audiences = self::term_slugs( $post_id, Taxonomies::AUDIENCE );

		$status = FreshnessStatus::for_post( $post_id );
		// The freshness status must not rely on colour alone (WCAG 1.4.1) and
		// must be reachable by assistive technology (1.1.1): the dot carries a
		// visually hidden text label, and its shape varies per status in CSS.
		$dot = '';
		if ( FreshnessStatus::NONE !== $status ) {
			$label = FreshnessStatus::label( $status );
			$dot   = '<span class="living-handbook-card__dot living-handbook-card__dot--' . esc_attr( $status ) . '" title="' . esc_attr( $label ) . '">'
				. '<span class="living-handbook-visually-hidden">' . esc_html( $label ) . '</span></span>';
		}

		$excerpt = has_excerpt( $post_id )
			? wp_strip_all_tags( get_the_excerpt( $post_id ) )
			: wp_trim_words( wp_strip_all_tags( (string) get_post_field( 'post_content', $post_id ) ), 22 );

		$badge = '' !== $type_name
			? '<span class="living-handbook-badge living-handbook-badge--type">' . esc_html( $type_name ) . '</span>'
			: '';

		$role_line = '<span></span>';
		if ( '' !== $role_name ) {
			/* translators: %s: name of the responsible role. */
			$role_line = '<span>' . esc_html( sprintf( __( 'Role: %s', 'living-handbook' ), $role_name ) ) . '</span>';
		}

		return sprintf(
			'<article class="living-handbook-card" data-title="%1$s" data-type="%2$s" data-topic="%3$s" data-role="%4$s" data-audience="%5$s">'
			. '<a class="living-handbook-card__link" href="%6$s">%7$s'
			. '<h3 class="living-handbook-card__title">%8$s</h3>'
			. '<p class="living-handbook-card__excerpt">%9$s</p>'
			. '<p class="living-handbook-card__meta">%10$s%11$s</p></a></article>',
			esc_attr( wp_strip_all_tags( get_the_title( $post_id ) ) ),
			esc_attr( $type_slug ),
			esc_attr( implode( ' ', $topics ) ),
			esc_attr( $role_slug ),
			esc_attr( implode( ' ', $audiences ) ),
			esc_url( (string) get_permalink( $post_id ) ),
			$badge,
			esc_html( get_the_title( $post_id ) ),
			esc_html( $excerpt ),
			$role_line,
			$dot
		);
	}

	/**
	 * Render the badge row for a single handbook page (type, topic, audience).
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function badges( int $post_id ): string {
		$type     = self::first_term_name( $post_id, Taxonomies::PAGE_TYPE );
		$topic    = self::first_term_name( $post_id, Taxonomies::TOPIC );
		$audience = self::first_term_name( $post_id, Taxonomies::AUDIENCE );

		$out = '';
		if ( '' !== $type ) {
			$out .= '<span class="living-handbook-badge living-handbook-badge--type">' . esc_html( $type ) . '</span>';
		}
		if ( '' !== $topic ) {
			$out .= '<span class="living-handbook-badge">' . esc_html( $topic ) . '</span>';
		}
		if ( '' !== $audience ) {
			/* translators: %s: audience name. */
			$out .= '<span class="living-handbook-badge living-handbook-badge--audience">' . esc_html( sprintf( __( 'Audience: %s', 'living-handbook' ), $audience ) ) . '</span>';
		}

		if ( '' === $out ) {
			return '';
		}
		return '<p class="living-handbook-badges">' . $out . '</p>';
	}

	/**
	 * Render the most recently updated pages of a handbook as a card grid.
	 *
	 * @param int $term_id Handbook term ID.
	 * @param int $limit   Maximum number of cards (0 for all).
	 * @return string
	 */
	public static function page_grid( int $term_id, int $limit = 0 ): string {
		$query = new WP_Query(
			array(
				'post_type'      => Handbook::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => $limit > 0 ? $limit : -1,
				'orderby'        => 'modified',
				'order'          => 'DESC',
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

		$cards = '';
		foreach ( $query->posts as $post ) {
			if ( $post instanceof WP_Post ) {
				$cards .= self::page_card( $post->ID );
			}
		}
		if ( '' === $cards ) {
			return '';
		}
		return '<div class="living-handbook-cards">' . $cards . '</div>';
	}

	/**
	 * Render the top-level pages of a handbook as area tiles.
	 *
	 * The tiles walk the hierarchy (one query for the top level plus one per top
	 * page to count children), so the rendered markup is cached per handbook and
	 * reused until a handbook page or term changes. The cache shares the version
	 * counter that Navigation::invalidate() bumps.
	 *
	 * @param int $term_id Handbook term ID.
	 * @return string
	 */
	public static function areas( int $term_id ): string {
		// Shared cache version, bumped by Navigation::invalidate() on page and
		// term changes.
		$version   = (int) get_option( 'living_handbook_nav_version', 0 );
		$cache_key = 'lh_areas_' . $version . '_' . $term_id;

		$cached = get_transient( $cache_key );
		if ( is_string( $cached ) ) {
			return $cached;
		}

		$tops = self::child_pages( 0, $term_id );
		if ( empty( $tops ) ) {
			set_transient( $cache_key, '', HOUR_IN_SECONDS );
			return '';
		}

		$out = '<div class="living-handbook-cards living-handbook-cards--areas">';
		foreach ( $tops as $post ) {
			$children = count( self::child_pages( $post->ID, $term_id ) );
			$excerpt  = has_excerpt( $post->ID )
				? wp_strip_all_tags( get_the_excerpt( $post->ID ) )
				: wp_trim_words( wp_strip_all_tags( (string) $post->post_content ), 18 );

			/* translators: %d: number of subpages. */
			$count_label = sprintf( _n( '%d subpage', '%d subpages', $children, 'living-handbook' ), $children );

			$out .= sprintf(
				'<article class="living-handbook-card living-handbook-card--area"><a class="living-handbook-card__link" href="%1$s">'
				. '<h3 class="living-handbook-card__title">%2$s</h3><p class="living-handbook-card__excerpt">%3$s</p>'
				. '<p class="living-handbook-card__meta"><span>%4$s</span></p></a></article>',
				esc_url( (string) get_permalink( $post ) ),
				esc_html( get_the_title( $post ) ),
				esc_html( $excerpt ),
				esc_html( $count_label )
			);
		}
		$out .= '</div>';

		set_transient( $cache_key, $out, DAY_IN_SECONDS );
		return $out;
	}

	/**
	 * Render a handbook (grouping term) as a card that links to its entry page.
	 *
	 * @param WP_Term $term Handbook term.
	 * @return string
	 */
	public static function handbook_card( WP_Term $term ): string {
		$link = get_term_link( $term );
		if ( is_wp_error( $link ) ) {
			return '';
		}

		$excerpt = '' !== $term->description
			? '<p class="living-handbook-card__excerpt">' . esc_html( wp_strip_all_tags( $term->description ) ) . '</p>'
			: '';

		$count = (int) $term->count;
		/* translators: %d: number of pages in a handbook. */
		$count_label = sprintf( _n( '%d page', '%d pages', $count, 'living-handbook' ), $count );

		return sprintf(
			'<article class="living-handbook-card living-handbook-card--book"><a class="living-handbook-card__link" href="%1$s">'
			. '<h2 class="living-handbook-card__title">%2$s</h2>%3$s'
			. '<p class="living-handbook-card__meta"><span>%4$s</span></p></a></article>',
			esc_url( (string) $link ),
			esc_html( $term->name ),
			$excerpt,
			esc_html( $count_label )
		);
	}

	/**
	 * Return the published child pages of a page within a handbook.
	 *
	 * @param int $parent_id Parent post ID (0 for the top level).
	 * @param int $term_id   Handbook term ID.
	 * @return array<int, WP_Post>
	 */
	private static function child_pages( int $parent_id, int $term_id ): array {
		$query = new WP_Query(
			array(
				'post_type'      => Handbook::POST_TYPE,
				'post_parent'    => $parent_id,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'orderby'        => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => Handbooks::TAXONOMY,
						'field'    => 'term_id',
						'terms'    => $term_id,
					),
				),
			)
		);

		$posts = array();
		foreach ( $query->posts as $post ) {
			if ( $post instanceof WP_Post ) {
				$posts[] = $post;
			}
		}
		return $posts;
	}

	/**
	 * Name of the first term of a taxonomy on a post, or an empty string.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @return string
	 */
	private static function first_term_name( int $post_id, string $taxonomy ): string {
		$first = self::first_term( $post_id, $taxonomy );
		return $first instanceof WP_Term ? $first->name : '';
	}

	/**
	 * Slug of the first term of a taxonomy on a post, or an empty string.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @return string
	 */
	private static function first_term_slug( int $post_id, string $taxonomy ): string {
		$first = self::first_term( $post_id, $taxonomy );
		return $first instanceof WP_Term ? $first->slug : '';
	}

	/**
	 * The first term of a taxonomy on a post, or null.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @return WP_Term|null
	 */
	private static function first_term( int $post_id, string $taxonomy ): ?WP_Term {
		$terms = get_the_terms( $post_id, $taxonomy );
		if ( ! is_array( $terms ) ) {
			return null;
		}
		foreach ( $terms as $term ) {
			if ( $term instanceof WP_Term ) {
				return $term;
			}
		}
		return null;
	}

	/**
	 * All term slugs of a taxonomy on a post.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @return array<int, string>
	 */
	private static function term_slugs( int $post_id, string $taxonomy ): array {
		$terms = get_the_terms( $post_id, $taxonomy );
		if ( ! is_array( $terms ) ) {
			return array();
		}
		$slugs = array();
		foreach ( $terms as $term ) {
			if ( $term instanceof WP_Term ) {
				$slugs[] = $term->slug;
			}
		}
		return $slugs;
	}
}
