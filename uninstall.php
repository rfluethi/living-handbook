<?php
/**
 * Uninstall cleanup.
 *
 * Runs when the plugin is deleted from the Plugins screen. By default only the
 * plugin's own operational data is removed (its options, the scheduled sync,
 * and the navigation/area caches); user content (handbook pages, handbooks, and
 * their metadata) is kept. To also remove the content, turn on the "Also delete
 * all handbook pages, handbooks and their data" option on the plugin settings
 * page before deleting, or return true from the
 * `living_handbook_uninstall_remove_content` filter (for example from a small
 * must-use plugin). Both are off by default.
 *
 * The plugin's block templates are registered in code, so they disappear on
 * their own once the plugin is gone; nothing needs to be removed for them. Only
 * templates a user customised in the Site Editor are stored in the database, and
 * those are removed together with the content when the option above is on.
 *
 * The overview page created on activation is a normal page the user may have
 * edited, moved or built on, so it is only removed with the content option, and
 * only if it is still the page this plugin created.
 *
 * This cleanup runs on the current site only. The plugin is built for
 * single-site installations and is not network (multisite) aware, so on a
 * network install it must be uninstalled per site.
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

	// Read the choices before the options are deleted below.
	$remove_content   = (bool) get_option( 'living_handbook_uninstall_content', false );
	$overview_page_id = (int) get_option( 'living_handbook_overview_page', 0 );

	// Always: clear the scheduled sync and the plugin's own options.
	wp_clear_scheduled_hook( 'living_handbook_git_sync' );
	delete_option( 'living_handbook_sync_schedule' );
	delete_option( 'living_handbook_sync_offset' );
	delete_option( 'living_handbook_nav_version' );
	delete_option( 'living_handbook_db_version' );
	delete_option( 'living_handbook_uninstall_content' );
	delete_option( 'living_handbook_setup_notice' );
	delete_option( 'living_handbook_overview_page' );
	delete_option( 'living_handbook_custom_css' );

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
	if ( ! $remove_content && ! apply_filters( 'living_handbook_uninstall_remove_content', false ) ) {
		return;
	}

	// Load the autoloader and register the data model so wp_delete_post and
	// wp_delete_term run cleanly (init has not fired during uninstall). All
	// taxonomies must be registered before their terms can be deleted.
	require_once __DIR__ . '/living-handbook.php';
	( new LivingHandbook\PostType\Handbook() )->register_post_type();
	$handbooks = new LivingHandbook\Handbook\Handbooks();
	$handbooks->register_taxonomy();
	( new LivingHandbook\Taxonomy\Taxonomies() )->register_taxonomies();

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

	// Delete the terms of every plugin taxonomy: the handbook grouping plus the
	// four seeded vocabularies, whose terms would otherwise be left orphaned.
	$taxonomies = array(
		LivingHandbook\Handbook\Handbooks::TAXONOMY,
		LivingHandbook\Taxonomy\Taxonomies::PAGE_TYPE,
		LivingHandbook\Taxonomy\Taxonomies::TOPIC,
		LivingHandbook\Taxonomy\Taxonomies::ROLE,
		LivingHandbook\Taxonomy\Taxonomies::AUDIENCE,
	);
	foreach ( $taxonomies as $taxonomy ) {
		$term_ids = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);
		if ( is_array( $term_ids ) ) {
			foreach ( $term_ids as $term_id ) {
				wp_delete_term( (int) $term_id, $taxonomy );
			}
		}
	}

	// The overview page created on activation, if it is still a page.
	if ( $overview_page_id > 0 && 'page' === get_post_type( $overview_page_id ) ) {
		wp_delete_post( $overview_page_id, true );
	}

	// Remove any Site Editor customisations of the plugin's block templates.
	// They are stored as wp_template / wp_template_part posts assigned to this
	// plugin's theme identifier via the wp_theme taxonomy.
	$template_ids = get_posts(
		array(
			'post_type'      => array( 'wp_template', 'wp_template_part' ),
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'wp_theme',
					'field'    => 'name',
					'terms'    => 'living-handbook',
				),
			),
		)
	);
	foreach ( $template_ids as $template_id ) {
		wp_delete_post( (int) $template_id, true );
	}
}

living_handbook_run_uninstall();
