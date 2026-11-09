<?php
/**
 * Turns the HTML of a handbook page into Markdown the import reads back.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Export;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- childNodes, tagName and textContent are named by PHP's DOM extension.

/**
 * HTML in, GitHub-flavoured Markdown out, written for the way back in
 * (E-0083).
 *
 * The AI access has a converter of its own (Abilities\TextConverter). That
 * one writes text for a model to read and may lose what a reader would not
 * miss. This one writes a file the Markdown import turns back into the same
 * page, so it differs where the import would read something else:
 *
 * - Text is escaped where Markdown would take it for syntax: an asterisk, an
 *   underscore, a bracket, and at the start of a line a hash, a quote marker,
 *   a list marker or a number followed by a dot.
 * - A collapsible section stays a details element with its summary, which the
 *   import turns back into a details block. The body inside is Markdown, set
 *   off by blank lines so CommonMark reads it as such.
 * - A line break is a backslash at the end of the line, not two spaces that an
 *   editor removes without anyone seeing it.
 * - Addresses go through a callback, so a link to another exported page
 *   becomes a relative .md path and an image becomes a file next to the page.
 */
final class MarkdownWriter {

	/**
	 * Elements whose content is never text for the reader.
	 */
	private const SKIP = array( 'script', 'style', 'noscript', 'template', 'svg', 'form', 'button', 'input', 'select', 'textarea', 'iframe', 'object', 'embed', 'canvas', 'head', 'video', 'audio' );

	/**
	 * Elements that start a block of their own.
	 */
	private const BLOCKS = array( 'p', 'div', 'section', 'article', 'main', 'header', 'footer', 'aside', 'nav', 'figure', 'figcaption', 'details', 'summary', 'blockquote', 'ul', 'ol', 'table', 'pre', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'dl', 'dt', 'dd', 'li', 'address' );

	/**
	 * Gets an address and the kind ("link" or "image"), returns the address to
	 * write.
	 *
	 * @var callable|null
	 */
	private $map_url;

	/**
	 * Use convert().
	 *
	 * @param callable|null $map_url Callback.
	 */
	private function __construct( ?callable $map_url ) {
		$this->map_url = $map_url;
	}

	/**
	 * Converts HTML to Markdown.
	 *
	 * @param string        $html    HTML of the page body.
	 * @param callable|null $map_url Gets an address and "link" or "image", returns the address to write.
	 * @return string
	 */
	public static function convert( string $html, ?callable $map_url = null ): string {
		if ( '' === trim( $html ) ) {
			return '';
		}

		$doc      = new DOMDocument();
		$previous = libxml_use_internal_errors( true );
		// The XML declaration makes the HTML parser read UTF-8; without it, it
		// assumes ISO-8859-1 and every umlaut breaks.
		$doc->loadHTML( '<?xml encoding="UTF-8"?><html><body>' . $html . '</body></html>', LIBXML_NONET | LIBXML_COMPACT );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		$body = $doc->getElementsByTagName( 'body' )->item( 0 );
		if ( ! $body instanceof DOMElement ) {
			return '';
		}

		$text = implode( "\n\n", ( new self( $map_url ) )->blocks( $body ) );
		// A blank line inside a list item comes out indented; it holds nothing
		// and is written empty.
		$text = (string) preg_replace( '/^[ \t]+$/m', '', $text );
		$text = (string) preg_replace( "/\n{3,}/", "\n\n", $text );

		return trim( $text );
	}

	/**
	 * The blocks inside a node, each a string.
	 *
	 * @param DOMNode $node Node.
	 * @return array<int, string>
	 */
	private function blocks( DOMNode $node ): array {
		$out    = array();
		$inline = '';

		foreach ( $node->childNodes as $child ) {
			if ( $child instanceof DOMElement && self::is_block( $child ) ) {
				$this->flush( $inline, $out );
				$block = $this->block( $child );
				if ( '' !== trim( $block ) ) {
					$out[] = $block;
				}
				continue;
			}
			$inline .= $this->inline( $child );
		}
		$this->flush( $inline, $out );

		return $out;
	}

	/**
	 * Adds a run of inline text as a paragraph, if it holds anything.
	 *
	 * @param string             $inline Text gathered so far; emptied.
	 * @param array<int, string> $out    Blocks.
	 * @return void
	 */
	private function flush( string &$inline, array &$out ): void {
		$text = self::line_starts( self::tidy( $inline ) );
		if ( '' !== $text ) {
			$out[] = $text;
		}
		$inline = '';
	}

