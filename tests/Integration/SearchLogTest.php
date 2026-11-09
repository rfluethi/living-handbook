<?php
/**
 * The count of failed searches (E-0078).
 *
 * What is stored, when it is stored, what is refused, and how long it stays.
 * The rules are privacy rules first: a test that fails here means the plugin
 * keeps more than the decision allows, or keeps it for someone who could not
 * have searched.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Frontend\Filters;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use LivingHandbook\Search\SearchLog;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * Searches without a hit are counted per term, handbook, week and source only.
 */
final class SearchLogTest extends WP_UnitTestCase {

	/**
	 * A fixed Wednesday, 2026-11-11 12:00 UTC.
	 */
	private const WEDNESDAY = 1794398400;

	/**
	 * Empty the table and start the REST server.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		global $wpdb, $wp_rest_server;
		$wpdb->query( 'DELETE FROM ' . SearchLog::table() ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the test's own table state.
		update_option( SearchLog::OPTION_ENABLED, '1' );
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init' );
	}

	/**
	 * Drop the REST server.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null;
		parent::tear_down();
	}

	/**
	 * A handbook with a visibility.
	 *
	 * @param string $visibility Visibility.
	 * @return int
	 */
	private function handbook( string $visibility = Handbooks::VISIBILITY_PUBLIC ): int {
		$term_id = (int) self::factory()->term->create(
			array(
				'taxonomy' => Handbooks::TAXONOMY,
				'name'     => 'Handbook ' . wp_generate_password( 6, false ),
			)
		);
		update_term_meta( $term_id, Handbooks::META_VISIBILITY, $visibility );
		return $term_id;
	}

	/**
	 * A published page in a handbook.
	 *
	 * @param int    $term_id Handbook.
	 * @param string $title   Title.
	 * @return int
	 */
	private function page( int $term_id, string $title ): int {
		$post_id = (int) self::factory()->post->create(
			array(
				'post_type'    => Handbook::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => 'Body of ' . $title,
			)
		);
		wp_set_object_terms( $post_id, array( $term_id ), Handbooks::TAXONOMY );
		return $post_id;
	}

