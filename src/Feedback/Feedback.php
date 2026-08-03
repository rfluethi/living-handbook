<?php
/**
 * "Was this helpful?" feedback counters.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Feedback;

use LivingHandbook\Access\AccessController;
use LivingHandbook\PostType\Handbook;
use LivingHandbook\Setup\Settings;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores per-page yes/no counts and exposes a REST endpoint to increment them.
 *
 * A logged-in vote is counted once per user and page: a second submit from the
 * same user is accepted but does not change the counters. When public feedback
 * is switched on (Settings), a logged-out visitor may also vote on a page they
 * can view; that vote has no per-person limit and stores nothing personal (no
 * cookie, no IP, no id), so it stays privacy-friendly at the cost of dedup.
 */
final class Feedback {

	/**
	 * The vote counters, under the plugin's protected prefix like everything it
	 * writes about a page rather than for it. They were _living_handbook_ before,
	 * a third spelling next to living_handbook_ and _lh_.
	 */
	public const YES = '_lh_feedback_yes';
	public const NO  = '_lh_feedback_no';

	/**
	 * Meta key holding the list of user IDs that already voted on a page.
	 */
	public const VOTERS = '_lh_feedback_voters';

	/**
	 * Hook registration into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
	}

	/**
	 * Register the feedback REST route.
	 *
	 * @return void
	 */
	public function register_route(): void {
		register_rest_route(
			'living-handbook/v1',
			'/feedback',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => static function () {
					// Logged-in users may always vote (one vote each). Logged-out
					// visitors only when public feedback is switched on; the page
					// access check in handle() still limits them to public pages.
					return is_user_logged_in() || Settings::public_feedback_enabled();
				},
				'args'                => array(
					'post_id' => array(
						'required' => true,
						'type'     => 'integer',
					),
					'value'   => array(
						'required' => true,
						'type'     => 'string',
					),
				),
			)
		);
	}

	/**
	 * Increment the relevant counter, once per user and page.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function handle( WP_REST_Request $request ): WP_REST_Response {
		$post_id = (int) $request->get_param( 'post_id' );
		$value   = (string) $request->get_param( 'value' );
		$post    = get_post( $post_id );

		if ( ! $post instanceof WP_Post || Handbook::POST_TYPE !== $post->post_type ) {
			return new WP_REST_Response( array( 'ok' => false ), 400 );
		}

		// Only accept feedback for a page the user is actually allowed to read,
		// so voting follows the same per-handbook access rules as viewing.
		if ( ! AccessController::can_view_post( $post_id, get_current_user_id() ) ) {
			return new WP_REST_Response( array( 'ok' => false ), 403 );
		}

		$key = '';
		if ( 'yes' === $value ) {
			$key = self::YES;
		} elseif ( 'no' === $value ) {
			$key = self::NO;
		}
		if ( '' === $key ) {
			return new WP_REST_Response( array( 'ok' => false ), 400 );
		}

		$user_id = get_current_user_id();

		// A logged-out vote (user id 0) only counts when public feedback is on, and
		// carries no dedup: to stay privacy-friendly we store no cookie, no IP and
		// no id for it, so the same visitor can vote again after reloading. The
		// buttons hide client-side after a vote to blunt casual double-clicks.
		if ( 0 === $user_id ) {
			if ( ! Settings::public_feedback_enabled() ) {
				return new WP_REST_Response( array( 'ok' => false ), 403 );
			}
			$count = (int) get_post_meta( $post_id, $key, true ) + 1;
			update_post_meta( $post_id, $key, $count );
			return new WP_REST_Response(
				array(
					'ok'      => true,
					'counted' => true,
				)
			);
		}

		// A logged-in vote counts once per user and page: the user id is kept in a
		// voter list so a second submit is accepted but does not change the counts.
		$voters = get_post_meta( $post_id, self::VOTERS, true );
		if ( ! is_array( $voters ) ) {
			$voters = array();
		}

		if ( in_array( $user_id, $voters, true ) ) {
			return new WP_REST_Response(
				array(
					'ok'      => true,
					'counted' => false,
				)
			);
		}

		$count = (int) get_post_meta( $post_id, $key, true ) + 1;
		update_post_meta( $post_id, $key, $count );

		$voters[] = $user_id;
		update_post_meta( $post_id, self::VOTERS, $voters );

		return new WP_REST_Response(
			array(
				'ok'      => true,
				'counted' => true,
			)
		);
	}
}
