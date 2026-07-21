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
	private const DB_VERSION_OPTION = 'living_handbook_db_version';

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
	 * runs any migrations once. There are none yet; the hook re-applies the sync
	 * schedule and records the version so later migrations have an anchor.
	 *
	 * @return void
	 */
	public function maybe_upgrade(): void {
		$installed = (string) get_option( self::DB_VERSION_OPTION, '' );
		if ( LIVING_HANDBOOK_VERSION === $installed ) {
			return;
		}

		// Future migrations keyed by the previously installed version go here.

		global $wpdb;
		// 0.16.0: the feedback counters and voter list moved to underscore-
		// prefixed meta keys, so they no longer appear in the Custom Fields box.
		// Rename any rows written before the change. Idempotent: once renamed,
		// the old keys are gone.
		$feedback_meta = array(
			'living_handbook_feedback_yes'    => '_living_handbook_feedback_yes',
			'living_handbook_feedback_no'     => '_living_handbook_feedback_no',
			'living_handbook_feedback_voters' => '_living_handbook_feedback_voters',
		);
		foreach ( $feedback_meta as $old_key => $new_key ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$wpdb->postmeta,
				array( 'meta_key' => $new_key ),
				array( 'meta_key' => $old_key )
			);
		}

		GitSync::reschedule();
		update_option( self::DB_VERSION_OPTION, LIVING_HANDBOOK_VERSION );
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