	/**
	 * Every row of the table.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function rows(): array {
		global $wpdb;
		return (array) $wpdb->get_results( 'SELECT * FROM ' . SearchLog::table() . ' ORDER BY term', ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- reading the table is the point.
	}

	/**
	 * The table has exactly the five columns E-0078 allows.
	 *
	 * Anyone who adds a user id or a time stamp has to change this test, and
	 * with it the decision.
	 *
	 * @return void
	 */
	public function test_the_table_holds_nothing_about_a_person(): void {
		global $wpdb;
		$columns = $wpdb->get_col( 'SHOW COLUMNS FROM ' . SearchLog::table() ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the schema is the point.

		$this->assertSame( array( 'week', 'term_id', 'source', 'term', 'hits' ), $columns );
	}

	/**
	 * Lower case, whitespace collapsed, and the length limits.
	 *
	 * @return void
	 */
	public function test_terms_are_normalised_and_bounded(): void {
		$this->assertSame( 'müller hans', SearchLog::normalize( "  Müller \t  HANS " ) );
		$this->assertSame( '', SearchLog::normalize( 'ab' ) );
		$this->assertSame( 'abc', SearchLog::normalize( 'ABC' ) );
		$this->assertSame( str_repeat( 'a', 100 ), SearchLog::normalize( str_repeat( 'a', 100 ) ) );
		$this->assertSame( '', SearchLog::normalize( str_repeat( 'a', 101 ) ) );
	}

	/**
	 * The week is the Monday of the site's week, Sunday included.
	 *
	 * @return void
	 */
	public function test_the_week_starts_on_monday_in_the_site_zone(): void {
		update_option( 'timezone_string', 'Europe/Zurich' );

		$this->assertSame( '2026-11-09', SearchLog::week_of( self::WEDNESDAY ) );
		// Sunday 2026-11-15 23:30 in Zurich is still that week.
		$this->assertSame( '2026-11-09', SearchLog::week_of( strtotime( '2026-11-15 22:30:00 UTC' ) ) );
		// Monday 00:30 in Zurich is the next one, though it is Sunday in UTC.
		$this->assertSame( '2026-11-16', SearchLog::week_of( strtotime( '2026-11-15 23:30:00 UTC' ) ) );

		delete_option( 'timezone_string' );
	}

	/**
	 * Same term, handbook, week and source count up one row.
	 *
	 * @return void
	 */
	public function test_a_repeated_search_counts_up_one_row(): void {
		$term_id = $this->handbook();

		$this->assertTrue( SearchLog::record( $term_id, 'Zugangscode', SearchLog::SOURCE_SITE, self::WEDNESDAY ) );
		$this->assertTrue( SearchLog::record( $term_id, 'zugangscode ', SearchLog::SOURCE_SITE, self::WEDNESDAY + DAY_IN_SECONDS ) );
		$this->assertTrue( SearchLog::record( $term_id, 'Zugangscode', SearchLog::SOURCE_AI, self::WEDNESDAY ) );

		$rows = $this->rows();
		$this->assertCount( 2, $rows );
		$by_source = array_column( $rows, 'hits', 'source' );
		$this->assertSame( '2', (string) $by_source['site'] );
		$this->assertSame( '1', (string) $by_source['ai'] );
	}

	/**
	 * A search the person could not run is not counted.
	 *
	 * @return void
	 */
	public function test_a_handbook_the_person_may_not_read_counts_nothing(): void {
		$restricted = $this->handbook( Handbooks::VISIBILITY_RESTRICTED );
		wp_set_current_user( 0 );

		$this->assertFalse( SearchLog::record( $restricted, 'geheim', SearchLog::SOURCE_SITE ) );
		$this->assertFalse( SearchLog::record( 999999, 'kein handbuch', SearchLog::SOURCE_SITE ) );
		$this->assertSame( array(), $this->rows() );
	}

	/**
	 * Switched off, nothing is counted; an unknown source neither.
	 *
	 * @return void
	 */
	public function test_switched_off_counts_nothing(): void {
		$term_id = $this->handbook();
		update_option( SearchLog::OPTION_ENABLED, 0 );

		$this->assertFalse( SearchLog::enabled() );
		$this->assertFalse( SearchLog::record( $term_id, 'irgendwas' ) );

		update_option( SearchLog::OPTION_ENABLED, 1 );
		$this->assertFalse( SearchLog::record( $term_id, 'irgendwas', 'cli' ) );
		$this->assertSame( array(), $this->rows() );
	}

	/**
	 * Beyond the ceiling no new term enters, a known one still counts up.
	 *
	 * @return void
	 */
	public function test_the_ceiling_stops_new_terms_only(): void {
		global $wpdb;
		$term_id = $this->handbook();
		$week    = SearchLog::week_of( self::WEDNESDAY );
		$table   = SearchLog::table();
		$values  = array();
		for ( $i = 0; $i < SearchLog::MAX_TERMS; $i++ ) {
			$values[] = $wpdb->prepare( '(%s, %d, %s, %s, 1)', $week, $term_id, 'site', 'term-' . $i );
		}
		$wpdb->query( "INSERT INTO {$table} (week, term_id, source, term, hits) VALUES " . implode( ',', $values ) ); // phpcs:ignore WordPress.DB -- filling the table for the test.

		$this->assertFalse( SearchLog::record( $term_id, 'one too many', SearchLog::SOURCE_SITE, self::WEDNESDAY ) );
		$this->assertTrue( SearchLog::record( $term_id, 'term-7', SearchLog::SOURCE_SITE, self::WEDNESDAY ) );
		// Another handbook has its own ceiling.
		$this->assertTrue( SearchLog::record( $this->handbook(), 'one too many', SearchLog::SOURCE_SITE, self::WEDNESDAY ) );
	}

	/**
	 * Weeks older than the retention period go, newer ones stay; the filter is
	 * held between 7 and 365 days.
	 *
	 * @return void
	 */
	public function test_old_weeks_are_deleted(): void {
		$term_id = $this->handbook();
		SearchLog::record( $term_id, 'alt', SearchLog::SOURCE_SITE, self::WEDNESDAY - 95 * DAY_IN_SECONDS );
		SearchLog::record( $term_id, 'neu', SearchLog::SOURCE_SITE, self::WEDNESDAY - 80 * DAY_IN_SECONDS );

		$this->assertSame( 1, SearchLog::cleanup( self::WEDNESDAY ) );
		$this->assertSame( array( 'neu' ), array_column( $this->rows(), 'term' ) );

		add_filter( 'living_handbook_search_log_retention_days', static fn() => 1 );
		$this->assertSame( 7, SearchLog::retention_days() );
		remove_all_filters( 'living_handbook_search_log_retention_days' );
		add_filter( 'living_handbook_search_log_retention_days', static fn() => 5000 );
		$this->assertSame( 365, SearchLog::retention_days() );
		remove_all_filters( 'living_handbook_search_log_retention_days' );
	}

	/**
	 * The daily cleanup is scheduled on activation and gone on deactivation.
	 *
	 * @return void
	 */
	public function test_the_cleanup_is_scheduled_and_unscheduled(): void {
		SearchLog::unschedule();
		SearchLog::schedule();
		$this->assertNotFalse( wp_next_scheduled( SearchLog::CRON_HOOK ) );

		\LivingHandbook\Plugin::deactivate();
		$this->assertFalse( wp_next_scheduled( SearchLog::CRON_HOOK ) );
	}

	/**
	 * The typed beginnings of a word are left out, weeks add up, the most
	 * frequent come first.
	 *
	 * @return void
	 */
	public function test_the_list_folds_typed_beginnings_and_sorts(): void {
		$term_id = $this->handbook();
		foreach ( array( 'mül', 'müll', 'mülle', 'müller' ) as $typed ) {
			SearchLog::record( $term_id, $typed, SearchLog::SOURCE_SITE, self::WEDNESDAY );
		}
		SearchLog::record( $term_id, 'müller', SearchLog::SOURCE_SITE, self::WEDNESDAY - 7 * DAY_IN_SECONDS );
		SearchLog::record( $term_id, 'ferien', SearchLog::SOURCE_SITE, self::WEDNESDAY );

		$list = SearchLog::most_frequent( array( $term_id ), 30, 10, '', self::WEDNESDAY );

		$this->assertSame( array( 'müller', 'ferien' ), array_column( $list, 'term' ) );
		$this->assertSame( 2, $list[0]['hits'] );
		$this->assertSame( 2, $list[0]['weeks'] );
		$this->assertSame( '2026-11-09', $list[0]['last_week'] );
	}

	/**
	 * Only the handbooks asked for are read, and the period is honoured.
	 *
	 * @return void
	 */
	public function test_the_list_keeps_to_its_handbooks_and_period(): void {
		$one = $this->handbook();
		$two = $this->handbook();
		SearchLog::record( $one, 'eins', SearchLog::SOURCE_SITE, self::WEDNESDAY );
		SearchLog::record( $two, 'zwei', SearchLog::SOURCE_SITE, self::WEDNESDAY );
		SearchLog::record( $one, 'alt', SearchLog::SOURCE_SITE, self::WEDNESDAY - 40 * DAY_IN_SECONDS );

		$this->assertSame( array( 'eins' ), array_column( SearchLog::most_frequent( array( $one ), 30, 10, '', self::WEDNESDAY ), 'term' ) );
		$this->assertSame( array(), SearchLog::most_frequent( array(), 30, 10, '', self::WEDNESDAY ) );
	}

	/**
	 * The quick search counts an empty answer, not a full one.
	 *
	 * @return void
	 */
	public function test_the_quick_search_counts_only_empty_answers(): void {
		$term_id = $this->handbook();
		$this->page( $term_id, 'Ferienregelung' );

		$this->search( $term_id, 'ferien' );
		$this->assertSame( array(), $this->rows() );

		$this->search( $term_id, 'Spesenabrechnung' );
		$this->assertSame( array( 'spesenabrechnung' ), array_column( $this->rows(), 'term' ) );
	}

	/**
	 * The entry page counts a plain search on page one, not one with a facet.
	 *
	 * @return void
	 */
	public function test_the_entry_list_ignores_searches_with_facets(): void {
		$term_id = $this->handbook();
		$term    = get_term( $term_id, Handbooks::TAXONOMY );
		$this->page( $term_id, 'Ferienregelung' );

		Filters::filtered_results( $term, array( 'lh_type' => array( 'howto' ) ), 'nichts da', 1 );
		Filters::filtered_results( $term, array(), 'nichts da', 2 );
		$this->assertSame( array(), $this->rows() );

		Filters::filtered_results( $term, array(), 'nichts da', 1 );
		$this->assertSame( array( 'nichts da' ), array_column( $this->rows(), 'term' ) );
	}

	/**
	 * Run the quick search route.
	 *
	 * @param int    $term_id Handbook.
	 * @param string $q       Search.
	 * @return void
	 */
	private function search( int $term_id, string $q ): void {
		$request = new WP_REST_Request( 'GET', '/' . Filters::REST_NAMESPACE . Filters::REST_ROUTE_SEARCH );
		$request->set_param( 'term_id', $term_id );
		$request->set_param( 'q', $q );
		rest_get_server()->dispatch( $request );
	}
}