	/**
	 * One block element as Markdown.
	 *
	 * @param DOMElement $el Element.
	 * @return string
	 */
	private function block( DOMElement $el ): string {
		if ( self::skipped( $el ) ) {
			return '';
		}

		$tag = strtolower( $el->tagName );
		switch ( $tag ) {
			case 'h1':
			case 'h2':
			case 'h3':
			case 'h4':
			case 'h5':
			case 'h6':
				$text = self::tidy( str_replace( "\\\n", ' ', $this->inline_children( $el ) ) );
				return '' === $text ? '' : str_repeat( '#', (int) substr( $tag, 1 ) ) . ' ' . $text;

			case 'hr':
				return '---';

			case 'pre':
				return $this->code_block( $el );

			case 'ul':
			case 'ol':
				return $this->list_block( $el );

			case 'table':
				return $this->table( $el );

			case 'blockquote':
				$inner = implode( "\n\n", $this->blocks( $el ) );
				return '' === $inner ? '' : '> ' . str_replace( "\n", "\n> ", $inner );

			case 'details':
				return $this->details( $el );

			case 'summary':
			case 'dt':
				$text = self::tidy( $this->inline_children( $el ) );
				return '' === $text ? '' : '**' . $text . '**';

			case 'li':
				// A list item outside a list: treat it as a paragraph.
				return implode( "\n\n", $this->blocks( $el ) );

			default:
				// p, div, figure, section and the rest: their blocks in order.
				return implode( "\n\n", $this->blocks( $el ) );
		}
	}

	/**
	 * A collapsible section, kept as one.
	 *
	 * @param DOMElement $el The details element.
	 * @return string
	 */
	private function details( DOMElement $el ): string {
		$summary = '';
		$body    = array();
		$inline  = '';
		foreach ( $el->childNodes as $child ) {
			if ( $child instanceof DOMElement && 'summary' === strtolower( $child->tagName ) && '' === $summary ) {
				$summary = self::tidy( str_replace( "\\\n", ' ', $this->inline_children( $child ) ) );
				continue;
			}
			if ( $child instanceof DOMElement && self::is_block( $child ) ) {
				$this->flush( $inline, $body );
				$block = $this->block( $child );
				if ( '' !== trim( $block ) ) {
					$body[] = $block;
				}
				continue;
			}
			$inline .= $this->inline( $child );
		}
		$this->flush( $inline, $body );

		$out  = '<details' . ( $el->hasAttribute( 'open' ) ? ' open' : '' ) . ">\n<summary>" . $summary . "</summary>\n\n";
		$out .= implode( "\n\n", $body );
		return rtrim( $out ) . "\n\n</details>";
	}

	/**
	 * A preformatted block as a fenced code block.
	 *
	 * @param DOMElement $el The pre element.
	 * @return string
	 */
	private function code_block( DOMElement $el ): string {
		$lang  = '';
		$class = ' ' . $el->getAttribute( 'class' ) . ' ';
		if ( false !== strpos( $class, ' mermaid ' ) ) {
			$lang = 'mermaid';
		}
		foreach ( $el->childNodes as $child ) {
			if ( $child instanceof DOMElement && 'code' === strtolower( $child->tagName ) && '' === $lang ) {
				if ( 1 === preg_match( '/(?:^|\s)language-([\w+-]+)/', $child->getAttribute( 'class' ), $match ) ) {
					$lang = $match[1];
				}
			}
		}

		$code = rtrim( (string) $el->textContent, "\n" );
		if ( '' === trim( $code ) ) {
			return '';
		}

		// A fence longer than any run of backticks inside, so the code cannot
		// end it early.
		$fence = '```';
		while ( false !== strpos( $code, $fence ) ) {
			$fence .= '`';
		}

		return $fence . $lang . "\n" . $code . "\n" . $fence;
	}

