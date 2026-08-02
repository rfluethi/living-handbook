<?php
/**
 * The freshness rule.
 *
 * Freshness tracking is the reason this plugin exists: a handbook that nobody
 * revisits is the failure mode it is built against. The rule that decides
 * whether a page counts as reviewed, due or overdue is therefore worth pinning
 * exactly, boundaries included, and it needs neither WordPress nor the clock.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Unit\Frontend;

use LivingHandbook\Frontend\FreshnessStatus;
use PHPUnit\Framework\TestCase;

/**
 * Reviewed, due, overdue, or nothing to say.
 */
final class FreshnessStatusTest extends TestCase {

	/**
	 * A moment relative to a fixed review date, in days.
	 *
	 * @param string $reviewed Review date.
	 * @param int    $days     Days after that date.
	 * @return int Unix timestamp.
	 */
	private function days_after( string $reviewed, int $days ): int {
		return (int) strtotime( $reviewed . ' +' . $days . ' days' );
	}

	/**
	 * Inside the interval a page is simply reviewed.
	 *
	 * @return void
	 */
	public function test_a_page_within_its_interval_is_reviewed(): void {
		$this->assertSame(
			FreshnessStatus::OK,
			FreshnessStatus::status( '2026-01-01', 90, $this->days_after( '2026-01-01', 30 ) )
		);
	}

	/**
	 * Past the interval it is due, past twice the interval it is overdue. The
	 * second step is what the dashboard escalates on.
	 *
	 * @return void
	 */
	public function test_a_page_becomes_due_and_then_overdue(): void {
		$this->assertSame(
			FreshnessStatus::DUE,
			FreshnessStatus::status( '2026-01-01', 90, $this->days_after( '2026-01-01', 100 ) )
		);
		$this->assertSame(
			FreshnessStatus::OVERDUE,
			FreshnessStatus::status( '2026-01-01', 90, $this->days_after( '2026-01-01', 200 ) )
		);
	}

	/**
	 * The boundaries: on the due day itself the page is still reviewed, a second
	 * later it is due. Same for the escalation. Without this, an off-by-one day
	 * in either direction would go unnoticed.
	 *
	 * @return void
	 */
	public function test_the_boundaries_are_where_they_are_meant_to_be(): void {
		$due = $this->days_after( '2026-01-01', 90 );
		$this->assertSame( FreshnessStatus::OK, FreshnessStatus::status( '2026-01-01', 90, $due ), 'On the due date it has not passed yet.' );
		$this->assertSame( FreshnessStatus::DUE, FreshnessStatus::status( '2026-01-01', 90, $due + 1 ) );

		$escalated = $this->days_after( '2026-01-01', 180 );
		$this->assertSame( FreshnessStatus::DUE, FreshnessStatus::status( '2026-01-01', 90, $escalated ) );
		$this->assertSame( FreshnessStatus::OVERDUE, FreshnessStatus::status( '2026-01-01', 90, $escalated + 1 ) );
	}

	/**
	 * Without a review date or without an interval there is nothing to say, and
	 * saying nothing is not the same as saying "fine".
	 *
	 * @return void
	 */
	public function test_missing_data_gives_no_status(): void {
		$now = $this->days_after( '2026-01-01', 400 );

		$this->assertSame( FreshnessStatus::NONE, FreshnessStatus::status( '', 90, $now ), 'No review date.' );
		$this->assertSame( FreshnessStatus::NONE, FreshnessStatus::status( '2026-01-01', 0, $now ), 'No interval.' );
		$this->assertSame( FreshnessStatus::NONE, FreshnessStatus::status( '2026-01-01', -30, $now ), 'A negative interval is not an interval.' );
	}

	/**
	 * A date the parser cannot read is treated as no date, not as a page that is
	 * fine and not as one that is centuries overdue.
	 *
	 * @return void
	 */
	public function test_an_unreadable_date_gives_no_status(): void {
		$this->assertSame(
			FreshnessStatus::NONE,
			FreshnessStatus::status( 'irgendwann', 90, $this->days_after( '2026-01-01', 400 ) )
		);
	}

	/**
	 * A review date in the future is not overdue, whatever else one might think
	 * of it.
	 *
	 * @return void
	 */
	public function test_a_future_review_date_is_not_overdue(): void {
		$this->assertSame(
			FreshnessStatus::OK,
			FreshnessStatus::status( '2027-01-01', 30, (int) strtotime( '2026-06-01' ) )
		);
	}

	/**
	 * A one day interval still walks through all three states, so the rule does
	 * not quietly need a wide interval to work.
	 *
	 * @return void
	 */
	public function test_a_one_day_interval_works_too(): void {
		$this->assertSame( FreshnessStatus::OK, FreshnessStatus::status( '2026-01-01', 1, $this->days_after( '2026-01-01', 1 ) ) );
		$this->assertSame( FreshnessStatus::DUE, FreshnessStatus::status( '2026-01-01', 1, $this->days_after( '2026-01-01', 1 ) + 1 ) );
		$this->assertSame( FreshnessStatus::OVERDUE, FreshnessStatus::status( '2026-01-01', 1, $this->days_after( '2026-01-01', 2 ) + 1 ) );
	}
}
