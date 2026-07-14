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
	 * Supports shared by every handbook block. These blocks render their own
	 * markup in PHP and do not apply the editor's design controls, so the
	 * design panels (colour, typography, spacing, additional CSS class, HTML
	 * edit, anchor) are turned off to avoid controls that have no effect. The
	 * editor reads supports from the client registration in blocks.js; this
	 * mirror keeps the server registration consistent.
	 *
	 * @return array<string, mixed>
	 */
	private static function supports(): array {
		return array(
			'html'            => false,
			'anchor'          => false,
			'customClassName' => false,
			'color'           => false,
			'typography'      => false,
			'spacing'         => false,
			'dimensions'      => false,
			'border'          => false,
		);
	}

	/**
	 * Register the block types with their server render callbacks.
	 *
	 * @return void
	 */
	public function register_blocks(): void {
		$supports = self::supports();

		register_block_type(
			'living-handbook/navigation',
			array(
				'attributes'      => array(
					'variant' => array(
						'type'    => 'string',
						'default' => 'sidebar',
					),
				),
				'supports'        => $supports,
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
				'supports'        => $supports,
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
				'supports'        => $supports,
				'render_callback' => array( $this, 'render_pagemeta' ),
			)
		);

		register_block_type(
			'living-handbook/overview',
			array(
				'attributes'      => array(
					'display' => array(
						'type'    => 'string',
						'default' => 'cards',
					),
				),
				'supports'        => $supports,
				'render_callback' => array( $this, 'render_overview' ),
			)
		);

		register_block_type(
			'living-handbook/entry',
			array(
				'attributes'      => array(
					'display' => array(
						'type'    => 'string',
						'default' => 'cards',
					),
				),
				'supports'        => $supports,
				'render_callback' => array( $this, 'render_entry' ),
			)
		);

		register_block_type(
			'living-handbook/menu',
			array(
				'supports'        => $supports,
				'render_callback' => array( $this, 'render_menu' ),
			)
		);

		$simple = array(
			'living-handbook/badges'   => array( $this, 'render_badges' ),
			'living-handbook/feedback' => array( $this, 'render_feedback' ),
		);
		foreach ( $simple as $name => $callback ) {
			register_block_type(
				$name,
				array(
					'supports'        => $supports,
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
	 * The script uses wp.i18n.__ for its labels. Because it is hand-written
	 * rather than built, its strings are provided to the editor with an inline
	 * setLocaleData call (only for German locales) instead of a compiled JSON
	 * translation file.
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

		if ( 0 === strpos( determine_locale(), 'de' ) ) {
			wp_add_inline_script(
				'living-handbook-blocks',
				'wp.i18n.setLocaleData( ' . wp_json_encode( self::editor_locale_data() ) . ', "living-handbook" );',
				'before'
			);
		}
	}

	/**
	 * German translations for the editor script labels, in the Jed locale-data
	 * shape that wp.i18n.setLocaleData expects.
	 *
	 * @return array<string, mixed>
	 */
	private static function editor_locale_data(): array {
		return array(
			''                                  => array(
				'domain' => 'living-handbook',
				'lang'   => 'de_DE',
			),
			'Handbook overview'                 => array( 'Handbuch-Übersicht' ),
			'Overview'                          => array( 'Übersicht' ),
			'Display'                           => array( 'Anzeige' ),
			'Cards'                             => array( 'Karten' ),
			'List'                              => array( 'Liste' ),
			'Handbook navigation'               => array( 'Handbuch-Navigation' ),
			'Navigation'                        => array( 'Navigation' ),
			'Menu'                              => array( 'Menü' ),
			'Accordion'                         => array( 'Akkordeon' ),
			'Handbook navigation: the page tree of the current handbook, styled by the VSN plugin. Choose Menu or Accordion in the block settings.' => array( 'Handbuch-Navigation: der Seitenbaum des aktuellen Handbuchs, gestaltet vom VSN-Plugin. Wähle in den Blockeinstellungen Menü oder Akkordeon.' ),
			'Table of Contents'                 => array( 'Inhaltsverzeichnis' ),
			'Placement'                         => array( 'Platzierung' ),
			'Desktop (side column, open)'       => array( 'Desktop (Seitenspalte, offen)' ),
			'Mobile (above content, collapsed)' => array( 'Mobil (über dem Inhalt, eingeklappt)' ),
			'Heading depth (up to H…)'          => array( 'Überschriftentiefe (bis H…)' ),
			'Table of Contents: a collapsible list built from the headings of the current page, up to the chosen depth. A page can override the depth in its Handbook maintenance box. Empty if the page has no headings.' => array( 'Inhaltsverzeichnis: eine aufklappbare Liste aus den Überschriften der aktuellen Seite, bis zur gewählten Tiefe. Eine Seite kann die Tiefe in ihrer Box «Handbuch-Wartung» überschreiben. Leer, wenn die Seite keine Überschriften hat.' ),
			'Handbook page meta'                => array( 'Handbuch-Seiten-Meta' ),
			'Page meta'                         => array( 'Seiten-Meta' ),
			'Show people (avatar and name)'     => array( 'Personen anzeigen (Avatar und Name)' ),
			'Handbook page meta: the created, updated, reviewed and responsible-role footer. Turn the people on or off in the block settings.' => array( 'Handbuch-Seiten-Meta: die Fußzeile mit Erstellt, Aktualisiert, Geprüft und verantwortlicher Rolle. Schalte die Personen in den Blockeinstellungen ein oder aus.' ),
			'Handbook entry'                    => array( 'Handbuch-Eintrag' ),
			'Entry'                             => array( 'Eintrag' ),
			'Handbook entry: on a handbook page it shows the search, filters, areas and recently updated pages of that handbook.' => array( 'Handbuch-Eintrag: auf einer Handbuch-Seite zeigt er Suche, Filter, Bereiche und zuletzt aktualisierte Seiten dieses Handbuchs.' ),
			'Handbook menu'                     => array( 'Handbuch-Menü' ),
			'Handbook menu: a compact list of the handbooks the visitor may read, for a header or navigation area.' => array( 'Handbuch-Menü: eine kompakte Liste der Handbücher, die der Besucher lesen darf, für Kopfbereich oder Navigation.' ),
			'Handbook badges'                   => array( 'Handbuch-Badges' ),
			'Handbook badges: page type, topic and audience of the current page.' => array( 'Handbuch-Badges: Seitentyp, Thema und Zielgruppe der aktuellen Seite.' ),
			'Handbook feedback'                 => array( 'Handbuch-Feedback' ),
			'Handbook feedback: the "Was this helpful?" prompt for the current page.' => array( 'Handbuch-Feedback: die Frage «War das hilfreich?» für die aktuelle Seite.' ),
		);
	}

	/**
	 * Render the overview block (the handbook chooser).
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render_overview( array $attributes ): string {
		return Entry::render_chooser( self::display_mode( $attributes ) );
	}

	/**
	 * Render the entry block for the queried handbook term archive.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render_entry( array $attributes ): string {
		$term = get_queried_object();
		return $term instanceof WP_Term ? Entry::render_entry( $term, self::display_mode( $attributes ) ) : '';
	}

	/**
	 * Render the handbook menu block (accessible handbooks as a list).
	 *
	 * The block can sit in a header shown on every page, so the stylesheet and
	 * the frontend script (for the mobile toggle) are enqueued here rather than
	 * only on handbook views.
	 *
	 * @return string
	 */
	public function render_menu(): string {
		wp_enqueue_style( 'living-handbook', LIVING_HANDBOOK_URL . 'assets/frontend.css', array(), LIVING_HANDBOOK_VERSION );
		wp_enqueue_script( 'living-handbook', LIVING_HANDBOOK_URL . 'assets/frontend.js', array(), LIVING_HANDBOOK_VERSION, true );
		return Entry::render_menu();
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
			. '<summary class="living-handbook-toc__summary">' . esc_html__( 'Table of Contents', 'living-handbook' ) . '</summary>'
			. '<nav aria-label="' . esc_attr__( 'Table of Contents', 'living-handbook' ) . '"><ul class="living-handbook-toc__list"></ul></nav>'
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
	 * Resolve the card/list display attribute, defaulting to cards.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	private static function display_mode( array $attributes ): string {
		return ( isset( $attributes['display'] ) && 'list' === $attributes['display'] ) ? 'list' : 'cards';
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
