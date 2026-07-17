<?php
/**
 * Frontend assets for handbook views, plus a navigation fallback shortcode and
 * an optional injection of the handbook list into a core Navigation block.
 *
 * The feedback prompt and metadata footer are rendered by the PageMeta block
 * placed in the single template.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Frontend;

use LivingHandbook\Access\AccessController;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use LivingHandbook\Setup\Onboarding;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads the frontend stylesheet and script and offers the navigation shortcode.
 */
final class FrontendRenderer {

	/**
	 * Marker class a site puts on a core Navigation block, a navigation submenu,
	 * or a single navigation link, to have the handbook list injected.
	 *
	 * On a navigation link or submenu the item becomes (or stays) a submenu whose
	 * children are the accessible handbooks, keeping its own label (any text). On
	 * the whole navigation block a "Handbooks" submenu is added as the first item.
	 */
	private const NAV_MARKER_CLASS = 'has-handbook-menu';

	/**
	 * Hook registration into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'enqueue_block_assets', array( $this, 'enqueue_editor_preview' ) );
		add_filter( 'body_class', array( $this, 'body_class' ) );
		add_filter( 'render_block', array( $this, 'inject_handbook_menu' ), 10, 2 );
		add_shortcode( 'living_handbook_nav', array( $this, 'nav_shortcode' ) );
	}

	/**
	 * Enqueue the stylesheet and script on every handbook view.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		if ( ! self::is_handbook_view() ) {
			return;
		}

		wp_enqueue_style( 'living-handbook', LIVING_HANDBOOK_URL . 'assets/frontend.css', array(), LIVING_HANDBOOK_VERSION );

		wp_enqueue_script( 'living-handbook', LIVING_HANDBOOK_URL . 'assets/frontend.js', array(), LIVING_HANDBOOK_VERSION, true );
		wp_localize_script(
			'living-handbook',
			'livingHandbook',
			array(
				'rest'   => esc_url_raw( rest_url( 'living-handbook/v1/feedback' ) ),
				'filter' => esc_url_raw( rest_url( Filters::REST_NAMESPACE . Filters::REST_ROUTE ) ),
				'nonce'  => wp_create_nonce( 'wp_rest' ),
				'thanks' => __( 'Thanks for your feedback.', 'living-handbook' ),
			)
		);
	}

	/**
	 * Load the frontend stylesheet inside the block editor as well, so the
	 * server-rendered previews of the handbook blocks (overview, entry, menu)
	 * look like the real page instead of an unstyled list. enqueue_block_assets
	 * runs in both contexts; the admin check keeps this out of the frontend,
	 * where enqueue() already handles the stylesheet on handbook views only.
	 *
	 * @return void
	 */
	public function enqueue_editor_preview(): void {
		if ( ! is_admin() ) {
			return;
		}
		wp_enqueue_style( 'living-handbook', LIVING_HANDBOOK_URL . 'assets/frontend.css', array(), LIVING_HANDBOOK_VERSION );
	}

	/**
	 * Add a scope class to the body on handbook views so a site can style the
	 * blocks used inside a handbook without affecting the rest of the site.
	 *
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public function body_class( array $classes ): array {
		if ( is_singular( Handbook::POST_TYPE )
			|| is_post_type_archive( Handbook::POST_TYPE )
			|| is_tax( Handbooks::TAXONOMY ) ) {
			$classes[] = 'living-handbook-page';
		}
		return $classes;
	}

	/**
	 * Inject the accessible handbooks into a core Navigation block, a navigation
	 * submenu, or a navigation link that carries the marker class, so the
	 * handbooks appear inside the theme's own navigation and its mobile overlay.
	 *
	 * This mirrors the core navigation markup, so it depends on that block's
	 * structure and may need adjusting across WordPress versions.
	 *
	 * @param string               $block_content Rendered block HTML.
	 * @param array<string, mixed> $block         Parsed block.
	 * @return string
	 */
	public function inject_handbook_menu( string $block_content, array $block ): string {
		$name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
		if ( 'core/navigation' !== $name && 'core/navigation-submenu' !== $name && 'core/navigation-link' !== $name ) {
			return $block_content;
		}
		if ( ! self::has_marker_class( $block ) ) {
			return $block_content;
		}

		$items = self::handbook_items_html();
		if ( '' === $items ) {
			return $block_content;
		}

		if ( 'core/navigation-submenu' === $name ) {
			return self::inject_into_submenu( $block_content, $items );
		}
		if ( 'core/navigation-link' === $name ) {
			return self::convert_link_to_submenu( $block_content, $items );
		}
		return self::inject_top_level_submenu( $block_content, $items );
	}

