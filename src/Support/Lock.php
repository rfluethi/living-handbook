<?php
/**
 * A lock held in a row of the options table.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One run at a time, across requests, without anything but the database.
 *
 * Two things in the plugin must not run side by side: the version migrations
 * and the scheduled GitHub sync. Both used to lock with add_option() and
 * delete_option(), which has two holes. add_option() writes with "ON DUPLICATE
 * KEY UPDATE", so when the object cache says the row is missing (a stale
 * notoptions entry) the second run overwrites the first one's lock and goes
 * ahead. And delete_option() deletes whoever's lock is there: a run that took
 * over an abandoned lock lost it the moment the old run, which was only slow,
 * reached its own release, and a third run came in beside it.
 *
 * Here each holder writes its own token, "<time>:<random>", the lock is taken
 * with INSERT IGNORE (the one atomic step the options table offers, and the way
 * core's upgrader locks too), a stale lock is taken over with an UPDATE that
 * names the value it found, and a holder releases only a row that still
 * carries its own token.
 */
final class Lock {

	/**
	 * Takes the lock, or learns that someone else holds it.
	 *
	 * @param string $name Option name of the lock.
	 * @param int    $ttl  Age in seconds from which a lock counts as abandoned and is taken over.
	 * @return string The token to release the lock with, or '' when it is held by someone else.
	 */
	public static function acquire( string $name, int $ttl ): string {
		global $wpdb;

		$token = time() . ':' . wp_generate_password( 12, false );

		// Twice at most: once more when the holder released the row between
		// the insert that found it and the read that wanted its age.
		for ( $attempt = 0; $attempt < 2; $attempt++ ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- an atomic insert is the point; the cache is cleared below.
			$taken = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `{$wpdb->options}` ( `option_name`, `option_value`, `autoload` ) VALUES ( %s, %s, 'off' )", $name, $token ) );
			self::forget_cached( $name );
			if ( 1 === (int) $taken ) {
				return $token;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the value another process just wrote, not a cached one.
			$held = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM `{$wpdb->options}` WHERE option_name = %s", $name ) );
			if ( null === $held ) {
				continue;
			}
			$held = (string) $held;
			if ( self::taken_at( $held ) > time() - $ttl ) {
				return '';
			}

			// Abandoned. Take it over only if nobody else did in the meantime: the
			// update names the value it found, so of two runs doing this, one wins.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the same atomic step, for a stale lock.
			$taken = $wpdb->query( $wpdb->prepare( "UPDATE `{$wpdb->options}` SET option_value = %s WHERE option_name = %s AND option_value = %s", $token, $name, $held ) );
			self::forget_cached( $name );
			return 1 === (int) $taken ? $token : '';
		}
		return '';
	}

	/**
	 * Releases the lock, if it is still the caller's.
	 *
	 * A run that took longer than the TTL has had its lock taken over; deleting
	 * the row then would hand the new holder's lock to whoever comes next.
	 *
	 * @param string $name  Option name of the lock.
	 * @param string $token The token acquire() returned.
	 * @return void
	 */
	public static function release( string $name, string $token ): void {
		global $wpdb;

		if ( '' === $token ) {
			return;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- deletes only the caller's own row; the cache is cleared below.
		$wpdb->query( $wpdb->prepare( "DELETE FROM `{$wpdb->options}` WHERE option_name = %s AND option_value = %s", $name, $token ) );
		self::forget_cached( $name );
	}

	/**
	 * Returns when a lock value was taken.
	 *
	 * Reads the token of this class and the bare timestamp that the locks up to
	 * 0.75.x stored, so a lock left by an older version still ages out.
	 *
	 * @param string $value The stored lock value.
	 * @return int Unix time, 0 when the value carries none.
	 */
	public static function taken_at( string $value ): int {
		$time = strstr( $value, ':', true );
		$time = false === $time ? $value : $time;
		return ctype_digit( $time ) ? (int) $time : 0;
	}

	/**
	 * Drop what the object cache knows about the lock row, so get_option()
	 * and add_option() elsewhere see the table as it is.
	 *
	 * @param string $name Option name of the lock.
	 * @return void
	 */
	private static function forget_cached( string $name ): void {
		wp_cache_delete( $name, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
	}
}
