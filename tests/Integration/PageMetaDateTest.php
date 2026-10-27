<?php
/**
 * The dates in the metadata footer are the days that were stored.
 *
 * A review date is a calendar day in the site's time zone. Printing it must
 * not depend on PHP's default time zone, which WordPress sets to UTC but which
 * a plugin or the server can change.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Frontend\PageMeta;
use LivingHandbook\Meta\Metadata;
use LivingHandbook\PostType\Handbook;
use WP_UnitTestCase;

/**
 * PageMeta::render_meta, its dates.
 */
final class PageMetaDateTest extends WP_UnitTestCase {

	/**
	 * PHP's default zone before the test.
	 *
	 * @var string
	 */
	private string $php_zone = 'UTC';

	/**
	 * Remembers PHP's default zone.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->php_zone = date_default_timezone_get();
	}

	/**
	 * Restores PHP's default zone.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		date_default_timezone_set( $this->php_zone ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.timezone_change_date_default_timezone_set -- Restores what the test changed.
		parent::tear_down();
	}

	/**
	 * The stored review day is printed as that day, whatever PHP's default
	 * zone is. Up to 0.75.1 a site with PHP set east of UTC showed the day
	 * before.
	 *
	 * @return void
	 */
	public function test_the_review_date_is_the_stored_day(): void {
		update_option( 'timezone_string', 'Europe/Zurich' );
		update_option( 'date_format', 'Y-m-d' );
		$page = (int) self::factory()->post->create(
			array(
				'post_type'   => Handbook::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		update_post_meta( $page, Metadata::REVIEWED, '2026-03-04' );

		date_default_timezone_set( 'Europe/Zurich' ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.timezone_change_date_default_timezone_set -- The case under test: a plugin or server that changed it.
		$out = PageMeta::render_meta( $page, false );

		$this->assertStringContainsString( '2026-03-04', $out );
		$this->assertStringNotContainsString( '2026-03-03', $out );
	}
}
