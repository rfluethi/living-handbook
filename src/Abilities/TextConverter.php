<?php
/**
 * Turns rendered page HTML into structured plain text.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Abilities;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- childNodes, tagName and textContent are named by PHP's DOM extension.

/**
 * Rendered HTML in, Markdown-like text out, for a language model to read.
 *
 * A model reading block HTML spends most of its context on class names and
 * wrappers, and loses the structure the page is built on. This keeps the
 * structure and drops the rest: headings as #, lists as - and 1., tables as
 * pipe tables, code as fenced blocks (a Mermaid diagram as ```mermaid), links
 * as [text](address), collapsible sections opened with their summary in bold.
 * Scripts, styles, forms and anything marked aria-hidden are left out.
 *
 * It does not sanitize and does not need to: the result is text, nothing in
 * it is rendered as markup by the plugin. What the model then does with
 * instructions inside a page is the risk Konzept 04 names, not one this can
 * remove.
 *
 * The optional $hide_url callback decides, for each link and image address,
 * whether the address may be shown. In a restricted handbook the uploads are
 * reachable by anyone who has their address until the file protection of
 * E-0076 exists, so the ability passes a callback that hides upload addresses
 * there and keeps only the link text or the image's alternative text.
 */
final class TextConverter {

	/**
	 * Elements whose content is never text for the reader.
	 */
	private const SKIP = array( 'script', 'style', 'noscript', 'template', 'svg', 'form', 'button', 'input', 'select', 'textarea', 'iframe', 'object', 'embed', 'canvas', 'head' );

	/**
	 * Elements that start a block of their own.
	 */
	private const BLOCKS = array( 'p', 'div', 'section', 'article', 'main', 'header', 'footer', 'aside', 'nav', 'figure', 'figcaption', 'details', 'summary', 'blockquote', 'ul', 'ol', 'table', 'pre', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'dl', 'dt', 'dd', 'li', 'address' );

	/**
	 * Callback deciding whether an address is hidden, or null.
	 *
	 * @var callable|null
	 */
	private $hide_url;

	/**
	 * Use convert().
	 *
	 * @param callable|null $hide_url Callback.
	 */
	private function __construct( ?callable $hide_url ) {
		$this->hide_url = $hide_url;
	}

	/**
	 * Converts rendered HTML to structured text.
	 *
	 * @param string        $html     Rendered content.
	 * @param callable|null $hide_url Gets an address, returns true to hide it.
	 * @return string
	 */
	public static function convert( string $html, ?callable $hide_url = null ): string {
		if ( '' === trim( $html ) ) {
			return '';
		}

		$doc      = new DOMDocument();
		$previous = libxml_use_internal_errors( true );
		// The XML declaration is the documented way to make the HTML parser read
		// UTF-8; without it, it assumes ISO-8859-1 and every umlaut breaks.
		$doc->loadHTML( '<?xml encoding="UTF-8"?><html><body>' . $html . '</body></html>', LIBXML_NONET | LIBXML_COMPACT );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		$body = $doc->getElementsByTagName( 'body' )->item( 0 );
		if ( ! $body instanceof DOMElement ) {
			return '';
		}

		$text = implode( "\n\n", ( new self( $hide_url ) )->blocks( $body ) );
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
		$text = self::tidy( $inline );
		if ( '' !== $text ) {
			$out[] = $text;
		}
		$inline = '';
	}

