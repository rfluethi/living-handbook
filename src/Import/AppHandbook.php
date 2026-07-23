<?php
/**
 * The app's own handbook, offered as a one-click GitHub import.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Import;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Points the import screen at the handbook the plugin's own documentation lives
 * in, on GitHub.
 *
 * The handbook is not shipped inside the plugin. It is written and maintained in
 * a public GitHub repository, and the "App handbook" tab is a shortcut that runs
 * the ordinary GitHub folder import against a fixed URL, choosing the folder that
 * matches the admin language. That way the documentation has one source, is
 * visible and editable where it is written, and every install pulls the current
 * state instead of a snapshot frozen at release time.
 *
 * This class holds no logic beyond picking the right URL. The import itself is
 * the same code path as the GitHub tab: {@see GitSync::import_folder()}.
 *
 * The default URLs point at this plugin's own repository. A fork with its own
 * documentation overrides them through the `living_handbook_app_handbook_url`
 * filter rather than editing this file; returning an empty string from the
 * filter hides the tab and the setup hint, so there is never a button that leads
 * nowhere.
 */
final class AppHandbook {

	/**
	 * The GitHub folder holding the German handbook, as a tree URL.
	 */
	private const URL_DE = 'https://github.com/rfluethi/living-handbook/tree/main/handbuch/de';

	/**
	 * The GitHub folder holding the English handbook, as a tree URL. Also the
	 * fallback for any language without its own folder.
	 */
	private const URL_EN = 'https://github.com/rfluethi/living-handbook/tree/main/handbuch/en';

	/**
	 * Whether the app handbook can be offered: the user may import, and a URL is
	 * configured.
	 *
	 * @return bool
	 */
	public static function can_load(): bool {
		return MarkdownImportPage::can_import() && '' !== self::url();
	}

	/**
	 * The tree URL for the current admin language, English as the fallback,
	 * filterable so a fork can point it at its own repository.
	 *
	 * @return string The URL, or '' when the filter clears it.
	 */
	public static function url(): string {
		$locale  = determine_locale();
		$default = ( 0 === strpos( $locale, 'de' ) ) ? self::URL_DE : self::URL_EN;

		/**
		 * Filter the GitHub folder URL the app handbook is loaded from.
		 *
		 * @param string $default The default URL for the admin language.
		 * @param string $locale  The current admin locale.
		 */
		$url = (string) apply_filters( 'living_handbook_app_handbook_url', $default, $locale );

		return trim( $url );
	}
}
