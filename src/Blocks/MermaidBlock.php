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
 *
 * The 3.5 MB mermaid library is not a hard dependency of the editor script, so
 * it is not loaded on every editor open. The editor script pulls it in on demand
 * (only when a Mermaid block is edited), using the URL handed to it in a small
 * inline script. On the frontend the library is a dependency of the view script,
 * which WordPress only enqueues on a page that actually contains a Mermaid block.
 *
 * For accessibility the block carries a title (rendered as a caption) and a
 * description; the rendered SVG is given role="img" and an aria-label built from
 * them, so the diagram is not an unlabelled image (WCAG 1.1.1).
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

		// The editor script does NOT depend on the library, so opening the block
		// editor does not pull in 3.5 MB on every content type. The library URL
		// is handed over so the editor can load it on demand for a Mermaid block.
		wp_register_script(
			'living-handbook-mermaid-block',
			LIVING_HANDBOOK_URL . 'assets/js/mermaid-block.js',
			array( 'wp-blocks', 'wp-block-editor', 'wp-element', 'wp-components', 'wp-i18n' ),
			LIVING_HANDBOOK_VERSION,
			true
		);
		wp_localize_script(
			'living-handbook-mermaid-block',
			'lhMermaid',
			array( 'src' => LIVING_HANDBOOK_URL . $lib_rel )
		);
		wp_set_script_translations( 'living-handbook-mermaid-block', 'living-handbook', LIVING_HANDBOOK_DIR . 'languages' );

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
					'code'        => array(
						'type'    => 'string',
						'default' => '',
					),
					'title'       => array(
						'type'    => 'string',
						'default' => '',
					),
					'description' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
	}

	/**
	 * Render the block on the frontend: a figure holding the mermaid.js container
	 * and an optional caption. The view script gives the rendered SVG a text
	 * alternative from the title and description.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render( array $attributes ): string {
		$code = isset( $attributes['code'] ) ? (string) $attributes['code'] : '';
		if ( '' === trim( $code ) ) {
			return '';
		}
		$title = isset( $attributes['title'] ) ? trim( (string) $attributes['title'] ) : '';
		$desc  = isset( $attributes['description'] ) ? trim( (string) $attributes['description'] ) : '';

		$wrapper = get_block_wrapper_attributes( array( 'class' => 'living-handbook-figure' ) );

		$pre = '<pre class="mermaid"';
		if ( '' !== $title ) {
			$pre .= ' data-title="' . esc_attr( $title ) . '"';
		}
		if ( '' !== $desc ) {
			$pre .= ' data-description="' . esc_attr( $desc ) . '"';
		}
		$pre .= '>' . esc_html( $code ) . '</pre>';

		$caption = '' !== $title
			? '<figcaption class="living-handbook-figure__caption">' . esc_html( $title ) . '</figcaption>'
			: '';

		return '<figure ' . $wrapper . '>' . $pre . $caption . '</figure>';
	}
}
