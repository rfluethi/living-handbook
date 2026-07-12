<?php
/**
 * Frontend assets for handbook views, plus a navigation fallback shortcode.
 *
 * The feedback prompt and metadata footer are rendered by the PageMeta block
 * placed in the single template.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Frontend;

use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads the frontend stylesheet and script and offers the navigation shortcode.
 */
final class FrontendRenderer {

	/**
	 * Hook registration into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_shortcode( 'living_handbook_nav', array( $this, 'nav_shortcode' ) );
	}

	/**
	 * Enqueue the stylesheet and script on every handbook view.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		$is_handbook = is_singular( Handbook::POST_TYPE )
			|| is_post_type_archive( Handbook::POST_TYPE )
			|| is_tax( Handbooks::TAXONOMY )
			|| has_block( 'living-handbook/overview' )
			|| has_block( 'living-handbook/entry' )
			|| has_block( 'living-handbook/navigation' );

		if ( ! $is_handbook ) {
			return;
		}

		wp_enqueue_style( 'living-handbook', LIVING_HANDBOOK_URL . 'assets/frontend.css', array(), LIVING_HANDBOOK_VERSION );

		wp_enqueue_script( 'living-handbook', LIVING_HANDBOOK_URL . 'assets/frontend.js', array(), LIVING_HANDBOOK_VERSION, true );
		wp_localize_script(
			'living-handbook',
			'livingHandbook',
			array(
				'rest'   => esc_url_raw( rest_url( 'living-handbook/v1/feedback' ) ),
				'nonce'  => wp_create_nonce( 'wp_rest' ),
				'thanks' => __( 'Thanks for your feedback.', 'living-handbook' ),
			)
		);
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
