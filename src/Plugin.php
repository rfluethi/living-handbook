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
use LivingHandbook\Feedback\Feedback;
use LivingHandbook\Frontend\Filters;
use LivingHandbook\Frontend\FrontendRenderer;
use LivingHandbook\Frontend\Templates;
use LivingHandbook\Handbook\HandbookAdmin;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\Meta\Metadata;
use LivingHandbook\PostType\Handbook;
use LivingHandbook\Setup\Seeder;
use LivingHandbook\Taxonomy\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central plugin class. Modules are wired up in boot().
 */
final class Plugin {

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
		load_plugin_textdomain(
			'living-handbook',
			false,
			dirname( plugin_basename( LIVING_HANDBOOK_FILE ) ) . '/languages'
		);

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
		( new Blocks() )->register();
	}

	/**
	 * Activation callback: register the data model, seed the vocabulary, and
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

		flush_rewrite_rules();
	}

	/**
	 * Deactivation callback.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
