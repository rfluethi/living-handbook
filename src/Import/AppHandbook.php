<?php
/**
 * The app's own handbook, offered as a one-click import.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Import;

use LivingHandbook\Git\GitSync;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads the handbook the plugin's own documentation lives in.
 *
 * The handbook ships inside the plugin, as Markdown under handbuch/, and the
 * "App handbook" tab imports it from there. Shipping it means it always matches
 * the installed version and no install ever depends on a repository staying
 * reachable. The Markdown is authored in a public repository and copied into the
 * plugin at build time, so it still has one editing source; it just travels with
 * the release.
 *
 * A fork, or anyone who would rather pull the latest state straight from GitHub,
 * overrides the source through the `living_handbook_app_handbook_url` filter: any
 * non-empty tree URL it returns is imported instead of the bundled folder. The
 * default is empty, meaning "use the bundled copy". Returning nothing from the
 * filter with no bundled folder present hides the tab and the setup hint, so
 * there is never a button that leads nowhere.
 */
final class AppHandbook {

	/**
	 * The bundled folder holding the German handbook, relative to the plugin.
	 */
	private const PATH_DE = 'handbuch/de';

	/**
	 * The bundled folder holding the English handbook, relative to the plugin.
	 * Also the fallback for any language without its own folder.
	 */
	private const PATH_EN = 'handbuch/en';

	/**
	 * Whether the app handbook can be offered: the user may import, and there is
	 * either a bundled folder or a GitHub override to load from.
	 *
	 * @return bool
	 */
	public static function can_load(): bool {
		return MarkdownImportPage::can_import() && ( '' !== self::override_url() || '' !== self::local_dir() );
	}

	/**
	 * The bundled folder for the current admin language, English as the fallback,
	 * or '' when the folder is not present (a source build without the docs).
	 *
	 * @return string Absolute path, or ''.
	 */
	public static function local_dir(): string {
		$locale = determine_locale();
		$rel    = ( 0 === strpos( $locale, 'de' ) ) ? self::PATH_DE : self::PATH_EN;
		$dir    = rtrim( LIVING_HANDBOOK_DIR, '/' ) . '/' . $rel;
		return is_dir( $dir ) ? $dir : '';
	}

	/**
	 * A GitHub tree URL to load from instead of the bundled folder, or '' to use
	 * the bundle. Empty by default: the app handbook ships with the plugin.
	 *
	 * @return string The override URL, or ''.
	 */
	public static function override_url(): string {
		$locale = determine_locale();

		/**
		 * Filter the source the app handbook is loaded from. Return a
		 * github.com/.../tree/<branch>/<path> URL to pull from GitHub instead of
		 * the bundled folder, or '' (the default) to use the bundled copy.
		 *
		 * @param string $default The default, an empty string (use the bundle).
		 * @param string $locale  The current admin locale.
		 */
		$url = (string) apply_filters( 'living_handbook_app_handbook_url', '', $locale );

		return trim( $url );
	}

	/**
	 * Load the app handbook into a handbook, published straight away. It is
	 * curated content, so its front-end visibility is governed by the handbook it
	 * lands in, not by a draft status. A GitHub override is imported as a folder;
	 * otherwise the bundled folder is imported from disk.
	 *
	 * @param int $handbook_id Target handbook term id (0 for none).
	 * @return array<string, mixed>|WP_Error The pages on success, a WP_Error on failure.
	 */
	public static function load( int $handbook_id ) {
		$sync     = new GitSync();
		$override = self::override_url();
		if ( '' !== $override ) {
			// In one call: this runs from a button that expects the whole handbook
			// back at once, and the app handbook is small enough for that.
			return $sync->import_folder_complete( $override, $handbook_id, true );
		}

		$dir = self::local_dir();
		if ( '' === $dir ) {
			return new WP_Error( 'living_handbook_import', __( 'The app handbook is not available in this build.', 'living-handbook' ), array( 'status' => 404 ) );
		}
		return $sync->import_local_folder( $dir, $handbook_id, true );
	}
}
