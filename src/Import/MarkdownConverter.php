<?php
/**
 * Server-side Markdown to HTML conversion for the import.
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
 * Turns a Markdown draft into title, HTML, and transport metadata. It strips a
 * leading YAML front matter block, splits off the transport block, lifts the
 * first top-level heading out as the title, converts the rest with a real
 * GitHub Flavored Markdown library, and rewrites image sources to media URLs.
 * The browser turns the HTML into blocks.
 *
 * Raw HTML in the Markdown passes through the converter, so the converted HTML
 * is run through the shared HtmlSanitizer allowlist before it is returned: a
 * hostile draft cannot smuggle a script into the page a capable user imports.
 */
final class MarkdownConverter {

	/**
	 * The Markdown library's converter, as the library itself names it.
	 *
	 * Written as a string, not as an import: a release build moves the library
	 * into its own namespace, so the name is resolved at runtime. See Vendored.
	 */
	private const CONVERTER = 'League\\CommonMark\\GithubFlavoredMarkdownConverter';

	/**
	 * The result interface of the library's 2.x API, used to tell it from 1.x.
	 */
	private const RENDERED = 'League\\CommonMark\\Output\\RenderedContentInterface';

	/**
	 * Whether the Markdown library is installed.
	 *
	 * @return bool
	 */
	public static function available(): bool {
		Vendored::load();

		$converter = Vendored::name( self::CONVERTER );
		if ( ! class_exists( $converter ) ) {
			return false;
		}

		// The class alone is not enough. A release build has its own prefixed copy
		// and is safe, but a development checkout ships the library unprefixed, so
		// another plugin may have loaded CommonMark first: in version 1.x the same
		// class exists but converts through convertToHtml() and returns a plain
		// string, and calling convert()->getContent() on it is a fatal error.
		// Check for the 2.x API instead of trusting the name.
		return method_exists( $converter, 'convert' )
			&& interface_exists( Vendored::name( self::RENDERED ) );
	}

	/**
	 * Convert a draft into title, HTML, and transport metadata.
	 *
	 * @param string                $markdown  Full Markdown draft.
	 * @param array<string, string> $image_map Map of image file name to media URL.
	 * @return array{title:string,html:string,transport:array<string, mixed>}
	 */
	public function convert( string $markdown, array $image_map = array() ): array {
		$markdown = $this->strip_front_matter( $markdown );

		$transport_raw = '';
		$position      = self::transport_marker_position( $markdown );
		if ( null !== $position ) {
			$transport_raw = substr( $markdown, $position );
			$markdown      = rtrim( (string) substr( $markdown, 0, $position ) );
			$markdown      = (string) preg_replace( '/\n-{3,}\s*$/', '', $markdown );
		}
		$transport = ( new TransportBlock() )->parse( $transport_raw );

		$title = '';
		if ( preg_match( '/^[ \t]*#[ \t]+(.+?)[ \t]*$/m', $markdown, $heading, PREG_OFFSET_CAPTURE ) ) {
			$title    = trim( $heading[1][0] );
			$markdown = substr_replace( $markdown, '', (int) $heading[0][1], strlen( $heading[0][0] ) );
		}

		$html = $this->to_html( $markdown );
		// GitHub task lists render as <input type="checkbox">, which the block
		// editor's paste handler drops. Turn them into ballot symbols so the
		// checkbox stays visible as text on every import path.
		$html = $this->render_task_checkboxes( $html );
		if ( ! empty( $image_map ) ) {
			$html = $this->rewrite_images( $html, $image_map );
		}
		// The HTML came from an external source; strip anything unsafe before it
		// reaches the browser and the database.
		$html = HtmlSanitizer::clean( $html );

		return array(
			'title'     => $title,
			'html'      => $html,
			'transport' => $transport,
		);
	}

