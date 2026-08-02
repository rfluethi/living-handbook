<?php
/**
 * Dynamic blocks, rendered server-side in PHP.
 *
 * The editor script is hand-written on top of the WordPress packages, so no
 * Node build step is needed yet. A move to a proper build will keep the PHP
 * render callbacks and only replace the editor script.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Blocks;

use LivingHandbook\Frontend\Cards;
use LivingHandbook\Frontend\Entry;
use LivingHandbook\Frontend\Navigation;
use LivingHandbook\Frontend\PageMeta;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\Meta\Metadata;
use LivingHandbook\PostType\Handbook;
use WP_HTML_Tag_Processor;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the handbook blocks and their editor category.
 */
final class Blocks {

	public const CATEGORY = 'living-handbook';

	/**
	 * Hook registration into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_blocks' ) );
		add_filter( 'block_categories_all', array( $this, 'block_category' ) );

		// Invalidate the cached per-handbook navigation markup when a handbook
		// page or a handbook term changes.
		add_action( 'save_post_' . Handbook::POST_TYPE, array( Navigation::class, 'invalidate' ) );
		add_action( 'trashed_post', array( Navigation::class, 'invalidate_for_post' ) );
		add_action( 'untrashed_post', array( Navigation::class, 'invalidate_for_post' ) );
		add_action( 'created_' . Handbooks::TAXONOMY, array( Navigation::class, 'invalidate' ) );
		add_action( 'edited_' . Handbooks::TAXONOMY, array( Navigation::class, 'invalidate' ) );
		add_action( 'delete_' . Handbooks::TAXONOMY, array( Navigation::class, 'invalidate' ) );
	}

	/**
	 * Register the block types with their server render callbacks.
	 *
	 * @return void
	 */
	public function register_blocks(): void {
		// Each block takes its metadata (title, category, icon, attributes,
		// supports, keywords, description) from blocks/<name>/block.json, the
		// single source; only the server render callback is supplied here.
		$blocks = array(
			'navigation' => array( $this, 'render_navigation' ),
			'toc'        => array( $this, 'render_toc' ),
			'pagemeta'   => array( $this, 'render_pagemeta' ),
			'overview'   => array( $this, 'render_overview' ),
			'entry'      => array( $this, 'render_entry' ),
			'menu'       => array( $this, 'render_menu' ),
			'badges'     => array( $this, 'render_badges' ),
			'feedback'   => array( $this, 'render_feedback' ),
			'search'     => array( $this, 'render_search' ),
		);
		foreach ( $blocks as $dir => $callback ) {
			register_block_type(
				LIVING_HANDBOOK_DIR . 'blocks/' . $dir,
				array( 'render_callback' => $callback )
			);
		}
	}

	/**
	 * Add a dedicated block category so the blocks group together.
	 *
	 * @param array<int, array<string, mixed>> $categories Existing categories.
	 * @return array<int, array<string, mixed>>
	 */
	public function block_category( array $categories ): array {
		$categories[] = array(
			'slug'  => self::CATEGORY,
			'title' => __( 'Living Handbook', 'living-handbook' ),
			'icon'  => null,
		);
		return $categories;
	}

