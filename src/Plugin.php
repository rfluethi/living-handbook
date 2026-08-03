<?php
/**
 * Main plugin bootstrap.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook;

use LivingHandbook\Access\AccessController;
use LivingHandbook\Admin\Maintenance;
use LivingHandbook\Blocks\Blocks;
use LivingHandbook\Blocks\MermaidBlock;
use LivingHandbook\Blocks\SourceNoteBlock;
use LivingHandbook\Feedback\Feedback;
use LivingHandbook\Frontend\Filters;
use LivingHandbook\Frontend\FrontendRenderer;
use LivingHandbook\Frontend\Templates;
use LivingHandbook\Git\GitSync;
use LivingHandbook\Handbook\HandbookAdmin;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\Import\HandbookExport;
use LivingHandbook\Import\HandbookImport;
use LivingHandbook\Import\MarkdownImportPage;
use LivingHandbook\Meta\Metadata;
use LivingHandbook\PostType\Handbook;
use LivingHandbook\Setup\Onboarding;
use LivingHandbook\Setup\Seeder;
use LivingHandbook\Setup\Settings;
use LivingHandbook\Taxonomy\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central plugin class. Modules are wired up in boot().
 *
 * The text domain is deliberately not loaded here. Since WordPress 4.6 the
 * translations of a plugin are loaded automatically, just in time, from the
 * language packs and from the plugin's own Domain Path; calling
 * load_plugin_textdomain() is unnecessary and Plugin Check flags it.
 */
final class Plugin {

	/**
	 * Option that stores the version the database was last set up for.
	 */
	public const DB_VERSION_OPTION = 'living_handbook_db_version';

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Return the shared instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor: use instance().
	 */
	private function __construct() {}

	/**
	 * Register hooks and modules.
	 *
	 * @return void
	 */
	public function boot(): void {
		( new Handbook() )->register();
		( new Taxonomies() )->register();
		( new Handbooks() )->register();
		( new HandbookAdmin() )->register();
		( new AccessController() )->register();
		( new Metadata() )->register();
		( new Feedback() )->register();
		( new FrontendRenderer() )->register();
		( new Templates() )->register();
		( new Filters() )->register();
		( new Maintenance() )->register();
		( new MarkdownImportPage() )->register();
		( new HandbookExport() )->register();
		( new HandbookImport() )->register();
		( new GitSync() )->register();
		( new Settings() )->register();
		( new Blocks() )->register();
		( new MermaidBlock() )->register();
		( new SourceNoteBlock() )->register();
		( new Onboarding() )->register();

		add_action( 'admin_init', array( $this, 'maybe_upgrade' ) );
	}

	/**
	 * Run version-keyed migrations after a plugin update.
	 *
	 * Updating a plugin replaces its files without running the activation hook,
	 * so this compares the stored database version against the code version and
	 * runs the migrations once, then records the version.
	 *
	 * @return void
	 */
	public function maybe_upgrade(): void {
		$installed = (string) get_option( self::DB_VERSION_OPTION, '' );
		if ( LIVING_HANDBOOK_VERSION === $installed ) {
			return;
		}

		self::rename_meta_keys();

		GitSync::reschedule();
		update_option( self::DB_VERSION_OPTION, LIVING_HANDBOOK_VERSION );
	}

	/**
	 * Bring the meta keys of an older installation to their current names.
	 *
	 * The plugin writes two kinds of custom field: the editorial ones a person
	 * fills in, under living_handbook_, and the bookkeeping it keeps about a
	 * page, under the protected _lh_. Three keys were on neither side of that
	 * line, and the two source keys were the expensive kind of wrong: without an
	 * underscore they sit in the Custom Fields box of every handbook page, where
	 * switching the source from GitHub to WordPress silently stops the sync, and
	 * the other way round hands a hand-written page to the next one.
	 *
	 * The renames run in order, so an installation from before 0.16.0 arrives at
	 * the current name in two steps. Each one is idempotent: once a key is
	 * renamed, no row carries the old name.
	 *
	 * @return void
	 */
	private static function rename_meta_keys(): void {
		global $wpdb;

		$renames = array(
			// 0.16.0: the feedback counters left the Custom Fields box.
			'living_handbook_feedback_yes'     => '_living_handbook_feedback_yes',
			'living_handbook_feedback_no'      => '_living_handbook_feedback_no',
			'living_handbook_feedback_voters'  => '_living_handbook_feedback_voters',
			// 0.56.0: one protected prefix, _lh_, instead of two.
			'_living_handbook_feedback_yes'    => '_lh_feedback_yes',
			'_living_handbook_feedback_no'     => '_lh_feedback_no',
			'_living_handbook_feedback_voters' => '_lh_feedback_voters',
			// 0.56.0: the source of a page is bookkeeping, not an editorial field.
			'living_handbook_source'           => '_lh_source',
			'living_handbook_markdown_source'  => '_lh_source_url',
		);

		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One-time migration renaming meta keys, not a repeated runtime query.
		$touched = array();
		foreach ( $renames as $old_key => $new_key ) {
			// Which pages are affected, before the rename makes them unfindable.
			$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare( "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s", $old_key )
			);
			if ( ! $ids ) {
				continue;
			}
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->postmeta,
				array( 'meta_key' => $new_key ),
				array( 'meta_key' => $old_key )
			);
			$touched = array_merge( $touched, array_map( 'intval', $ids ) );
		}
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key

		// The rows were changed underneath WordPress, so a persistent object cache
		// would keep answering with the old keys.
		if ( $touched ) {
			wp_cache_delete_multiple( array_values( array_unique( $touched ) ), 'post_meta' );
		}
	}

	/**
	 * Activation callback: register the data model, seed the vocabulary, create
	 * the overview page, schedule the GitHub sync, record the version, and
	 * flush rewrite rules.
	 *
	 * @return void
	 */
	public static function activate(): void {
		( new Handbook() )->register_post_type();
		( new Taxonomies() )->register_taxonomies();
		$handbooks = new Handbooks();
		$handbooks->register_taxonomy();
		$handbooks->register_meta();

		Seeder::seed();
		Onboarding::activate();
		GitSync::schedule();
		update_option( self::DB_VERSION_OPTION, LIVING_HANDBOOK_VERSION );

		flush_rewrite_rules();
	}

	/**
	 * Deactivation callback.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		GitSync::unschedule();
		flush_rewrite_rules();
	}
}
