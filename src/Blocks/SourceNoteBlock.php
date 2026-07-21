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

		register_block_type(
			self::BLOCK,
			array(
				'api_version'     => '3',
				'editor_script'   => 'living-handbook-git-source-note-block',
				'render_callback' => array( $this, 'render' ),
				'attributes'      => array(
					'label' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
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
		$wrapper = get_block_wrapper_attributes( array( 'class' => 'lh-source-note' ) );
		return '<div ' . $wrapper . '>' . esc_html( $label ) . '</div>';
	}
}
