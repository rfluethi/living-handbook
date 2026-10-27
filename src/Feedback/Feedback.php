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
 * What limits it is a ceiling per page and hour (ANONYMOUS_LIMIT).
 */
final class Feedback {

	/**
	 * Meta key of the "yes" counter, under the plugin's protected prefix like
	 * everything it writes about a page rather than for it. Up to 0.75.1 the
	 * counters were _living_handbook_, a third spelling next to
	 * living_handbook_ and _lh_.
	 */
	public const YES = '_lh_feedback_yes';

	/**
	 * Meta key of the "no" counter, see YES.
	 */
	public const NO = '_lh_feedback_no';

	/**
	 * Meta key holding the list of user IDs that already voted on a page.
	 */
	public const VOTERS = '_lh_feedback_voters';

	/**
	 * How many anonymous votes one page accepts per hour, by default.
	 *
	 * Deliberately generous: it is a ceiling against a script, not a quota for
	 * readers. A handbook page that genuinely collects 200 anonymous votes in an
	 * hour is having a very good day, and the site can raise the number.
	 */
	public const ANONYMOUS_LIMIT = 200;

	/**
	 * Registers the hooks with WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
	}

	/**
	 * Registers the feedback REST route.
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
						'enum'     => array( 'yes', 'no' ),
					),
				),
			)
		);
	}

	/**
	 * Counts a vote: once per signed-in user and page, and for anonymous votes
	 * within the hourly ceiling of the page.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function handle( WP_REST_Request $request ): WP_REST_Response {
		$post_id = (int) $request->get_param( 'post_id' );
		$value   = (string) $request->get_param( 'value' );
		$post    = get_post( $post_id );

		// Only a published page takes a vote. The access check below asks about
		// the handbook, not about the page, and up to 0.75.1 a vote on the id of
		// a draft, a pending or a private page was counted. A draft gets the same
		// answer as an id that does not exist, so the endpoint does not confirm
		// that there is one.
		if ( ! $post instanceof WP_Post || Handbook::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return new WP_REST_Response( array( 'ok' => false ), 400 );
		}

		// A password-protected page takes a vote only from somebody who has
		// entered the password, the same rule core applies to its content.
		if ( post_password_required( $post ) ) {
			return new WP_REST_Response( array( 'ok' => false ), 403 );
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
		//
		// What that costs is a database write per request, without limit, which is
		// why there is a ceiling per page and hour below. It is not a dedup and
		// does not pretend to be one: it keeps a script from turning the counter
		// into noise and the postmeta table into a write log.
		if ( 0 === $user_id ) {
			if ( ! Settings::public_feedback_enabled() ) {
				return new WP_REST_Response( array( 'ok' => false ), 403 );
			}
			if ( ! self::anonymous_vote_allowed( $post_id ) ) {
				return new WP_REST_Response(
					array(
						'ok'      => true,
						'counted' => false,
					),
					429
				);
			}
			self::increment( $post_id, $key );
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

		self::increment( $post_id, $key );

		$voters[] = $user_id;
		update_post_meta( $post_id, self::VOTERS, $voters );

		return new WP_REST_Response(
			array(
				'ok'      => true,
				'counted' => true,
			)
		);
	}

	/**
	 * Add one to a vote counter, in the database rather than in PHP.
	 *
	 * Up to 0.75.1 the counter was read, increased in PHP and written back, so
	 * two votes arriving at the same moment read the same number and one of them
	 * was lost. The addition now happens in a single UPDATE, which the database
	 * applies one after the other.
	 *
	 * The row has to exist for the UPDATE to find it, so it is created first
	 * with 0. That step is not atomic: two first votes at once can each create a
	 * row. It still counts right, because every later UPDATE adds to all rows of
	 * the key, the oldest row has been there for every one of them, and the
	 * oldest row is the one get_post_meta() reads.
	 *
	 * @param int    $post_id Page.
	 * @param string $key     Counter meta key.
	 * @return void
	 */
	private static function increment( int $post_id, string $key ): void {
		global $wpdb;

		add_post_meta( $post_id, $key, 0, true );

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- an atomic increment has no API; the cache is cleared below.
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_value = meta_value + 1 WHERE post_id = %d AND meta_key = %s",
				$post_id,
				$key
			)
		);

		wp_cache_delete( $post_id, 'post_meta' );
	}

	/**
	 * Whether another anonymous vote on this page fits inside the hourly ceiling.
	 *
	 * Counted in one transient per page and hour, not per visitor: an anonymous
	 * vote is deliberately not tied to a person, so there is nobody to count. The
	 * ceiling therefore protects the page, not the fairness of the vote, and it
	 * is set far above what a team of readers produces. A site that needs a real
	 * one-vote-per-person rule switches public feedback off and lets people sign
	 * in, which is what the logged-in path does.
	 *
	 * Sites can raise or lower it through living_handbook_anonymous_feedback_limit,
	 * and a value of 0 or less switches the ceiling off.
	 *
	 * @param int $post_id Page being voted on.
	 * @return bool
	 */
	private static function anonymous_vote_allowed( int $post_id ): bool {
		/**
		 * Filters how many anonymous votes one page accepts per hour.
		 *
		 * @param int $limit   Votes per page and hour. 0 or less switches it off.
		 * @param int $post_id Page being voted on.
		 */
		$limit = (int) apply_filters( 'living_handbook_anonymous_feedback_limit', self::ANONYMOUS_LIMIT, $post_id );
		if ( $limit <= 0 ) {
			return true;
		}

		$key   = 'lh_fb_' . $post_id . '_' . gmdate( 'YmdH' );
		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return false;
		}

		// Two hours, so the entry outlives its own window and a page that stops
		// being voted on does not leave a counter behind that never expires.
		set_transient( $key, $count + 1, 2 * HOUR_IN_SECONDS );

		return true;
	}
}
