<?php
/**
 * The source note block: marks a public page as maintained on GitHub.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Blocks;

use LivingHandbook\Git\GitSync;
use LivingHandbook\PostType\Handbook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A dynamic block you place in the single-handbook template. On a GitHub-synced
 * page it renders a short note; on a page maintained in WordPress it renders
 * nothing, so the template can carry it unconditionally.
 */
final class SourceNoteBlock {

	public const BLOCK = 'living-handbook/git-source-note';

	/**
	 * Hook registration into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_block' ) );
	}

	/**
	 * Register the editor script and the block type.
	 *
	 * @return void
	 */
	public function register_block(): void {
		wp_register_script(
			'living-handbook-git-source-note-block',
			LIVING_HANDBOOK_URL . 'assets/js/git-source-note-block.js',
			array( 'wp-blocks', 'wp-block-editor', 'wp-element', 'wp-components', 'wp-i18n' ),
			LIVING_HANDBOOK_VERSION,
			true
		);
		wp_set_script_translations( 'living-handbook-git-source-note-block', 'living-handbook', LIVING_HANDBOOK_DIR . 'languages' );

		// Metadata (title, category, icon, attributes, editorScript) comes from
		// blocks/git-source-note/block.json; only the render callback is added here.
		register_block_type(
			LIVING_HANDBOOK_DIR . 'blocks/git-source-note',
			array( 'render_callback' => array( $this, 'render' ) )
		);
	}

	/**
	 * Render the note on the frontend, only on a GitHub-synced handbook page.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render( array $attributes ): string {
		if ( ! is_singular( Handbook::POST_TYPE ) ) {
			return '';
		}
		$post_id = get_the_ID();
		if ( false === $post_id || GitSync::SOURCE_GITHUB !== get_post_meta( $post_id, GitSync::META_SOURCE, true ) ) {
			return '';
		}
		$label = isset( $attributes['label'] ) ? trim( (string) $attributes['label'] ) : '';
		if ( '' === $label ) {
			$label = __( 'This page is maintained on GitHub and updated automatically.', 'living-handbook' );
		}

		$link = self::source_link( (int) $post_id );
		$body = esc_html( $label );
		if ( '' !== $link ) {
			$body .= ' <a href="' . esc_url( $link ) . '" rel="noopener" target="_blank">'
				. esc_html__( 'View the source file', 'living-handbook' ) . '</a>';
		}

		$wrapper = get_block_wrapper_attributes( array( 'class' => 'lh-source-note' ) );
		return '<div ' . $wrapper . '>' . $body . '</div>';
	}

	/**
	 * A human-facing github.com link to the page's source file, from the stored
	 * Markdown source URL. A raw.githubusercontent.com URL is turned into the
	 * matching blob URL so it opens the file in GitHub's interface; any other
	 * host is linked as it is.
	 *
	 * @param int $post_id Page id.
	 * @return string The URL, or '' when there is none.
	 */
	private static function source_link( int $post_id ): string {
		$url = (string) get_post_meta( $post_id, GitSync::META_URL, true );
		if ( '' === $url ) {
			return '';
		}
		if ( 1 === preg_match( '#^https?://raw\.githubusercontent\.com/([^/]+)/([^/]+)/(.+)$#', $url, $parts ) ) {
			return 'https://github.com/' . $parts[1] . '/' . $parts[2] . '/blob/' . $parts[3];
		}
		return $url;
	}
}
