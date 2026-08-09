<?php
/**
 * The HTML of a statically exported handbook.
 *
 * Everything here produces files that have to work with no server behind them:
 * opened from a folder, by double-clicking index.html, on a machine that has
 * never heard of WordPress. That single constraint decides most of what follows.
 * Every link is relative, the table of contents is built here instead of by a
 * script that reads the DOM, and the search index is a JavaScript file rather
 * than JSON, because a page opened over file:// may not fetch its neighbours.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Export;

use LivingHandbook\Frontend\Appearance;
use LivingHandbook\Frontend\Cards;
use LivingHandbook\Frontend\PageMeta;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns handbook pages into a self-contained website.
 */
final class SiteRenderer {

	/**
	 * How much of a page's text goes into the search index, in characters.
	 *
	 * The index is one JavaScript file that every page loads, so it is a size
	 * the reader pays for on every click. Four thousand characters is roughly a
	 * long page's worth of prose and keeps a 500-page handbook's index around
	 * two megabytes.
	 */
	private const INDEX_LENGTH = 4000;

	/**
	 * The path a page gets inside the export, without the extension.
	 *
	 * The slugs of a page's ancestors, joined with slashes, which is the same key
	 * the bundle export uses. Files rather than folders with an index.html: a
	 * relative link to a file works over file://, a link to a folder asks the
	 * browser for a directory listing and gets one only sometimes.
	 *
	 * @param int                              $post_id Page id.
	 * @param array<int, array<string, mixed>> $index   The export index, keyed by post id.
	 * @return string
	 */
	public static function path_for( int $post_id, array $index ): string {
		$parts = array();
		$seen  = array();
		$id    = $post_id;

		while ( $id > 0 && isset( $index[ $id ] ) && ! isset( $seen[ $id ] ) ) {
			$seen[ $id ] = true;
			array_unshift( $parts, (string) $index[ $id ]['slug'] );
			$id = (int) $index[ $id ]['parent'];
		}

		return implode( '/', $parts ) . '.html';
	}

	/**
	 * The prefix that leads from one file back to the export root.
	 *
	 * @param string $path File path inside the export.
	 * @return string Empty for a file at the root, otherwise "../" per level.
	 */
	public static function root_prefix( string $path ): string {
		$depth = substr_count( $path, '/' );

		return 0 === $depth ? '' : str_repeat( '../', $depth );
	}

	/**
	 * A relative link from one file in the export to another.
	 *
	 * @param string $from File path of the page holding the link.
	 * @param string $to   File path of the target.
	 * @return string
	 */
	public static function relative( string $from, string $to ): string {
		$from_parts = explode( '/', $from );
		array_pop( $from_parts );
		$to_parts = explode( '/', $to );
		$file     = (string) array_pop( $to_parts );

		while ( array() !== $from_parts && array() !== $to_parts && $from_parts[0] === $to_parts[0] ) {
			array_shift( $from_parts );
			array_shift( $to_parts );
		}

		$up   = array() === $from_parts ? '' : str_repeat( '../', count( $from_parts ) );
		$down = array() === $to_parts ? '' : implode( '/', $to_parts ) . '/';

		return $up . $down . $file;
	}

	/**
	 * The full HTML of one page.
	 *
	 * @param WP_Post                          $post    The page.
	 * @param string                           $content Rendered, link-rewritten content.
	 * @param array<int, array<string, mixed>> $index   The export index.
	 * @param array<string, mixed>             $site    Site-wide values: title, generated.
	 * @return string
	 */
	public static function page( WP_Post $post, string $content, array $index, array $site ): string {
		$path   = self::path_for( (int) $post->ID, $index );
		$prefix = self::root_prefix( $path );
		$toc    = self::toc( $content );

		$body  = '<article class="lh-page">';
		$body .= '<nav class="lh-crumbs">' . self::crumbs( (int) $post->ID, $index, $path ) . '</nav>';
		$body .= '<h1 class="lh-title">' . esc_html( get_the_title( $post ) ) . '</h1>';
		$body .= Cards::badges( (int) $post->ID );
		if ( '' !== $toc ) {
			$body .= $toc;
		}
		$body .= '<div class="lh-content entry-content">' . $content . '</div>';
		// The people are left out on purpose: an avatar is a request to an
		// external service, and this file may end up on a laptop that is offline,
		// in a mail attachment, or somewhere the names have no business being.
		$body .= PageMeta::render_meta( (int) $post->ID, false );
		$body .= '</article>';

		return self::document( get_the_title( $post ), $body, $prefix, $index, (int) $post->ID, $site, self::has_diagram( $content ) );
	}

