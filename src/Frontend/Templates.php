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
				'description' => __( 'Entry page of a single handbook: navigation, search bar, results, filter bar.', 'living-handbook' ),
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
			. '<!-- wp:living-handbook/navigation {"variant":"accordion"} /-->'
			. '</div><!-- /wp:column -->'
			. '<!-- wp:column {"width":"78%"} --><div class="wp-block-column" style="flex-basis:78%">'
			// The three parts of an entry page as three blocks, so what the editor
			// shows is what renders, and each can be moved or removed on its own.
			// They find each other through the handbook id on the result column.
			. '<!-- wp:living-handbook/search-form /-->'
			. '<!-- wp:columns --><div class="wp-block-columns">'
			. '<!-- wp:column {"width":"70%"} --><div class="wp-block-column" style="flex-basis:70%">'
			. '<!-- wp:living-handbook/entry /-->'
			. '</div><!-- /wp:column -->'
			. '<!-- wp:column {"width":"30%"} --><div class="wp-block-column" style="flex-basis:30%">'
			. '<!-- wp:living-handbook/filters /-->'
			. '</div><!-- /wp:column -->'
			. '</div><!-- /wp:columns -->'
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
		// The page itself comes first: title, then content. Everything about the
		// page follows it, in one block at the foot: the feedback prompt, then the
		// source note, the badges and the metadata footer.
		//
		// The two separator blocks are static, so the dividers are there whether or
		// not their neighbours render: a guest without public feedback gets no
		// prompt, and a page maintained in WordPress gets no source note. Styled
		// through the living-handbook-divider class.
		//
		// The comments block sits last. The post type has supported comments since
		// the beginning and the access filters cover them, but no shipped template
		// ever rendered them, so a page with comments open showed nothing and the
		// setting looked broken. It renders nothing on a page whose comments are
		// closed, which is the default, so nothing appears until a site opens them.
		$center = '<!-- wp:post-title {"level":1} /-->'
			. '<!-- wp:living-handbook/toc {"variant":"mobile"} /-->'
			. '<!-- wp:post-content /-->'
			. '<!-- wp:living-handbook/feedback /-->'
			. '<!-- wp:separator {"className":"living-handbook-divider"} --><hr class="wp-block-separator has-alpha-channel-opacity living-handbook-divider"/><!-- /wp:separator -->'
			. '<!-- wp:living-handbook/git-source-note /-->'
			. '<!-- wp:living-handbook/badges /-->'
			. '<!-- wp:separator {"className":"living-handbook-divider"} --><hr class="wp-block-separator has-alpha-channel-opacity living-handbook-divider"/><!-- /wp:separator -->'
			. '<!-- wp:living-handbook/pagemeta /-->'
			. '<!-- wp:comments --><div class="wp-block-comments">'
			. '<!-- wp:comments-title /-->'
			. '<!-- wp:comment-template --><!-- wp:columns -->'
			. '<div class="wp-block-columns"><!-- wp:column {"width":"40px"} -->'
			. '<div class="wp-block-column" style="flex-basis:40px"><!-- wp:avatar {"size":40} /--></div>'
			. '<!-- /wp:column --><!-- wp:column -->'
			. '<div class="wp-block-column"><!-- wp:comment-author-name /-->'
			. '<!-- wp:comment-date /--><!-- wp:comment-content /-->'
			. '<!-- wp:comment-reply-link /--></div><!-- /wp:column --></div>'
			. '<!-- /wp:columns --><!-- /wp:comment-template -->'
			. '<!-- wp:comments-pagination --><!-- wp:comments-pagination-previous /-->'
			. '<!-- wp:comments-pagination-numbers /--><!-- wp:comments-pagination-next /-->'
			. '<!-- /wp:comments-pagination -->'
			. '<!-- wp:post-comments-form /-->'
			. '</div><!-- /wp:comments -->';

		$columns = '<!-- wp:columns {"align":"wide"} --><div class="wp-block-columns alignwide">'
			. '<!-- wp:column {"width":"22%"} --><div class="wp-block-column" style="flex-basis:22%">'
			. '<!-- wp:living-handbook/navigation {"variant":"accordion"} /-->'
			. '<!-- wp:living-handbook/search /-->'
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
