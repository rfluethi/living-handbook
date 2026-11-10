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

use LivingHandbook\Handbook\HandbookVersion;
use LivingHandbook\Handbook\Handbooks;
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
	 * Avatar size in pixels next to a person in the footer: the height of two
	 * lines of the footer text.
	 */
	private const AVATAR_SIZE = 28;

	/**
	 * Returns the feedback prompt markup.
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

		// The buttons say "Yes" and "No", which means nothing on its own. Up to
		// 0.75.1 nothing tied them to the question beside them, so a screen
		// reader that jumps from button to button announced "Yes, button" with
		// no question. The container is a group named by the question now.
		$question = wp_unique_id( 'living-handbook-feedback-' );

		return sprintf(
			'<div class="living-handbook-feedback" data-post="%1$d" role="group" aria-labelledby="%2$s"><span id="%2$s">%3$s</span> <button type="button" data-value="yes">%4$s</button> <button type="button" data-value="no">%5$s</button></div>',
			$post_id,
			esc_attr( $question ),
			esc_html__( 'Was this helpful?', 'living-handbook' ),
			esc_html__( 'Yes', 'living-handbook' ),
			esc_html__( 'No', 'living-handbook' )
		);
	}

	/**
	 * Returns the metadata footer markup.
	 *
	 * @param int  $post_id      Post ID.
	 * @param bool $show_people  Whether to show the person (avatar and name).
	 * @param bool $show_version Whether to show the handbook version; the
	 *                           website export writes it into its own footer.
	 * @return string
	 */
	public static function render_meta( int $post_id, bool $show_people = true, bool $show_version = true ): string {
		$updated = (string) get_post_meta( $post_id, Metadata::UPDATED, true );

		$author_id = (int) get_post_field( 'post_author', $post_id );
		$editor_id = (int) get_post_meta( $post_id, '_edit_last', true );

		$created_value = esc_html( (string) get_the_date( '', $post_id ) );
		$updated_value = '' !== $updated ? esc_html( self::format_date( $updated ) ) : '—';

		$items  = self::item( __( 'Created', 'living-handbook' ), $created_value, self::person( $author_id, $show_people ) );
		$items .= self::item( __( 'Last updated', 'living-handbook' ), $updated_value, self::person( $editor_id, $show_people ) );
		$items .= '<div class="living-handbook-metagrid__item">'
			. '<dt class="living-handbook-metagrid__label">' . esc_html__( 'Last reviewed', 'living-handbook' ) . '</dt>'
			. '<dd class="living-handbook-metagrid__date living-handbook-metagrid__date--reviewed">' . self::reviewed_html( $post_id, $show_people ) . '</dd>'
			. '</div>';

		// Like the badges, the cards and the facets: a taxonomy the site has
		// switched off is not shown, though its terms stay on the page.
		if ( Taxonomies::is_enabled( Taxonomies::ROLE ) ) {
			$role_terms = wp_get_object_terms( $post_id, Taxonomies::ROLE, array( 'fields' => 'names' ) );
			$role       = ( ! is_wp_error( $role_terms ) && ! empty( $role_terms ) ) ? (string) $role_terms[0] : '—';
			$items     .= self::item( __( 'Responsible role', 'living-handbook' ), esc_html( $role ), '' );
		}

		// The version of the handbook, for every reader and without a date
		// (E-0084). Not the plugin's, which only signed-in people see (E-0082).
		$term_id = Handbooks::for_post( $post_id );
		if ( $show_version && $term_id > 0 ) {
			$items .= self::item( __( 'Handbook version', 'living-handbook' ), esc_html( HandbookVersion::get( $term_id ) ), '' );
		}

		return '<footer class="living-handbook-meta"><dl class="living-handbook-metagrid">' . $items . '</dl></footer>';
	}

	/**
	 * The content of the footer's review entry: date, status badge, the
	 * review button for whoever may edit the page (ReviewConfirm), and the
	 * reviewer.
	 *
	 * All four states are shown, the fourth one included: a page nobody has
	 * reviewed says so, instead of leaving the reader to guess whether the
	 * blank means fresh or forgotten. Shared with the review route, which
	 * answers with this markup after a click.
	 *
	 * @param int  $post_id     Post ID.
	 * @param bool $show_people Whether to show the person (avatar and name).
	 * @return string Escaped markup.
	 */
	public static function reviewed_html( int $post_id, bool $show_people = true ): string {
		$reviewed = (string) get_post_meta( $post_id, Metadata::REVIEWED, true );
		$status   = FreshnessStatus::for_post( $post_id );
		$label    = FreshnessStatus::label( $status );
		$badge    = '';
		if ( '' !== $label ) {
			$badge = ' <span class="living-handbook-badge living-handbook-badge--' . esc_attr( $status ) . '">' . esc_html( $label ) . '</span>';
		}

		$value = '' !== $reviewed ? esc_html( self::format_date( $reviewed ) ) : esc_html__( 'never', 'living-handbook' );

		return $value . $badge . ReviewConfirm::control( $post_id, $show_people ) . self::person( (int) get_post_meta( $post_id, Metadata::REVIEWER, true ), $show_people );
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
		$avatar = (string) get_avatar( $user_id, self::AVATAR_SIZE, '', '', array( 'class' => 'living-handbook-person__avatar' ) );
		return '<span class="living-handbook-person">' . $avatar
			. '<span class="living-handbook-person__name">' . esc_html( $user->display_name ) . '</span></span>';
	}

	/**
	 * Formats a stored Y-m-d date with the site's date format.
	 *
	 * The date is a calendar day in the site's time zone, so it is read in that
	 * zone and printed with wp_date(). Up to 0.75.1 it went through strtotime()
	 * and date_i18n(), which read the day in PHP's default zone: right only as
	 * long as that zone is UTC, and a day early on a site where a plugin or the
	 * server set another one east of UTC.
	 *
	 * @param string $ymd Date in Y-m-d.
	 * @return string The formatted date, or $ymd as it is when it cannot be read.
	 */
	private static function format_date( string $ymd ): string {
		try {
			$day = new \DateTimeImmutable( $ymd, wp_timezone() );
		} catch ( \Exception $e ) {
			return $ymd;
		}

		$out = wp_date( (string) get_option( 'date_format' ), $day->getTimestamp() );

		return is_string( $out ) ? $out : $ymd;
	}
}