	/**
	 * A page whose body was rendered from the block template.
	 *
	 * The template already carries the title, the content and everything the site
	 * arranged around them, so nothing is added here but the trail back to the
	 * start page: a breadcrumb is navigation between files in a folder, and no
	 * template on a website has a reason to contain one.
	 *
	 * @param WP_Post                          $post     The page.
	 * @param string                           $rendered The rendered template, links already rewritten.
	 * @param array<int, array<string, mixed>> $index    The export index.
	 * @param array<string, mixed>             $site     Site-wide values.
	 * @return string
	 */
	public static function page_from_template( WP_Post $post, string $rendered, array $index, array $site ): string {
		$path     = self::path_for( (int) $post->ID, $index );
		$rendered = self::fill_toc( self::strip_people( $rendered ) );

		$body = '<nav class="lh-crumbs">' . self::crumbs( (int) $post->ID, $index, $path ) . '</nav>'
			. '<div class="wp-site-blocks">' . $rendered . '</div>';

		return self::document( get_the_title( $post ), $body, self::root_prefix( $path ), $index, (int) $post->ID, $site, self::has_diagram( $rendered ) );
	}

	/**
	 * The start page: what the handbook is, and everything in it.
	 *
	 * @param array<int, array<string, mixed>> $index The export index.
	 * @param array<string, mixed>             $site  Site-wide values.
	 * @return string
	 */
	public static function start_page( array $index, array $site ): string {
		$body  = '<article class="lh-page">';
		$body .= '<h1 class="lh-title">' . esc_html( (string) $site['title'] ) . '</h1>';

		$description = (string) $site['description'];
		if ( '' !== $description ) {
			$body .= '<p class="lh-lede">' . esc_html( $description ) . '</p>';
		}

		$body .= '<p class="lh-note">' . esc_html(
			sprintf(
				/* translators: 1: number of pages, 2: date of the export. */
				_n( 'A copy of %1$d page, exported on %2$s.', 'A copy of %1$d pages, exported on %2$s.', count( $index ), 'living-handbook' ),
				count( $index ),
				(string) $site['generated']
			)
		) . '</p>';

		$body .= '<h2>' . esc_html__( 'Contents', 'living-handbook' ) . '</h2>';
		$body .= self::tree( $index, 0, 'index.html', 0, 0 );
		$body .= '</article>';

		return self::document( (string) $site['title'], $body, '', $index, 0, $site, false );
	}