	/**
	 * A list. A nested list is indented by the item that holds it.
	 *
	 * @param DOMElement $el The ul or ol.
	 * @return string
	 */
	private function list_block( DOMElement $el ): string {
		$ordered = 'ol' === strtolower( $el->tagName );
		$number  = $ordered && '' !== $el->getAttribute( 'start' ) ? (int) $el->getAttribute( 'start' ) : 1;
		$lines   = array();

		foreach ( $el->childNodes as $item ) {
			if ( ! $item instanceof DOMElement || 'li' !== strtolower( $item->tagName ) || self::skipped( $item ) ) {
				continue;
			}

			$marker = $ordered ? $number . '. ' : '- ';
			++$number;
			// Continuation lines line up with the text after the marker, which is
			// what CommonMark needs to keep them inside the item.
			$pad = str_repeat( ' ', strlen( $marker ) );

			$parts  = array();
			$inline = '';
			foreach ( $item->childNodes as $child ) {
				if ( $child instanceof DOMElement && in_array( strtolower( $child->tagName ), array( 'ul', 'ol' ), true ) ) {
					$this->flush( $inline, $parts );
					$nested = $this->list_block( $child );
					if ( '' !== $nested ) {
						$parts[] = "\n" . $nested;
					}
					continue;
				}
				if ( $child instanceof DOMElement && self::is_block( $child ) ) {
					$this->flush( $inline, $parts );
					$block = $this->block( $child );
					if ( '' !== $block ) {
						$parts[] = $block;
					}
					continue;
				}
				$inline .= $this->inline( $child );
			}
			$this->flush( $inline, $parts );

			$text = '';
			foreach ( $parts as $part ) {
				if ( 0 === strpos( $part, "\n" ) ) {
					// A nested list: right under the item, indented to its text.
					$text .= "\n" . $pad . str_replace( "\n", "\n" . $pad, substr( $part, 1 ) );
					continue;
				}
				$part  = str_replace( "\n", "\n" . $pad, $part );
				$text .= '' === $text ? $part : "\n\n" . $pad . $part;
			}
			$lines[] = $marker . $text;
		}

		return implode( "\n", $lines );
	}

	/**
	 * A table as a pipe table, the first row as its header.
	 *
	 * @param DOMElement $el The table.
	 * @return string
	 */
	private function table( DOMElement $el ): string {
		$rows = array();
		foreach ( $el->getElementsByTagName( 'tr' ) as $tr ) {
			$cells = array();
			foreach ( $tr->childNodes as $cell ) {
				if ( $cell instanceof DOMElement && in_array( strtolower( $cell->tagName ), array( 'td', 'th' ), true ) ) {
					// A pipe table cell holds one line: a break inside it becomes
					// the HTML break the import keeps.
					$text    = self::tidy( str_replace( array( "\\\n", "\n" ), array( '<br>', ' ' ), $this->inline_children( $cell ) ) );
					$cells[] = str_replace( '|', '\\|', $text );
				}
			}
			if ( array() !== $cells ) {
				$rows[] = $cells;
			}
		}
		if ( array() === $rows ) {
			return '';
		}

		$width = max( array_map( 'count', $rows ) );
		$lines = array();
		foreach ( $rows as $i => $cells ) {
			$cells   = array_pad( $cells, $width, '' );
			$lines[] = '| ' . implode( ' | ', $cells ) . ' |';
			if ( 0 === $i ) {
				$lines[] = '|' . str_repeat( ' --- |', $width );
			}
		}

		$caption = $el->getElementsByTagName( 'caption' )->item( 0 );
		$title   = $caption instanceof DOMElement ? self::tidy( $this->inline_children( $caption ) ) : '';

		return ( '' !== $title ? $title . "\n\n" : '' ) . implode( "\n", $lines );
	}

	/**
	 * The inline Markdown of a node's children.
	 *
	 * @param DOMNode $el Node.
	 * @return string
	 */
	private function inline_children( DOMNode $el ): string {
		$text = '';
		foreach ( $el->childNodes as $child ) {
			$text .= $this->inline( $child );
		}
		return $text;
	}

	/**
	 * One inline node as Markdown.
	 *
	 * @param DOMNode $node Node.
	 * @return string
	 */
	private function inline( DOMNode $node ): string {
		if ( $node instanceof DOMText ) {
			return self::escape( (string) preg_replace( '/\s+/u', ' ', $node->textContent ) );
		}
		if ( ! $node instanceof DOMElement || self::skipped( $node ) ) {
			return '';
		}

		$tag = strtolower( $node->tagName );
		switch ( $tag ) {
			case 'br':
				return "\\\n";

			case 'strong':
			case 'b':
				return self::wrap( '**', $this->inline_children( $node ) );

			case 'em':
			case 'i':
				return self::wrap( '*', $this->inline_children( $node ) );

			case 's':
			case 'del':
			case 'strike':
				return self::wrap( '~~', $this->inline_children( $node ) );

			case 'code':
				return self::code_span( (string) $node->textContent );

			case 'a':
				$text = self::tidy( $this->inline_children( $node ) );
				$href = trim( $node->getAttribute( 'href' ) );
				if ( 0 === strpos( $href, '#' ) && in_array( $text, array( '#', '¶', '§' ), true ) ) {
					// The anchor a heading carries (Headings::anchor()) says
					// nothing as text, and the import makes it again.
					return '';
				}
				if ( '' === $href ) {
					return $text;
				}
				$href = $this->mapped( $href, 'link' );
				return '[' . ( '' !== $text ? $text : self::escape( $href ) ) . '](' . self::destination( $href ) . ')';

			case 'img':
				$alt = self::escape( self::tidy( $node->getAttribute( 'alt' ) ) );
				$src = trim( $node->getAttribute( 'src' ) );
				if ( '' === $src ) {
					return '';
				}
				return '![' . $alt . '](' . self::destination( $this->mapped( $src, 'image' ) ) . ')';

			default:
				if ( self::is_block( $node ) ) {
					// A block inside inline content, such as a list in a table
					// cell: keep its text on the line.
					return ' ' . str_replace( "\n", ' ', $this->block( $node ) ) . ' ';
				}
				return $this->inline_children( $node );
		}
	}

