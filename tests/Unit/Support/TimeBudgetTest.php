<?php
/**
 * The rules shared by the jobs that run in passes.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Unit\Support;

use LivingHandbook\Support\TimeBudget;
use PHPUnit\Framework\TestCase;

/**
 * Support\TimeBudget.
 */
final class TimeBudgetTest extends TestCase {

	/**
	 * Without a limit, or under a generous one, the job's own ceiling applies.
	 *
	 * @return void
	 */
	public function test_the_ceiling_applies_when_php_allows_more(): void {
		$this->assertSame( 20.0, TimeBudget::seconds( 20.0, 0.0 ) );
		$this->assertSame( 15.0, TimeBudget::seconds( 15.0, 300.0 ) );
	}

	/**
	 * Under a tight limit a pass takes sixty per cent of it.
	 *
	 * @return void
	 */
	public function test_a_tight_limit_leaves_room_to_answer(): void {
		$this->assertEqualsWithDelta( 12.0, TimeBudget::seconds( 20.0, 20.0 ), 0.001 );
	}

	/**
	 * However tight the limit, a pass gets five seconds.
	 *
	 * @return void
	 */
	public function test_a_pass_gets_a_minimum(): void {
		$this->assertSame( 5.0, TimeBudget::seconds( 20.0, 2.0 ) );
	}

	/**
	 * A job id keeps letters and digits, in their case, and nothing else.
	 *
	 * @return void
	 */
	public function test_a_job_id_keeps_its_case_and_loses_the_rest(): void {
		$this->assertSame( 'AbC123', TimeBudget::job_id( 'AbC123' ) );
		$this->assertSame( 'abcXYZ', TimeBudget::job_id( '../abc/XYZ;' ) );
		$this->assertSame( '', TimeBudget::job_id( '-_/' ) );
	}
}
