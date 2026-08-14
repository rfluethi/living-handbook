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
use LivingHandbook\Frontend\Filters;
use LivingHandbook\Frontend\Navigation;
use LivingHandbook\Frontend\PageMeta;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\Meta\Metadata;
use LivingHandbook\PostType\Handbook;
use LivingHandbook\Training\PathView;
use WP_Block_Supports;
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
			'navigation'  => array( $this, 'render_navigation' ),
			'toc'         => array( $this, 'render_toc' ),
			'pagemeta'    => array( $this, 'render_pagemeta' ),
			'overview'    => array( $this, 'render_overview' ),
			'entry'       => array( $this, 'render_entry' ),
			'menu'        => array( $this, 'render_menu' ),
			'badges'      => array( $this, 'render_badges' ),
			'feedback'    => array( $this, 'render_feedback' ),
			'search'      => array( $this, 'render_search' ),
			'search-form' => array( $this, 'render_search_form' ),
			'filters'     => array( $this, 'render_filters' ),
			'lessons'     => array( $this, 'render_lessons' ),
			'path-nav'    => array( $this, 'render_path_nav' ),
			'paths'       => array( $this, 'render_paths' ),
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
	 * Render the lesson list of a learning path.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render_lessons( array $attributes ): string {
		$markup = PathView::render_lessons();

		return '' === $markup ? '' : self::with_block_attributes( $markup, $attributes );
	}

	/**
	 * Render the learning paths of a handbook on its entry page.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render_paths( array $attributes ): string {
		$markup = PathView::render_paths();

		return '' === $markup ? '' : self::with_block_attributes( $markup, $attributes );
	}

	/**
	 * Render the path bar on a handbook page read as a lesson.
	 *
	 * Returns an empty string on every page that is not being read through a
	 * learning path, which is nearly all of them: the block sits in the shipped
	 * single-page template and has to be invisible until it is not needed.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render_path_nav( array $attributes ): string {
		$markup = PathView::render_path_nav();

		return '' === $markup ? '' : self::with_block_attributes( $markup, $attributes );
	}

	/**
	 * Render the overview block (the handbook chooser).
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render_overview( array $attributes ): string {
		return self::with_block_attributes(
			Entry::render_chooser( self::display_mode( $attributes, 'list' ), self::preview_count( $attributes ) ),
			$attributes
		);
	}

	/**
	 * How many page titles the overview lists under a handbook, clamped to 0..10.
	 *
	 * Ten is not a technical limit, it is where a preview stops being one. The
	 * value comes from post content and can be anything, so it is bounded here
	 * rather than trusted.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return int
	 */
	private static function preview_count( array $attributes ): int {
		$count = isset( $attributes['previewCount'] ) ? (int) $attributes['previewCount'] : 3;

		return max( 0, min( 10, $count ) );
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
	 * Render the handbook search bar as its own block.
	 *
	 * Same control as the one inside the entry block, so a template can put it
	 * where it wants instead of taking the layout the entry block draws. It finds
	 * its handbook itself: the queried handbook on an entry page, the page's
	 * handbook on a single page.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render_search_form( array $attributes = array() ): string {
		$term_id = self::current_term_id();
		$term    = $term_id > 0 ? get_term( $term_id, Handbooks::TAXONOMY ) : null;
		if ( ! $term instanceof WP_Term ) {
			return '';
		}

		return self::with_block_attributes(
			Filters::search_form(
				$term,
				array(
					'show_label'         => ! empty( $attributes['showLabel'] ),
					'label'              => isset( $attributes['label'] ) ? (string) $attributes['label'] : '',
					'placeholder'        => isset( $attributes['placeholder'] ) ? (string) $attributes['placeholder'] : '',
					'button_text'        => isset( $attributes['buttonText'] ) ? (string) $attributes['buttonText'] : '',
					'button_position'    => isset( $attributes['buttonPosition'] ) ? (string) $attributes['buttonPosition'] : 'button-outside',
					'wrapper_attributes' => self::wrapper_attributes( 'living-handbook-start__search' ),
				)
			),
			$attributes
		);
	}

	/**
	 * Render the handbook filter bar as its own block.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render_filters( array $attributes = array() ): string {
		$term_id = self::current_term_id();
		$term    = $term_id > 0 ? get_term( $term_id, Handbooks::TAXONOMY ) : null;
		if ( ! $term instanceof WP_Term ) {
			return '';
		}

		return self::with_block_attributes(
			Filters::facets( $term, self::wrapper_attributes( 'living-handbook-filterform' ) ),
			$attributes
		);
	}

	/**
	 * The block wrapper attributes for a block that renders its own root element.
	 *
	 * This is what makes the block supports real: colour, border, typography and
	 * spacing are set in the editor and arrive as classes and inline styles from
	 * here. The plugin's own class is passed in so it survives, because the
	 * wrapper attributes replace the class attribute rather than add to it.
	 *
	 * Only while a block is actually being rendered, though. get_block_wrapper_
	 * attributes() reads WP_Block_Supports::$block_to_render, which is set by
	 * render_block() and is null at any other time; WordPress 6.8 and the current
	 * release read that property without checking, so calling this from anywhere
	 * else is a fatal error there. A render callback is a public method and gets
	 * called directly, by our own tests among others, so the check belongs here
	 * rather than in a note telling people not to. Without a block, the plugin
	 * class alone is the honest answer: there are no block settings to apply.
	 *
	 * @param string $keep The plugin class the markup needs to keep.
	 * @return string
	 */
	private static function wrapper_attributes( string $keep ): string {
		if ( ! function_exists( 'get_block_wrapper_attributes' )
			|| ! class_exists( 'WP_Block_Supports' )
			|| ! is_array( WP_Block_Supports::$block_to_render )
		) {
			return 'class="' . esc_attr( $keep ) . '"';
		}

		return get_block_wrapper_attributes( array( 'class' => $keep ) );
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
		// Wherever the page knows its handbook: a single page, and the entry page
		// too. It used to bail on anything but a single page, so placing it on an
		// entry page rendered nothing at all and said nothing about why.
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
		$id          = wp_unique_id( 'living-handbook-search-' );
		$label       = isset( $attributes['label'] ) && '' !== $attributes['label']
			? (string) $attributes['label']
			: __( 'Search this handbook', 'living-handbook' );
		$placeholder = isset( $attributes['placeholder'] ) && '' !== $attributes['placeholder']
			? (string) $attributes['placeholder']
			: __( 'Search this handbook …', 'living-handbook' );

		// The label stays in the document whether or not it is shown: a search
		// field with nothing but a placeholder has no accessible name as soon as
		// something is typed into it.
		$html = sprintf(
			'<div %1$s data-term-id="%2$s">'
			. '<label class="%6$s" for="%3$s">%4$s</label>'
			. '<input type="search" id="%3$s" class="living-handbook-page-search__input" autocomplete="off" placeholder="%5$s" aria-describedby="%3$s-status">'
			. '<p id="%3$s-status" class="living-handbook-visually-hidden" role="status"></p>'
			. '<ul id="%3$s-results" class="living-handbook-page-search__results" hidden></ul>'
			. '</div>',
			self::wrapper_attributes( 'living-handbook-page-search' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes escapes its own output.
			esc_attr( (string) $term_id ),
			esc_attr( $id ),
			esc_html( $label ),
			esc_attr( $placeholder ),
			empty( $attributes['showLabel'] ) ? 'living-handbook-visually-hidden' : 'living-handbook-page-search__label'
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
			return Handbooks::for_post( (int) $post_id );
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