	/**
	 * An address as the callback wants it written.
	 *
	 * @param string $url  Address.
	 * @param string $kind "link" or "image".
	 * @return string
	 */
	private function mapped( string $url, string $kind ): string {
		if ( null === $this->map_url ) {
			return $url;
		}
		$out = call_user_func( $this->map_url, $url, $kind );
		return is_string( $out ) && '' !== $out ? $out : $url;
	}

	/**
	 * A link destination, in angle brackets where it holds a space or a
	 * parenthesis that would end it early.
	 *
	 * @param string $url Address.
	 * @return string
	 */
	private static function destination( string $url ): string {
		if ( 1 === preg_match( '/[\s()<>]/', $url ) ) {
			return '<' . str_replace( array( '<', '>' ), array( '%3C', '%3E' ), $url ) . '>';
		}
		return $url;
	}

	/**
	 * A code span, with a fence longer than any run of backticks inside.
	 *
	 * @param string $code Code.
	 * @return string
	 */
	private static function code_span( string $code ): string {
		if ( '' === $code ) {
			return '';
		}
		$fence = '`';
		while ( false !== strpos( $code, $fence ) ) {
			$fence .= '`';
		}
		$pad = ( '`' === substr( $code, 0, 1 ) || '`' === substr( $code, -1 ) ) ? ' ' : '';
		return $fence . $pad . $code . $pad . $fence;
	}

	/**
	 * Text with the characters escaped that Markdown would read as syntax
	 * anywhere in a line.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function escape( string $text ): string {
		return (string) preg_replace( '/([\\\\`*_\[\]<~])/', '\\\\$1', $text );
	}

	/**
	 * A paragraph with the start of each line escaped where Markdown would read
	 * a heading, a quote, a list or a rule.
	 *
	 * @param string $text Paragraph.
	 * @return string
	 */
	private static function line_starts( string $text ): string {
		return (string) preg_replace(
			array( '/^([#>+=-])/m', '/^(\d+)([.)])/m' ),
			array( '\\\\$1', '$1\\\\$2' ),
			$text
		);
	}

	/**
	 * Wraps text in a marker, keeping the spaces outside it.
	 *
	 * @param string $marker Marker.
	 * @param string $text   Text.
	 * @return string
	 */
	private static function wrap( string $marker, string $text ): string {
		$trimmed = trim( $text );
		if ( '' === $trimmed ) {
			return $text;
		}
		$lead  = ' ' === substr( $text, 0, 1 ) ? ' ' : '';
		$trail = ' ' === substr( $text, -1 ) ? ' ' : '';
		return $lead . $marker . $trimmed . $marker . $trail;
	}

	/**
	 * Spaces collapsed and trimmed, line breaks from br kept.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function tidy( string $text ): string {
		$text = (string) preg_replace( '/[ \t]+/u', ' ', $text );
		$text = (string) preg_replace( '/ *\n */', "\n", $text );
		return trim( $text );
	}

	/**
	 * Whether an element starts a block.
	 *
	 * @param DOMElement $el Element.
	 * @return bool
	 */
	private static function is_block( DOMElement $el ): bool {
		return in_array( strtolower( $el->tagName ), self::BLOCKS, true );
	}

	/**
	 * Whether an element is left out with everything in it.
	 *
	 * @param DOMElement $el Element.
	 * @return bool
	 */
	private static function skipped( DOMElement $el ): bool {
		return in_array( strtolower( $el->tagName ), self::SKIP, true ) || 'true' === $el->getAttribute( 'aria-hidden' );
	}
}