	/**
	 * Whether a block carries the marker class.
	 *
	 * The class list is compared entry by entry, not searched as a substring, so
	 * a class like `has-handbook-menu-alt` on some unrelated block is not
	 * mistaken for the marker.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @return bool
	 */
	private static function has_marker_class( array $block ): bool {
		$class_name = isset( $block['attrs']['className'] ) ? (string) $block['attrs']['className'] : '';
		if ( '' === trim( $class_name ) ) {
			return false;
		}
		$classes = preg_split( '/\s+/', trim( $class_name ) );

		return is_array( $classes ) && in_array( self::NAV_MARKER_CLASS, $classes, true );
	}

	/**
	 * Add the handbook links to a marked navigation submenu, keeping its label.
	 *
	 * Ensures the item has the `has-child` class and a toggle button, because a
	 * submenu left empty in the editor renders without them and the theme then
	 * never reveals the container.
	 *
	 * @param string $content Submenu block HTML.
	 * @param string $items   Handbook list-item HTML.
	 * @return string
	 */
	private static function inject_into_submenu( string $content, string $items ): string {
		$content = self::ensure_has_child( $content );

		if ( false !== strpos( $content, 'wp-block-navigation__submenu-container' ) ) {
			if ( false === strpos( $content, 'wp-block-navigation__submenu-icon' ) ) {
				$content = (string) preg_replace(
					'/(<\/a>)(\s*<ul[^>]*wp-block-navigation__submenu-container)/',
					'$1' . self::submenu_toggle() . '$2',
					$content,
					1
				);
			}
			$pattern = '/(<ul[^>]*wp-block-navigation__submenu-container[^>]*>)/';
			return (string) preg_replace( $pattern, '$1' . $items, $content, 1 );
		}

		// No container at all: add the toggle and container before the item close.
		$container = self::submenu_toggle() . '<ul class="wp-block-navigation__submenu-container wp-block-navigation-submenu">' . $items . '</ul>';
		return (string) preg_replace( '/<\/li>\s*$/', $container . '</li>', $content, 1 );
	}

	/**
	 * Turn a marked navigation link into a submenu whose children are the
	 * accessible handbooks, keeping the link's own label.
	 *
	 * @param string $content Navigation-link block HTML.
	 * @param string $items   Handbook list-item HTML.
	 * @return string
	 */
	private static function convert_link_to_submenu( string $content, string $items ): string {
		$content = (string) preg_replace(
			'/(<li\b[^>]*class="wp-block-navigation-item)\b/',
			'$1 wp-block-navigation-submenu has-child',
			$content,
			1
		);

		$container = self::submenu_toggle() . '<ul class="wp-block-navigation__submenu-container wp-block-navigation-submenu">' . $items . '</ul>';
		return (string) preg_replace( '/<\/li>\s*$/', $container . '</li>', $content, 1 );
	}

	/**
	 * Add the `has-child` class to the first navigation item if it is missing.
	 *
	 * @param string $content Navigation item HTML.
	 * @return string
	 */
	private static function ensure_has_child( string $content ): string {
		if ( false !== strpos( $content, 'has-child' ) ) {
			return $content;
		}
		return (string) preg_replace(
			'/(<li\b[^>]*class="wp-block-navigation-item)\b/',
			'$1 has-child',
			$content,
			1
		);
	}

