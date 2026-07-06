<?php
/**
 * Main plugin bootstrap.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central plugin class. Modules (post type, taxonomies, access, maintenance)
 * are wired up here as the plugin grows.
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

		/*
		 * Modules are registered here in later versions: content type,
		 * taxonomies, handbook grouping and access, metadata, menu
		 * generation, maintenance dashboard, and settings.
		 */
	}

	/**
	 * Activation callback.
	 *
	 * Once modules register their post types, this flushes rewrite rules so
	 * the permalinks work immediately.
	 *
	 * @return void
	 */
	public static function activate(): void {
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
