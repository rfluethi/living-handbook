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
	 * The bundled folders holding the technical documentation.
	 */
	private const PATH_DOCS_DE = 'docs-de';
	private const PATH_DOCS_EN = 'docs';

	/**
	 * What can be loaded, in the order it is offered.
	 *
	 * Four entries rather than one, because the plugin ships four handbooks and
	 * the import used to pick one of them for you, from the admin language. That
	 * is right often enough to be annoying when it is wrong: a German admin who
	 * wants the English handbook had no way to say so, and the technical
	 * documentation could not be loaded at all.
	 *
	 * A folder that is not in this build is left out rather than offered and then
	 * failing. A source checkout without a `composer install` of the docs, or a
	 * ZIP built before 0.67.0, simply shows fewer entries.
	 *
	 * @return array<string, array{label: string, dir: string, german: bool}>
	 */
	public static function entries(): array {
		$all = array(
			'user-de' => array(
				'label'  => __( 'User handbook (German)', 'living-handbook' ),
				'dir'    => self::PATH_DE,
				'german' => true,
			),
			'user-en' => array(
				'label'  => __( 'User handbook (English)', 'living-handbook' ),
				'dir'    => self::PATH_EN,
				'german' => false,
			),
			'docs-de' => array(
				'label'  => __( 'Technical documentation (German)', 'living-handbook' ),
				'dir'    => self::PATH_DOCS_DE,
				'german' => true,
			),
			'docs-en' => array(
				'label'  => __( 'Technical documentation (English)', 'living-handbook' ),
				'dir'    => self::PATH_DOCS_EN,
				'german' => false,
			),
		);

		$out = array();
		foreach ( $all as $key => $entry ) {
			$dir = rtrim( LIVING_HANDBOOK_DIR, '/' ) . '/' . $entry['dir'];
			if ( is_dir( $dir ) ) {
				$entry['dir'] = $dir;
				$out[ $key ]  = $entry;
			}
		}

		return $out;
	}

	/**
	 * The entry preselected in the form: the user handbook in the admin language,
	 * or the first one there is.
	 *
	 * @return string The key, or '' when nothing is bundled.
	 */
	public static function default_key(): string {
		$entries = self::entries();
		if ( array() === $entries ) {
			return '';
		}

		$wanted = ( 0 === strpos( determine_locale(), 'de' ) ) ? 'user-de' : 'user-en';
		if ( isset( $entries[ $wanted ] ) ) {
			return $wanted;
		}

		return (string) array_key_first( $entries );
	}

	/**
	 * Whether the app handbook can be offered: the user may import, and there is
	 * either something bundled or a GitHub override to load from.
	 *
	 * @return bool
	 */
	public static function can_load(): bool {
		return MarkdownImportPage::can_import() && ( '' !== self::override_url() || array() !== self::entries() );
	}

	/**
	 * The bundled folder of one entry, or of the default entry.
	 *
	 * @param string $key Entry key; empty for the default.
	 * @return string Absolute path, or ''.
	 */
	public static function local_dir( string $key = '' ): string {
		$entries = self::entries();
		if ( '' === $key ) {
			$key = self::default_key();
		}

		return isset( $entries[ $key ] ) ? $entries[ $key ]['dir'] : '';
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
	 * @param int    $handbook_id Target handbook term id (0 for none).
	 * @param string $key         Which of the bundled handbooks; empty for the default.
	 * @param bool   $managed     Whether the pages stay tied to the shipped copy: locked in the
	 *                            editor and refreshed by a later load. False loads them as
	 *                            ordinary pages, free to edit, and they age from then on.
	 * @return array<string, mixed>|WP_Error The pages on success, a WP_Error on failure.
	 */
	public static function load( int $handbook_id, string $key = '', bool $managed = true ) {
		$sync     = new GitSync();
		$override = self::override_url();
		if ( '' !== $override && ( '' === $key || self::default_key() === $key ) ) {
			// The override is about the app's own handbook, so it applies to the
			// entry that used to be the only one. In one call: this runs from a
			// button that expects the whole handbook back at once, and the app
			// handbook is small enough for that.
			return $sync->import_folder_complete( $override, $handbook_id, true );
		}

		$dir = self::local_dir( $key );
		if ( '' === $dir ) {
			return new WP_Error( 'living_handbook_import', __( 'That handbook is not available in this build.', 'living-handbook' ), array( 'status' => 404 ) );
		}

		return $sync->import_local_folder( $dir, $handbook_id, true, $managed );
	}
}
