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
 */
final class FreshnessStatus {

	public const OK      = 'ok';
	public const DUE     = 'due';
	public const OVERDUE = 'overdue';
	public const NONE    = 'none';

	/**
	 * Compute the status for a post.
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
			time()
		);
	}

	/**
	 * The rule itself, without WordPress and without the clock.
	 *
	 * Freshness tracking is what this plugin is for, so the rule that decides
	 * whether a page counts as reviewed, due or overdue is worth having in one
	 * place that can be asked directly. Taking "now" as an argument is what makes
	 * the boundaries testable: a page is due the moment the interval has passed,
	 * not a day later.
	 *
	 * @param string $reviewed Date of the last review, as stored (Y-m-d).
	 * @param int    $interval Review interval in days.
	 * @param int    $now      The moment to judge against, as a Unix timestamp.
	 * @return string One of the class constants.
	 */
	public static function status( string $reviewed, int $interval, int $now ): string {
		if ( '' === $reviewed || $interval <= 0 ) {
			return self::NONE;
		}

		$due      = strtotime( $reviewed . ' +' . $interval . ' days' );
		$escalate = strtotime( $reviewed . ' +' . ( 2 * $interval ) . ' days' );
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
	 * Human-readable label for a status.
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
			default:
				return '';
		}
	}
}