	/**
	 * Wrap a body in the page frame: head, sidebar, search, footer.
	 *
	 * @param string                           $title      Page title.
	 * @param string                           $body       Body markup.
	 * @param string                           $prefix     Path back to the root.
	 * @param array<int, array<string, mixed>> $index      The export index.
	 * @param int                              $current_id Current page id, 0 on the start page.
	 * @param array<string, mixed>             $site       Site-wide values.
	 * @param bool                             $diagram    Whether this page holds a Mermaid diagram.
	 * @return string
	 */
	private static function document( string $title, string $body, string $prefix, array $index, int $current_id, array $site, bool $diagram ): string {
		$self       = 0 === $current_id ? 'index.html' : self::path_for( $current_id, $index );
		$page_title = 0 === $current_id ? $title : $title . ' – ' . (string) $site['title'];

		$out  = '<!DOCTYPE html>' . "\n";
		$out .= '<html lang="' . esc_attr( (string) $site['language'] ) . '">' . "\n";
		$out .= '<head>' . "\n";
		$out .= '<meta charset="utf-8">' . "\n";
		$out .= '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
		$out .= '<title>' . esc_html( $page_title ) . '</title>' . "\n";
		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- written into an exported file, where there is no WordPress to enqueue with.
		$out .= '<link rel="stylesheet" href="' . esc_attr( $prefix ) . 'assets/style.css">' . "\n";
		$out .= '</head>' . "\n";
		// living-handbook-page is what the plugin's own frontend script looks for
		// before it makes content images and diagrams clickable. The export reuses
		// that script rather than carrying a second copy of the same behaviour.
		$out .= '<body class="lh-body living-handbook-page">' . "\n";

		$out .= '<a class="lh-skip" href="#lh-main">' . esc_html__( 'Skip to content', 'living-handbook' ) . '</a>';
		$out .= '<header class="lh-head">';
		$out .= '<a class="lh-home" href="' . esc_attr( $prefix ) . 'index.html">' . esc_html( (string) $site['title'] ) . '</a>';
		$out .= '<form class="lh-searchform" role="search" onsubmit="return false;">';
		$out .= '<label class="lh-visually-hidden" for="lh-search">' . esc_html__( 'Search', 'living-handbook' ) . '</label>';
		$out .= '<input type="search" id="lh-search" autocomplete="off" placeholder="' . esc_attr__( 'Search', 'living-handbook' ) . '">';
		$out .= '</form>';
		$out .= '<button type="button" class="lh-menu-toggle" aria-expanded="false" aria-controls="lh-nav">' . esc_html__( 'Pages', 'living-handbook' ) . '</button>';
		$out .= '</header>' . "\n";

		$out .= '<div class="lh-layout">';
		$out .= '<nav id="lh-nav" class="lh-nav" aria-label="' . esc_attr__( 'Handbook', 'living-handbook' ) . '">';
		$out .= self::tree( $index, 0, $self, $current_id, 0 );
		$out .= '</nav>';
		$out .= '<main id="lh-main" class="lh-main">';
		$out .= '<div class="lh-results" id="lh-results" hidden></div>';
		$out .= '<div id="lh-body">' . $body . '</div>';
		$out .= '</main>';
		$out .= '</div>' . "\n";

		$out .= '<footer class="lh-foot"><p>' . esc_html(
			sprintf(
				/* translators: %s: date of the export. */
				__( 'Exported from a Living Handbook on %s. This copy is not kept up to date.', 'living-handbook' ),
				(string) $site['generated']
			)
		) . '</p></footer>' . "\n";

		// The labels the plugin's frontend script reads for the enlarged-image
		// overlay. Only these three: without a REST configuration the script's
		// search, filter and feedback parts find nothing to bind to and stay out
		// of the way, which is exactly what an export needs from them.
		$out .= '<script>window.livingHandbook = ' . self::labels() . ';</script>' . "\n";
		if ( $diagram ) {
			// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- written into an exported file.
			$out .= '<script src="' . esc_attr( $prefix ) . 'assets/mermaid.js"></script>' . "\n";
			// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- written into an exported file.
			$out .= '<script src="' . esc_attr( $prefix ) . 'assets/mermaid-view.js"></script>' . "\n";
		}
		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- written into an exported file.
		$out .= '<script src="' . esc_attr( $prefix ) . 'assets/frontend.js"></script>' . "\n";
		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- written into an exported file.
		$out .= '<script src="' . esc_attr( $prefix ) . 'assets/search-index.js"></script>' . "\n";
		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- written into an exported file.
		$out .= '<script src="' . esc_attr( $prefix ) . 'assets/site.js"></script>' . "\n";
		$out .= '</body>' . "\n</html>\n";

		return $out;
	}

	/**
	 * The page tree as a nested list, with the current page marked.
	 *
	 * @param array<int, array<string, mixed>> $index      The export index.
	 * @param int                              $above      Parent page id, 0 for the top level.
	 * @param string                           $here       File path of the page being rendered.
	 * @param int                              $current_id Current page id.
	 * @param int                              $depth      Recursion depth.
	 * @return string
	 */
	private static function tree( array $index, int $above, string $here, int $current_id, int $depth ): string {
		if ( $depth > 20 ) {
			return '';
		}

		$children = array();
		foreach ( $index as $id => $entry ) {
			if ( (int) $entry['parent'] === $above ) {
				$children[ (int) $id ] = $entry;
			}
		}
		if ( array() === $children ) {
			return '';
		}

		uasort(
			$children,
			static function ( array $a, array $b ): int {
				if ( (int) $a['order'] === (int) $b['order'] ) {
					return strnatcasecmp( (string) $a['title'], (string) $b['title'] );
				}
				return (int) $a['order'] <=> (int) $b['order'];
			}
		);

		$out = '<ul class="lh-tree">';
		foreach ( $children as $id => $entry ) {
			$target  = self::relative( $here, self::path_for( (int) $id, $index ) );
			$current = (int) $id === $current_id ? ' aria-current="page"' : '';
			$out    .= '<li><a href="' . esc_attr( $target ) . '"' . $current . '>' . esc_html( (string) $entry['title'] ) . '</a>';
			$out    .= self::tree( $index, (int) $id, $here, $current_id, $depth + 1 );
			$out    .= '</li>';
		}

		return $out . '</ul>';
	}

