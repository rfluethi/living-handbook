<?php
/**
 * What deleting the plugin does, and above all what it does not do.
 *
 * uninstall.php is the most destructive file in the plugin: it can delete every
 * handbook page, every handbook and every term. It runs once, unattended, at a
 * moment when nobody is looking, and a mistake in it is not recoverable from
 * inside WordPress. So the guarantees are pinned here: content survives unless
 * it was explicitly opted out of, the opt-in removes what it promises and
 * nothing else, and the cache cleanup keeps its hands off other plugins' rows.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Git\GitSync;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use LivingHandbook\Setup\Onboarding;
use LivingHandbook\Setup\Settings;
use LivingHandbook\Taxonomy\Taxonomies;
use WP_UnitTestCase;

/**
 * The uninstall cleanup.
 */
final class UninstallTest extends WP_UnitTestCase {

	/**
	 * Remove the opt-in and any filter between tests.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		delete_option( GitSync::OPTION_UNINSTALL );
		remove_all_filters( 'living_handbook_uninstall_remove_content' );
		parent::tear_down();
	}

	/**
	 * Run uninstall.php the way WordPress runs it.
	 *
	 * The file calls its function on include, so the first run happens there and
	 * every later one calls the function directly. Both are the same code path.
	 *
	 * @return void
	 */
	private function uninstall(): void {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'living-handbook/living-handbook.php' );
		}

		if ( ! function_exists( 'living_handbook_run_uninstall' ) ) {
			require_once dirname( __DIR__, 2 ) . '/uninstall.php';
			return;
		}

		living_handbook_run_uninstall();
	}

	/**
	 * A handbook with one page, a term in every seeded taxonomy, and a page of
	 * the site that has nothing to do with the plugin.
	 *
	 * @return array<string, int>
	 */
	private function make_content(): array {
		$handbook = wp_insert_term( 'Team handbook', Handbooks::TAXONOMY );
		$this->assertIsArray( $handbook );

		$page = self::factory()->post->create( array( 'post_type' => Handbook::POST_TYPE ) );
		wp_set_object_terms( $page, array( (int) $handbook['term_id'] ), Handbooks::TAXONOMY );

		$page_type = wp_insert_term( 'How-to', Taxonomies::PAGE_TYPE );
		$this->assertIsArray( $page_type );

		// Not the plugin's: an ordinary page and a post of the site.
		$foreign_page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$foreign_post = self::factory()->post->create();

		return array(
			'handbook'     => (int) $handbook['term_id'],
			'page'         => $page,
			'page_type'    => (int) $page_type['term_id'],
			'foreign_page' => $foreign_page,
			'foreign_post' => $foreign_post,
		);
	}

	/**
	 * By default the content stays. Only the plugin's own settings and its
	 * schedule go.
	 *
	 * @return void
	 */
	public function test_content_survives_an_ordinary_uninstall(): void {
		$content = $this->make_content();
		update_option( Settings::OPTION_CUSTOM_CSS, '.x { color: red }' );
		update_option( GitSync::OPTION_SCHEDULE, 'daily' );

		$this->uninstall();

		$this->assertSame( Handbook::POST_TYPE, get_post_type( $content['page'] ), 'A handbook page must not be deleted by default.' );
		$this->assertInstanceOf( \WP_Term::class, get_term( $content['handbook'], Handbooks::TAXONOMY ), 'The handbook itself must survive.' );
		$this->assertInstanceOf( \WP_Term::class, get_term( $content['page_type'], Taxonomies::PAGE_TYPE ) );

		$this->assertFalse( get_option( Settings::OPTION_CUSTOM_CSS ), 'The plugin settings are what an uninstall is for.' );
		$this->assertFalse( get_option( GitSync::OPTION_SCHEDULE ) );
	}

	/**
	 * With the option on, the handbook content goes: pages, handbooks, and the
	 * terms of the seeded taxonomies.
	 *
	 * @return void
	 */
	public function test_the_opt_in_removes_the_handbook_content(): void {
		$content = $this->make_content();
		update_option( GitSync::OPTION_UNINSTALL, true );

		$this->uninstall();

		$this->assertNull( get_post( $content['page'] ), 'The handbook page should be gone.' );
		$this->assertNull( get_term( $content['handbook'], Handbooks::TAXONOMY ) );
		$this->assertNull( get_term( $content['page_type'], Taxonomies::PAGE_TYPE ) );
	}

	/**
	 * Even then it stays inside its own house: a page and a post of the site are
	 * none of its business.
	 *
	 * @return void
	 */
	public function test_the_opt_in_leaves_the_rest_of_the_site_alone(): void {
		$content = $this->make_content();
		update_option( GitSync::OPTION_UNINSTALL, true );

		$this->uninstall();

		$this->assertSame( 'page', get_post_type( $content['foreign_page'] ), 'A page of the site is not the plugin to delete.' );
		$this->assertSame( 'post', get_post_type( $content['foreign_post'] ) );
	}

	/**
	 * The filter is the second way in, for a site that cannot use the setting.
	 *
	 * @return void
	 */
	public function test_the_filter_also_removes_the_content(): void {
		$content = $this->make_content();
		add_filter( 'living_handbook_uninstall_remove_content', '__return_true' );

		$this->uninstall();

		$this->assertNull( get_post( $content['page'] ) );
	}

	/**
	 * The overview page from the activation is removed with the content, but
	 * only while it is still a page: a stored id that now points at something
	 * else is somebody else's post.
	 *
	 * @return void
	 */
	public function test_the_overview_page_is_only_removed_when_it_is_still_one(): void {
		$overview = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_option( Onboarding::OPTION_OVERVIEW_PAGE, $overview );
		update_option( GitSync::OPTION_UNINSTALL, true );

		$this->uninstall();

		$this->assertNull( get_post( $overview ), 'The page the plugin created goes with the content.' );

		// Again, with the id pointing at an ordinary post instead.
		$other = self::factory()->post->create();
		update_option( Onboarding::OPTION_OVERVIEW_PAGE, $other );
		update_option( GitSync::OPTION_UNINSTALL, true );

		$this->uninstall();

		$this->assertSame( 'post', get_post_type( $other ), 'An id that is no longer a page must be left alone.' );
	}

	/**
	 * The cache cleanup is a LIKE over the options table, which is exactly the
	 * kind of query that takes other people's rows with it. It must not.
	 *
	 * @return void
	 */
	public function test_the_cache_cleanup_only_takes_the_plugins_own_transients(): void {
		set_transient( 'lh_areas_7_abc', 'areas', HOUR_IN_SECONDS );
		set_transient( 'other_plugin_cache', 'keep me', HOUR_IN_SECONDS );
		set_transient( 'lh_something_else', 'keep me too', HOUR_IN_SECONDS );
		update_option( 'unrelated_option', 'keep me as well' );

		$this->uninstall();

		// The cleanup is raw SQL, so the check is raw too: the object cache of this
		// process still holds what the query deleted underneath it.
		$this->assertFalse( $this->option_row( '_transient_lh_areas_7_abc' ), 'The area cards cache is the plugin own.' );
		$this->assertFalse( $this->option_row( '_transient_timeout_lh_areas_7_abc' ), 'Its timeout row goes with it.' );
		$this->assertSame( 'keep me', $this->option_row( '_transient_other_plugin_cache' ), 'Another plugin cache must survive.' );
		$this->assertSame( 'keep me too', $this->option_row( '_transient_lh_something_else' ), 'The pattern must not widen to every lh_ key.' );
		$this->assertSame( 'keep me as well', $this->option_row( 'unrelated_option' ) );
	}

	/**
	 * Read an option straight from the table, past the object cache.
	 *
	 * @param string $name Option name.
	 * @return string|false The stored value, or false when the row is gone.
	 */
	private function option_row( string $name ) {
		global $wpdb;

		$value = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name )
		);

		return null === $value ? false : $value;
	}

	/**
	 * The scheduled sync is unscheduled, both the recurring event and its one-off
	 * follow-up, so a deleted plugin leaves no cron entry pointing at nothing.
	 *
	 * @return void
	 */
	public function test_the_scheduled_sync_is_removed(): void {
		wp_schedule_event( time() + 300, 'daily', 'living_handbook_git_sync' );
		wp_schedule_single_event( time() + 60, 'living_handbook_git_sync_continue' );

		$this->uninstall();

		$this->assertFalse( wp_next_scheduled( 'living_handbook_git_sync' ) );
		$this->assertFalse( wp_next_scheduled( 'living_handbook_git_sync_continue' ) );
	}
}