	/**
	 * One block element as text.
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
				$text = self::tidy( $this->inline_children( $el ) );
				return '' === $text ? '' : str_repeat( '#', (int) substr( $tag, 1 ) ) . ' ' . $text;

			case 'hr':
				return '---';

			case 'pre':
				return $this->code_block( $el );

			case 'ul':
			case 'ol':
				return $this->list_block( $el, 0 );

			case 'table':
				return $this->table( $el );

			case 'blockquote':
				$inner = implode( "\n\n", $this->blocks( $el ) );
				return '' === $inner ? '' : '> ' . str_replace( "\n", "\n> ", $inner );

			case 'summary':
			case 'dt':
				$text = self::tidy( $this->inline_children( $el ) );
				return '' === $text ? '' : '**' . $text . '**';

			case 'li':
				// A list item outside a list: treat it as a paragraph.
				return implode( "\n\n", $this->blocks( $el ) );

			default:
				// p, div, figure, details, section and the rest: their blocks in
				// order. A details element comes out opened, its summary first.
				return implode( "\n\n", $this->blocks( $el ) );
		}
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
	 * A list, nested lists indented.
	 *
	 * @param DOMElement $el    The ul or ol.
	 * @param int        $depth Nesting depth.
	 * @return string
	 */
	private function list_block( DOMElement $el, int $depth ): string {
		$ordered = 'ol' === strtolower( $el->tagName );
		$number  = $ordered && '' !== $el->getAttribute( 'start' ) ? (int) $el->getAttribute( 'start' ) : 1;
		$indent  = str_repeat( '  ', $depth );
		$lines   = array();

		foreach ( $el->childNodes as $item ) {
			if ( ! $item instanceof DOMElement || 'li' !== strtolower( $item->tagName ) || self::skipped( $item ) ) {
				continue;
			}

			$marker = $ordered ? $number . '. ' : '- ';
			++$number;

			$parts  = array();
			$inline = '';
			foreach ( $item->childNodes as $child ) {
				if ( $child instanceof DOMElement && in_array( strtolower( $child->tagName ), array( 'ul', 'ol' ), true ) ) {
					$this->flush( $inline, $parts );
					$nested = $this->list_block( $child, $depth + 1 );
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
					$text .= $part;
					continue;
				}
				$part  = str_replace( "\n", "\n" . $indent . '  ', $part );
				$text .= '' === $text ? $part : "\n" . $indent . '  ' . $part;
			}
			$lines[] = $indent . $marker . $text;
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
					$text    = self::tidy( str_replace( "\n", ' ', $this->inline_children( $cell ) ) );
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
	 * The inline text of a node's children.
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
	 * One inline node as text.
	 *
	 * @param DOMNode $node Node.
	 * @return string
	 */
	private function inline( DOMNode $node ): string {
		if ( $node instanceof DOMText ) {
			return (string) preg_replace( '/\s+/u', ' ', $node->textContent );
		}
		if ( ! $node instanceof DOMElement || self::skipped( $node ) ) {
			return '';
		}

		$tag = strtolower( $node->tagName );
		switch ( $tag ) {
			case 'br':
				return "\n";

			case 'strong':
			case 'b':
				return self::wrap( '**', $this->inline_children( $node ) );

			case 'em':
			case 'i':
				return self::wrap( '*', $this->inline_children( $node ) );

			case 'code':
				$code = (string) $node->textContent;
				return '' === $code ? '' : '`' . $code . '`';

			case 'a':
				$text = self::tidy( $this->inline_children( $node ) );
				$href = trim( $node->getAttribute( 'href' ) );
				if ( 0 === strpos( $href, '#' ) ) {
					// A link within the page. The anchor a heading carries (a lone
					// "#", Headings::anchor()) says nothing as text and goes.
					return in_array( $text, array( '#', '¶', '§' ), true ) ? '' : $text;
				}
				if ( '' === $href || $this->hidden( $href ) ) {
					return $text;
				}
				if ( '' === $text ) {
					return $href;
				}
				return '[' . $text . '](' . $href . ')';

			case 'img':
				$alt = self::tidy( $node->getAttribute( 'alt' ) );
				$src = trim( $node->getAttribute( 'src' ) );
				if ( '' === $src || $this->hidden( $src ) ) {
					return '' === $alt ? '' : '[Image: ' . $alt . ']';
				}
				return '![' . $alt . '](' . $src . ')';

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
	 * Whether an address is to be hidden.
	 *
	 * @param string $url Address.
	 * @return bool
	 */
	private function hidden( string $url ): bool {
		return null !== $this->hide_url && true === call_user_func( $this->hide_url, $url );
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
