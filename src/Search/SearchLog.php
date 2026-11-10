<?php
/**
 * Counts searches that found nothing.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Search;

use DateTimeImmutable;
use LivingHandbook\Access\AccessController;
use LivingHandbook\Handbook\Handbooks;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The record of searches without a hit, and nothing else.
 *
 * A handbook's search only ever tells the reader what it found. What it did
 * not find is the more useful half for the editors: a word people look for and
 * no page answers. This class keeps that half, under four rules (decision
 * E-0078):
 *
 * - What is stored is a count per search term, handbook, calendar week and
 *   source ("site" for the handbook's own search, "ai" for the search ability).
 *   No user id, no IP address, no time of day, no cookie, no referrer. A week
 *   rather than a day, because in a restricted handbook with three members
 *   "dismissal, Tuesday" is nearly a name; a week blurs that and is enough to
 *   see what is missing.
 * - Only a search the person was allowed to run is counted: the handbook must
 *   be readable to them. A search with facets selected is not counted at all,
 *   because there an empty list says the combination is empty, not that a page
 *   is missing. The callers make that call; record() checks access itself.
 * - Weeks older than the retention period, 90 days unless a filter says
 *   otherwise, are deleted by a daily cron run.
 * - At most MAX_TERMS distinct terms per handbook and week. A public handbook's
 *   search answers anyone, and without a ceiling a script could fill the table
 *   with nonsense. Beyond the ceiling only terms already there are counted up.
 *
 * Nothing here can be exported or erased per person through the privacy tools
 * of WordPress, because nothing here belongs to a person. The suggested privacy
 * policy says so.
 */
final class SearchLog {

	/**
	 * The table, without the site's prefix.
	 */
	public const TABLE = 'living_handbook_failed_searches';

	/**
	 * Option: whether failed searches are counted. On unless switched off.
	 */
	public const OPTION_ENABLED = 'living_handbook_search_log';

	/**
	 * Daily cron event that deletes old weeks.
	 */
	public const CRON_HOOK = 'living_handbook_search_log_cleanup';

	/**
	 * Source of a search made on the site: the entry page or the quick search.
	 */
	public const SOURCE_SITE = 'site';

	/**
	 * Source of a search made through the search ability.
	 */
	public const SOURCE_AI = 'ai';

	/**
	 * Shortest term worth keeping, in characters. Two letters are noise, and
	 * the live search sends every keystroke.
	 */
	public const MIN_LENGTH = 3;

	/**
	 * Longest term kept, in characters. Anything longer is a pasted paragraph,
	 * not a search, and the likeliest place for a name or an address. It is
	 * dropped, not cut.
	 */
	public const MAX_LENGTH = 100;

	/**
	 * Distinct terms kept per handbook and week.
	 */
	public const MAX_TERMS = 500;

	/**
	 * Retention in days unless the filter says otherwise.
	 */
	public const DEFAULT_RETENTION_DAYS = 90;

	/**
	 * Registers the cleanup event's handler.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( self::CRON_HOOK, array( $this, 'run_cleanup' ) );
		add_filter( 'wpmu_drop_tables', array( self::class, 'drop_with_site' ), 10, 2 );
	}

	/**
	 * Puts the table on the list WordPress drops with a deleted site of a
	 * network (E-0085). The name comes from the site's own prefix rather than
	 * the current one: WordPress switches to the site before it asks, but a
	 * caller of the filter need not.
	 *
	 * The filter runs where the site is deleted, usually the network admin of
	 * the main site; where the plugin is not active there, the table stays
	 * until the plugin is deleted, and uninstall.php drops it then.
	 *
	 * @param mixed $tables  Tables to drop.
	 * @param int   $site_id Site being deleted.
	 * @return mixed
	 */
	public static function drop_with_site( $tables, $site_id = 0 ) {
		global $wpdb;
		if ( ! is_array( $tables ) ) {
			return $tables;
		}
		$site_id  = (int) $site_id;
		$tables[] = $site_id > 0 ? $wpdb->get_blog_prefix( $site_id ) . self::TABLE : self::table();
		return $tables;
	}

	/**
	 * The cron handler: cleanup() without its return value.
	 *
	 * @return void
	 */
	public function run_cleanup(): void {
		self::cleanup();
	}

	/**
	 * The full table name.
	 *
	 * @return string
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Whether failed searches are counted on this site.
	 *
	 * A missing option means on: the switch is on by default (E-0078), and
	 * Plugin::store_default_options() writes it on activation and update.
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		return '0' !== (string) get_option( self::OPTION_ENABLED, '1' );
	}

	/**
	 * Keeps the switch a plain 0 or 1.
	 *
	 * @param mixed $value Submitted value.
	 * @return int
	 */
	public static function sanitize_enabled( $value ): int {
		return empty( $value ) ? 0 : 1;
	}

