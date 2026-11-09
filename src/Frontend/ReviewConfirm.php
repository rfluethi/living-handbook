<?php
/**
 * The one-click review confirmation on a handbook page (E-0079).
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Frontend;

use LivingHandbook\Export\StaticSite;
use LivingHandbook\Git\GitSync;
use LivingHandbook\Meta\Metadata;
use LivingHandbook\PostType\Handbook;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * "Reviewed, still current" next to the review badge.
 *
 * Whoever may edit the page sees the button in the page footer, beside the
 * review date. A click sets the review date to today and the reviewer to the
 * person who clicked, and nothing else: the content and its "last updated"
 * date stay as they are, because nothing in the page changed. Setting the date
 * without the person would leave the footer naming someone who did not review.
 *
 * Two places get no button. A page synced from GitHub takes its review date
 * from the transport block of its file on every sync, so a click would be
 * undone by the next one; an editor sees a note there instead. And the static
 * export, which renders as the exporting person, who may well edit the page:
 * the file it writes has no server to answer the button.
 */
final class ReviewConfirm {

	/**
	 * REST route, under Filters::REST_NAMESPACE.
	 */
	public const REST_ROUTE = '/review';

	/**
	 * Registers the route.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_rest' ) );
	}

	/**
	 * Registers POST /living-handbook/v1/review.
	 *
	 * @return void
	 */
	public function register_rest(): void {
		register_rest_route(
			Filters::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'confirm' ),
				'permission_callback' => array( self::class, 'permission' ),
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'people'  => array(
						'sanitize_callback' => 'rest_sanitize_boolean',
						'default'           => true,
					),
				),
			)
		);
	}

	/**
	 * Whether the current person may confirm the review of the requested page.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public static function permission( WP_REST_Request $request ): bool {
		$post_id = (int) $request->get_param( 'post_id' );
		return $post_id > 0
			&& Handbook::POST_TYPE === get_post_type( $post_id )
			&& current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Sets the review date to today and the reviewer to the current person.
	 *
	 * Answers with the new markup of the footer's review entry, which the
	 * script puts in place of the old one.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function confirm( WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'post_id' );
		if ( self::is_synced( $post_id ) ) {
			return new WP_Error(
				'living_handbook_review_synced',
				__( 'The review date of this page is kept in its source file on GitHub.', 'living-handbook' ),
				array( 'status' => 409 )
			);
		}

		self::mark_reviewed( $post_id, get_current_user_id() );

		return new WP_REST_Response(
			array(
				'html'    => PageMeta::reviewed_html( $post_id, (bool) $request->get_param( 'people' ) ),
				'message' => __( 'Review confirmed.', 'living-handbook' ),
			),
			200
		);
	}

	/**
	 * Records a review: today, by this person.
	 *
	 * Meta only, no wp_update_post(): a save would set "last updated" and
	 * make a revision of a page whose content did not change. The shared cache
	 * version is bumped so the cards and previews show the new status.
	 *
	 * @param int $post_id Page.
	 * @param int $user_id Reviewer.
	 * @return void
	 */
	public static function mark_reviewed( int $post_id, int $user_id ): void {
		update_post_meta( $post_id, Metadata::REVIEWED, wp_date( 'Y-m-d' ) );
		update_post_meta( $post_id, Metadata::REVIEWER, $user_id );
		Navigation::invalidate();
	}

	/**
	 * Whether a page takes its content and review date from GitHub.
	 *
	 * @param int $post_id Page.
	 * @return bool
	 */
	public static function is_synced( int $post_id ): bool {
		return GitSync::SOURCE_GITHUB === get_post_meta( $post_id, GitSync::META_SOURCE, true );
	}

	/**
	 * The button, the note for a synced page, or nothing.
	 *
	 * @param int  $post_id     Page.
	 * @param bool $show_people Whether the footer shows people, passed back to the route.
	 * @return string
	 */
	public static function control( int $post_id, bool $show_people ): string {
		if ( StaticSite::is_rendering() || ! is_user_logged_in() || ! current_user_can( 'edit_post', $post_id ) ) {
			return '';
		}

		if ( self::is_synced( $post_id ) ) {
			return ' <span class="living-handbook-review-note">' . esc_html__( 'Review date is kept in the source file', 'living-handbook' ) . '</span>';
		}

		return sprintf(
			' <button type="button" class="living-handbook-review-confirm" data-post="%1$d" data-people="%2$d">%3$s</button>',
			$post_id,
			$show_people ? 1 : 0,
			esc_html__( 'Reviewed, still current', 'living-handbook' )
		);
	}
}
