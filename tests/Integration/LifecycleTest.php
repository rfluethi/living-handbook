<?php
/**
 * Activation, update and deactivation: what happens between the plugin's
 * files changing and the site working with them.
 *
 * Three things went wrong at these seams.
 *
 * - Deactivating flushed the rewrite rules while the plugin's post types were
 *   still registered, so the rebuild put the handbook's rules straight back
 *   and /handbook/... kept resolving to a type nothing served any more.
 * - The migrations after an update ran on admin_init only. After an update
 *   from WP-CLI or a background auto-update, the front end and cron worked
 *   against unmigrated data until an administrator opened the dashboard.
 * - Network activation on a multisite ran the activation once, on the main
 *   site, and marked the plugin active everywhere: no other site got its
 *   terms, its overview page or its rewrite rules.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Plugin;
use LivingHandbook\Setup\Onboarding;
use WP_UnitTestCase;
use WPDieException;

/**
 * Plugin::activate(), maybe_upgrade() and deactivate().
 */
final class LifecycleTest extends WP_UnitTestCase {

	/**
	 * Pretty permalinks, so there are rewrite rules to look at.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->set_permalink_structure( '/%postname%/' );
		// set_permalink_structure() re-initialises WP_Rewrite, which drops the
		// permastructs the post types added at init. Register the handbook type
		// again, the way a fresh request would, so its rules exist.
		( new \LivingHandbook\PostType\Handbook() )->register_post_type();
		delete_option( Plugin::UPGRADE_LOCK );
	}

	/**
	 * Leave the stored version as the code has it, so other tests see a
	 * migrated site.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		update_option( Plugin::DB_VERSION_OPTION, LIVING_HANDBOOK_VERSION );
		delete_option( Plugin::UPGRADE_LOCK );
		parent::tear_down();
	}

	/**
	 * Whether the stored rewrite rules know the handbook's address.
	 *
	 * @return bool
	 */
	private function stored_rules_know_the_handbook(): bool {
		$rules = get_option( 'rewrite_rules' );
		if ( ! is_array( $rules ) ) {
			return false;
		}
		foreach ( array_keys( $rules ) as $pattern ) {
			if ( str_starts_with( (string) $pattern, 'handbook/' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * After deactivation the stored rules no longer route the plugin's
	 * addresses. They are rebuilt on the next request, which no longer loads it.
	 *
	 * @return void
	 */
	public function test_deactivation_does_not_rebuild_the_plugins_own_rules(): void {
		flush_rewrite_rules( false );
		$this->assertTrue( $this->stored_rules_know_the_handbook(), 'Precondition: the active plugin has its rules.' );

		Plugin::deactivate();

		$this->assertFalse( $this->stored_rules_know_the_handbook(), 'The rules are still built from the registered handbook type.' );
	}

	/**
	 * The migrations are reached on every kind of request, not only in the
	 * dashboard: wp_loaded fires for the front end, REST, cron and WP-CLI.
	 *
	 * @return void
	 */
	public function test_the_upgrade_runs_outside_the_dashboard_too(): void {
		$this->assertNotFalse(
			has_action( 'wp_loaded', array( Plugin::instance(), 'maybe_upgrade' ) ),
			'maybe_upgrade() is only reached from admin_init.'
		);

		update_option( Plugin::DB_VERSION_OPTION, '0.1.0' );
		do_action( 'wp_loaded' );

		$this->assertSame( LIVING_HANDBOOK_VERSION, get_option( Plugin::DB_VERSION_OPTION ) );
	}

	/**
	 * The rebuild in the upgrade happens after the registrations, so it knows
	 * the handbook's address.
	 *
	 * @return void
	 */
	public function test_the_upgrade_builds_the_rules_from_the_registered_types(): void {
		delete_option( 'rewrite_rules' );
		update_option( Plugin::DB_VERSION_OPTION, '0.1.0' );

		Plugin::instance()->maybe_upgrade();

		$this->assertTrue( $this->stored_rules_know_the_handbook() );
	}

	/**
	 * A second request that arrives while the first is migrating leaves it to
	 * the first.
	 *
	 * @return void
	 */
	public function test_a_running_upgrade_is_not_started_twice(): void {
		update_option( Plugin::DB_VERSION_OPTION, '0.1.0' );
		add_option( Plugin::UPGRADE_LOCK, time(), '', false );

		Plugin::instance()->maybe_upgrade();

		$this->assertSame( '0.1.0', get_option( Plugin::DB_VERSION_OPTION ), 'The migration ran past a fresh lock.' );
	}

	/**
	 * A running upgrade keeps its lock also when the object cache lists the
	 * lock row as missing. add_option(), the lock up to 0.76.0, trusted that
	 * entry and wrote over the row with "ON DUPLICATE KEY UPDATE".
	 *
	 * @return void
	 */
	public function test_a_lock_the_cache_misses_still_holds(): void {
		global $wpdb;

		update_option( Plugin::DB_VERSION_OPTION, '0.1.0' );
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the row another request wrote, past this request's cache.
			$wpdb->options,
			array(
				'option_name'  => Plugin::UPGRADE_LOCK,
				'option_value' => time() . ':first-request',
				'autoload'     => 'off',
			)
		);
		wp_cache_delete( Plugin::UPGRADE_LOCK, 'options' );
		wp_cache_set( 'notoptions', array( Plugin::UPGRADE_LOCK => true ), 'options' );

		Plugin::instance()->maybe_upgrade();

		$this->assertSame( '0.1.0', get_option( Plugin::DB_VERSION_OPTION ), 'The migration ran past a held lock.' );
	}

	/**
	 * A lock left behind by a request that died does not block the upgrade
	 * for good, and a finished upgrade releases its lock.
	 *
	 * @return void
	 */
	public function test_a_stale_lock_is_taken_over_and_released(): void {
		update_option( Plugin::DB_VERSION_OPTION, '0.1.0' );
		add_option( Plugin::UPGRADE_LOCK, time() - HOUR_IN_SECONDS, '', false );

		Plugin::instance()->maybe_upgrade();

		$this->assertSame( LIVING_HANDBOOK_VERSION, get_option( Plugin::DB_VERSION_OPTION ) );
		$this->assertFalse( get_option( Plugin::UPGRADE_LOCK ) );
	}

	/**
	 * A network activation stops before it changes anything.
	 *
	 * @return void
	 */
	public function test_network_activation_is_refused(): void {
		delete_option( Onboarding::OPTION_OVERVIEW_PAGE );

		try {
			Plugin::activate( true );
			$this->fail( 'A network activation went through.' );
		} catch ( WPDieException $e ) {
			$this->assertStringContainsString( 'each site', $e->getMessage() );
		}

		$this->assertFalse( get_option( Onboarding::OPTION_OVERVIEW_PAGE ), 'Nothing was set up before the refusal.' );
	}

	/**
	 * Activating on a single site works as before.
	 *
	 * @return void
	 */
	public function test_single_site_activation_still_works(): void {
		delete_option( Plugin::DB_VERSION_OPTION );

		Plugin::activate( false );

		$this->assertSame( LIVING_HANDBOOK_VERSION, get_option( Plugin::DB_VERSION_OPTION ) );
	}
}
