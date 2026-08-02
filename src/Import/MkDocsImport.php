<?php
/**
 * MkDocs import: build a page structure from a mkdocs.yml nav.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Import;

use LivingHandbook\Support\Vendored;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns a mkdocs.yml navigation tree into an ordered, flat list of page specs
 * (parents before children) that the browser then creates. The nav gives the
 * title, the nesting (parent), and the order of every page; a section whose
 * first child is an index.md or README.md uses that file as the section page,
 * otherwise a synthetic container page is emitted. Markdown files are located
 * inside the ZIP by matching the nav path against the end of each entry, so the
 * ZIP layout and the configured docs_dir do not have to line up. Each real page
 * carries the slug from its transport block, so an area start page keeps its
 * intended URL instead of a file-name slug like "readme".
 */
final class MkDocsImport {

	/**
	 * The YAML parser, as its library names it.
	 *
	 * A string rather than an import: a release build moves the library into its
	 * own namespace, so the name is resolved at runtime. See Vendored.
	 */
	private const YAML = 'Symfony\\Component\\Yaml\\Yaml';

	/**
	 * Whether a YAML parser is available.
	 *
	 * @return bool
	 */
	public static function available(): bool {
		return Vendored::exists( self::YAML );
	}

	/**
	 * Build the ordered page specs from a mkdocs.yml.
	 *
	 * @param string                $yaml      The mkdocs.yml contents.
	 * @param array<string, string> $files     Markdown files, keyed by ZIP path.
	 * @param array<string, string> $image_map Image basenames mapped to media URLs.
	 * @param MarkdownConverter     $converter Converter for the page bodies.
	 * @return array<int, mixed>
	 */
	public static function build_specs( string $yaml, array $files, array $image_map, MarkdownConverter $converter ): array {
		$parser = Vendored::name( self::YAML );

		try {
			$config = $parser::parse( $yaml );
		} catch ( \Throwable $e ) {
			return array();
		}
		if ( ! is_array( $config ) || ! isset( $config['nav'] ) || ! is_array( $config['nav'] ) ) {
			return array();
		}
		$specs = array();
		self::walk( $config['nav'], '', $files, $image_map, $converter, $specs );
		return $specs;
	}

	/**
	 * Walk a nav level, appending page specs in order.
	 *
	 * @param array<int, mixed>     $nodes       Nav nodes.
	 * @param string                $parent_path Parent source path.
	 * @param array<string, string> $files       Markdown files by ZIP path.
	 * @param array<string, string> $image_map   Image map.
	 * @param MarkdownConverter     $converter   Converter.
	 * @param array<int, mixed>     $specs       Collected specs, by reference.
	 * @return void
	 */
	private static function walk( array $nodes, string $parent_path, array $files, array $image_map, MarkdownConverter $converter, array &$specs ): void {
		$order = 0;
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			$title = (string) array_key_first( $node );
			$value = $node[ $title ];

			if ( is_string( $value ) ) {
				$specs[] = self::page_spec( $title, $value, $parent_path, $order, $files, $image_map, $converter );
				++$order;
				continue;
			}

			if ( is_array( $value ) ) {
				$index_path = self::find_index_child( $value );
				if ( null !== $index_path ) {
					$specs[]      = self::page_spec( $title, $index_path, $parent_path, $order, $files, $image_map, $converter );
					$section_path = $index_path;
					$children     = self::without_child( $value, $index_path );
				} else {
					$section_path = ( '' !== $parent_path ? $parent_path . '/' : '' ) . '~' . sanitize_title( $title );
					$specs[]      = array(
						'navTitle'   => $title,
						'sourcePath' => $section_path,
						'parentPath' => $parent_path,
						'order'      => $order,
						'html'       => '',
						'slug'       => '',
						'synthetic'  => true,
					);
					$children     = $value;
				}
				++$order;
				self::walk( $children, $section_path, $files, $image_map, $converter, $specs );
			}
		}
	}

	/**
	 * Find the first child that is a section index file (index.md or README.md).
	 *
	 * @param array<int, mixed> $children Nav children.
	 * @return string|null The index path, or null.
	 */
	private static function find_index_child( array $children ): ?string {
		foreach ( $children as $child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}
			$key   = (string) array_key_first( $child );
			$value = $child[ $key ];
			if ( is_string( $value ) ) {
				$base = strtolower( basename( $value ) );
				if ( 'index.md' === $base || 'readme.md' === $base ) {
					return $value;
				}
			}
		}
		return null;
	}

	/**
	 * Return the children without the given file path.
	 *
	 * @param array<int, mixed> $children Nav children.
	 * @param string            $path     Path to remove.
	 * @return array<int, mixed>
	 */
	private static function without_child( array $children, string $path ): array {
		$out = array();
		foreach ( $children as $child ) {
			if ( is_array( $child ) ) {
				$key = (string) array_key_first( $child );
				if ( isset( $child[ $key ] ) && $child[ $key ] === $path ) {
					continue;
				}
			}
			$out[] = $child;
		}
		return $out;
	}

	/**
	 * Build one page spec, converting the Markdown body to HTML.
	 *
	 * @param string                $title       Nav title.
	 * @param string                $nav_path    Nav path.
	 * @param string                $parent_path Parent source path.
	 * @param int                   $order       Order among siblings.
	 * @param array<string, string> $files       Markdown files by ZIP path.
	 * @param array<string, string> $image_map   Image map.
	 * @param MarkdownConverter     $converter   Converter.
	 * @return array<string, mixed>
	 */
	private static function page_spec( string $title, string $nav_path, string $parent_path, int $order, array $files, array $image_map, MarkdownConverter $converter ): array {
		$content = self::find_content( $nav_path, $files );
		$html    = '';
		$slug    = '';
		if ( null !== $content ) {
			$result = $converter->convert( $content, $image_map );
			$html   = (string) $result['html'];
			if ( is_array( $result['transport'] ) && isset( $result['transport']['slug'] ) ) {
				$slug = (string) $result['transport']['slug'];
			}
		}
		return array(
			'navTitle'   => $title,
			'sourcePath' => $nav_path,
			'parentPath' => $parent_path,
			'order'      => $order,
			'html'       => $html,
			'slug'       => $slug,
			'synthetic'  => false,
		);
	}

	/**
	 * Find a Markdown file in the ZIP whose path ends with the nav path.
	 *
	 * @param string                $nav_path Nav path.
	 * @param array<string, string> $files    Markdown files by ZIP path.
	 * @return string|null
	 */
	private static function find_content( string $nav_path, array $files ): ?string {
		if ( isset( $files[ $nav_path ] ) ) {
			return $files[ $nav_path ];
		}
		$needle = '/' . $nav_path;
		$length = strlen( $needle );
		foreach ( $files as $path => $content ) {
			if ( substr( $path, -$length ) === $needle ) {
				return $content;
			}
		}
		return null;
	}
}