	/**
	 * Render the overview block (the handbook chooser).
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render_overview( array $attributes ): string {
		return self::with_block_attributes( Entry::render_chooser( self::display_mode( $attributes, 'list' ) ), $attributes );
	}

	/**
	 * Render the entry block for the queried handbook term archive.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render_entry( array $attributes ): string {
		$term = get_queried_object();
		return self::with_block_attributes( $term instanceof WP_Term ? Entry::render_entry( $term, self::display_mode( $attributes ) ) : '', $attributes );
	}

	/**
	 * Render the handbook menu block (accessible handbooks as a list).
	 *
	 * The block can sit in a header shown on every page. Its stylesheet and the
	 * frontend script come with it through block.json, so they arrive there too,
	 * without this class knowing where "there" is.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render_menu( array $attributes = array() ): string {
		return self::with_block_attributes( Entry::render_menu(), $attributes );
	}

	/**
	 * Render the navigation block for the current handbook.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render_navigation( array $attributes ): string {
		$variant = ( isset( $attributes['variant'] ) && 'accordion' === $attributes['variant'] ) ? 'accordion' : 'sidebar';
		$term_id = self::current_term_id();
		return self::with_block_attributes( $term_id > 0 ? Navigation::render( $term_id, $variant ) : '', $attributes );
	}

	/**
	 * Render the single-page badge row.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render_badges( array $attributes = array() ): string {
		$post_id = self::current_handbook_id();
		return self::with_block_attributes( $post_id > 0 ? Cards::badges( $post_id ) : '', $attributes );
	}

	/**
	 * Render the on-this-page table of contents container (filled by JS).
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render_toc( array $attributes ): string {
		$post_id = self::current_handbook_id();
		if ( $post_id <= 0 ) {
			return '';
		}
		$variant = ( isset( $attributes['variant'] ) && 'mobile' === $attributes['variant'] ) ? 'mobile' : 'desktop';
		$open    = 'desktop' === $variant ? ' open' : '';
		$depth   = self::toc_depth( $post_id, $attributes );

		$html = '<details class="living-handbook-toc living-handbook-toc--' . esc_attr( $variant ) . '"' . $open . ' hidden data-max-depth="' . esc_attr( (string) $depth ) . '">'
			. '<summary class="living-handbook-toc__summary">' . esc_html__( 'Table of Contents', 'living-handbook' ) . '</summary>'
			. '<nav aria-label="' . esc_attr__( 'Table of Contents', 'living-handbook' ) . '"><ul class="living-handbook-toc__list"></ul></nav>'
			. '</details>';
		return self::with_block_attributes( $html, $attributes );
	}

	/**
	 * Render the feedback prompt for the current page.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render_feedback( array $attributes = array() ): string {
		$post_id = self::current_handbook_id();
		return self::with_block_attributes( $post_id > 0 ? PageMeta::render_feedback( $post_id ) : '', $attributes );
	}

	/**
	 * Render the on-page search box for the current handbook (single page only).
	 *
	 * A search-as-you-type field: the frontend script queries the handbook's
	 * pages and shows the matches as links in a dropdown, so the visitor jumps
	 * straight to a page without leaving the current one. The results are always
	 * scoped to the current handbook and access-checked on the server.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render_search( array $attributes = array() ): string {
		if ( ! is_singular( Handbook::POST_TYPE ) ) {
			return '';
		}
		$term_id = self::current_term_id();
		if ( $term_id <= 0 ) {
			return '';
		}
		// A plain list of links, not an ARIA combobox. The results are pages, and
		// a link is what a visitor expects of a page: middle click, open in a new
		// tab, copy the address. A combobox would have to take that away, and the
		// markup promised the pattern without implementing it. Arrow keys walk the
		// list, Escape closes it, and a status line says how many matches there
		// are, which is what the pattern was announcing in the first place.
		$id   = wp_unique_id( 'living-handbook-search-' );
		$html = sprintf(
			'<div class="living-handbook-page-search" data-term-id="%1$s">'
			. '<label class="living-handbook-visually-hidden" for="%2$s">%3$s</label>'
			. '<input type="search" id="%2$s" class="living-handbook-page-search__input" autocomplete="off" placeholder="%4$s" aria-describedby="%2$s-status">'
			. '<p id="%2$s-status" class="living-handbook-visually-hidden" role="status"></p>'
			. '<ul id="%2$s-results" class="living-handbook-page-search__results" hidden></ul>'
			. '</div>',
			esc_attr( (string) $term_id ),
			esc_attr( $id ),
			esc_html__( 'Search this handbook', 'living-handbook' ),
			esc_attr__( 'Search this handbook …', 'living-handbook' )
		);
		return self::with_block_attributes( $html, $attributes );
	}

	/**
	 * Render the metadata footer for the current page.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render_pagemeta( array $attributes ): string {
		$post_id = self::current_handbook_id();
		if ( $post_id <= 0 ) {
			return '';
		}
		$show_people = ! isset( $attributes['showPeople'] ) || false !== $attributes['showPeople'];
		return self::with_block_attributes( PageMeta::render_meta( $post_id, $show_people ), $attributes );
	}

	/**
	 * Resolve the card/list display attribute.
	 *
	 * The registered block defaults normally fill the attribute in, so the
	 * fallback only applies to a block saved before the attribute existed. It
	 * is passed per block because the overview defaults to a list while the
	 * entry defaults to cards.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $fallback   Mode to use when the attribute is absent.
	 * @return string
	 */
	private static function display_mode( array $attributes, string $fallback = 'cards' ): string {
		if ( ! isset( $attributes['display'] ) ) {
			return 'list' === $fallback ? 'list' : 'cards';
		}
		return 'list' === $attributes['display'] ? 'list' : 'cards';
	}