	/**
	 * The trail from the start page down to this one.
	 *
	 * @param int                              $post_id Current page.
	 * @param array<int, array<string, mixed>> $index   The export index.
	 * @param string                           $here    File path of the current page.
	 * @return string
	 */
	private static function crumbs( int $post_id, array $index, string $here ): string {
		$trail = array();
		$id    = isset( $index[ $post_id ] ) ? (int) $index[ $post_id ]['parent'] : 0;
		$seen  = array();

		while ( $id > 0 && isset( $index[ $id ] ) && ! isset( $seen[ $id ] ) ) {
			$seen[ $id ] = true;
			array_unshift( $trail, $id );
			$id = (int) $index[ $id ]['parent'];
		}

		$out = '<a href="' . esc_attr( self::root_prefix( $here ) ) . 'index.html">' . esc_html__( 'Start', 'living-handbook' ) . '</a>';
		foreach ( $trail as $ancestor ) {
			$out .= ' <span aria-hidden="true">/</span> <a href="' . esc_attr( self::relative( $here, self::path_for( $ancestor, $index ) ) ) . '">'
				. esc_html( (string) $index[ $ancestor ]['title'] ) . '</a>';
		}

		return $out;
	}

	/**
	 * Fill the table-of-contents block the template rendered empty.
	 *
	 * On the site that list is built in the browser, from the headings in the
	 * document. In an export it is built here instead, from the same anchors, so
	 * a reader who opened the folder with scripting switched off still gets it,
	 * and so the printed copy has it too.
	 *
	 * @param string $html The rendered template.
	 * @return string
	 */
	public static function fill_toc( string $html ): string {
		if ( ! str_contains( $html, 'living-handbook-toc__list' ) ) {
			return $html;
		}

		$items = self::toc_items( $html );
		if ( '' === $items ) {
			return $html;
		}

		$html = (string) preg_replace(
			'#(<ul class="living-handbook-toc__list">)\s*(</ul>)#',
			'$1' . str_replace( '$', '\$', $items ) . '$2',
			$html
		);

		// The block ships hidden and the script unhides it once it has something
		// to show; here it has something to show already.
		return (string) preg_replace( '#(<details class="living-handbook-toc[^"]*")([^>]*)\shidden#', '$1$2', $html );
	}

	/**
	 * Drop the people from the metadata footer.
	 *
	 * An avatar is a request to an external service and a name is a name. Both
	 * belong on the site, not in a file that gets mailed around. The dates and
	 * the responsible role stay: they are what the footer is for.
	 *
	 * @param string $html The rendered template.
	 * @return string
	 */
	public static function strip_people( string $html ): string {
		return (string) preg_replace( '#<span class="living-handbook-person">.*?</span>\s*</span>#s', '', $html );
	}

	/**
	 * The list items of a table of contents, from the headings in the content.
	 *
	 * @param string $content Rendered content.
	 * @return string
	 */
	private static function toc_items( string $content ): string {
		preg_match_all( '#<h([23])[^>]*\sid="([^"]+)"[^>]*>(.*?)</h\1>#is', $content, $matches, PREG_SET_ORDER );
		if ( count( $matches ) < 2 ) {
			return '';
		}

		$items = '';
		foreach ( $matches as $match ) {
			$level  = 3 === (int) $match[1] ? ' living-handbook-toc__item--sub' : '';
			$text   = (string) preg_replace( '#<a class="living-handbook-anchor".*?</a>#is', '', $match[3] );
			$items .= '<li class="living-handbook-toc__item' . $level . '"><a href="#' . esc_attr( $match[2] ) . '">'
				. esc_html( trim( wp_strip_all_tags( $text ) ) ) . '</a></li>';
		}

		return $items;
	}

