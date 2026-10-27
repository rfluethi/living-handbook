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

use LivingHandbook\Access\AccessController;
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
		// Same rule as the badges: a taxonomy the site does not use is neither
		// shown on the card nor written into the data attributes the filter reads.
		$type_name = Taxonomies::is_enabled( Taxonomies::PAGE_TYPE ) ? self::first_term_name( $post_id, Taxonomies::PAGE_TYPE ) : '';
		$type_slug = Taxonomies::is_enabled( Taxonomies::PAGE_TYPE ) ? self::first_term_slug( $post_id, Taxonomies::PAGE_TYPE ) : '';
		$role_name = Taxonomies::is_enabled( Taxonomies::ROLE ) ? self::first_term_name( $post_id, Taxonomies::ROLE ) : '';
		$role_slug = Taxonomies::is_enabled( Taxonomies::ROLE ) ? self::first_term_slug( $post_id, Taxonomies::ROLE ) : '';
		$topics    = Taxonomies::is_enabled( Taxonomies::TOPIC ) ? self::term_slugs( $post_id, Taxonomies::TOPIC ) : array();
		$audiences = Taxonomies::is_enabled( Taxonomies::AUDIENCE ) ? self::term_slugs( $post_id, Taxonomies::AUDIENCE ) : array();

		$status = FreshnessStatus::for_post( $post_id );
		// The freshness status must not rely on colour alone (WCAG 1.4.1) and
		// must be reachable by assistive technology (1.1.1): the dot carries a
		// visually hidden text label, and its shape varies per status in CSS.
		$dot   = '';
		$label = FreshnessStatus::label( $status );
		if ( '' !== $label ) {
			$dot = '<span class="living-handbook-card__dot living-handbook-card__dot--' . esc_attr( $status ) . '" title="' . esc_attr( $label ) . '">'
				. '<span class="living-handbook-visually-hidden">' . esc_html( $label ) . '</span></span>';
		}

		$excerpt = self::excerpt( $post_id, 22 );

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
	 * The short text of a page on a card: its excerpt, or its first words.
	 *
	 * Nothing for a password-protected page whose password the visitor has not
	 * entered. Up to 0.75.1 the card printed the first words of the content
	 * regardless, so the password kept the page closed and the card next to it
	 * open; and with an excerpt the card printed core's "There is no excerpt
	 * because this is a protected post." as if it were the page's summary.
	 *
	 * @param int $post_id Post ID.
	 * @param int $words   How many words of the content when there is no excerpt.
	 * @return string Plain text.
	 */
	private static function excerpt( int $post_id, int $words ): string {
		if ( post_password_required( $post_id ) ) {
			return '';
		}

		return has_excerpt( $post_id )
			? wp_strip_all_tags( get_the_excerpt( $post_id ) )
			: wp_trim_words( wp_strip_all_tags( (string) get_post_field( 'post_content', $post_id ) ), $words );
	}

	/**
	 * Render the badge row for a single handbook page (type, topic, audience).
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function badges( int $post_id ): string {
		// A taxonomy the site does not use has no badge. The terms are still on
		// the page; nothing here deletes them, this only stops showing them.
		$type     = Taxonomies::is_enabled( Taxonomies::PAGE_TYPE ) ? self::first_term_name( $post_id, Taxonomies::PAGE_TYPE ) : '';
		$topic    = Taxonomies::is_enabled( Taxonomies::TOPIC ) ? self::first_term_name( $post_id, Taxonomies::TOPIC ) : '';
		$audience = Taxonomies::is_enabled( Taxonomies::AUDIENCE ) ? self::first_term_name( $post_id, Taxonomies::AUDIENCE ) : '';

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
						'taxonomy'         => Handbooks::TAXONOMY,
						'field'            => 'term_id',
						'terms'            => $term_id,
						'include_children' => false,
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
	 * Cache key of the rendered area cards, scoped to the current viewer.
	 *
	 * Public so a test can assert that two users who may see different handbooks
	 * do not share one cache entry.
	 *
	 * @param int $term_id Handbook term ID.
	 * @return string
	 */
	public static function areas_cache_key( int $term_id ): string {
		// Shared cache version, bumped by Navigation::invalidate() on page and
		// term changes.
		$version = (int) get_option( Navigation::CACHE_VERSION_OPTION, 0 );

		return 'lh_areas_' . $version . '_' . $term_id . '_' . self::viewer_scope();
	}

	/**
	 * Cache key of a handbook's preview on the overview, scoped like the areas.
	 *
	 * @param int $term_id Handbook term ID.
	 * @param int $limit   How many pages the preview lists.
	 * @return string
	 */
	public static function preview_cache_key( int $term_id, int $limit ): string {
		$version = (int) get_option( Navigation::CACHE_VERSION_OPTION, 0 );

		return 'lh_preview_' . $version . '_' . $term_id . '_' . $limit . '_' . self::viewer_scope();
	}

	/**
	 * What decides which pages of a handbook the current viewer gets, as a
	 * short string for a cache key.
	 *
	 * @return string
	 */
	private static function viewer_scope(): string {
		// The cached lists are built from queries that are filtered per user, so
		// the key has to carry who is asking. Without that, the first editor to
		// open the page fills the cache with pages a guest may not see, and
		// everyone gets them for the next day. Two things decide what a viewer
		// gets: the handbooks they may read, and whether they read everything
		// (AccessController::reads_everything()), which viewable_term_ids() does
		// not express.
		return AccessController::reads_everything( get_current_user_id() )
			? 'all'
			: substr( md5( implode( ',', AccessController::viewable_term_ids( get_current_user_id() ) ) ), 0, 12 );
	}

	/**
	 * Render the top-level pages of a handbook as area tiles.
	 *
	 * The whole handbook is loaded in one query (PageTree) and the child counts
	 * are read from that map, so the rendered markup is cached per handbook and
	 * viewer, and reused until a handbook page or term changes. The cache shares
	 * the version counter that Navigation::invalidate() bumps.
	 *
	 * @param int $term_id Handbook term ID.
	 * @return string
	 */
	public static function areas( int $term_id ): string {
		// Shared cache version, bumped by Navigation::invalidate() on page and
		// term changes.
		$cache_key = self::areas_cache_key( $term_id );

		$cached = get_transient( $cache_key );
		if ( is_string( $cached ) ) {
			return $cached;
		}

		$map  = PageTree::children_map( $term_id );
		$tops = $map[0] ?? array();
		if ( empty( $tops ) ) {
			set_transient( $cache_key, '', HOUR_IN_SECONDS );
			return '';
		}

		// The map holds partial pages without their text, and a tile shows the
		// first words of each top-level page. Read those pages whole in one
		// query, rather than one get_post() per tile in excerpt().
		_prime_post_caches( array_map( static fn( WP_Post $post ): int => (int) $post->ID, $tops ), false, false );

		$out = '<div class="living-handbook-cards living-handbook-cards--areas">';
		foreach ( $tops as $post ) {
			$children = count( $map[ (int) $post->ID ] ?? array() );
			// This markup is cached and shared by everybody with the same
			// handbooks, so whether one visitor has entered the password cannot
			// decide what goes in. A protected page shows no text here at all.
			$excerpt = '' !== $post->post_password ? '' : self::excerpt( $post->ID, 18 );

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
	 * @param WP_Term $term        Handbook term.
	 * @param int     $preview     How many page titles to list under the card; 0 for none.
	 * @param string  $parent_name Name of the handbook this one belongs to, when there is one.
	 * @return string
	 */
	public static function handbook_card( WP_Term $term, int $preview = 0, string $parent_name = '' ): string {
		$link = get_term_link( $term );
		if ( is_wp_error( $link ) ) {
			return '';
		}
		$link = (string) $link;

		$excerpt = '' !== $term->description
			? '<p class="living-handbook-card__excerpt">' . esc_html( wp_strip_all_tags( $term->description ) ) . '</p>'
			: '';

		$count = (int) $term->count;
		/* translators: %d: number of pages in a handbook. */
		$count_label = sprintf( _n( '%d page', '%d pages', $count, 'living-handbook' ), $count );

		$meta = '<span>' . esc_html( $count_label ) . '</span>';
		if ( '' !== $parent_name ) {
			$meta .= '<span class="living-handbook-card__parent">' . esc_html(
				sprintf(
					/* translators: %s: name of the handbook this one belongs to. */
					__( 'in %s', 'living-handbook' ),
					$parent_name
				)
			) . '</span>';
		}

		// The card is one link, so the preview cannot sit inside it: a link inside
		// a link is not markup a browser can make sense of. It follows the card's
		// link as a sibling, inside the same article.
		return sprintf(
			'<article class="living-handbook-card living-handbook-card--book"><a class="living-handbook-card__link" href="%1$s">'
			. '<h2 class="living-handbook-card__title">%2$s</h2>%3$s'
			. '<p class="living-handbook-card__meta">%4$s</p></a>%5$s</article>',
			esc_url( $link ),
			esc_html( $term->name ),
			$excerpt,
			$meta,
			self::handbook_preview( $term, $preview, $link )
		);
	}

	/**
	 * The first pages of a handbook, as links under its card.
	 *
	 * A handbook's name and page count say what it is called and how big it is,
	 * not what is in it. Three page titles do, and they are the fastest way to
	 * tell two handbooks apart on an overview that lists several.
	 *
	 * Deliberately not DocsPress's "all" option: five handbooks of two hundred
	 * pages would put a thousand links on one page, which is a worse overview
	 * than no preview at all. The query fetches one page more than is shown, so
	 * the "more" link appears exactly when there is more.
	 *
	 * @param WP_Term $term  Handbook term.
	 * @param int     $limit How many pages to show; 0 for none.
	 * @param string  $link  The handbook's entry page.
	 * @return string
	 */
	private static function handbook_preview( WP_Term $term, int $limit, string $link ): string {
		if ( $limit < 1 ) {
			return '';
		}

		// Cached like the area tiles, per handbook and per access profile, and
		// retired by the same version counter when a page or a handbook changes.
		// An overview of ten handbooks otherwise ran four queries per card on
		// every view, for three titles each.
		$cache_key = self::preview_cache_key( (int) $term->term_id, $limit );
		$cached    = get_transient( $cache_key );
		if ( is_string( $cached ) ) {
			return $cached;
		}

		$out   = '';
		$pages = PageTree::top_level( (int) $term->term_id, $limit );
		if ( array() !== $pages ) {
			$more  = count( $pages ) > $limit;
			$pages = array_slice( $pages, 0, $limit );

			$items = '';
			foreach ( $pages as $post ) {
				$items .= sprintf(
					'<li><a href="%1$s">%2$s</a></li>',
					esc_url( (string) get_permalink( $post ) ),
					esc_html( get_the_title( $post ) )
				);
			}

			if ( $more ) {
				$items .= sprintf(
					'<li class="living-handbook-card__more"><a href="%1$s">%2$s</a></li>',
					esc_url( $link ),
					esc_html__( 'All pages', 'living-handbook' )
				);
			}

			$out = '<ul class="living-handbook-card__preview">' . $items . '</ul>';
		}

		set_transient( $cache_key, $out, DAY_IN_SECONDS );
		return $out;
	}

	/**
	 * Read the cached previews of several handbooks in one query.
	 *
	 * Without a persistent object cache a transient is two rows in the options
	 * table, and get_transient() reads them per call: one query per card even
	 * when every preview is cached. The overview knows all its cards up front,
	 * so it asks for all their rows at once and each card then finds its own in
	 * memory. With a persistent object cache transients do not live in the
	 * options table, and there is nothing to read ahead.
	 *
	 * @param array<int, WP_Term> $terms Handbooks on the overview.
	 * @param int                 $limit How many pages each preview lists.
	 * @return void
	 */
	public static function prime_previews( array $terms, int $limit ): void {
		if ( $limit < 1 || array() === $terms || wp_using_ext_object_cache() ) {
			return;
		}

		$names = array();
		foreach ( $terms as $term ) {
			if ( $term instanceof WP_Term ) {
				$key     = self::preview_cache_key( (int) $term->term_id, $limit );
				$names[] = '_transient_' . $key;
				$names[] = '_transient_timeout_' . $key;
			}
		}
		if ( array() !== $names ) {
			wp_prime_option_caches( $names );
		}
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