	/**
	 * Resolve the table-of-contents depth: a per-page override wins, otherwise
	 * the block attribute, clamped to 1..6.
	 *
	 * @param int                  $post_id    Post ID.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return int
	 */
	private static function toc_depth( int $post_id, array $attributes ): int {
		$override = (int) get_post_meta( $post_id, Metadata::TOC_DEPTH, true );
		if ( $override >= 1 && $override <= 6 ) {
			return $override;
		}
		$depth = isset( $attributes['maxDepth'] ) ? (int) $attributes['maxDepth'] : 6;
		if ( $depth < 1 || $depth > 6 ) {
			$depth = 6;
		}
		return $depth;
	}

	/**
	 * The current single handbook page ID, or 0 when not on one.
	 *
	 * @return int
	 */
	private static function current_handbook_id(): int {
		if ( ! is_singular( Handbook::POST_TYPE ) ) {
			return 0;
		}
		$post_id = get_the_ID();
		return false !== $post_id ? (int) $post_id : 0;
	}

	/**
	 * The handbook term ID for the current view: the page's handbook on a
	 * single page, or the queried handbook on an entry (term archive).
	 *
	 * @return int
	 */
	private static function current_term_id(): int {
		if ( is_singular( Handbook::POST_TYPE ) ) {
			$post_id = get_the_ID();
			if ( false === $post_id ) {
				return 0;
			}
			$terms = wp_get_object_terms( (int) $post_id, Handbooks::TAXONOMY, array( 'fields' => 'ids' ) );
			return ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? (int) $terms[0] : 0;
		}
		if ( is_tax( Handbooks::TAXONOMY ) ) {
			$term = get_queried_object();
			return $term instanceof WP_Term ? (int) $term->term_id : 0;
		}
		return 0;
	}

	/**
	 * Apply the block's HTML anchor and additional CSS class (the Advanced panel)
	 * to the root element of already-rendered block markup. These blocks render
	 * their own markup rather than a standard block wrapper, so the id and class
	 * are merged onto the first tag with the HTML API. Empty markup (a block that
	 * renders nothing on this page) is returned untouched.
	 *
	 * @param string               $html       The rendered block markup.
	 * @param array<string, mixed> $attributes Block attributes ('className', 'anchor').
	 * @return string
	 */
	private static function with_block_attributes( string $html, array $attributes ): string {
		$class  = isset( $attributes['className'] ) ? trim( (string) $attributes['className'] ) : '';
		$anchor = isset( $attributes['anchor'] ) ? trim( (string) $attributes['anchor'] ) : '';
		if ( '' === $html || ( '' === $class && '' === $anchor ) ) {
			return $html;
		}
		$processor = new WP_HTML_Tag_Processor( $html );
		if ( ! $processor->next_tag() ) {
			return $html;
		}
		if ( '' !== $class ) {
			foreach ( preg_split( '/\s+/', $class ) as $single ) {
				if ( is_string( $single ) && '' !== $single ) {
					$processor->add_class( $single );
				}
			}
		}
		if ( '' !== $anchor ) {
			$processor->set_attribute( 'id', $anchor );
		}
		return $processor->get_updated_html();
	}
}
