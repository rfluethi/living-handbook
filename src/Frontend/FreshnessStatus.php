<?php
/**
 * Freshness status of a handbook page.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Frontend;

use LivingHandbook\Meta\Metadata;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Derives a review status from the last review date and the review interval.
 *
 * Four states, not three. A page without a review date or without an interval
 * is not reviewed, due or overdue; it is a page nobody has looked at, and that is a
 * state of its own with its own answer ("set a review date"). It used to be the
 * absence of a state: NONE existed as a value, had no label, and was skipped
 * wherever a badge or a dot was drawn, so a freshly imported handbook showed
 * nothing at all where its freshness belongs. NONE is deliberately neutral in
 * colour rather than alarming: never looked at is not the same as overdue.
 */
final class FreshnessStatus {

	/**
	 * Reviewed, and the interval has not passed yet.
	 */
	public const OK = 'ok';

	/**
	 * The interval has passed, twice the interval has not.
	 */
	public const DUE = 'due';

	/**
	 * More than twice the interval has passed.
	 */
	public const OVERDUE = 'overdue';

	/**
	 * No review date or no interval: nobody has looked at the page yet.
	 */
	public const NONE = 'none';

	/**
	 * Returns every status, in the order a person reads them: from the page that needs
	 * attention most to the one that needs none, with the page nobody has looked
	 * at yet at the end. Used wherever all four are offered at once.
	 *
	 * @return array<int, string>
	 */
	public static function all(): array {
		return array( self::OVERDUE, self::DUE, self::OK, self::NONE );
	}

	/**
	 * Computes the status for a post.
	 *
	 * Overdue (escalated) means more than twice the interval has passed.
	 *
	 * @param int $post_id Post ID.
	 * @return string One of the class constants.
	 */
	public static function for_post( int $post_id ): string {
		return self::status(
			(string) get_post_meta( $post_id, Metadata::REVIEWED, true ),
			(int) get_post_meta( $post_id, Metadata::INTERVAL, true ),
			time(),
			wp_timezone()
		);
	}

	/**
	 * Decides the status from a review date, an interval and a moment: the rule
	 * itself, without WordPress and without the clock.
	 *
	 * Freshness tracking is what this plugin is for, so the rule that decides
	 * whether a page counts as reviewed, due or overdue is worth having in one
	 * place that can be asked directly. Taking "now" as an argument is what makes
	 * the boundaries testable: a page is due the moment the interval has passed,
	 * not a day later.
	 *
	 * The review date is a calendar date in the site's time zone: it is what an
	 * editor picked in a date field, or the day an import ran, both local. Up to
	 * 0.75.1 it was read with strtotime(), which takes a date without a zone as
	 * UTC, so on a site in Zurich a page turned due at 01:00 in winter and 02:00
	 * in summer instead of at midnight, and the list of overdue pages was wrong
	 * for the first hours of every day. The days are now counted in the zone the
	 * caller names; for_post() passes the site's.
	 *
	 * @param string             $reviewed Date of the last review, as stored (Y-m-d).
	 * @param int                $interval Review interval in days.
	 * @param int                $now      The moment to judge against, as a Unix timestamp.
	 * @param \DateTimeZone|null $timezone Zone the date is read in. Default UTC.
	 * @return string One of the class constants.
	 */
	public static function status( string $reviewed, int $interval, int $now, ?\DateTimeZone $timezone = null ): string {
		if ( '' === $reviewed || $interval <= 0 ) {
			return self::NONE;
		}

		$due      = self::days_later( $reviewed, $interval, $timezone );
		$escalate = self::days_later( $reviewed, 2 * $interval, $timezone );
		if ( false === $due ) {
			return self::NONE;
		}

		if ( false !== $escalate && $escalate < $now ) {
			return self::OVERDUE;
		}
		if ( $due < $now ) {
			return self::DUE;
		}
		return self::OK;
	}

	/**
	 * Midnight at the start of the day a number of days after a date, in a zone.
	 *
	 * Calendar days, not 86400-second blocks: across a change to or from summer
	 * time the day stays the day.
	 *
	 * @param string             $date     A date DateTimeImmutable can read, usually Y-m-d.
	 * @param int                $days     Days to add.
	 * @param \DateTimeZone|null $timezone Zone. Default UTC.
	 * @return int|false Unix timestamp, or false for a date that cannot be read.
	 */
	private static function days_later( string $date, int $days, ?\DateTimeZone $timezone ) {
		try {
			$moment = new \DateTimeImmutable( $date, $timezone ?? new \DateTimeZone( 'UTC' ) );
		} catch ( \Exception $e ) {
			return false;
		}

		return $moment->modify( '+' . $days . ' days' )->getTimestamp();
	}

	/**
	 * Returns the human-readable label for a status.
	 *
	 * @param string $status Status value.
	 * @return string
	 */
	public static function label( string $status ): string {
		switch ( $status ) {
			case self::OK:
				return __( 'Reviewed', 'living-handbook' );
			case self::DUE:
				return __( 'Review due', 'living-handbook' );
			case self::OVERDUE:
				return __( 'Review overdue', 'living-handbook' );
			case self::NONE:
				return __( 'Not reviewed', 'living-handbook' );
			default:
				return '';
		}
	}
}
