<?php
/**
 * Work that runs in passes: how long a pass may take, and the job id it carries.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The shared rules of the jobs that stop on their own and are continued by the
 * browser: the folder import and the website export.
 *
 * Each pass renders or imports until its budget is spent, saves its place under
 * a job id and answers. The browser sends the id back for the next pass. Both
 * jobs used to carry their own copy of these two rules.
 */
final class TimeBudget {

	/**
	 * Share of PHP's max_execution_time a pass may spend on its work.
	 *
	 * The pass stops only between two pages, so the page it is on when the
	 * budget runs out still has to finish, and the answer still has to be
	 * built and sent. Forty per cent of the limit is left for that.
	 */
	private const SHARE_OF_LIMIT = 0.6;

	/**
	 * Fewest seconds a pass gets, however low the limit.
	 *
	 * Below this a pass spends more of its time starting up than working, and a
	 * single large page may not fit at all.
	 */
	private const MINIMUM_SECONDS = 5.0;

	/**
	 * Returns the seconds a pass may spend, given the job's own ceiling.
	 *
	 * The ceiling applies where PHP sets no limit or a generous one. Under a
	 * tighter limit the pass takes its share of it, but never less than the
	 * minimum.
	 *
	 * @param float      $ceiling The most the job wants a pass to take, in seconds.
	 * @param float|null $limit   PHP's limit in seconds, 0 for none. Read from
	 *                            max_execution_time when not given.
	 * @return float
	 */
	public static function seconds( float $ceiling, ?float $limit = null ): float {
		$limit = $limit ?? (float) ini_get( 'max_execution_time' );
		if ( $limit <= 0.0 ) {
			return $ceiling;
		}

		return min( $ceiling, max( self::MINIMUM_SECONDS, $limit * self::SHARE_OF_LIMIT ) );
	}

	/**
	 * Returns a job id from a request reduced to the characters an id holds.
	 *
	 * Letters and digits only, and the case is kept. That is not cosmetic: the
	 * id goes into a transient name, and lower-casing it would build a key that
	 * the database still finds, because its collation ignores case, while the
	 * object cache does not. One pass would read a stale copy of the job from
	 * the cache and repeat a page forever. sanitize_key() lower-cases, so it is
	 * not used.
	 *
	 * @param string $raw Job id as it arrived.
	 * @return string Empty when nothing usable is left.
	 */
	public static function job_id( string $raw ): string {
		$clean = preg_replace( '/[^A-Za-z0-9]/', '', $raw );

		return is_string( $clean ) ? $clean : '';
	}
}
