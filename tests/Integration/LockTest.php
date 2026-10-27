<?php
/**
 * The options-table lock behind the upgrade and the scheduled sync.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Support\Lock;
use WP_UnitTestCase;

/**
 * Support\Lock: one holder at a time, takeover of an abandoned lock, and a
 * release that only ever frees the caller's own lock.
 */
final class LockTest extends WP_UnitTestCase {

	private const NAME = 'living_handbook_test_lock';

	/**
	 * Leave no lock row behind.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		delete_option( self::NAME );
		parent::tear_down();
	}

	/**
	 * Move the stored lock's time an hour back, as if its holder had died.
	 *
	 * @return void
	 */
	private function age_the_lock(): void {
		global $wpdb;
		$value = (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::NAME ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the stored row is what is being tested.
		$aged  = (string) preg_replace( '/^\d+/', (string) ( time() - HOUR_IN_SECONDS ), $value );
		$wpdb->update( $wpdb->options, array( 'option_value' => $aged ), array( 'option_name' => self::NAME ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- stands in for the passing of time.
	}

	/**
	 * A holds the lock, it ages out, B takes it over. When A, which was only
	 * slow, finishes and releases, B still holds the lock and C does not get it.
	 *
	 * @return void
	 */
	public function test_a_late_release_does_not_free_a_lock_taken_over(): void {
		$a = Lock::acquire( self::NAME, MINUTE_IN_SECONDS );
		$this->assertNotSame( '', $a );
		$this->assertSame( '', Lock::acquire( self::NAME, MINUTE_IN_SECONDS ), 'A fresh lock is not handed out twice.' );

		$this->age_the_lock();
		$b = Lock::acquire( self::NAME, MINUTE_IN_SECONDS );
		$this->assertNotSame( '', $b, 'An abandoned lock is taken over.' );
		$this->assertNotSame( $a, $b );

		Lock::release( self::NAME, $a );
		$this->assertSame( $b, get_option( self::NAME ), 'B still holds the lock after A let go.' );
		$this->assertSame( '', Lock::acquire( self::NAME, MINUTE_IN_SECONDS ), 'C does not get in beside B.' );

		Lock::release( self::NAME, $b );
		$this->assertFalse( get_option( self::NAME ), 'B frees its own lock.' );
	}

	/**
	 * A row the object cache wrongly lists as missing still holds the lock.
	 *
	 * @return void
	 */
	public function test_a_stale_notoptions_entry_does_not_open_the_lock(): void {
		$this->assertNotSame( '', Lock::acquire( self::NAME, MINUTE_IN_SECONDS ) );
		wp_cache_set( 'notoptions', array( self::NAME => true ), 'options' );

		$this->assertSame( '', Lock::acquire( self::NAME, MINUTE_IN_SECONDS ) );
	}

	/**
	 * The bare timestamp that the locks up to 0.75.x stored still ages out.
	 *
	 * @return void
	 */
	public function test_a_lock_of_the_old_format_is_read(): void {
		$this->assertSame( 1700000000, Lock::taken_at( '1700000000' ) );
		$this->assertSame( 1700000000, Lock::taken_at( '1700000000:abcdefabcdef' ) );
		$this->assertSame( 0, Lock::taken_at( 'garbage' ) );

		add_option( self::NAME, (string) time(), '', false );
		$this->assertSame( '', Lock::acquire( self::NAME, MINUTE_IN_SECONDS ), 'A fresh lock of the old format holds.' );
		update_option( self::NAME, (string) ( time() - HOUR_IN_SECONDS ), false );
		$this->assertNotSame( '', Lock::acquire( self::NAME, MINUTE_IN_SECONDS ), 'An old one is taken over.' );
	}
}
