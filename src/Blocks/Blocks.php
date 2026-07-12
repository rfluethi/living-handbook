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
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor' ) );
		add_filter( 'block_categories_all', array( $this, 'block_category' ) );

		// Invalidate the cached per-handbook navigation markup when a handbook
		// page or a handbook term changes.
		add_action( 'save_post_' . Handbook::POST_TYPE, array( Navigation::class, 'invalidate' ) );
		add_action( 'trashed_post', array( Navigation::class, 'invalidate' ) );
		add_action( 'untrashed_post', array( Navigation::class, 'invalidate' ) );
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
		register_block_type(
			'living-handbook/navigation',
			array(
				'attributes'      => array(
					'variant' => array(
						'type'    => 'string',
						'default' => 'sidebar',
					),
				),
				'render_callback' => array( $this, 'render_navigation' ),
			)
		);

		register_block_type(
			'living-handbook/toc',
			array(
				'attributes'      => array(
					'variant'  => array(
						'type'    => 'string',
						'default' => 'desktop',
					),
					'maxDepth' => array(
						'type'    => 'number',
						'default' => 6,
					),
				),
				'render_callback' => array( $this, 'render_toc' ),
			)
		);

		register_block_type(
			'living-handbook/pagemeta',
			array(
				'attributes'      => array(
					'showPeople' => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
				'render_callback' => array( $this, 'render_pagemeta' ),
			)
		);

		$simple = array(
			'living-handbook/overview' => array( $this, 'render_overview' ),
			'living-handbook/entry'    => array( $this, 'render_entry' ),
			'living-handbook/badges'   => array( $this, 'render_badges' ),
			'living-handbook/feedback' => array( $this, 'render_feedback' ),
		);
		foreach ( $simple as $name => $callback ) {
			register_block_type(
				$name,
				array(
					'render_callback' => $callback,
				)
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
	 * Enqueue the hand-written editor script that registers the blocks.
	 *
	 * @return void
	 */
	public function enqueue_editor(): void {
		wp_enqueue_script(
			'living-handbook-blocks',
			LIVING_HANDBOOK_URL . 'assets/blocks.js',
			array( 'wp-blocks', 'wp-element', 'wp-server-side-render', 'wp-i18n', 'wp-block-editor', 'wp-components' ),
			LIVING_HANDBOOK_VERSION,
			true
		);
	}

	/**
	 * Render the overview block (the handbook chooser).
	 *
	 * @return string
	 */
	public function render_overview(): string {
		return Entry::render_chooser();
	}

	/**
	 * Render the entry block for the queried handbook term archive.
	 *
	 * @return string
	 */
	public function render_entry(): string {
		$term = get_queried_object();
		return $term instanceof WP_Term ? Entry::render_entry( $term ) : '';
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
		return $term_id > 0 ? Navigation::render( $term_id, $variant ) : '';
	}

	/**
	 * Render the single-page badge row.
	 *
	 * @return string
	 */
	public function render_badges(): string {
		$post_id = self::current_handbook_id();
		return $post_id > 0 ? Cards::badges( $post_id ) : '';
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

		return '<details class="living-handbook-toc living-handbook-toc--' . esc_attr( $variant ) . '"' . $open . ' hidden data-max-depth="' . esc_attr( (string) $depth ) . '">'
			. '<summary class="living-handbook-toc__summary">' . esc_html__( 'On this page', 'living-handbook' ) . '</summary>'
			. '<nav aria-label="' . esc_attr__( 'On this page', 'living-handbook' ) . '"><ul class="living-handbook-toc__list"></ul></nav>'
			. '</details>';
	}

	/**
	 * Render the feedback prompt for the current page.
	 *
	 * @return string
	 */
	public function render_feedback(): string {
		$post_id = self::current_handbook_id();
		return $post_id > 0 ? PageMeta::render_feedback( $post_id ) : '';
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
		return PageMeta::render_meta( $post_id, $show_people );
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
}
