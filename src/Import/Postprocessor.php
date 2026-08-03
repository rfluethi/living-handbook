<?php
/**
 * Shared post-processing for imported and synced handbook pages.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Import;

use LivingHandbook\Access\AccessController;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\Meta\Metadata;
use LivingHandbook\PostType\Handbook;
use LivingHandbook\Taxonomy\Taxonomies;
use WP_Post;
use WP_Query;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies transport metadata to a page and resolves cross-page references. Both
 * the Markdown import and the GitHub sync use this so a page gets the same
 * taxonomies, excerpt, order, review data, handbook, parent, and internal link
 * conversion no matter how it entered the handbook. Pages that carry a source
 * path (from a folder or MkDocs import) resolve links by path so files that share
 * a name, like many index.md, still link correctly.
 */
final class Postprocessor {

	public const PARENT_META = '_lh_import_parent_title';

	public const META_SOURCE_PATH = '_lh_source_path';

	/**
	 * Lookup tables for one finalize pass, or null when none is loaded.
	 *
	 * Resolving a link asks for a page by source path, by slug or by title. Each
	 * of those was a query, so a run of a few hundred pages with a handful of
	 * links each ran into thousands of them, in the one request that has to
	 * finish. The tables answer the same three questions from one query over the
	 * handbook. They are only loaded during finalize_report(); a single page
	 * converted on its own keeps asking the database, where one query beats
	 * reading the whole handbook.
	 *
	 * @var array{path: array<string, int>, slug: array<string, int>, title: array<string, int[]>}|null
	 */
	private static ?array $index = null;

