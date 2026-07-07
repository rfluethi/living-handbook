<?php
/**
 * Simple frontend output for handbook pages.
 *
 * This appends a feedback prompt and a metadata footer to the page content,
 * and offers a navigation shortcode. It is the quick, testable rendering; the
 * polished UI moves to the dynamic blocks.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Frontend;

use LivingHandbook\Meta\Metadata;
use LivingHandbook\PostType\Handbook;
use LivingHandbook\Taxonomy\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the default frontend additions for handbook pages.
 */
final class FrontendRenderer {

	/**
	 * Hook registration into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'the_content', array( $this, 'append_meta' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_shortcode( 'living_handbook_nav', array( $this, 'nav_shortcode' ) );
	}

	/**
	 * Enqueue the frontend stylesheet and feedback script on handbook pages.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		if ( ! is_singular( Handbook::POST_TYPE ) ) {
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
	 * Append the feedback prompt and the metadata footer to a handbook page.
	 *
	 * @param string $content The post content.
	 * @return string
	 */
	public function append_meta( string $content ): string {
		if ( ! is_singular( Handbook::POST_TYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$post_id = get_the_ID();
		if ( false === $post_id ) {
			return $content;
		}
		return $content . $this->render_feedback( (int) $post_id ) . $this->render_footer( (int) $post_id );
	}

	/**
	 * Build the metadata footer markup.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function render_footer( int $post_id ): string {
		$updated  = (string) get_post_meta( $post_id, Metadata::UPDATED, true );
		$reviewed = (string) get_post_meta( $post_id, Metadata::REVIEWED, true );

		$badge  = '';
		$status = FreshnessStatus::for_post( $post_id );
		if ( FreshnessStatus::NONE !== $status ) {
			$badge = sprintf(
				' <span class="living-handbook-badge living-handbook-badge--%s">%s</span>',
				esc_attr( $status ),
				esc_html( FreshnessStatus::label( $status ) )
			);
		}

		$reviewer_id = (int) get_post_meta( $post_id, Metadata::REVIEWER, true );
		$reviewer    = $reviewer_id > 0 ? get_userdata( $reviewer_id ) : false;
		$role_terms  = wp_get_object_terms( $post_id, Taxonomies::ROLE, array( 'fields' => 'names' ) );
		$role        = ( ! is_wp_error( $role_terms ) && ! empty( $role_terms ) ) ? (string) $role_terms[0] : '';

		$rows = '';
		if ( '' !== $updated ) {
			$rows .= $this->row( __( 'Last updated', 'living-handbook' ), esc_html( $updated ) );
		}
		if ( '' !== $reviewed ) {
			$rows .= $this->row( __( 'Last reviewed', 'living-handbook' ), esc_html( $reviewed ) . $badge );
		}
		$responsible = trim( $role . ( false !== $reviewer ? ' · ' . $reviewer->display_name : '' ) );
		if ( '' !== $responsible ) {
			$rows .= $this->row( __( 'Responsible', 'living-handbook' ), esc_html( $responsible ) );
		}

		if ( '' === $rows ) {
			return '';
		}
		return '<footer class="living-handbook-meta"><dl>' . $rows . '</dl></footer>';
	}

	/**
	 * Build one label/value row.
	 *
	 * @param string $label      Field label.
	 * @param string $value_html Already-escaped value markup.
	 * @return string
	 */
	private function row( string $label, string $value_html ): string {
		return sprintf(
			'<div><dt class="living-handbook-meta__label">%s</dt><dd class="living-handbook-meta__value">%s</dd></div>',
			esc_html( $label ),
			$value_html
		);
	}

	/**
	 * Build the feedback prompt markup.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function render_feedback( int $post_id ): string {
		return sprintf(
			'<div class="living-handbook-feedback" data-post="%d"><span>%s</span> <button type="button" data-value="yes">%s</button> <button type="button" data-value="no">%s</button></div>',
			$post_id,
			esc_html__( 'Was this helpful?', 'living-handbook' ),
			esc_html__( 'Yes', 'living-handbook' ),
			esc_html__( 'No', 'living-handbook' )
		);
	}

	/**
	 * Shortcode that renders the current handbook's page tree as navigation.
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