	/**
	 * Add a "Handbooks" submenu as the first item of a marked navigation block.
	 * The label can be changed with the living_handbook_nav_label filter.
	 *
	 * Needs a destination for the parent item. The post type archive is switched
	 * off, so the overview page created on activation is used. Without a usable
	 * overview page nothing is injected: a parent item pointing at "#" would be
	 * worse than no menu entry. Marking a navigation link or a submenu instead
	 * has no such problem, because those keep their own destination.
	 *
	 * @param string $content Navigation block HTML.
	 * @param string $items   Handbook list-item HTML.
	 * @return string
	 */
	private static function inject_top_level_submenu( string $content, string $items ): string {
		$href = self::overview_url();
		if ( '' === $href ) {
			return $content;
		}

		/**
		 * Filter the label of the injected top-level handbook submenu.
		 *
		 * @param string $label Menu label.
		 */
		$label = (string) apply_filters( 'living_handbook_nav_label', __( 'Handbooks', 'living-handbook' ) );

		$submenu = '<li class="wp-block-navigation-item wp-block-navigation-submenu has-child">'
			. '<a class="wp-block-navigation-item__content" href="' . esc_url( $href ) . '"><span class="wp-block-navigation-item__label">' . esc_html( $label ) . '</span></a>'
			. self::submenu_toggle()
			. '<ul class="wp-block-navigation__submenu-container wp-block-navigation-submenu">' . $items . '</ul>'
			. '</li>';

		$pattern = '/(<ul[^>]*wp-block-navigation__container[^>]*>)/';
		if ( 1 === preg_match( $pattern, $content ) ) {
			return (string) preg_replace( $pattern, '$1' . $submenu, $content, 1 );
		}
		return $content;
	}

	/**
	 * The permalink of the overview page created on activation, or an empty
	 * string when there is none to point at.
	 *
	 * @return string
	 */
	private static function overview_url(): string {
		$page_id = (int) get_option( Onboarding::OPTION_OVERVIEW_PAGE, 0 );
		if ( $page_id <= 0 || 'publish' !== get_post_status( $page_id ) ) {
			return '';
		}
		$link = get_permalink( $page_id );

		return is_string( $link ) ? $link : '';
	}

	/**
	 * The submenu open/close toggle button, mirroring the core navigation markup.
	 *
	 * @return string
	 */
	private static function submenu_toggle(): string {
		return '<button class="wp-block-navigation__submenu-icon wp-block-navigation-submenu__toggle" aria-label="' . esc_attr__( 'Handbooks', 'living-handbook' ) . '" aria-expanded="false">'
			. '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true" focusable="false"><path d="M1.5 4L6 8l4.5-4" stroke="currentColor" stroke-width="1.5"></path></svg>'
			. '</button>';
	}

	/**
	 * The handbook links (as navigation list items) the current user may read.
	 *
	 * @return string
	 */
	private static function handbook_items_html(): string {
		$terms = get_terms(
			array(
				'taxonomy'   => Handbooks::TAXONOMY,
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) ) {
			return '';
		}

		$user_id = get_current_user_id();
		$items   = '';
		foreach ( $terms as $term ) {
			if ( ! $term instanceof WP_Term || ! AccessController::can_view_term( $term->term_id, $user_id ) ) {
				continue;
			}
			$link = get_term_link( $term );
			if ( is_wp_error( $link ) ) {
				continue;
			}
			$items .= '<li class="wp-block-navigation-item wp-block-navigation-link">'
				. '<a class="wp-block-navigation-item__content" href="' . esc_url( (string) $link ) . '">'
				. '<span class="wp-block-navigation-item__label">' . esc_html( $term->name ) . '</span></a></li>';
		}
		return $items;
	}

	/**
	 * Whether the current request is a handbook view that needs the assets.
	 *
	 * @return bool
	 */
	private static function is_handbook_view(): bool {
		return is_singular( Handbook::POST_TYPE )
			|| is_post_type_archive( Handbook::POST_TYPE )
			|| is_tax( Handbooks::TAXONOMY )
			|| has_block( 'living-handbook/overview' )
			|| has_block( 'living-handbook/entry' )
			|| has_block( 'living-handbook/menu' )
			|| has_block( 'living-handbook/navigation' );
	}

	/**
	 * Fallback shortcode that renders the current handbook's page tree.
	 *
	 * The primary navigation is the living-handbook/navigation block placed in
	 * the single template. This shortcode covers classic templates that cannot
	 * place the block; it renders the same per-handbook tree.
	 *
	 * @return string
	 */
	public function nav_shortcode(): string {
		if ( ! is_singular( Handbook::POST_TYPE ) ) {
			return '';
		}
		$post_id = get_the_ID();
		return false !== $post_id ? Navigation::render_for_post( (int) $post_id ) : '';
	}
}
