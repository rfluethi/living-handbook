<?php
/**
 * The Mermaid block: stores diagram code and renders it live with mermaid.js.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A dynamic block that holds a Mermaid diagram as text. The editor shows a code
 * field with a live preview; the frontend renders the diagram with mermaid.js.
 * The mermaid library is shipped locally in assets/js/mermaid.min.js.
 */
final class MermaidBlock {

	public const BLOCK = 'living-handbook/mermaid';

	/**
	 * Hook registration into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_block' ) );
	}

	/**
	 * Register the mermaid library, the block scripts, and the block type.
	 *
	 * @return void
	 */
	public function register_block(): void {
		$lib_rel  = 'assets/js/mermaid.min.js';
		$lib_path = LIVING_HANDBOOK_DIR . $lib_rel;
		$lib_ver  = is_readable( $lib_path ) ? (string) filemtime( $lib_path ) : LIVING_HANDBOOK_VERSION;

		wp_register_script( 'living-handbook-mermaid', LIVING_HANDBOOK_URL . $lib_rel, array(), $lib_ver, true );

		wp_register_script(
			'living-handbook-mermaid-block',
			LIVING_HANDBOOK_URL . 'assets/js/mermaid-block.js',
			array( 'wp-blocks', 'wp-block-editor', 'wp-element', 'wp-components', 'wp-i18n', 'living-handbook-mermaid' ),
			LIVING_HANDBOOK_VERSION,
			true
		);

		wp_register_script(
			'living-handbook-mermaid-view',
			LIVING_HANDBOOK_URL . 'assets/js/mermaid-view.js',
			array( 'living-handbook-mermaid' ),
			LIVING_HANDBOOK_VERSION,
			true
		);

		register_block_type(
			self::BLOCK,
			array(
				'api_version'     => '3',
				'editor_script'   => 'living-handbook-mermaid-block',
				'view_script'     => 'living-handbook-mermaid-view',
				'render_callback' => array( $this, 'render' ),
				'attributes'      => array(
					'code' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
	}

	/**
	 * Render the block on the frontend: a mermaid.js container with the code.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render( array $attributes ): string {
		$code = isset( $attributes['code'] ) ? (string) $attributes['code'] : '';
		if ( '' === trim( $code ) ) {
			return '';
		}
		$wrapper = get_block_wrapper_attributes( array( 'class' => 'mermaid' ) );
		return '<pre ' . $wrapper . '>' . esc_html( $code ) . '</pre>';
	}
}
