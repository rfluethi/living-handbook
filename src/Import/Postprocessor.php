<?php
/**
 * Shared post-processing for imported and synced handbook pages.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Import;

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
		$converted = 0;
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( 0 === $id ) {
				continue;
			}
			self::resolve_parent( $id );
			$converted += self::convert_md_links( $id );
		}
		return $converted;
	}

	/**
	 * The internal .md links that finalize() could not resolve to a page.
	 *
	 * Run after finalize(): every link whose target exists has been rewritten to
	 * a permalink, so anything still pointing at a .md file is a dead link, a
	 * typo or a page that is not in the import. Reporting these turns "click every
	 * link to find the broken ones" into a list the importer hands you.
	 *
	 * @param array<int, int> $ids Post ids of the import.
	 * @return array<int, array{source: string, target: string}> Source page title and target file name per dead link.
	 */
	public static function unresolved_md_links( array $ids ): array {
		$found = array();
		foreach ( $ids as $id ) {
			$id   = (int) $id;
			$post = 0 !== $id ? get_post( $id ) : null;
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$count = preg_match_all( '/<a href="([^"]+\.md)"/i', (string) $post->post_content, $matches );
			if ( ! $count ) {
				continue;
			}
			foreach ( $matches[1] as $href ) {
				$clean   = rawurldecode( (string) preg_replace( '/[?#].*$/', '', $href ) );
				$found[] = array(
					'source' => (string) get_the_title( $id ),
					'target' => basename( $clean ),
				);
			}
		}
		return $found;
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
	 * Convert internal .md links to WordPress permalinks where the target exists.
	 *
	 * Pages with a source path resolve links by path; otherwise the target is
	 * found by file-name slug. When the visible link text is itself a file name
	 * ending in .md, it is replaced with the target page title.
	 *
	 * Public because the GitHub sync re-renders a page's Markdown on every pull
	 * and has to run this again: without it, the first scheduled sync after an
	 * import would turn every resolved cross-link back into a raw .md link.
	 *
	 * @param int $post_id Post id.
	 * @return int Number of links converted.
	 */
	public static function convert_md_links( int $post_id ): int {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return 0;
		}
		$source_path = (string) get_post_meta( $post_id, self::META_SOURCE_PATH, true );
		$base_dir    = '' !== $source_path ? self::dir_of( $source_path ) : '';
		$count       = 0;
		$content     = (string) preg_replace_callback(
			'/<a href="([^"]+\.md)"([^>]*)>(.*?)<\/a>/is',
			static function ( array $found ) use ( &$count, $source_path, $base_dir ): string {
				$clean  = rawurldecode( (string) preg_replace( '/[?#].*$/', '', $found[1] ) );
				$target = 0;
				if ( '' !== $source_path ) {
					$target = self::find_page_by_source_path( self::resolve_path( $base_dir, $clean ) );
				}
				if ( 0 === $target ) {
					$target = self::find_page_by_slug( sanitize_title( pathinfo( $clean, PATHINFO_FILENAME ) ) );
				}
				if ( 0 === $target ) {
					return $found[0];
				}
				$permalink = get_permalink( $target );
				if ( ! is_string( $permalink ) ) {
					return $found[0];
				}
				++$count;
				$text  = $found[3];
				$plain = trim( wp_strip_all_tags( $text ) );
				if ( '' === $plain || 1 === preg_match( '/\.md$/i', $plain ) ) {
					$title = get_the_title( $target );
					$text  = esc_html( '' !== $title ? $title : $plain );
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
		return $count;
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
		$query = new WP_Query(
			array(
				'post_type'      => Handbook::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				'meta_key'       => self::META_SOURCE_PATH, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $path, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
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
		$query = new WP_Query(
			array(
				'post_type'      => Handbook::POST_TYPE,
				'post_status'    => 'any',
				'title'          => $title,
				'posts_per_page' => 2,
				'no_found_rows'  => true,
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
		$posts = get_posts(
			array(
				'post_type'   => Handbook::POST_TYPE,
				'name'        => $slug,
				'post_status' => 'any',
				'numberposts' => 1,
			)
		);
		$post  = $posts[0] ?? null;
		return $post instanceof WP_Post ? (int) $post->ID : 0;
	}
}