	/**
	 * Creates or updates the table.
	 *
	 * Runs on activation and on every version change; dbDelta() leaves a table
	 * that already has the right shape alone. The term is compared with the
	 * site's collation, so "Müller" and "muller" may share a row on a site with
	 * an accent-insensitive collation. For a count of what is missing that is a
	 * help rather than a fault.
	 *
	 * @return void
	 */
	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$collate = $wpdb->get_charset_collate();

		// dbDelta wants two spaces after PRIMARY KEY and one field per line.
		dbDelta(
			"CREATE TABLE {$table} (
  week date NOT NULL,
  term_id bigint(20) unsigned NOT NULL,
  source varchar(8) NOT NULL,
  term varchar(100) NOT NULL,
  hits int(10) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY  (week,term_id,source,term),
  KEY term_id (term_id)
) {$collate};"
		);
	}

	/**
	 * Drops the table. For the uninstall.
	 *
	 * @return void
	 */
	public static function drop(): void {
		global $wpdb;

		$table = self::table();
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- the plugin's own table, removed on uninstall.
	}

	/**
	 * Schedules the daily cleanup, unless it is scheduled already.
	 *
	 * @return void
	 */
	public static function schedule(): void {
		if ( false === wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Removes the daily cleanup.
	 *
	 * @return void
	 */
	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * The retention period in days, between 7 and 365.
	 *
	 * @return int
	 */
	public static function retention_days(): int {
		/**
		 * Filters how many days failed searches are kept.
		 *
		 * The value is held between 7 and 365. Counting is per calendar week, and
		 * a week is deleted once its first day is older than this, so a search is
		 * never kept longer than this many days.
		 *
		 * @param int $days Days to keep. Default 90.
		 */
		$days = (int) apply_filters( 'living_handbook_search_log_retention_days', self::DEFAULT_RETENTION_DAYS );

		return max( 7, min( 365, $days ) );
	}

	/**
	 * Brings a search term into the form it is counted in, or '' when it is
	 * not to be counted.
	 *
	 * Lower case, whitespace collapsed to single spaces, trimmed. A term
	 * shorter than MIN_LENGTH or longer than MAX_LENGTH characters gives ''.
	 *
	 * @param string $search Search as typed.
	 * @return string
	 */
	public static function normalize( string $search ): string {
		$search = (string) preg_replace( '/[\p{C}\s]+/u', ' ', $search );
		$search = trim( mb_strtolower( $search, 'UTF-8' ) );
		$length = mb_strlen( $search, 'UTF-8' );

		if ( $length < self::MIN_LENGTH || $length > self::MAX_LENGTH ) {
			return '';
		}

		return $search;
	}

	/**
	 * The Monday of the week a moment falls in, in the site's time zone.
	 *
	 * @param int $now Unix timestamp.
	 * @return string Y-m-d.
	 */
	public static function week_of( int $now ): string {
		$day     = ( new DateTimeImmutable( '@' . $now ) )->setTimezone( wp_timezone() );
		$weekday = (int) $day->format( 'N' );

		return $day->modify( '-' . ( $weekday - 1 ) . ' days' )->format( 'Y-m-d' );
	}

	/**
	 * Counts one search without a hit.
	 *
	 * @param int    $term_id Handbook searched.
	 * @param string $search  The search as typed.
	 * @param string $source  SOURCE_SITE or SOURCE_AI.
	 * @param int    $now     Moment of the search, for tests. Default now.
	 * @return bool Whether it was counted.
	 */
	public static function record( int $term_id, string $search, string $source = self::SOURCE_SITE, int $now = 0 ): bool {
		if ( ! self::enabled() || ! in_array( $source, array( self::SOURCE_SITE, self::SOURCE_AI ), true ) ) {
			return false;
		}

		$term = self::normalize( $search );
		if ( '' === $term || $term_id <= 0 ) {
			return false;
		}

		$handbook = get_term( $term_id, Handbooks::TAXONOMY );
		if ( ! $handbook instanceof WP_Term || ! AccessController::can_view_term( $term_id, get_current_user_id() ) ) {
			return false;
		}

		global $wpdb;

		$table = self::table();
		$week  = self::week_of( $now > 0 ? $now : time() );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- the plugin's own counting table; no WordPress API reads or writes it, and a cached count would be a wrong count.
		$known = null !== $wpdb->get_var(
			$wpdb->prepare( "SELECT hits FROM {$table} WHERE week = %s AND term_id = %d AND source = %s AND term = %s", $week, $term_id, $source, $term )
		);
		if ( ! $known ) {
			$distinct = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE week = %s AND term_id = %d", $week, $term_id )
			);
			if ( $distinct >= self::MAX_TERMS ) {
				return false;
			}
		}

		// One statement for "new row or one more": two searches at once must not
		// lose a count, which a read followed by a write would.
		$done = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (week, term_id, source, term, hits) VALUES (%s, %d, %s, %s, 1) ON DUPLICATE KEY UPDATE hits = hits + 1",
				$week,
				$term_id,
				$source,
				$term
			)
		);
		// phpcs:enable

		return false !== $done;
	}

	/**
	 * Deletes every week whose first day is older than the retention period.
	 *
	 * @param int $now Moment to measure from, for tests. Default now.
	 * @return int Rows deleted.
	 */
	public static function cleanup( int $now = 0 ): int {
		global $wpdb;

		$now    = $now > 0 ? $now : time();
		$cutoff = ( new DateTimeImmutable( '@' . ( $now - self::retention_days() * DAY_IN_SECONDS ) ) )->setTimezone( wp_timezone() )->format( 'Y-m-d' );
		$table  = self::table();

		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE week < %s", $cutoff ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- the plugin's own counting table.

		return is_int( $deleted ) ? $deleted : 0;
	}

	/**
	 * The most frequent failed searches in a set of handbooks.
	 *
	 * The live search sends a request while the person types, so a word that
	 * finds nothing arrives as "mül", "müll", "mülle", "müller". A term that is
	 * the beginning of another term of the same handbook, source and week is
	 * therefore left out; the longer term carries the count. A real short word
	 * that happens to begin a longer one is lost with it, a price E-0078 names.
	 *
	 * @param array<int, int> $term_ids Handbooks to read. The caller has checked access.
	 * @param int             $days     How many days back, at most the retention period.
	 * @param int             $limit    Most rows to return.
	 * @param string          $source   SOURCE_SITE, SOURCE_AI or '' for both.
	 * @param int             $now      Moment to measure from, for tests. Default now.
	 * @return array<int, array{term:string, handbook_id:int, hits:int, weeks:int, last_week:string}>
	 */
	public static function most_frequent( array $term_ids, int $days = 30, int $limit = 50, string $source = '', int $now = 0 ): array {
		$term_ids = array_values( array_unique( array_filter( array_map( 'intval', $term_ids ) ) ) );
		if ( array() === $term_ids ) {
			return array();
		}

		global $wpdb;

		$now   = $now > 0 ? $now : time();
		$days  = max( 1, min( self::retention_days(), $days ) );
		$since = self::week_of( $now - ( $days - 1 ) * DAY_IN_SECONDS );
		$table = self::table();
		$in    = implode( ',', $term_ids );

		$sql  = "SELECT week, term_id, source, term, hits FROM {$table} WHERE term_id IN ({$in}) AND week >= %s";
		$args = array( $since );
		if ( '' !== $source ) {
			$sql   .= ' AND source = %s';
			$args[] = $source;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- the plugin's own counting table; the ids are integers, the rest is prepared.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$groups = array();
		foreach ( $rows as $row ) {
			$groups[ $row['week'] . '|' . $row['term_id'] . '|' . $row['source'] ][] = $row;
		}

		$totals = array();
		foreach ( $groups as $group ) {
			$terms = array_map( static fn( array $row ): string => (string) $row['term'], $group );
			foreach ( $group as $row ) {
				$term = (string) $row['term'];
				if ( self::begins_another( $term, $terms ) ) {
					continue;
				}
				$key = $row['term_id'] . '|' . $term;
				if ( ! isset( $totals[ $key ] ) ) {
					$totals[ $key ] = array(
						'term'        => $term,
						'handbook_id' => (int) $row['term_id'],
						'hits'        => 0,
						'weeks'       => array(),
						'last_week'   => '',
					);
				}
				$totals[ $key ]['hits']                 += (int) $row['hits'];
				$totals[ $key ]['weeks'][ $row['week'] ] = true;
				$totals[ $key ]['last_week']             = max( $totals[ $key ]['last_week'], (string) $row['week'] );
			}
		}

		$result = array_map(
			static fn( array $total ): array => array(
				'term'        => $total['term'],
				'handbook_id' => $total['handbook_id'],
				'hits'        => $total['hits'],
				'weeks'       => count( $total['weeks'] ),
				'last_week'   => $total['last_week'],
			),
			array_values( $totals )
		);

		usort(
			$result,
			static fn( array $a, array $b ): int => array( $b['hits'], $b['last_week'], $a['term'] ) <=> array( $a['hits'], $a['last_week'], $b['term'] )
		);

		return array_slice( $result, 0, max( 1, $limit ) );
	}

	/**
	 * Whether a term is the beginning of another term in the list.
	 *
	 * @param string            $term  The term.
	 * @param array<int,string> $terms All terms of its group.
	 * @return bool
	 */
	private static function begins_another( string $term, array $terms ): bool {
		foreach ( $terms as $other ) {
			if ( $other !== $term && str_starts_with( $other, $term ) ) {
				return true;
			}
		}
		return false;
	}
}
