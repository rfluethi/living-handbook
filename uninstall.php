<?php
/**
 * Uninstall cleanup.
 *
 * Runs when the plugin is deleted from the Plugins screen. By default only the
 * plugin's own operational data is removed (its options, the scheduled sync,
 * and its transients: the overview caches, running jobs, reports and feedback
 * counters); user content (handbook pages, handbooks, and
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
 * Runs the uninstall cleanup.
 *
 * Kept in a function so its variables stay local; the file body runs in the
 * global scope, where locals would collide with WordPress globals.
 *
 * @return void
 */
function living_handbook_run_uninstall(): void {
	global $wpdb;

	// Load the plugin so the autoloader and the option-name constants are
	// available. Requiring the file only defines constants and registers the
	// autoloader; the plugins_loaded bootstrap does not fire during uninstall.
	require_once __DIR__ . '/living-handbook.php';

	// Read the choices before the options are deleted below.
	$remove_content   = (bool) get_option( LivingHandbook\Git\GitSync::OPTION_UNINSTALL, false );
	$overview_page_id = (int) get_option( LivingHandbook\Setup\Onboarding::OPTION_OVERVIEW_PAGE, 0 );

	// Always: clear the scheduled sync (both the recurring hook and its one-off
	// follow-up) and the plugin's own options.
	LivingHandbook\Git\GitSync::unschedule();
	delete_option( LivingHandbook\Git\GitSync::OPTION_SCHEDULE );
	delete_option( LivingHandbook\Git\GitSync::OPTION_CRON_OFFSET );
	delete_option( LivingHandbook\Frontend\Navigation::CACHE_VERSION_OPTION );
	delete_option( LivingHandbook\Plugin::DB_VERSION_OPTION );
	delete_option( LivingHandbook\Plugin::UPGRADE_LOCK );
	delete_option( LivingHandbook\Git\GitSync::OPTION_UNINSTALL );
	delete_option( LivingHandbook\Setup\Onboarding::OPTION_SETUP_NOTICE );
	delete_option( LivingHandbook\Setup\Onboarding::OPTION_OVERVIEW_PAGE );
	delete_option( LivingHandbook\Setup\Settings::OPTION_CUSTOM_CSS );
	delete_option( LivingHandbook\Setup\Settings::OPTION_PUBLIC_FEEDBACK );
	delete_option( LivingHandbook\Setup\Settings::OPTION_DENIED_PAGE );
	delete_option( LivingHandbook\Taxonomy\Taxonomies::OPTION_ENABLED );
	delete_option( LivingHandbook\Training\Training::OPTION_ENABLED );
	delete_option( LivingHandbook\Training\Training::OPTION_FLUSH );
	delete_option( LivingHandbook\Frontend\Appearance::OPTION_COLORS );
	delete_option( LivingHandbook\Frontend\Appearance::OPTION_BASE_SIZE );
	delete_option( LivingHandbook\Admin\MoveToHandbook::OPTION_ANY_MOVED );
	delete_option( LivingHandbook\Git\GitSync::OPTION_SYNC_LOCK );

	// Always: remove the transients the plugin writes. The area cards and the
	// handbook previews of the overview (keyed by version and by viewer; the
	// navigation itself is not cached, see Navigation::CACHE_VERSION_OPTION),
	// and, which the cleanup did not know about up to 0.75.1, the state of a
	// running GitHub import (GitSync::JOB_PREFIX) and of a running static
	// export (StaticSite::JOB_PREFIX), each up to a whole handbook's worth of
	// page data; the report of a bundle import (HandbookImport::NOTICE_TRANSIENT);
	// and the hourly counters of anonymous feedback (Feedback,
	// "lh_fb_<post>_<hour>"). The prefixes are written out here because the
	// constants are private to their classes. Each pattern ends in an
	// underscore, so it cannot widen to a longer prefix another plugin might use.
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- No API deletes transients by prefix; this runs once, at uninstall.
	$transient_prefixes = array(
		'lh_areas_',
		'lh_preview_',
		'living_handbook_import_',
		'living_handbook_static_',
		'living_handbook_bundle_report_',
		'lh_fb_',
	);
	foreach ( $transient_prefixes as $transient_prefix ) {
		$like = $wpdb->esc_like( $transient_prefix ) . '%';
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_' ) . $like,
				$wpdb->esc_like( '_transient_timeout_' ) . $like
			)
		);
	}
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

	// On a persistent object cache (Redis, Memcached) the transients live in
	// the cache rather than the options table above, where they cannot be
	// listed by prefix. They are deliberately not flushed: wp_cache_flush()
	// empties the cache of the entire installation, including every other
	// plugin and, on a shared cache server, other sites. The leftovers expire
	// on their own, within a day at most, and nothing reads them again.

	/**
	 * Filters whether the uninstall also removes all handbook content.
	 *
	 * @param bool $remove Whether to delete handbook pages, handbooks and their meta.
	 */
	if ( ! $remove_content && ! apply_filters( 'living_handbook_uninstall_remove_content', false ) ) {
		return;
	}

	// Register the data model so wp_delete_post and wp_delete_term run cleanly
	// (init has not fired during uninstall). All taxonomies must be registered
	// before their terms can be deleted. The plugin is already loaded above.
	( new LivingHandbook\PostType\Handbook() )->register_post_type();
	( new LivingHandbook\Training\Training() )->register_post_type();
	$handbooks = new LivingHandbook\Handbook\Handbooks();
	$handbooks->register_taxonomy();
	( new LivingHandbook\Taxonomy\Taxonomies() )->register_taxonomies();

	// Marked internal, like every maintenance lookup in the plugin: the reader
	// filter narrows a query to the handbooks the current user may see, and an
	// uninstall has no user. Without this, a context that has the plugin's hooks
	// registered would delete only the public handbooks and report success.
	$handbook_ids = get_posts(
		LivingHandbook\Access\AccessController::internal(
			array(
				// Learning paths are handbook content too: they carry a handbook
				// term, they are useless without their lessons, and leaving them
				// behind would leave rows nothing can reach.
				'post_type'      => array( LivingHandbook\PostType\Handbook::POST_TYPE, LivingHandbook\Training\Training::POST_TYPE ),
				// Every status, listed. 'any' leaves out the statuses registered
				// with exclude_from_search, which are 'trash' and 'auto-draft':
				// with "delete everything" switched on, a page in the trash
				// survived with its text, its meta and its revisions.
				'post_status'    => array_keys( get_post_stati() ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		)
	);
	foreach ( $handbook_ids as $handbook_id ) {
		wp_delete_post( (int) $handbook_id, true );
	}

	// Delete the terms of every plugin taxonomy: the handbook grouping plus the
	// four seeded taxonomies, whose terms would otherwise be left orphaned.
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

	// The plugin's bookkeeping on the images the Markdown import uploaded: the
	// source file name and its hash, which let a re-import reuse an unchanged
	// image. The images themselves stay on purpose, they belong to the media
	// library and may be used anywhere on the site; the meta means nothing
	// without the plugin. Up to 0.75.1 it was left on every one of them.
	foreach ( array( LivingHandbook\Import\MarkdownImportPage::META_IMPORT_FILE, LivingHandbook\Import\MarkdownImportPage::META_IMPORT_HASH ) as $attachment_key ) {
		delete_post_meta_by_key( $attachment_key );
	}

	// The same for ordinary WordPress pages a bundle import created ("import as
	// pages"): they stay, because they are not handbook pages, but the origin
	// and bundle key the importer put on them mean nothing without the plugin.
	foreach ( array( LivingHandbook\Import\HandbookImport::META_ORIGIN, LivingHandbook\Import\HandbookImport::META_BUNDLE_KEY ) as $page_key ) {
		delete_post_meta_by_key( $page_key );
	}

	// The overview page created on activation, if it is still a page.
	if ( $overview_page_id > 0 && 'page' === get_post_type( $overview_page_id ) ) {
		wp_delete_post( $overview_page_id, true );
	}

	// Remove any Site Editor customisations of the plugin's block templates.
	//
	// This used to look for wp_template posts with the wp_theme term
	// "living-handbook", which no template ever carries, so it removed nothing.
	// Since WordPress 6.7 a template registered with register_block_template()
	// belongs to the theme that is active: its id is "<theme>//<slug>", and when
	// somebody saves it in the Site Editor, core (the templates REST controller)
	// stores a wp_template post named after the slug, with the wp_theme term of
	// the theme's stylesheet and the meta origin "plugin". The plugin is named
	// nowhere in the stored row; it is only the registry that says which plugin
	// a slug belongs to, and the registry is empty during uninstall.
	//
	// So the match is on what this plugin registers: the three slugs from
	// Frontend\Templates, with origin "plugin". That leaves the theme's own
	// templates alone (their origin is empty or "theme"), and it covers every
	// theme, because a customisation made under a theme that is no longer
	// active is still this plugin's template. Template parts are not matched:
	// the plugin registers none.
	$template_ids = get_posts(
		LivingHandbook\Access\AccessController::internal(
			array(
				'post_type'      => 'wp_template',
				'post_status'    => array_keys( get_post_stati() ),
				'post_name__in'  => array(
					'taxonomy-' . LivingHandbook\Handbook\Handbooks::TAXONOMY,
					'single-' . LivingHandbook\PostType\Handbook::POST_TYPE,
					'single-' . LivingHandbook\Training\Training::POST_TYPE,
				),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => 'origin', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One run, at uninstall.
				'meta_value'     => 'plugin', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- One run, at uninstall.
			)
		)
	);
	foreach ( $template_ids as $template_id ) {
		wp_delete_post( (int) $template_id, true );
	}
}

living_handbook_run_uninstall();