	/**
	 * A table of contents from the headings the content already carries.
	 *
	 * Built here rather than by a script: the anchors are added on the server by
	 * Frontend\Headings, so the ids are in the file, and a reader who has
	 * JavaScript switched off still gets the list.
	 *
	 * @param string $content Rendered page content.
	 * @return string
	 */
	private static function toc( string $content ): string {
		if ( 1 !== preg_match_all( '#<h([23])[^>]*\sid="([^"]+)"[^>]*>(.*?)</h\1>#is', $content, $matches, PREG_SET_ORDER ) && array() === $matches ) {
			return '';
		}
		if ( count( $matches ) < 2 ) {
			// One heading is a title, not a table of contents.
			return '';
		}

		$items = '';
		foreach ( $matches as $match ) {
			$level = 3 === (int) $match[1] ? ' class="lh-toc__sub"' : '';
			// The heading carries the "#" link to itself; in a list of links to
			// those same headings it would read as part of every title.
			$text   = (string) preg_replace( '#<a class="living-handbook-anchor".*?</a>#is', '', $match[3] );
			$items .= '<li' . $level . '><a href="#' . esc_attr( $match[2] ) . '">' . esc_html( trim( wp_strip_all_tags( $text ) ) ) . '</a></li>';
		}

		return '<details class="lh-toc" open><summary>' . esc_html__( 'On this page', 'living-handbook' ) . '</summary><ul>' . $items . '</ul></details>';
	}

	/**
	 * Whether a page holds a Mermaid diagram.
	 *
	 * The block renders its source into `<pre class="mermaid">`, which is already
	 * in the exported file; all that is missing is the library that draws it. It
	 * is 3.5 MB, so it travels only with an export that has something to draw.
	 *
	 * @param string $content Rendered content.
	 * @return bool
	 */
	public static function has_diagram( string $content ): bool {
		return str_contains( $content, 'class="mermaid"' );
	}

	/**
	 * The handful of labels the plugin's frontend script reads, as JSON.
	 *
	 * @return string
	 */
	private static function labels(): string {
		$labels = wp_json_encode(
			array(
				'lightboxOpen'    => __( 'Enlarge', 'living-handbook' ),
				'lightboxClose'   => __( 'Close', 'living-handbook' ),
				'lightboxDiagram' => __( 'Enlarged diagram', 'living-handbook' ),
			),
			JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
		);

		return is_string( $labels ) ? $labels : '{}';
	}

	/**
	 * One entry of the search index.
	 *
	 * @param string $title   Page title.
	 * @param string $path    File path inside the export.
	 * @param string $content Rendered content.
	 * @return array<string, string>
	 */
	public static function index_entry( string $title, string $path, string $content ): array {
		// Without this every heading would contribute a stray "#" to the text, and
		// the search would show it in the snippet around a hit.
		$text = (string) preg_replace( '#<a class="living-handbook-anchor".*?</a>#is', '', $content );
		$text = wp_strip_all_tags( $text );
		$text = (string) preg_replace( '/\s+/u', ' ', $text );
		$text = trim( $text );

		if ( mb_strlen( $text ) > self::INDEX_LENGTH ) {
			$text = mb_substr( $text, 0, self::INDEX_LENGTH );
		}

		return array(
			't' => $title,
			'u' => $path,
			'x' => $text,
		);
	}

