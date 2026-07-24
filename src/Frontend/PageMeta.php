<?php
/**
 * Feedback prompt and metadata footer for a single handbook page.
 *
 * Rendered as two separate blocks placed after the content in the single
 * template, so they do not depend on the_content running inside the loop.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Frontend;

use LivingHandbook\Meta\Metadata;
use LivingHandbook\Setup\Settings;
use LivingHandbook\Taxonomy\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the per-page feedback prompt and metadata footer.
 */
final class PageMeta {

	/**
	 * Build the feedback prompt markup.
	 *
	 * A logged-in visitor always sees the buttons. A logged-out visitor sees them
	 * only when public feedback is switched on, matching what the REST endpoint
	 * accepts, so a guest never gets buttons that would only error.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function render_feedback( int $post_id ): string {
		if ( ! is_user_logged_in() && ! Settings::public_feedback_enabled() ) {
			return '';
		}
		return sprintf(
			'<div class="living-handbook-feedback" data-post="%d"><span>%s</span> <button type="button" data-value="yes">%s</button> <button type="button" data-value="no">%s</button></div>',
			$post_id,
			esc_html__( 'Was this helpful?', 'living-handbook' ),
			esc_html__( 'Yes', 'living-handbook' ),
			esc_html__( 'No', 'living-handbook' )
		);
	}

	/**
	 * Build the metadata footer markup.
	 *
	 * @param int  $post_id     Post ID.
	 * @param bool $show_people Whether to show the person (avatar and name).
	 * @return string
	 */
	public static function render_meta( int $post_id, bool $show_people = true ): string {
		$updated  = (string) get_post_meta( $post_id, Metadata::UPDATED, true );
		$reviewed = (string) get_post_meta( $post_id, Metadata::REVIEWED, true );

		$status = FreshnessStatus::for_post( $post_id );
		$badge  = '';
		if ( FreshnessStatus::NONE !== $status ) {
			$badge = ' <span class="living-handbook-badge living-handbook-badge--' . esc_attr( $status ) . '">' . esc_html( FreshnessStatus::label( $status ) ) . '</span>';
		}

		$author_id   = (int) get_post_field( 'post_author', $post_id );
		$editor_id   = (int) get_post_meta( $post_id, '_edit_last', true );
		$reviewer_id = (int) get_post_meta( $post_id, Metadata::REVIEWER, true );

		$role_terms = wp_get_object_terms( $post_id, Taxonomies::ROLE, array( 'fields' => 'names' ) );
		$role       = ( ! is_wp_error( $role_terms ) && ! empty( $role_terms ) ) ? (string) $role_terms[0] : '—';

		$created_value  = esc_html( (string) get_the_date( '', $post_id ) );
		$updated_value  = '' !== $updated ? esc_html( self::format_date( $updated ) ) : '—';
		$reviewed_value = '' !== $reviewed ? esc_html( self::format_date( $reviewed ) ) : esc_html__( 'never', 'living-handbook' );

		$items  = self::item( __( 'Created', 'living-handbook' ), $created_value, self::person( $author_id, $show_people ) );
		$items .= self::item( __( 'Last updated', 'living-handbook' ), $updated_value, self::person( $editor_id, $show_people ) );
		$items .= self::item( __( 'Last reviewed', 'living-handbook' ), $reviewed_value . $badge, self::person( $reviewer_id, $show_people ) );
		$items .= self::item( __( 'Responsible role', 'living-handbook' ), esc_html( $role ), '' );

		return '<footer class="living-handbook-meta"><dl class="living-handbook-metagrid">' . $items . '</dl></footer>';
	}

	/**
	 * Build one metadata grid item.
	 *
	 * @param string $label       Field label.
	 * @param string $value_html  Already-escaped value markup.
	 * @param string $person_html Already-escaped person markup, or empty.
	 * @return string
	 */
	private static function item( string $label, string $value_html, string $person_html ): string {
		return '<div class="living-handbook-metagrid__item">'
			. '<dt class="living-handbook-metagrid__label">' . esc_html( $label ) . '</dt>'
			. '<dd class="living-handbook-metagrid__date">' . $value_html . $person_html . '</dd>'
			. '</div>';
	}

	/**
	 * Build the avatar and name markup for a user.
	 *
	 * @param int  $user_id     User ID.
	 * @param bool $show_people Whether to render anything at all.
	 * @return string
	 */
	private static function person( int $user_id, bool $show_people ): string {
		if ( ! $show_people || $user_id <= 0 ) {
			return '';
		}
		$user = get_userdata( $user_id );
		if ( false === $user ) {
			return '';
		}
		$avatar = (string) get_avatar( $user_id, 28, '', '', array( 'class' => 'living-handbook-person__avatar' ) );
		return '<span class="living-handbook-person">' . $avatar
			. '<span class="living-handbook-person__name">' . esc_html( $user->display_name ) . '</span></span>';
	}

	/**
	 * Format a Y-m-d date with the site's date format.
	 *
	 * @param string $ymd Date in Y-m-d.
	 * @return string
	 */
	private static function format_date( string $ymd ): string {
		$timestamp = strtotime( $ymd );
		return false !== $timestamp ? date_i18n( (string) get_option( 'date_format' ), $timestamp ) : $ymd;
	}
}
