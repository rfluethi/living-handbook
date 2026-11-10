<?php
/**
 * Living Handbook on the sites of a network (E-0085).
 *
 * Runs only in the multisite run of the suite (WP_MULTISITE=1).
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Access\AccessController;
use LivingHandbook\Files\ServerRules;
use LivingHandbook\Git\GitSync;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\Plugin;
use LivingHandbook\PostType\Handbook;
use LivingHandbook\Search\SearchLog;
use WP_UnitTestCase;

/**
 * Who is a member, what the uninstall cleans, what a deleted site leaves,
 * and how the protected folder answers on a site in a subdirectory.
 */
final class MultisiteTest extends WP_UnitTestCase {

	/**
	 * Only in a network.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Runs in the multisite run of the suite (WP_MULTISITE=1).' );
		}
	}

	/**
	 * A table of failed searches whose site no longer exists.
	 */
	private const ORPHAN_SITE = 99999;

	/**
	 * The test framework turns CREATE and DROP TABLE into temporary tables
	 * inside a test, which SHOW TABLES does not list, and a real one would
	 * commit the test's transaction. So the two tables the orphan cleanup
	 * looks for are created for real before the class, outside any test: one
	 * of a deleted site, one with the number of a site that exists.
	 *
	 * @return void
	 */
	public static function wpSetUpBeforeClass(): void {
		global $wpdb;
		if ( ! is_multisite() ) {
			return;
		}
		foreach ( array( self::ORPHAN_SITE, 1 ) as $site ) {
			$wpdb->query( $wpdb->prepare( 'CREATE TABLE IF NOT EXISTS %i ( id bigint(20) unsigned NOT NULL AUTO_INCREMENT, PRIMARY KEY (id) )', self::orphan( $site ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- test.
		}
	}

	/**
	 * Drop them again.
	 *
	 * @return void
	 */
	public static function wpTearDownAfterClass(): void {
		global $wpdb;
		if ( ! is_multisite() ) {
			return;
		}
		foreach ( array( self::ORPHAN_SITE, 1 ) as $site ) {
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', self::orphan( $site ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- test.
		}
	}

	/**
	 * The name the table of a site has.
	 *
	 * @param int $site Site.
	 * @return string
	 */
	private static function orphan( int $site ): string {
		global $wpdb;
		return $wpdb->base_prefix . $site . '_' . SearchLog::TABLE;
	}

	/**
	 * Run a callback and return the tables it dropped. The statement is
	 * caught on its way, since the dropped tables are temporary ones.
	 *
	 * @param callable $run Callback.
	 * @return string[]
	 */
	private function dropped( callable $run ): array {
		$dropped = array();
		$catch   = static function ( string $query ) use ( &$dropped ): string {
			if ( 1 === preg_match( '/^\s*DROP\s+(?:TEMPORARY\s+)?TABLE\s+(?:IF\s+EXISTS\s+)?`?([A-Za-z0-9_]+)`?/i', $query, $match ) ) {
				$dropped[] = $match[1];
			}
			return $query;
		};
		add_filter( 'query', $catch, 1 );
		$run();
		remove_filter( 'query', $catch, 1 );
		return $dropped;
	}

	/**
	 * A handbook of the current site.
	 *
	 * @param string $visibility Visibility.
	 * @return int
	 */
	private function handbook( string $visibility ): int {
		$term_id = (int) self::factory()->term->create( array( 'taxonomy' => Handbooks::TAXONOMY ) );
		update_term_meta( $term_id, Handbooks::META_VISIBILITY, $visibility );
		return $term_id;
	}

	/**
	 * Signed in is not a member: an account on another site of the network
	 * opens no handbook here, an account on this site does, a super admin
	 * always does.
	 *
	 * @return void
	 */
	public function test_a_member_has_an_account_on_the_site(): void {
		$site     = (int) self::factory()->blog->create();
		$stranger = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$member   = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$super    = (int) self::factory()->user->create();
		grant_super_admin( $super );

		switch_to_blog( $site );
		add_user_to_blog( $site, $member, 'subscriber' );
		$members    = $this->handbook( Handbooks::VISIBILITY_MEMBERS );
		$restricted = $this->handbook( Handbooks::VISIBILITY_RESTRICTED );
		update_term_meta( $restricted, Handbooks::META_USERS, array( $stranger, $member ) );
		$page = (int) self::factory()->post->create(
			array(
				'post_type'   => Handbook::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		wp_set_object_terms( $page, array( $members ), Handbooks::TAXONOMY );

		$this->assertTrue( is_user_member_of_blog( $stranger, 1 ), 'The stranger has an account on the main site.' );
		$this->assertFalse( AccessController::can_view_term( $members, $stranger ), 'Signed in on the network, but no account here.' );
		$this->assertFalse( AccessController::can_view_term( $restricted, $stranger ), 'A name on the list without an account here.' );
		$this->assertFalse( AccessController::can_view_post( $page, $stranger ) );
		$this->assertTrue( AccessController::can_view_term( $members, $member ) );
		$this->assertTrue( AccessController::can_view_term( $restricted, $member ) );
		$this->assertTrue( AccessController::can_view_post( $page, $member ) );
		$this->assertTrue( AccessController::can_view_term( $members, $super ) );
		restore_current_blog();
	}

	/**
	 * On a site in a subdirectory the rule sends the browser to the site's
	 * own index.php, and each site has its own internal nginx location; the
	 * main site keeps the internal rewrite.
	 *
	 * @return void
	 */
	public function test_the_rule_of_a_site_in_a_subdirectory(): void {
		if ( is_subdomain_install() ) {
			$this->markTestSkipped( 'The test network uses subdomains.' );
		}
		$this->assertFalse( ServerRules::redirects() );
		$this->assertStringContainsString( '[L,QSA,B]', ServerRules::htaccess() );
		$this->assertSame( '/lh-protected-internal/', ServerRules::internal_path() );

		$site = (int) self::factory()->blog->create( array( 'path' => '/team-b/' ) );
		switch_to_blog( $site );
		$this->assertTrue( ServerRules::redirects() );
		$this->assertStringContainsString( 'RewriteRule ^(.*)$ /team-b/index.php?lh_file=$1 [R=302,NE,L,QSA,B]', ServerRules::htaccess() );
		$this->assertSame( '/lh-protected-internal-' . $site . '/', ServerRules::internal_path() );
		$nginx = ServerRules::nginx();
		$this->assertStringContainsString( 'location ^~ /wp-content/uploads/sites/' . $site . '/living-handbook-protected/ {', $nginx, 'The location guards the folder below the root, not the address with /team-b/.' );
		$this->assertStringContainsString( '/team-b/index.php?lh_file=$1 redirect;', $nginx );
		$this->assertStringContainsString( 'location /lh-protected-internal-' . $site . '/ {', $nginx );

		// Loaded on the site itself, WordPress gives the files an address
		// with the site's path, as a live network does.
		$with_path = static function ( array $dir ): array {
			$dir['baseurl'] = str_replace( '/wp-content/', '/team-b/wp-content/', (string) $dir['baseurl'] );
			return $dir;
		};
		add_filter( 'upload_dir', $with_path );
		$nginx = ServerRules::nginx();
		remove_filter( 'upload_dir', $with_path );
		$this->assertStringContainsString( 'location ^~ /wp-content/uploads/sites/' . $site . '/living-handbook-protected/ {', $nginx );
		$this->assertStringNotContainsString( '/team-b/wp-content/', $nginx );
		restore_current_blog();
	}

	/**
	 * A deleted site takes its table of failed searches along.
	 *
	 * @return void
	 */
	public function test_a_deleted_site_drops_its_table(): void {
		$site = (int) self::factory()->blog->create();
		switch_to_blog( $site );
		$table = SearchLog::table();
		restore_current_blog();
		$this->assertSame( self::orphan( $site ), $table );
		$this->assertContains( $table, apply_filters( 'wpmu_drop_tables', array(), $site ) );

		$dropped = $this->dropped(
			static function () use ( $site ): void {
				wp_delete_site( $site );
			}
		);
		$this->assertContains( $table, $dropped );
	}

	/**
	 * The uninstall cleans every site that ran the plugin, each by its own
	 * choice, leaves a site that never did alone, and drops the table of a
	 * deleted site, but not one whose site still exists.
	 *
	 * @return void
	 */
	public function test_the_uninstall_cleans_every_site(): void {
		$keep   = (int) self::factory()->blog->create();
		$remove = (int) self::factory()->blog->create();
		$never  = (int) self::factory()->blog->create();

		$pages = array();
		foreach ( array( $keep, $remove ) as $site ) {
			switch_to_blog( $site );
			update_option( Plugin::DB_VERSION_OPTION, LIVING_HANDBOOK_VERSION );
			update_option( GitSync::OPTION_SCHEDULE, 'daily' );
			$term           = $this->handbook( Handbooks::VISIBILITY_PUBLIC );
			$pages[ $site ] = (int) self::factory()->post->create( array( 'post_type' => Handbook::POST_TYPE ) );
			wp_set_object_terms( $pages[ $site ], array( $term ), Handbooks::TAXONOMY );
			restore_current_blog();
		}
		switch_to_blog( $remove );
		update_option( GitSync::OPTION_UNINSTALL, true );
		restore_current_blog();

		switch_to_blog( $never );
		update_option( GitSync::OPTION_SCHEDULE, 'not ours to touch' );
		restore_current_blog();

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'living-handbook/living-handbook.php' );
		}
		$dropped = $this->dropped(
			static function (): void {
				if ( function_exists( 'living_handbook_run_uninstall' ) ) {
					living_handbook_run_uninstall();
					return;
				}
				require_once dirname( __DIR__, 2 ) . '/uninstall.php';
			}
		);

		foreach ( array( $keep, $remove ) as $site ) {
			switch_to_blog( $site );
			$this->assertFalse( get_option( Plugin::DB_VERSION_OPTION ), 'The options go on site ' . $site . '.' );
			$this->assertFalse( get_option( GitSync::OPTION_SCHEDULE ) );
			restore_current_blog();
			$this->assertContains( self::orphan( $site ), $dropped, 'The table goes on site ' . $site . '.' );
		}
		switch_to_blog( $keep );
		$this->assertSame( Handbook::POST_TYPE, get_post_type( $pages[ $keep ] ), 'Without "remove everything" the content stays.' );
		restore_current_blog();
		switch_to_blog( $remove );
		$this->assertNull( get_post( $pages[ $remove ] ), 'With "remove everything" the content goes.' );
		restore_current_blog();

		switch_to_blog( $never );
		$this->assertSame( 'not ours to touch', get_option( GitSync::OPTION_SCHEDULE ), 'A site that never ran the plugin is left alone.' );
		restore_current_blog();
		$this->assertNotContains( self::orphan( $never ), $dropped );

		$this->assertContains( self::orphan( self::ORPHAN_SITE ), $dropped, 'The table of a deleted site goes.' );
		$this->assertNotContains( self::orphan( 1 ), $dropped, 'A table whose site exists is not an orphan.' );
	}
}