	/**
	 * Apply the transport values to a page.
	 *
	 * @param int                  $post_id            Post id.
	 * @param array<string, mixed> $transport          Transport values.
	 * @param int                  $default_handbook_id Handbook to use when the transport names none.
	 * @return void
	 */
	public static function apply_transport( int $post_id, array $transport, int $default_handbook_id = 0 ): void {
		$handbook_id   = $default_handbook_id;
		$handbook_name = trim( (string) ( $transport['handbook'] ?? '' ) );
		if ( '' !== $handbook_name ) {
			$term = get_term_by( 'name', $handbook_name, Handbooks::TAXONOMY );
			if ( $term instanceof WP_Term ) {
				$handbook_id = (int) $term->term_id;
			}
		}
		if ( 0 < $handbook_id ) {
			wp_set_object_terms( $post_id, array( $handbook_id ), Handbooks::TAXONOMY );
		}

		$type = trim( (string) ( $transport['page_type'] ?? '' ) );
		if ( '' !== $type ) {
			wp_set_object_terms( $post_id, $type, Taxonomies::PAGE_TYPE );
		}
		$topic = trim( (string) ( $transport['topic'] ?? '' ) );
		if ( '' !== $topic ) {
			wp_set_object_terms( $post_id, $topic, Taxonomies::TOPIC );
		}
		$role = trim( (string) ( $transport['role'] ?? '' ) );
		if ( '' !== $role ) {
			wp_set_object_terms( $post_id, $role, Taxonomies::ROLE );
		}
		$audiences = ( isset( $transport['audiences'] ) && is_array( $transport['audiences'] ) ) ? array_map( 'strval', $transport['audiences'] ) : array();
		if ( ! empty( $audiences ) ) {
			wp_set_object_terms( $post_id, $audiences, Taxonomies::AUDIENCE );
		}

		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_excerpt' => trim( (string) ( $transport['excerpt'] ?? '' ) ),
				'menu_order'   => (int) ( $transport['order'] ?? 0 ),
			)
		);

		$reviewed = trim( (string) ( $transport['reviewed'] ?? '' ) );
		if ( 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $reviewed ) ) {
			update_post_meta( $post_id, Metadata::REVIEWED, $reviewed );
		}
		$interval = (int) ( $transport['interval'] ?? 0 );
		if ( 0 < $interval ) {
			update_post_meta( $post_id, Metadata::INTERVAL, $interval );
		}

		$parent_title = trim( (string) ( $transport['parent'] ?? '' ) );
		if ( '' !== $parent_title && 0 !== strcasecmp( 'oberste Ebene', $parent_title ) ) {
			update_post_meta( $post_id, self::PARENT_META, $parent_title );
		} else {
			delete_post_meta( $post_id, self::PARENT_META );
		}
	}

	/**
	 * Resolve parents and convert internal .md links for the given pages.
	 *
	 * @param array<int, int|string> $ids Post ids.
	 * @return int Number of links converted.
	 */
	public static function finalize( array $ids ): int {
		return self::finalize_report( $ids )['converted'];
	}

	/**
	 * Like finalize(), but also returns the links that could not be resolved.
	 *
	 * A link with no target is turned into plain text by convert_md_links(), so
	 * after this runs no raw .md link is left in any page to become a 404. The
	 * unresolved list is what would otherwise be a dead link: a typo or a page
	 * not (yet) in the import.
	 *
	 * @param array<int, int|string> $ids Post ids.
	 * @return array{converted: int, unresolved: array<int, array{source: string, target: string}>}
	 */
	public static function finalize_report( array $ids ): array {
		$converted  = 0;
		$unresolved = array();

		$numeric = array();
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( 0 !== $id ) {
				$numeric[] = $id;
			}
		}
		if ( array() === $numeric ) {
			return array(
				'converted'  => 0,
				'unresolved' => array(),
			);
		}

		self::begin_run( $numeric );

		try {
			foreach ( $numeric as $id ) {
				$result     = self::finalize_one( $id );
				$converted += $result['converted'];
				$unresolved = array_merge( $unresolved, $result['unresolved'] );
			}
		} finally {
			self::end_run();
		}

		return array(
			'converted'  => $converted,
			'unresolved' => $unresolved,
		);
	}

	/**
	 * Open a finalize run: load the lookup tables and warm the caches for the
	 * pages that are about to be worked through.
	 *
	 * An import that does not finish in one request finalizes in passes too, so
	 * this is public: the pass opens a run, works until its time is up, and
	 * closes it again. Whoever opens a run must close it, hence the try/finally
	 * at every call site.
	 *
	 * @param int[] $ids Pages this pass will work through.
	 * @return void
	 */
	public static function begin_run( array $ids = array() ): void {
		self::load_index();

		if ( array() !== $ids ) {
			// Every page is read again below, and each one is asked for its
			// handbook. Both come from the caches this fills.
			_prime_post_caches( $ids, true, true );
		}
	}

	/**
	 * Close a finalize run.
	 *
	 * @return void
	 */
	public static function end_run(): void {
		self::forget_index();
	}

	/**
	 * Finalize one page: set its parent from the import, then convert its links.
	 *
	 * @param int $post_id Post id.
	 * @return array{converted: int, unresolved: array<int, array{source: string, target: string}>}
	 */
	public static function finalize_one( int $post_id ): array {
		if ( $post_id <= 0 ) {
			return array(
				'converted'  => 0,
				'unresolved' => array(),
			);
		}
		self::resolve_parent( $post_id );
		return self::convert_md_links( $post_id );
	}

	/**
	 * Read the handbook into the lookup tables, in one query.
	 *
	 * The tables answer what the per-link lookups used to ask the database for:
	 * a page by its source path, by its slug, and by its title. Order matches
	 * what those lookups returned, newest first, so a duplicate slug or title
	 * resolves to the same page as before.
	 *
	 * @return void
	 */
	private static function load_index(): void {
		global $wpdb;

		// What WP_Query means by post_status "any": everything except the statuses
		// excluded from search, which is where trash and auto-draft sit. Drafts
		// are not among them, and must not be: an import that does not publish at
		// once creates drafts, and their links have to resolve too.
		$excluded = get_post_stati( array( 'exclude_from_search' => true ) );
		// A maintenance read over one post type, deliberately not a WP_Query: the
		// point of these tables is to replace thousands of them. The status filter
		// is applied below rather than in the statement, so the SQL stays a fixed
		// string with two placeholders.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, p.post_name, p.post_status, m.meta_value AS source_path
				 FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
				 WHERE p.post_type = %s
				 ORDER BY p.post_date DESC, p.ID DESC",
				self::META_SOURCE_PATH,
				Handbook::POST_TYPE
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$index = array(
			'path'  => array(),
			'slug'  => array(),
			'title' => array(),
		);
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( in_array( (string) $row->post_status, $excluded, true ) ) {
				continue;
			}
			$id   = (int) $row->ID;
			$path = (string) ( $row->source_path ?? '' );
			$slug = (string) $row->post_name;
			// Newest wins, exactly as the single queries ordered it, so the first
			// row for a key is the one that counts.
			if ( '' !== $path && ! isset( $index['path'][ $path ] ) ) {
				$index['path'][ $path ] = $id;
			}
			if ( '' !== $slug && ! isset( $index['slug'][ $slug ] ) ) {
				$index['slug'][ $slug ] = $id;
			}
			// Titles are compared the way the database compares them, without
			// regard to case. Two entries are enough: the caller only needs one
			// that is not the page asking.
			$title = self::title_key( (string) $row->post_title );
			if ( '' !== $title && count( $index['title'][ $title ] ?? array() ) < 2 ) {
				$index['title'][ $title ][] = $id;
			}
		}

		self::$index = $index;
	}

	/**
	 * Drop the lookup tables.
	 *
	 * @return void
	 */
	private static function forget_index(): void {
		self::$index = null;
	}

	/**
	 * The handbooks a page belongs to.
	 *
	 * Read through the object term cache (get_the_terms), not around it
	 * (wp_get_object_terms), so that a run which primed that cache does not ask
	 * the database once per page and once more per link.
	 *
	 * @param int $post_id Post id.
	 * @return int[]
	 */
	private static function handbook_ids( int $post_id ): array {
		$terms = get_the_terms( $post_id, Handbooks::TAXONOMY );
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}
		$ids = array();
		foreach ( $terms as $term ) {
			if ( $term instanceof WP_Term ) {
				$ids[] = (int) $term->term_id;
			}
		}
		return $ids;
	}

	/**
	 * The key a title is stored and looked up under.
	 *
	 * @param string $title Title.
	 * @return string
	 */
	private static function title_key( string $title ): string {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $title, 'UTF-8' ) : strtolower( $title );
	}

	/**
	 * Set a page's parent from the stored parent title, then clear the marker.
	 *
	 * @param int $post_id Post id.
	 * @return void
	 */
	private static function resolve_parent( int $post_id ): void {
		$parent_title = (string) get_post_meta( $post_id, self::PARENT_META, true );
		if ( '' === $parent_title ) {
			return;
		}
		$parent = self::find_page_by_title( $parent_title, $post_id );
		if ( 0 < $parent ) {
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_parent' => $parent,
				)
			);
		}
		delete_post_meta( $post_id, self::PARENT_META );
	}

	/**
	 * Convert internal .md links to WordPress permalinks, and defuse the rest.
	 *
	 * A link is resolved to its page in three tries: by source path (exact, when
	 * the page carries one), then by the folder name for an index or README file
	 * (whose page takes the folder's slug, not "readme"), then by the file-name
	 * slug. When the visible link text is itself a file name ending in .md, it is
	 * replaced with the target page title.
	 *
	 * A link that still resolves to no page is turned into plain text: the anchor
	 * is dropped and only its text kept. This is the guarantee that a handbook
	 * never shows a 404 link. A raw relative .md link left in the content would
	 * resolve in the browser to a page that does not exist; turning it into text
	 * makes that impossible. The link comes back by itself if the target page is
	 * added later, because every sync re-runs this.
	 *
	 * Public because the GitHub sync re-renders a page's Markdown on every pull
	 * and has to run this again: without it, the first scheduled sync after an
	 * import would turn every resolved cross-link back into a raw .md link.
	 *
	 * $defuse is false while an import is still creating pages. A page imported
	 * before its link target exists would otherwise have that link turned into
	 * plain text for good, and the closing pass, whose whole purpose is to
	 * resolve links once every page is there, would find nothing left to resolve.
	 * With $defuse false an unresolved link is left exactly as it is, and the
	 * closing pass decides.
	 *
	 * @param int  $post_id Post id.
	 * @param bool $defuse  Whether a link with no page becomes plain text.
	 * @return array{converted: int, unresolved: array<int, array{source: string, target: string}>}
	 */
	public static function convert_md_links( int $post_id, bool $defuse = true ): array {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return array(
				'converted'  => 0,
				'unresolved' => array(),
			);
		}
		$source_path = (string) get_post_meta( $post_id, self::META_SOURCE_PATH, true );
		$base_dir    = '' !== $source_path ? self::dir_of( $source_path ) : '';
		$page_title  = (string) get_the_title( $post_id );

		// Which handbooks this page belongs to. A link may well point into another
		// handbook, and it stays a link: whether the reader may open it is decided
		// when they click. What must not happen is that the title of a page in a
		// stricter handbook is pulled into this one as the link text.
		$own_handbooks = self::handbook_ids( $post_id );
		$count         = 0;
		$unresolved    = array();
		$content       = (string) preg_replace_callback(
			'/<a href="([^"]+\.md)"([^>]*)>(.*?)<\/a>/is',
			static function ( array $found ) use ( &$count, &$unresolved, $source_path, $base_dir, $page_title, $own_handbooks, $defuse ): string {
				$clean  = rawurldecode( (string) preg_replace( '/[?#].*$/', '', $found[1] ) );
				$target = 0;
				if ( '' !== $source_path ) {
					$target = self::find_page_by_source_path( self::resolve_path( $base_dir, $clean ) );
				}
				if ( 0 === $target ) {
					$target = self::find_page_by_slug( self::slug_for_link( $clean ) );
				}
				$permalink = $target > 0 ? get_permalink( $target ) : false;
				if ( 0 === $target || ! is_string( $permalink ) ) {
					if ( ! $defuse ) {
						// Still importing: the target may be created in a moment.
						// Leave the link alone and let the closing pass judge it.
						return $found[0];
					}
					// No page: drop the anchor, keep the text. Never leave a raw
					// .md link that would 404 in the browser.
					$unresolved[] = array(
						'source' => $page_title,
						'target' => basename( $clean ),
					);
					return $found[3];
				}
				++$count;
				$text  = $found[3];
				$plain = trim( wp_strip_all_tags( $text ) );
				if ( '' === $plain || 1 === preg_match( '/\.md$/i', $plain ) ) {
					$target_handbooks = self::handbook_ids( $target );
					$same_handbook    = ! empty( array_intersect( $own_handbooks, $target_handbooks ) );

					if ( $same_handbook ) {
						$title = get_the_title( $target );
						$text  = esc_html( '' !== $title ? $title : $plain );
					} else {
						// Another handbook, possibly a stricter one: use the file
						// name the author wrote, not the target's title.
						$name = (string) preg_replace( '/\.md$/i', '', basename( $clean ) );
						$text = esc_html( '' !== $name ? $name : $plain );
					}
				}
				return '<a href="' . esc_url( $permalink ) . '"' . $found[2] . '>' . $text . '</a>';
			},
			$post->post_content
		);
		if ( $content !== $post->post_content ) {
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => (string) wp_slash( $content ),
				)
			);
		}
		return array(
			'converted'  => $count,
			'unresolved' => $unresolved,
		);
	}

	/**
	 * The slug a .md link points at. An index or README file resolves to its
	 * folder's slug, because a folder's page takes the folder name, not "readme".
	 *
	 * @param string $path The link target, e.g. "../area/README.md".
	 * @return string
	 */
	private static function slug_for_link( string $path ): string {
		$name = pathinfo( $path, PATHINFO_FILENAME );
		if ( in_array( strtolower( $name ), array( 'index', 'readme' ), true ) ) {
			$dir = self::dir_of( $path );
			if ( '' !== $dir ) {
				$name = basename( $dir );
			}
		}
		return sanitize_title( $name );
	}

	/**
	 * The directory part of a source path.
	 *
	 * @param string $path Source path.
	 * @return string
	 */
	private static function dir_of( string $path ): string {
		$path = trim( $path, '/' );
		$pos  = strrpos( $path, '/' );
		return false === $pos ? '' : substr( $path, 0, $pos );
	}

	/**
	 * Resolve a relative link against a base directory, collapsing . and ..
	 *
	 * @param string $base_dir Base directory.
	 * @param string $relative Relative path.
	 * @return string
	 */
	private static function resolve_path( string $base_dir, string $relative ): string {
		$relative = ltrim( $relative, '/' );
		$parts    = '' === $base_dir ? array() : explode( '/', $base_dir );
		foreach ( explode( '/', $relative ) as $segment ) {
			if ( '' === $segment || '.' === $segment ) {
				continue;
			}
			if ( '..' === $segment ) {
				array_pop( $parts );
				continue;
			}
			$parts[] = $segment;
		}
		return implode( '/', $parts );
	}

	/**
	 * Find a handbook page by its stored source path.
	 *
	 * @param string $path Source path.
	 * @return int Post id, or 0.
	 */
	private static function find_page_by_source_path( string $path ): int {
		if ( '' === $path ) {
			return 0;
		}
		if ( null !== self::$index ) {
			return self::$index['path'][ $path ] ?? 0;
		}
		$query = new WP_Query(
			AccessController::internal(
				array(
					'post_type'      => Handbook::POST_TYPE,
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'no_found_rows'  => true,
					'meta_key'       => self::META_SOURCE_PATH, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'     => $path, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			)
		);
		$post  = $query->posts[0] ?? null;
		return $post instanceof WP_Post ? (int) $post->ID : 0;
	}

	/**
	 * Find a handbook page by title, ignoring one page: the one asking, so a
	 * page cannot become its own parent.
	 *
	 * The page to ignore is skipped in PHP rather than excluded in the query.
	 * Excluding a single known id does not need a NOT IN clause, and exclusionary
	 * query parameters scale badly. Two candidates are fetched, which is enough:
	 * at most one of them can be the asking page.
	 *
	 * @param string $title   Title.
	 * @param int    $exclude Post id to ignore.
	 * @return int Post id, or 0.
	 */
	private static function find_page_by_title( string $title, int $exclude ): int {
		if ( null !== self::$index ) {
			foreach ( self::$index['title'][ self::title_key( $title ) ] ?? array() as $candidate ) {
				if ( $candidate !== $exclude ) {
					return $candidate;
				}
			}
			return 0;
		}
		$query = new WP_Query(
			AccessController::internal(
				array(
					'post_type'      => Handbook::POST_TYPE,
					'post_status'    => 'any',
					'title'          => $title,
					'posts_per_page' => 2,
					'no_found_rows'  => true,
				)
			)
		);
		foreach ( $query->posts as $post ) {
			if ( $post instanceof WP_Post && (int) $post->ID !== $exclude ) {
				return (int) $post->ID;
			}
		}
		return 0;
	}

	/**
	 * Find a handbook page by slug.
	 *
	 * @param string $slug Slug.
	 * @return int Post id, or 0.
	 */
	private static function find_page_by_slug( string $slug ): int {
		if ( '' === $slug ) {
			return 0;
		}
		if ( null !== self::$index ) {
			return self::$index['slug'][ $slug ] ?? 0;
		}
		$posts = get_posts(
			AccessController::internal(
				array(
					'post_type'   => Handbook::POST_TYPE,
					'name'        => $slug,
					'post_status' => 'any',
					'numberposts' => 1,
				)
			)
		);
		$post  = $posts[0] ?? null;
		return $post instanceof WP_Post ? (int) $post->ID : 0;
	}
}