	/**
	 * The search index as a JavaScript file.
	 *
	 * A .js file rather than .json, and a global rather than a fetch, because a
	 * page opened from the file system may not fetch the file next to it: most
	 * browsers treat every file:// document as its own origin. A script tag is
	 * still allowed, so this is the one way a search works without a server.
	 *
	 * @param array<int, array<string, string>> $entries Index entries.
	 * @return string
	 */
	public static function search_index( array $entries ): string {
		$json = wp_json_encode( array_values( $entries ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		return 'window.LH_PAGES = ' . ( is_string( $json ) ? $json : '[]' ) . ';' . "\n";
	}

	/**
	 * The stylesheet: the plugin's own frontend CSS, a small layout that stands
	 * in for the theme, and the colours of the chosen look.
	 *
	 * @param string $theme         The look the export was given.
	 * @param string $block_support The layout rules the style engine collected while rendering.
	 * @return string
	 */
	public static function stylesheet( string $theme = SiteThemes::DEFAULT_THEME, string $block_support = '' ): string {
		$frontend = '';
		$path     = LIVING_HANDBOOK_DIR . 'assets/frontend.css';
		if ( is_readable( $path ) ) {
			$frontend = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a file shipped with this plugin, not a request.
		}

		// Order matters, and every layer earns its place.
		//
		// The core block stylesheet and the theme's global styles come first,
		// because the exported pages are block markup: without them a columns
		// block is two stacked divs and a separator is a horizontal line nobody
		// styled. The global styles are also where the theme's palette, its fonts
		// and its spacing live, as --wp--preset--* properties, which is exactly
		// what the plugin's own stylesheet falls back to. That is why an export
		// can look like the site at all, and it is why they are here even for the
		// looks that do not want the site's colours: those override the handful of
		// properties they care about, further down, rather than starting from
		// nothing.
		//
		// Then the block supports collected while rendering, then the plugin's
		// stylesheet, then the export's own layout, then the colours of the chosen
		// look, then print.
		$colours = SiteThemes::uses_site_colours( $theme ) ? Appearance::css() : SiteThemes::css( $theme );

		return self::block_library_css() . "\n"
			. self::global_styles() . "\n"
			. $block_support . "\n"
			. $frontend . "\n"
			. self::layout_css() . "\n"
			. $colours . "\n"
			. SiteThemes::print_css();
	}

	/**
	 * WordPress's own stylesheet for the core blocks.
	 *
	 * @return string
	 */
	private static function block_library_css(): string {
		$path = ABSPATH . WPINC . '/css/dist/block-library/style.min.css';
		if ( ! is_readable( $path ) ) {
			$path = ABSPATH . WPINC . '/css/dist/block-library/style.css';
		}
		if ( ! is_readable( $path ) ) {
			return '';
		}

		return (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a core file, not a request.
	}

	/**
	 * The theme's global styles, and the font faces that go with them.
	 *
	 * This is the theme.json layer: the palette, the font families and sizes, the
	 * spacing scale and the layout rules, as the custom properties everything
	 * else reads. The font faces are fetched separately because WordPress prints
	 * them separately; the files they point at are copied into the export by the
	 * caller, which also rewrites these URLs.
	 *
	 * @return string
	 */
	public static function global_styles(): string {
		$css = '';
		if ( function_exists( 'wp_get_global_stylesheet' ) ) {
			$css .= (string) wp_get_global_stylesheet();
		}

		if ( function_exists( 'wp_print_font_faces' ) ) {
			ob_start();
			wp_print_font_faces();
			$fonts = (string) ob_get_clean();
			// What comes back is a style element; only its rules are wanted.
			$css .= "\n" . (string) preg_replace( '#</?style[^>]*>#i', '', $fonts );
		}

		return $css;
	}

	/**
	 * The layout the export brings along, because there is no theme in the ZIP.
	 *
	 * Deliberately short: a column for the pages, a column for the text, a
	 * readable measure, and the two states the small screen needs. Everything
	 * that styles the plugin's own parts (badges, the metadata grid, cards) comes
	 * from the plugin's stylesheet, so an export looks like the site it came
	 * from.
	 *
	 * @return string
	 */
	private static function layout_css(): string {
		return <<<'CSS'
:root {
	--lh-gap: 1.5rem;
	/* The same formulas the plugin's stylesheet uses, declared here as well
	   because it scopes them to its own components and this layout is not one of
	   them. Everything reads --lh-user-*, which is what a chosen look sets. */
	--lh-surface: var(--lh-user-surface, #ffffff);
	--lh-surface-text: var(--lh-user-surface-text, #1d2327);
	--lh-accent: var(--lh-user-accent, #2c5f8a);
	--lh-on-accent: var(--lh-user-on-accent, #ffffff);
	--lh-border: color-mix(in srgb, var(--lh-surface-text) 14%, transparent);
	--lh-muted: color-mix(in srgb, var(--lh-surface-text) 62%, var(--lh-surface));
	--lh-soft: color-mix(in srgb, var(--lh-surface-text) 5%, var(--lh-surface));
}
* { box-sizing: border-box; }
body.lh-body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; line-height: 1.6; color: var(--lh-surface-text); background: var(--lh-surface); }
.lh-skip { position: absolute; left: -9999px; }
.lh-skip:focus { left: 0.5rem; top: 0.5rem; z-index: 10; background: var(--lh-surface); padding: 0.5rem; }
.lh-visually-hidden { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
.lh-head { display: flex; align-items: center; gap: var(--lh-gap); flex-wrap: wrap; padding: 0.75rem 1.25rem; border-bottom: 1px solid var(--lh-border); position: sticky; top: 0; background: var(--lh-surface); z-index: 5; }
.lh-home { font-weight: 700; text-decoration: none; color: inherit; }
.lh-searchform { margin-left: auto; }
.lh-searchform input { padding: 0.4rem 0.6rem; border: 1px solid var(--lh-border); border-radius: 3px; min-width: 14rem; background: var(--lh-surface); color: var(--lh-surface-text); }
.lh-menu-toggle { display: none; }
.lh-layout { display: grid; grid-template-columns: minmax(14rem, 18rem) minmax(0, 1fr); gap: var(--lh-gap); max-width: 78rem; margin: 0 auto; padding: var(--lh-gap) 1.25rem 4rem; }
.lh-nav { font-size: 0.95rem; align-self: start; position: sticky; top: 4.5rem; max-height: calc(100vh - 6rem); overflow-y: auto; }
.lh-nav ul { list-style: none; margin: 0; padding-left: 0.85rem; }
.lh-nav > ul { padding-left: 0; }
.lh-nav li { margin: 0.25rem 0; }
.lh-nav a { text-decoration: none; color: var(--lh-surface-text); }
.lh-nav a:hover, .lh-nav a:focus { text-decoration: underline; }
.lh-nav a[aria-current="page"] { font-weight: 700; }
.lh-main { min-width: 0; }
.lh-page { max-width: 46rem; }
.lh-title { margin-top: 0; }
.lh-crumbs { font-size: 0.85rem; margin-bottom: 0.5rem; }
.lh-crumbs a { color: var(--lh-muted); }
.lh-lede { font-size: 1.1rem; }
.lh-note { color: var(--lh-muted); }
.lh-toc { margin: 1.5rem 0; padding: 0.75rem 1rem; background: var(--lh-soft); border-radius: 4px; }
.lh-toc ul { list-style: none; margin: 0.5rem 0 0; padding: 0; }
.lh-toc li { margin: 0.2rem 0; }
.lh-toc .lh-toc__sub { padding-left: 1rem; }
.lh-content img { max-width: 100%; height: auto; }
.lh-content pre { overflow-x: auto; padding: 0.75rem; background: var(--lh-soft); }
.lh-content table { border-collapse: collapse; }
.lh-content th, .lh-content td { border: 1px solid var(--lh-border); padding: 0.4rem 0.6rem; }
.lh-content a { color: var(--lh-accent); }
.lh-content .mermaid { background: var(--lh-surface); }
.lh-results { margin-bottom: 2rem; }
.lh-results ol { list-style: none; padding: 0; }
.lh-results li { margin: 0 0 1rem; }
.lh-results mark { background: color-mix(in srgb, var(--lh-accent) 25%, var(--lh-surface)); color: inherit; }
.lh-foot { border-top: 1px solid var(--lh-border); padding: 1rem 1.25rem; color: var(--lh-muted); font-size: 0.9rem; }
@media (max-width: 48rem) {
	.lh-layout { grid-template-columns: minmax(0, 1fr); }
	.lh-menu-toggle { display: inline-block; }
	.lh-nav { display: none; position: static; max-height: none; }
	.lh-nav.is-open { display: block; }
}
CSS;
	}

	/**
	 * The one script the export ships: the menu on small screens, and the search.
	 *
	 * @return string
	 */
	public static function script(): string {
		$strings = wp_json_encode(
			array(
				'noResults' => __( 'Nothing found.', 'living-handbook' ),
				/* translators: %d: number of results. */
				'results'   => __( '%d pages found', 'living-handbook' ),
				'clear'     => __( 'Clear the search', 'living-handbook' ),
			),
			JSON_UNESCAPED_UNICODE
		);

		$script = <<<'JS'
( function () {
	var pages = window.LH_PAGES || [];
	var results = document.getElementById( 'lh-results' );
	var body = document.getElementById( 'lh-body' );
	var field = document.getElementById( 'lh-search' );
	var toggle = document.querySelector( '.lh-menu-toggle' );
	var nav = document.getElementById( 'lh-nav' );
	var depth = ( document.querySelector( 'link[rel="stylesheet"]' ).getAttribute( 'href' ) || '' ).replace( 'assets/style.css', '' );

	if ( toggle && nav ) {
		toggle.addEventListener( 'click', function () {
			var open = nav.classList.toggle( 'is-open' );
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		} );
	}

	if ( ! field || ! results || ! body ) {
		return;
	}

	function escapeHtml( text ) {
		return text.replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	function snippet( text, needle ) {
		var at = text.toLowerCase().indexOf( needle );
		if ( at < 0 ) {
			return escapeHtml( text.slice( 0, 160 ) );
		}
		var from = Math.max( 0, at - 60 );
		var part = text.slice( from, at + needle.length + 100 );
		var mark = part.toLowerCase().indexOf( needle );
		return ( from > 0 ? '… ' : '' ) + escapeHtml( part.slice( 0, mark ) ) +
			'<mark>' + escapeHtml( part.slice( mark, mark + needle.length ) ) + '</mark>' +
			escapeHtml( part.slice( mark + needle.length ) ) + ' …';
	}

	function search( term ) {
		var needle = term.trim().toLowerCase();
		if ( needle.length < 2 ) {
			results.hidden = true;
			results.innerHTML = '';
			body.hidden = false;
			return;
		}

		var hits = pages.filter( function ( page ) {
			return page.t.toLowerCase().indexOf( needle ) >= 0 || page.x.toLowerCase().indexOf( needle ) >= 0;
		} ).slice( 0, 50 );

		var html = '<h2>' + LH_STRINGS.results.replace( '%d', hits.length ) + '</h2>';
		if ( ! hits.length ) {
			html += '<p>' + escapeHtml( LH_STRINGS.noResults ) + '</p>';
		} else {
			html += '<ol>';
			hits.forEach( function ( page ) {
				html += '<li><a href="' + depth + page.u + '">' + escapeHtml( page.t ) + '</a>' +
					'<p>' + snippet( page.x, needle ) + '</p></li>';
			} );
			html += '</ol>';
		}

		results.innerHTML = html;
		results.hidden = false;
		body.hidden = true;
	}

	var timer = null;
	field.addEventListener( 'input', function () {
		window.clearTimeout( timer );
		timer = window.setTimeout( function () {
			search( field.value );
		}, 150 );
	} );
	field.addEventListener( 'keydown', function ( event ) {
		if ( 'Escape' === event.key ) {
			field.value = '';
			search( '' );
		}
	} );
}() );
JS;

		return 'var LH_STRINGS = ' . ( is_string( $strings ) ? $strings : '{}' ) . ";\n" . $script . "\n";
	}

	/**
	 * The note that travels with the ZIP, so somebody who unpacks it in a year
	 * knows what it is and what it is not.
	 *
	 * @param array<string, mixed> $site Site-wide values.
	 * @return string
	 */
	public static function readme( array $site ): string {
		$lines = array(
			(string) $site['title'],
			str_repeat( '=', max( 3, mb_strlen( (string) $site['title'] ) ) ),
			'',
			sprintf(
				/* translators: %s: date of the export. */
				__( 'A copy of a handbook, exported on %s.', 'living-handbook' ),
				(string) $site['generated']
			),
			'',
			__( 'Open index.html in a browser. No server, no installation and no internet connection are needed.', 'living-handbook' ),
			'',
			__( 'This copy is not kept up to date, and it carries no access rules: whoever holds this folder can read every page in it. Pass it on accordingly.', 'living-handbook' ),
			'',
			sprintf(
				/* translators: %s: source site URL. */
				__( 'Source: %s', 'living-handbook' ),
				(string) $site['source']
			),
		);

		return implode( "\n", $lines ) . "\n";
	}
}
