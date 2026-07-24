<?php
/**
 * Block templates for the handbook entry and single pages.
 *
 * Registers block templates (WP 6.7+) so each handbook term archive shows its
 * entry page with the handbook navigation, and single handbook pages get
 * navigation, content and an on-this-page column, using the active block
 * theme's header and footer. Needs a block theme; classic themes fall back to
 * their own templates.
 *
 * There is deliberately no template for the overview: the post type archive is
 * switched off (Handbook::register_post_type, has_archive => false), so an
 * archive template could never apply. The overview is a normal page holding the
 * living-handbook/overview block.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Frontend;

use LivingHandbook\Handbook\Handbooks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the plugin's block templates.
 */
final class Templates {

	/**
	 * Hook registration into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_templates' ) );
	}

	/**
	 * Register the entry and single block templates.
	 *
	 * @return void
	 */
	public function register_templates(): void {
		if ( ! function_exists( 'register_block_template' ) ) {
			return;
		}

		register_block_template(
			'living-handbook//taxonomy-' . Handbooks::TAXONOMY,
			array(
				'title'       => __( 'Handbook entry', 'living-handbook' ),
				'description' => __( 'Entry page of a single handbook: navigation, search, filters, areas and recently updated pages.', 'living-handbook' ),
				'content'     => self::entry_content(),
			)
		);

		register_block_template(
			'living-handbook//single-handbook',
			array(
				'title'       => __( 'Handbook page', 'living-handbook' ),
				'description' => __( 'Single handbook page: navigation left, content centre, on-this-page right.', 'living-handbook' ),
				'content'     => self::single_content(),
			)
		);
	}

	/**
	 * Wrap inner block markup with the theme header, a constrained main group,
	 * and the theme footer.
	 *
	 * @param string $inner Inner block markup.
	 * @return string
	 */
	private static function wrap( string $inner ): string {
		return '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->'
			. '<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} --><main class="wp-block-group">'
			. $inner
			. '</main><!-- /wp:group -->'
			. '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->';
	}

	/**
	 * The handbook entry template markup: navigation left, entry content right.
	 *
	 * @return string
	 */
	private static function entry_content(): string {
		$inner = '<!-- wp:query-title {"type":"archive"} /-->'
			. '<!-- wp:term-description /-->'
			. '<!-- wp:columns {"align":"wide"} --><div class="wp-block-columns alignwide">'
			. '<!-- wp:column {"width":"22%"} --><div class="wp-block-column" style="flex-basis:22%">'
			. '<!-- wp:living-handbook/navigation /-->'
			. '</div><!-- /wp:column -->'
			. '<!-- wp:column {"width":"78%"} --><div class="wp-block-column" style="flex-basis:78%">'
			. '<!-- wp:living-handbook/entry /-->'
			. '</div><!-- /wp:column -->'
			. '</div><!-- /wp:columns -->';

		return self::wrap( $inner );
	}

	/**
	 * The single handbook page template markup.
	 *
	 * @return string
	 */
	private static function single_content(): string {
		// A separator block sits between the feedback prompt and the metadata
		// footer. It is a static block, so the divider is always there, whether or
		// not the feedback prompt renders (a guest without public feedback gets no
		// prompt). Styled through the living-handbook-divider class.
		$center = '<!-- wp:living-handbook/badges /-->'
			. '<!-- wp:post-title {"level":1} /-->'
			. '<!-- wp:living-handbook/toc {"variant":"mobile"} /-->'
			. '<!-- wp:post-content /-->'
			. '<!-- wp:living-handbook/feedback /-->'
			. '<!-- wp:separator {"className":"living-handbook-divider"} --><hr class="wp-block-separator has-alpha-channel-opacity living-handbook-divider"/><!-- /wp:separator -->'
			. '<!-- wp:living-handbook/pagemeta /-->';

		$columns = '<!-- wp:columns {"align":"wide"} --><div class="wp-block-columns alignwide">'
			. '<!-- wp:column {"width":"22%"} --><div class="wp-block-column" style="flex-basis:22%">'
			. '<!-- wp:living-handbook/navigation /-->'
			. '</div><!-- /wp:column -->'
			. '<!-- wp:column {"width":"54%"} --><div class="wp-block-column" style="flex-basis:54%">'
			. $center
			. '</div><!-- /wp:column -->'
			. '<!-- wp:column {"width":"22%"} --><div class="wp-block-column" style="flex-basis:22%">'
			. '<!-- wp:living-handbook/toc /-->'
			. '</div><!-- /wp:column -->'
			. '</div><!-- /wp:columns -->';

		return self::wrap( $columns );
	}
}
