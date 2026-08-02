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
	 * @param array<int, string>    $notes     Filled with what went wrong, if anything.
	 * @return array<int, mixed>
	 */
	public static function build_specs( string $yaml, array $files, array $image_map, MarkdownConverter $converter, array &$notes = array() ): array {
		$nav = self::read_nav( $yaml, $notes );
		if ( array() === $nav ) {
			return array();
		}

		$specs = array();
		self::walk( $nav, '', $files, $image_map, $converter, $specs );
		return $specs;
	}

	/**
	 * Read the navigation out of a mkdocs.yml.
	 *
	 * A real mkdocs.yml is not only navigation. It configures Python plugins, and
	 * it does so with YAML tags that only Python understands: the recommended
	 * setup for Mermaid diagrams alone writes
	 * "!!python/name:pymdownx.superfences.fence_code_format". A YAML parser in any
	 * other language stops at that, which used to mean the whole file was
	 * discarded and a documentation project was imported as a flat pile of files
	 * with their file names as titles.
	 *
	 * So the file is read twice if need be. Whole, which is the normal case and
	 * keeps anchors working; and if that fails, only the nav block, which is the
	 * part this import actually uses and the part least likely to carry anything
	 * exotic.
	 *
	 * @param string             $yaml  The mkdocs.yml contents.
	 * @param array<int, string> $notes Filled with what went wrong, if anything.
	 * @return array<int, mixed> The nav nodes, empty when there are none.
	 */
	private static function read_nav( string $yaml, array &$notes ): array {
		$parser = Vendored::name( self::YAML );
		$reason = '';

		try {
			$config = $parser::parse( $yaml );
			if ( is_array( $config ) && isset( $config['nav'] ) && is_array( $config['nav'] ) ) {
				return $config['nav'];
			}
			if ( is_array( $config ) && ! isset( $config['nav'] ) ) {
				$notes[] = __( 'The mkdocs.yml has no nav section. The files were imported without structure: their file names became the page titles.', 'living-handbook' );
				return array();
			}
		} catch ( \Throwable $e ) {
			$reason = $e->getMessage();
		}

		$block = self::nav_block( $yaml );
		if ( '' !== $block ) {
			try {
				$config = $parser::parse( $block );
				if ( is_array( $config ) && isset( $config['nav'] ) && is_array( $config['nav'] ) ) {
					// Nothing to report: the navigation is in, which is all this
					// import wanted from the file. That the plugin configuration
					// around it stayed unread is the importer's business, not the
					// reader's, and a warning on a successful import only teaches
					// people to ignore warnings.
					return $config['nav'];
				}
			} catch ( \Throwable $e ) {
				$reason = '' !== $reason ? $reason : $e->getMessage();
			}
		}

		if ( '' !== $reason ) {
			// Here the detail earns its place: the structure did not arrive, and
			// whoever wants it has to fix the file. Plain sentence first, parser
			// wording last.
			$notes[] = sprintf(
				/* translators: %s: the YAML parser's error message, in English. */
				__( 'The structure from the mkdocs.yml could not be applied: the file has an error. The files were imported without structure, their file names became the page titles. The reader reports: %s', 'living-handbook' ),
				$reason
			);
		}

		return array();
	}

	/**
	 * Cut the top-level nav block out of a mkdocs.yml, as text.
	 *
	 * Everything from the "nav:" line up to the next line that starts a new
	 * top-level key. Indented lines, blank lines and comments belong to the block.
	 *
	 * @param string $yaml The mkdocs.yml contents.
	 * @return string The block, or an empty string when there is no nav.
	 */
	private static function nav_block( string $yaml ): string {
		$lines = preg_split( '/\R/', $yaml );
		if ( ! is_array( $lines ) ) {
			return '';
		}

		$block  = array();
		$inside = false;
		foreach ( $lines as $line ) {
			$line = (string) $line;

			if ( ! $inside ) {
				if ( 1 === preg_match( '/^nav:\s*(#.*)?$/', $line ) ) {
					$inside  = true;
					$block[] = 'nav:';
				}
				continue;
			}

			$trimmed = trim( $line );
			if ( '' === $trimmed || 0 === strpos( $trimmed, '#' ) || 1 === preg_match( '/^\s/', $line ) ) {
				$block[] = $line;
				continue;
			}

			break;
		}

		return $inside ? implode( "\n", $block ) . "\n" : '';
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