	/**
	 * Byte offset of the transport-block marker, or null if there is none.
	 *
	 * The marker is the German heading «## Transport-Metadaten». Two rules make
	 * the detection robust enough that a page can talk about the marker without
	 * being cut in half by it. A marker inside a fenced code block is an example,
	 * not a marker, so fenced lines are skipped. And when the marker appears more
	 * than once outside code, the last occurrence wins, because the transport
	 * block is by definition the tail of the file. The app handbook's import page
	 * quotes the marker in its documentation, which is exactly this case.
	 *
	 * Public and static so the pure string logic is unit-testable without
	 * WordPress or the Markdown library.
	 *
	 * @param string $markdown Markdown draft.
	 * @return int|null
	 */
	public static function transport_marker_position( string $markdown ): ?int {
		$offset   = 0;
		$fence    = null;
		$position = null;
		foreach ( (array) preg_split( '/(?<=\n)/', $markdown ) as $line ) {
			$line = (string) $line;
			if ( preg_match( '/^ {0,3}(([`~])\2{2,})/', $line, $found ) ) {
				// A fence line: it opens a code block, or closes one when it
				// uses the same character and is at least as long (CommonMark).
				if ( null === $fence ) {
					$fence = array(
						'char' => $found[2],
						'len'  => strlen( $found[1] ),
					);
				} elseif ( $found[2] === $fence['char'] && strlen( $found[1] ) >= $fence['len'] ) {
					$fence = null;
				}
			} elseif ( null === $fence && 1 === preg_match( '/^##[ \t]*Transport-Metadaten/i', $line ) ) {
				$position = $offset;
			}
			$offset += strlen( $line );
		}
		return $position;
	}

	/**
	 * Remove a leading YAML front matter block, if present.
	 *
	 * @param string $markdown Markdown.
	 * @return string
	 */
	private function strip_front_matter( string $markdown ): string {
		return (string) preg_replace( '/\A---\r?\n.*?\r?\n---\r?\n?/s', '', $markdown );
	}

	/**
	 * Convert Markdown to HTML, keeping embedded raw HTML but not unsafe links.
	 *
	 * @param string $markdown Markdown.
	 * @return string
	 */
	private function to_html( string $markdown ): string {
		$class = Vendored::name( self::CONVERTER );

		/**
		 * The library class, under whichever name this installation has it.
		 *
		 * @var \League\CommonMark\GithubFlavoredMarkdownConverter $converter
		 */
		$converter = new $class(
			array(
				'html_input'         => 'allow',
				'allow_unsafe_links' => false,
			)
		);
		return (string) $converter->convert( $markdown )->getContent();
	}

	/**
	 * Replace GitHub task-list checkbox inputs with ballot symbols.
	 *
	 * A task list item renders as a disabled <input type="checkbox">. The block
	 * editor's paste handler drops <input> elements, so the checkbox would
	 * vanish on import. A ballot symbol (empty or checked) keeps it visible as
	 * plain text, and it needs no special styling to render.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	private function render_task_checkboxes( string $html ): string {
		return (string) preg_replace_callback(
			'/<input\b[^>]*\btype=(["\'])checkbox\1[^>]*>/i',
			static function ( array $found ): string {
				return ( false !== stripos( $found[0], 'checked' ) ) ? "\xE2\x98\x91 " : "\xE2\x98\x90 ";
			},
			$html
		);
	}

	/**
	 * Rewrite image sources to media URLs, matched by file name.
	 *
	 * @param string                $html      HTML.
	 * @param array<string, string> $image_map Map of image file name to media URL.
	 * @return string
	 */
	private function rewrite_images( string $html, array $image_map ): string {
		return (string) preg_replace_callback(
			'/(<img\b[^>]*\bsrc=")([^"]+)("[^>]*>)/i',
			static function ( array $found ) use ( $image_map ): string {
				$path = wp_parse_url( $found[2], PHP_URL_PATH );
				$base = basename( is_string( $path ) ? $path : $found[2] );
				if ( isset( $image_map[ $base ] ) ) {
					return $found[1] . esc_url( $image_map[ $base ] ) . $found[3];
				}
				return $found[0];
			},
			$html
		);
	}
}
