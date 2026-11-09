<?php
/**
 * Main plugin bootstrap.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook;

use LivingHandbook\Abilities\Abilities;
use LivingHandbook\Access\AccessController;
use LivingHandbook\Admin\Maintenance;
use LivingHandbook\Admin\MoveToHandbook;
use LivingHandbook\Blocks\Blocks;
use LivingHandbook\Blocks\MermaidBlock;
use LivingHandbook\Blocks\SourceNoteBlock;
use LivingHandbook\Export\StaticSite;
use LivingHandbook\Feedback\Feedback;
use LivingHandbook\Frontend\Filters;
use LivingHandbook\Frontend\FrontendRenderer;
use LivingHandbook\Frontend\Headings;
use LivingHandbook\Frontend\ReviewConfirm;
use LivingHandbook\Frontend\Templates;
use LivingHandbook\Git\GitSync;
use LivingHandbook\Handbook\HandbookAdmin;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\Import\HandbookExport;
use LivingHandbook\Import\HandbookImport;
use LivingHandbook\Import\MarkdownImportPage;
use LivingHandbook\Meta\Metadata;
use LivingHandbook\PostType\Handbook;
use LivingHandbook\Privacy\Privacy;
use LivingHandbook\Search\SearchLog;
use LivingHandbook\Setup\Onboarding;
use LivingHandbook\Setup\Seeder;
use LivingHandbook\Setup\Settings;
use LivingHandbook\Support\Lock;
use LivingHandbook\Taxonomy\Taxonomies;
use LivingHandbook\Training\LessonPicker;
use LivingHandbook\Training\Training;

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
	 * Lock that keeps two requests from running the same migration at once.
	 */
	public const UPGRADE_LOCK = 'living_handbook_upgrade_lock';

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Returns the shared instance.
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
	 * Registers the hooks of every module.
	 *
	 * @return void
	 */
	public function boot(): void {
		( new Handbook() )->register();
		( new Training() )->register();
		( new LessonPicker() )->register();
		( new Taxonomies() )->register();
		( new Handbooks() )->register();
		( new HandbookAdmin() )->register();
		( new AccessController() )->register();
		( new Metadata() )->register();
		( new Feedback() )->register();
		( new FrontendRenderer() )->register();
		( new Templates() )->register();
		( new Headings() )->register();
		( new Filters() )->register();
		( new ReviewConfirm() )->register();
		( new Maintenance() )->register();
		( new MoveToHandbook() )->register();
		( new MarkdownImportPage() )->register();
		( new HandbookExport() )->register();
		( new StaticSite() )->register();
		( new HandbookImport() )->register();
		( new GitSync() )->register();
		( new Settings() )->register();
		( new Blocks() )->register();
		( new MermaidBlock() )->register();
		( new SourceNoteBlock() )->register();
		( new Onboarding() )->register();
		( new Privacy() )->register();
		( new SearchLog() )->register();
		( new Abilities() )->register();

		// After an update the migrations have to run before anything relies on
		// them. This used to hang on admin_init, which left it to the next visit
		// of the dashboard: an update from WP-CLI or a background auto-update
		// replaces the files, and until an administrator happened by, every
		// front-end request and every cron run worked against unmigrated data.
		//
		// wp_loaded fires on every kind of request, front end, admin, AJAX, REST,
		// cron and WP-CLI, after init has finished and before the request is
		// parsed or a cron event runs. Not init itself, even late: maybe_upgrade()
		// rebuilds the rewrite rules, and a rebuild at init priority 99 would
		// leave out the rules of every other plugin that registers its types
		// later than that. On wp_loaded every registration is done. Once the
		// version is stored this costs one autoloaded option read.
		add_action( 'wp_loaded', array( $this, 'maybe_upgrade' ) );
	}

	/**
	 * Runs the version-keyed migrations after a plugin update.
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

		// Running on wp_loaded means running on every request that arrives while
		// the first one is still migrating, and on a busy site that is several
		// at once. The migrations are idempotent, but a flush and a reschedule
		// per concurrent request is waste, and two renames racing over the same
		// rows is worse. Up to 0.75.1 the lock was add_option(), which writes
		// with "ON DUPLICATE KEY UPDATE" and so let a second request in whenever
		// the object cache listed the row as missing. Support\Lock takes it with
		// an INSERT IGNORE instead, and a lock left behind by a request that died
		// half-way is taken over after ten minutes instead of blocking the
		// upgrade for good.
		$lock = Lock::acquire( self::UPGRADE_LOCK, 10 * MINUTE_IN_SECONDS );
		if ( '' === $lock ) {
			return;
		}

		self::rename_meta_keys();
		self::store_default_options();

		GitSync::reschedule();

		// 0.77.0: the count of failed searches has its own table and a daily
		// cleanup. dbDelta() leaves a table of the right shape alone, so running
		// this on every version change costs nothing once it exists.
		SearchLog::install();
		SearchLog::schedule();

		// An update can bring a post type or a rewrite rule the stored rules do
		// not know: 0.75.0 added learning paths, and an installation that had
		// the module switched on before the update had no rule for them. Rebuild
		// once per version. This runs on wp_loaded, after every post type and
		// taxonomy of every plugin is registered, so the rules are built from the
		// current registrations.
		flush_rewrite_rules( false );

		update_option( self::DB_VERSION_OPTION, LIVING_HANDBOOK_VERSION );
		Lock::release( self::UPGRADE_LOCK, $lock );
	}

	/**
	 * Store the switches a site has never saved, with the value they already
	 * mean.
	 *
	 * Three options are read on every request: whether learning paths are on,
	 * which classifying taxonomies are in use, and whether guests may vote. A
	 * site that never opened those settings has no row for them, and without a
	 * persistent object cache WordPress remembers a missing option for one
	 * request only, so each of them cost a database query on every page view
	 * (measured for 0.76.0). Stored once, autoloaded, they come with the options
	 * WordPress loads anyway. The values change nothing: an empty taxonomy
	 * setting means "all four", as a missing one does, and the two others are
	 * off, as a missing one is. add_option() leaves a saved choice alone.
	 *
	 * @return void
	 */
	private static function store_default_options(): void {
		add_option( Taxonomies::OPTION_ENABLED, '', '', true );
		add_option( Training::OPTION_ENABLED, '0', '', true );
		add_option( Settings::OPTION_PUBLIC_FEEDBACK, '0', '', true );
		// Read only when a search finds nothing, so not autoloaded. On by
		// default (E-0078).
		add_option( SearchLog::OPTION_ENABLED, '1', '', false );
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
			$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time migration; no API renames a meta key.
				$wpdb->prepare( "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s", $old_key )
			);
			if ( ! $ids ) {
				continue;
			}
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One statement per key instead of one per row; the cache is cleared below.
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
	 * Activates the plugin: registers the data model, seeds the taxonomies,
	 * creates the overview page, stores the default switches, schedules the
	 * GitHub sync, records the version, and flushes the rewrite rules.
	 *
	 * A network activation is refused. WordPress runs this callback once, on
	 * the main site, and then marks the plugin active on every site of the
	 * network: none of the others gets its terms, its overview page, its
	 * schedule or its rewrite rules, and the uninstall cleans only the site it
	 * runs on. The readme says multisite is not supported; this is where that
	 * stops being a sentence and becomes a guard. Activating per site still
	 * works, each site runs its own activation then.
	 *
	 * @param bool $network_wide Whether the plugin is being activated for the whole network.
	 * @return void
	 */
	public static function activate( bool $network_wide = false ): void {
		// WordPress passes true only from the network admin of a multisite, so
		// the flag alone is the test.
		if ( $network_wide ) {
			wp_die(
				esc_html__( 'Living Handbook cannot be activated for the whole network. Activate it on each site that needs a handbook instead.', 'living-handbook' ),
				esc_html__( 'Network activation not supported', 'living-handbook' ),
				array(
					'response'  => 400,
					'back_link' => true,
				)
			);
		}

		( new Handbook() )->register_post_type();
		( new Training() )->register_post_type();
		( new Taxonomies() )->register_taxonomies();
		$handbooks = new Handbooks();
		$handbooks->register_taxonomy();
		$handbooks->register_meta();

		Seeder::seed();
		Onboarding::activate();
		self::store_default_options();
		GitSync::schedule();
		SearchLog::install();
		SearchLog::schedule();
		update_option( self::DB_VERSION_OPTION, LIVING_HANDBOOK_VERSION );

		flush_rewrite_rules();
	}

	/**
	 * Deactivates the plugin: unschedules the sync and drops the stored rewrite
	 * rules.
	 *
	 * The rewrite rules have to lose the plugin's addresses. Flushing them here
	 * did not do that: deactivation runs inside the request that still has the
	 * plugin loaded, with its post types and taxonomies registered, so
	 * flush_rewrite_rules() rebuilt exactly the rules it was meant to remove,
	 * and /handbook/... kept resolving to a type nothing serves any more until
	 * the permalinks were saved by hand. Deleting the stored rules instead makes
	 * WordPress build them afresh on the next request, which no longer loads the
	 * plugin.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		GitSync::unschedule();
		SearchLog::unschedule();
		delete_option( 'rewrite_rules' );
	}
}
