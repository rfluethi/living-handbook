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

use LivingHandbook\Frontend\Navigation;
use LivingHandbook\PostType\Handbook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the overview and navigation blocks.
 */
final class Blocks {

	/**
	 * Hook registration into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_blocks' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor' ) );
	}

	/**
	 * Register the block types with their server render callbacks.
	 *
	 * @return void
	 */
	public function register_blocks(): void {
		register_block_type(
			'living-handbook/overview',
			array(
				'render_callback' => array( $this, 'render_overview' ),
			)
		);
		register_block_type(
			'living-handbook/navigation',
			array(
				'render_callback' => array( $this, 'render_navigation' ),
			)
		);
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
			array( 'wp-blocks', 'wp-element', 'wp-server-side-render', 'wp-i18n' ),
			LIVING_HANDBOOK_VERSION,
			true
		);
	}

	/**
	 * Render the overview block.
	 *
	 * @return string
	 */
	public function render_overview(): string {
		return Navigation::render_overview();
	}

	/**
	 * Render the navigation block.
	 *
	 * @return string
	 */
	public function render_navigation(): string {
		if ( ! is_singular( Handbook::POST_TYPE ) ) {
			return '';
		}
		$post_id = get_the_ID();
		return false !== $post_id ? Navigation::render_for_post( (int) $post_id ) : '';
	}
}
