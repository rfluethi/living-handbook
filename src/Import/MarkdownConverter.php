<?php
/**
 * Server-side Markdown to HTML conversion for the import.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Import;

use League\CommonMark\GithubFlavoredMarkdownConverter;

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
	 * Whether the Markdown library is installed.
	 *
	 * @return bool
	 */
	public static function available(): bool {
		$autoload = LIVING_HANDBOOK_DIR . 'vendor/autoload.php';
		if ( is_readable( $autoload ) ) {
			require_once $autoload;
		}
		return class_exists( GithubFlavoredMarkdownConverter::class );
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
		if ( preg_match_all( '/^##\s*Transport-Metadaten.*$/mi', $markdown, $all, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $all[0] as $match ) {
				$position = (int) $match[1];
				// A "## Transport-Metadaten" inside a fenced code block is a page
				// documenting the format, not its own transport section. An odd
				// number of ``` fences before the line means it sits in one, so
				// skip it and take the first real marker (which ends the body).
				if ( 1 === (int) preg_match_all( '/^```/m', substr( $markdown, 0, $position ) ) % 2 ) {
					continue;
				}
				$transport_raw = substr( $markdown, $position );
				$markdown      = rtrim( (string) substr( $markdown, 0, $position ) );
				$markdown      = (string) preg_replace( '/\n-{3,}\s*$/', '', $markdown );
				break;
			}
		}
		$transport = ( new TransportBlock() )->parse( $transport_raw );

		$title = '';
		if ( preg_match( '/^[ \t]*#[ \t]+(.+?)[ \t]*$/m', $markdown, $heading, PREG_OFFSET_CAPTURE ) ) {
			$title    = trim( $heading[1][0] );
			$markdown = substr_replace( $markdown, '', (int) $heading[0][1], strlen( $heading[0][0] ) );
		}

		$markdown = $this->convert_admonitions( $markdown );
		$html     = $this->to_html( $markdown );
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
	 * Turn MkDocs admonitions into Markdown blockquotes.
	 *
	 * `!!! note "Title"` with an indented body becomes a blockquote led by the
	 * title in bold, so a note that GitHub Flavored Markdown does not understand
	 * still reads as a set-apart block instead of stray text and lost indentation.
	 * A collapsible `???` admonition is treated the same way. Without an explicit
	 * title the admonition type is used, capitalised.
	 *
	 * @param string $markdown Markdown.
	 * @return string
	 */
	private function convert_admonitions( string $markdown ): string {
		$lines = preg_split( '/\r\n|\r|\n/', $markdown );
		if ( ! is_array( $lines ) ) {
			return $markdown;
		}
		$out   = array();
		$count = count( $lines );
		$i     = 0;
		while ( $i < $count ) {
			$line = $lines[ $i ];
			if ( 1 !== preg_match( '/^(?:!!!|\?\?\??)\s+([A-Za-z][\w-]*)(?:\s+"([^"]*)")?\s*$/', $line, $matched ) ) {
				$out[] = $line;
				++$i;
				continue;
			}

			$title = ( isset( $matched[2] ) && '' !== $matched[2] ) ? $matched[2] : ucfirst( $matched[1] );
			$body  = array();
			++$i;
			while ( $i < $count && ( '' === trim( $lines[ $i ] ) || 1 === preg_match( '/^(?:\t| {4})/', $lines[ $i ] ) ) ) {
				$body[] = (string) preg_replace( '/^(?:\t| {4})/', '', $lines[ $i ] );
				++$i;
			}
			while ( ! empty( $body ) && '' === trim( (string) end( $body ) ) ) {
				array_pop( $body );
			}

			$out[] = '> **' . $title . '**';
			$out[] = '>';
			foreach ( $body as $body_line ) {
				$out[] = '' === trim( $body_line ) ? '>' : '> ' . $body_line;
			}
			$out[] = '';
		}
		return implode( "\n", $out );
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
		$converter = new GithubFlavoredMarkdownConverter(
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
