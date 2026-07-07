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
		$reviewed = (string) get_post_meta( $post_id, Metadata::REVIEWED, true );
		$interval = (int) get_post_meta( $post_id, Metadata::INTERVAL, true );
		if ( '' === $reviewed || $interval <= 0 ) {
			return self::NONE;
		}

		$due      = strtotime( $reviewed . ' +' . $interval . ' days' );
		$escalate = strtotime( $reviewed . ' +' . ( 2 * $interval ) . ' days' );
		if ( false === $due ) {
			return self::NONE;
		}

		$now = time();
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
				return __( 'Unchecked', 'living-handbook' );
			default:
				return '';
		}
	}
}
