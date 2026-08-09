<?php
/**
 * Addressable sections: stable heading ids and a link to each one.
 *
 * Pointing someone at one section of a long page is the everyday case in a
 * handbook, and until now it could not be done. The ids existed, but they were
 * made in the browser from the position of the heading (`lh-section-3`), which
 * is not an address: insert a heading anywhere above and every link below it
 * silently points at the wrong section. Nobody outside had copied such a link
 * yet, which made this the cheap moment to fix it.
 *
 * The id is therefore made on the server from the heading text, with a counter
 * for repeats, and a small link is added at the end of the heading so the
 * address can be picked up without reading the HTML. An id set by hand in the
 * editor always wins, which is also the way out of the one weakness of readable
 * ids: they are bare words, so a heading called "Comments" and a theme element
 * called `#comments` can collide. Renaming the anchor on that one page settles
 * it, and it stays settled, because the id no longer depends on position.
 *
 * Only h2 to h4 take part. h1 is the page title, and h5 and h6 are detail
 * inside a section rather than a place one links to; the table of contents
 * still gives those a browser-made id, as it always did.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Frontend;

use LivingHandbook\PostType\Handbook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gives the headings of a handbook page an id and an anchor link.
 */
final class Headings {

	/**
	 * Hook registration into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		// After do_blocks (9) and wpautop (10), so the headings of the rendered
		// page are in front of us rather than block markup.
		add_filter( 'the_content', array( $this, 'add_anchors' ), 12 );
	}

	/**
	 * Add an id and an anchor link to the h2, h3 and h4 of a handbook page.
	 *
	 * @param string $content The post content.
	 * @return string
	 */
	public function add_anchors( string $content ): string {
		if ( ! is_singular( Handbook::POST_TYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		return self::anchor( $content );
	}

	/**
	 * Add the ids and anchor links to a piece of rendered content, whatever the
	 * request looks like.
	 *
	 * The filter above asks where it is before doing anything, which is right for
	 * a page being served and wrong for the static export: that one renders pages
	 * in a back-end request, outside the loop, and would get a table of contents
	 * with nothing to point at. So the decision "should this content get anchors"
	 * and the work of adding them are two things now.
	 *
	 * @param string $content Rendered content.
	 * @return string
	 */
	public static function anchor( string $content ): string {
		return ( new self() )->apply( $content );
	}

	/**
	 * Add an id and an anchor link to every h2, h3 and h4 in the content.
	 *
	 * @param string $content Rendered content.
	 * @return string
	 */
	private function apply( string $content ): string {
		if ( '' === trim( $content ) || ! str_contains( $content, '<h' ) ) {
			return $content;
		}

		/**
		 * Whether headings get an id and an anchor link.
		 *
		 * @param bool $enabled Whether to add them. Default true.
		 */
		if ( ! apply_filters( 'living_handbook_heading_anchors', true ) ) {
			return $content;
		}

		$used  = array();
		$index = 0;

		$out = preg_replace_callback(
			'#<h([2-4])([^>]*)>(.*?)</h\1>#is',
			function ( array $found ) use ( &$used, &$index ): string {
				++$index;
				return $this->one_heading( $found[1], $found[2], $found[3], $used, $index );
			},
			$content
		);

		return is_string( $out ) ? $out : $content;
	}

	/**
	 * Rebuild one heading with an id and an anchor link.
	 *
	 * @param string              $level Heading level, 2 to 4.
	 * @param string              $attrs The heading's attributes, as written.
	 * @param string              $inner The heading's inner HTML.
	 * @param array<string, bool> &$used  Ids already given out on this page.
	 * @param int                 $index Position of this heading, for the fallback id.
	 * @return string
	 */
	private function one_heading( string $level, string $attrs, string $inner, array &$used, int $index ): string {
		$original = '<h' . $level . $attrs . '>' . $inner . '</h' . $level . '>';

		// The content filter can run more than once on the same content; a second
		// pass must not add a second link.
		if ( str_contains( $inner, 'living-handbook-anchor' ) ) {
			return $original;
		}

		$existing = array();
		if ( preg_match( '/\bid=["\']([^"\']+)["\']/i', $attrs, $existing ) ) {
			$id = $existing[1];
		} else {
			$id    = $this->slug( $inner, $index, $used );
			$attrs = rtrim( $attrs ) . ' id="' . esc_attr( $id ) . '"';
		}
		$used[ $id ] = true;

		$text = trim( wp_strip_all_tags( $inner ) );
		$link = sprintf(
			'<a class="living-handbook-anchor" href="#%1$s" aria-label="%2$s">#</a>',
			esc_attr( $id ),
			esc_attr(
				sprintf(
					/* translators: %s: the heading's text. */
					__( 'Link to this section: %s', 'living-handbook' ),
					$text
				)
			)
		);

		return '<h' . $level . $attrs . '>' . $inner . ' ' . $link . '</h' . $level . '>';
	}

	/**
	 * A readable, unique id from the heading text.
	 *
	 * A repeat gets a counter rather than the position, so the first "Result"
	 * keeps `result` when a second one is added further down.
	 *
	 * @param string              $inner The heading's inner HTML.
	 * @param int                 $index Position of this heading, for the fallback.
	 * @param array<string, bool> &$used  Ids already given out on this page.
	 * @return string
	 */
	private function slug( string $inner, int $index, array &$used ): string {
		$base = sanitize_title( wp_strip_all_tags( $inner ) );
		if ( '' === $base ) {
			// A heading of nothing but an image or a formula still needs an id, and
			// there is nothing to build a readable one from.
			$base = 'section-' . $index;
		}

		$id    = $base;
		$count = 1;
		while ( isset( $used[ $id ] ) ) {
			++$count;
			$id = $base . '-' . $count;
		}

		return $id;
	}
}
