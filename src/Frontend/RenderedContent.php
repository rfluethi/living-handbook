<?php
/**
 * A page's content as the front end shows it, outside the front end.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Frontend;

use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders a page's content with the reading filters, from any kind of request.
 *
 * The static export and the get-page ability both need what a reader sees,
 * from a request that is not the page's own: dynamic blocks rendered, the
 * Mermaid code in its figure, the headings with their anchors. Up to 0.76.0
 * this lived in StaticSite alone.
 */
final class RenderedContent {

	/**
	 * Returns the rendered content of a page.
	 *
	 * The content filters (the_content) run with the page set up as the current
	 * post, so the filters that build the reading experience see what they
	 * expect. The anchors are added explicitly, because the filter that normally
	 * does it asks whether it is inside the loop of a handbook page, and this is
	 * not that request. The global post and query are restored afterwards.
	 *
	 * The caller decides who may read the page. Nothing here checks access.
	 *
	 * @param WP_Post $post The page, already checked.
	 * @return string
	 */
	public static function of( WP_Post $post ): string {
		global $wp_query;

		$previous_post  = $GLOBALS['post'] ?? null;
		$previous_query = $wp_query;

		$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored below; the content filters read it.
		setup_postdata( $post );

		try {
			$content = (string) apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook, applied on purpose.
			$content = Headings::anchor( $content );
		} finally {
			wp_reset_postdata();
			$wp_query        = $previous_query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restoring what was there.
			$GLOBALS['post'] = $previous_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restoring what was there.
		}

		return $content;
	}
}
