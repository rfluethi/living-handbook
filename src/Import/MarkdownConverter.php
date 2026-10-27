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
	 * Returns whether the Markdown library is installed.
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
	 * Converts a draft into title, HTML, and transport metadata.
	 *
	 * @param string                $markdown  Full Markdown draft.
	 * @param array<string, string> $image_map Map of image file name to media URL.
	 * @return array{title:string,html:string,transport:array<string, mixed>}
	 */
	public function convert( string $markdown, array $image_map = array() ): array {
		// A byte order mark in front of the file is not text. Windows editors
		// write one, and with it the front matter did not start at the start of
		// the file any more, so its "---" block was not recognised and landed in
		// the page as a horizontal rule followed by YAML.
		if ( str_starts_with( $markdown, "\xEF\xBB\xBF" ) ) {
			$markdown = substr( $markdown, 3 );
		}
		$markdown = $this->strip_front_matter( $markdown );

		$transport_raw = '';
		$position      = self::transport_marker_position( $markdown );
		if ( null !== $position ) {
			$transport_raw = substr( $markdown, $position );
			$markdown      = rtrim( (string) substr( $markdown, 0, $position ) );
			$markdown      = (string) preg_replace( '/\n-{3,}\s*$/', '', $markdown );
		}
		$transport = ( new TransportBlock() )->parse( $transport_raw );

		$title   = '';
		$heading = self::title_heading( $markdown );
		if ( null !== $heading ) {
			$title    = $heading['title'];
			$markdown = substr_replace( $markdown, '', $heading['offset'], $heading['length'] );
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
	 * Returns the byte offset of the transport-block marker, or null if there is none.
	 *
	 * The marker is the heading «## Transport-Metadaten» (the transport block is
	 * written in German, see TransportBlock). Two rules make
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
			$line                     = (string) $line;
			list( $is_fence, $fence ) = self::track_fence( $line, $fence );
			if ( ! $is_fence && null === $fence && 1 === preg_match( '/^##[ \t]*Transport-Metadaten/i', $line ) ) {
				$position = $offset;
			}
			$offset += strlen( $line );
		}
		return $position;
	}

	/**
	 * Returns the first top-level heading outside code, which becomes the page title.
	 *
	 * Up to 0.75.1 this was the first line anywhere that started with "# ". A
	 * page that opens with a shell example, "# install deps" in a code fence,
	 * had that comment taken as its title and cut out of the code. Fenced lines
	 * are skipped now, the same way the transport marker skips them, and a line
	 * indented four spaces or a tab is an indented code block in CommonMark, not
	 * a heading.
	 *
	 * Public and static so the string logic is unit-testable on its own.
	 *
	 * @param string $markdown Markdown draft.
	 * @return array{title: string, offset: int, length: int}|null
	 */
	public static function title_heading( string $markdown ): ?array {
		$offset = 0;
		$fence  = null;
		foreach ( (array) preg_split( '/(?<=\n)/', $markdown ) as $line ) {
			$line                     = (string) $line;
			list( $is_fence, $fence ) = self::track_fence( $line, $fence );
			if ( ! $is_fence && null === $fence && 1 === preg_match( '/^ {0,3}#[ \t]+(.+?)[ \t]*(\r?\n)?$/', $line, $heading ) ) {
				// The line break stays in the text, so the lines around the
				// heading do not run together.
				$length = strlen( $line ) - strlen( $heading[2] ?? '' );
				return array(
					'title'  => trim( $heading[1] ),
					'offset' => $offset,
					'length' => $length,
				);
			}
			$offset += strlen( $line );
		}
		return null;
	}

	/**
	 * Follows fenced code blocks line by line, the way CommonMark does.
	 *
	 * A fence line opens a code block, or closes the open one when it uses the
	 * same character and is at least as long. Shared by the two scans that must
	 * not look inside code: the transport marker and the title heading.
	 *
	 * @param string                             $line  One line of the draft.
	 * @param array{char: string, len: int}|null $fence The open fence, or null outside code.
	 * @return array{0: bool, 1: array{char: string, len: int}|null} Whether the line is a fence line, and the open fence after it.
	 */
	private static function track_fence( string $line, ?array $fence ): array {
		if ( 1 !== preg_match( '/^ {0,3}(([`~])\2{2,})/', $line, $found ) ) {
			return array( false, $fence );
		}
		if ( null === $fence ) {
			return array(
				true,
				array(
					'char' => $found[2],
					'len'  => strlen( $found[1] ),
				),
			);
		}
		if ( $found[2] === $fence['char'] && strlen( $found[1] ) >= $fence['len'] ) {
			return array( true, null );
		}
		return array( true, $fence );
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
		// Both sides are compared decoded. The map is keyed by the file name as
		// it lies in the ZIP or as the Markdown wrote it, and CommonMark writes
		// the src percent-encoded: "Bild ä.png" arrives as "Bild%20%C3%A4.png".
		// Compared raw, such an image was uploaded but never linked, and the page
		// pointed at a path that does not exist on the site.
		$lookup = array();
		foreach ( $image_map as $name => $url ) {
			$lookup[ rawurldecode( (string) $name ) ] = $url;
		}

		return (string) preg_replace_callback(
			'/(<img\b[^>]*\bsrc=")([^"]+)("[^>]*>)/i',
			static function ( array $found ) use ( $lookup ): string {
				$src  = html_entity_decode( $found[2], ENT_QUOTES, 'UTF-8' );
				$path = wp_parse_url( $src, PHP_URL_PATH );
				$base = rawurldecode( basename( is_string( $path ) ? $path : $src ) );
				if ( isset( $lookup[ $base ] ) ) {
					return $found[1] . esc_url( $lookup[ $base ] ) . $found[3];
				}
				return $found[0];
			},
			$html
		);
	}
}
