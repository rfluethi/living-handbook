<?php
/**
 * Uninstall cleanup.
 *
 * Runs when the plugin is deleted from the Plugins screen. By default only the
 * plugin's own operational data is removed (its options, the scheduled sync,
 * and the navigation/area caches); user content (handbook pages, handbooks, and
 * their metadata) is kept. To also remove the content, opt in deliberately by
 * returning true from the `living_handbook_uninstall_remove_content` filter
 * (for example from a small must-use plugin), which is off by default.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Run the uninstall cleanup.
 *
 * Kept in a function so its variables stay local; the file body runs in the
 * global scope, where locals would collide with WordPress globals.
 *
 * @return void
 */
function living_handbook_run_uninstall(): void {
	global $wpdb;

	// Always: clear the scheduled sync and the plugin's own options.
	wp_clear_scheduled_hook( 'living_handbook_git_sync' );
	delete_option( 'living_handbook_sync_schedule' );
	delete_option( 'living_handbook_nav_version' );

	// Always: remove the navigation and area caches (transients keyed by version).
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query(
		"DELETE FROM {$wpdb->options}
		 WHERE option_name LIKE '\\_transient\\_lh\\_nav\\_%'
		    OR option_name LIKE '\\_transient\\_timeout\\_lh\\_nav\\_%'
		    OR option_name LIKE '\\_transient\\_lh\\_areas\\_%'
		    OR option_name LIKE '\\_transient\\_timeout\\_lh\\_areas\\_%'"
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

	/**
	 * Opt in to remove all handbook content on uninstall.
	 *
	 * @param bool $remove Whether to delete handbook pages, handbooks and their meta.
	 */
	if ( ! apply_filters( 'living_handbook_uninstall_remove_content', false ) ) {
		return;
	}

	// Load the autoloader and register the data model so wp_delete_post and
	// wp_delete_term run cleanly (init has not fired during uninstall).
	require_once __DIR__ . '/living-handbook.php';
	( new LivingHandbook\PostType\Handbook() )->register_post_type();
	$handbooks = new LivingHandbook\Handbook\Handbooks();
	$handbooks->register_taxonomy();

	$handbook_ids = get_posts(
		array(
			'post_type'      => 'handbook',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);
	foreach ( $handbook_ids as $handbook_id ) {
		wp_delete_post( (int) $handbook_id, true );
	}

	$handbook_terms = get_terms(
		array(
			'taxonomy'   => 'handbook_set',
			'hide_empty' => false,
			'fields'     => 'ids',
		)
	);
	if ( is_array( $handbook_terms ) ) {
		foreach ( $handbook_terms as $handbook_term_id ) {
			wp_delete_term( (int) $handbook_term_id, 'handbook_set' );
		}
	}
}

living_handbook_run_uninstall();
